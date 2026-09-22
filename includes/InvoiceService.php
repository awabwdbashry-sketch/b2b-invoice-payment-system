<?php
declare(strict_types=1);

/**
 * All invoice/payment financial logic lives here so that the Dashboard,
 * Invoice pages, Payment pages, Reports, Customer pages and PDF generation
 * never duplicate calculations and always agree with each other.
 */
final class InvoiceService
{
    /** Server-side generation of a unique, sequential invoice number: PREFIX-YYYY-NNNN */
    public static function generateInvoiceNumber(PDO $pdo, string $prefix, int $year): string
    {
        if (DB_DRIVER === 'mysql') {
            // Row lock prevents two concurrent requests from getting the same number.
            $stmt = $pdo->prepare('SELECT last_number FROM invoice_number_sequences WHERE year = :y FOR UPDATE');
            $stmt->execute(['y' => $year]);
            $row = $stmt->fetch();
            if ($row === false) {
                $pdo->prepare('INSERT INTO invoice_number_sequences (year, last_number) VALUES (:y, 0)')->execute(['y' => $year]);
                $next = 1;
            } else {
                $next = (int)$row['last_number'] + 1;
            }
            $pdo->prepare('UPDATE invoice_number_sequences SET last_number = :n WHERE year = :y')
                ->execute(['n' => $next, 'y' => $year]);
        } else {
            // SQLite (dev/testing only): single-writer, no row locking needed.
            $stmt = $pdo->prepare('SELECT last_number FROM invoice_number_sequences WHERE year = :y');
            $stmt->execute(['y' => $year]);
            $row = $stmt->fetch();
            if ($row === false) {
                $pdo->prepare('INSERT INTO invoice_number_sequences (year, last_number) VALUES (:y, 1)')->execute(['y' => $year]);
                $next = 1;
            } else {
                $next = (int)$row['last_number'] + 1;
                $pdo->prepare('UPDATE invoice_number_sequences SET last_number = :n WHERE year = :y')
                    ->execute(['n' => $next, 'y' => $year]);
            }
        }

        return sprintf('%s-%d-%04d', $prefix, $year, $next);
    }

    /** Compute a single line item's total. Server is authoritative — never trust client math. */
    public static function calculateItemTotal(float $qty, float $unitPrice, float $discount, float $taxPercent): float
    {
        $base = ($qty * $unitPrice) - $discount;
        $base = max(0, $base);
        $tax  = $base * ($taxPercent / 100);
        return round($base + $tax, 2);
    }

    /**
     * Recalculate subtotal/discount/tax/total for an invoice from its items,
     * then refresh paid/remaining/status. Must run inside a transaction when
     * called alongside other writes (callers already wrap this as needed).
     */
    public static function recalcInvoiceFromItems(PDO $pdo, int $invoiceId): void
    {
        $items = $pdo->prepare('SELECT quantity, unit_price, discount_amount, tax_percent FROM invoice_items WHERE invoice_id = :id');
        $items->execute(['id' => $invoiceId]);

        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;

        foreach ($items->fetchAll() as $item) {
            $qty = (float)$item['quantity'];
            $price = (float)$item['unit_price'];
            $discount = (float)$item['discount_amount'];
            $taxPct = (float)$item['tax_percent'];

            $lineBase = max(0, ($qty * $price) - $discount);
            $lineTax = $lineBase * ($taxPct / 100);

            $subtotal += $qty * $price;
            $discountTotal += $discount;
            $taxTotal += $lineTax;
        }

        $total = round($subtotal - $discountTotal + $taxTotal, 2);

        $stmt = $pdo->prepare(
            'UPDATE invoices SET subtotal = :sub, discount_amount = :disc, tax_amount = :tax, total_amount = :total
             WHERE id = :id'
        );
        $stmt->execute([
            'sub' => round($subtotal, 2),
            'disc' => round($discountTotal, 2),
            'tax' => round($taxTotal, 2),
            'total' => $total,
            'id' => $invoiceId,
        ]);

        self::refreshPaidAndStatus($pdo, $invoiceId);
    }

    /** Recompute paid_amount, remaining_amount and status from real payment records. */
    public static function refreshPaidAndStatus(PDO $pdo, int $invoiceId): void
    {
        $inv = $pdo->prepare('SELECT * FROM invoices WHERE id = :id');
        $inv->execute(['id' => $invoiceId]);
        $invoice = $inv->fetch();
        if (!$invoice) return;

        $paidStmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE invoice_id = :id');
        $paidStmt->execute(['id' => $invoiceId]);
        $paid = round((float)$paidStmt->fetchColumn(), 2);

        $total = (float)$invoice['total_amount'];
        $remaining = round(max(0, $total - $paid), 2);

        $newStatus = self::determineStatus(
            $invoice['status'],
            $paid,
            $remaining,
            $invoice['due_date']
        );

        $stmt = $pdo->prepare('UPDATE invoices SET paid_amount = :paid, remaining_amount = :rem, status = :status WHERE id = :id');
        $stmt->execute(['paid' => $paid, 'rem' => $remaining, 'status' => $newStatus, 'id' => $invoiceId]);
    }

    /**
     * Single source of truth for the invoice status lifecycle.
     * Draft and Cancelled are terminal/manual states not driven by dates or payments.
     */
    public static function determineStatus(string $currentStatus, float $paid, float $remaining, string $dueDate): string
    {
        if ($currentStatus === 'cancelled') {
            return 'cancelled';
        }
        if ($currentStatus === 'draft') {
            return 'draft';
        }

        if ($remaining <= 0.001) {
            return 'paid';
        }

        $today = date('Y-m-d');
        if ($dueDate < $today) {
            return 'overdue';
        }

        if ($paid > 0.001) {
            return 'partially_paid';
        }

        return 'issued';
    }

    /**
     * Batch job: re-evaluate status for every non-terminal invoice whose due
     * date may have just passed. Called opportunistically (dashboard/list load)
     * since this system has no persistent background cron in this environment.
     */
    public static function refreshOverdueStatuses(PDO $pdo): int
    {
        $stmt = $pdo->query(
            "SELECT id FROM invoices WHERE status NOT IN ('draft','cancelled','paid')"
        );
        $count = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            self::refreshPaidAndStatus($pdo, (int)$id);
            $count++;
        }
        return $count;
    }

    /**
     * Record a payment against an invoice. Fully transactional:
     * validate -> insert payment -> recalc invoice -> notify -> audit.
     * Throws RuntimeException with a user-facing message on validation failure.
     */
    public static function applyPayment(PDO $pdo, int $invoiceId, float $amount, string $paymentDate, string $method, ?string $reference, ?string $notes, int $userId): int
    {
        if ($amount <= 0) {
            throw new RuntimeException('يجب أن يكون مبلغ الدفعة أكبر من صفر.');
        }

        $inv = $pdo->prepare('SELECT * FROM invoices WHERE id = :id');
        $inv->execute(['id' => $invoiceId]);
        $invoice = $inv->fetch();
        if (!$invoice) {
            throw new RuntimeException('الفاتورة غير موجودة.');
        }
        if (in_array($invoice['status'], ['draft', 'cancelled'], true)) {
            throw new RuntimeException('لا يمكن تسجيل دفعة على فاتورة في حالة مسودة أو ملغاة.');
        }

        $currentRemaining = (float)$invoice['remaining_amount'];
        if (round($amount - $currentRemaining, 2) > 0.001) {
            throw new RuntimeException(sprintf(
                'الدفعة بمبلغ %s تتجاوز الرصيد المتبقي البالغ %s. الدفع الزائد غير مدعوم.',
                number_format($amount, 2),
                number_format($currentRemaining, 2)
            ));
        }

        $stmt = $pdo->prepare(
            'INSERT INTO payments (invoice_id, customer_id, amount, payment_date, payment_method, reference_number, notes, created_by, created_at)
             VALUES (:inv, :cust, :amt, :pdate, :method, :ref, :notes, :uid, :now)'
        );
        $stmt->execute([
            'inv' => $invoiceId,
            'cust' => $invoice['customer_id'],
            'amt' => $amount,
            'pdate' => $paymentDate,
            'method' => $method,
            'ref' => $reference,
            'notes' => $notes,
            'uid' => $userId,
            'now' => date('Y-m-d H:i:s'),
        ]);
        $paymentId = (int)$pdo->lastInsertId();

        self::refreshPaidAndStatus($pdo, $invoiceId);

        // Reload to know the resulting status for notifications/audit.
        $after = $pdo->prepare('SELECT * FROM invoices WHERE id = :id');
        $after->execute(['id' => $invoiceId]);
        $updated = $after->fetch();

        Notifier::notifyRoles(
            ['Admin', 'Finance'],
            'تم تسجيل دفعة',
            sprintf('تم تسجيل دفعة بمبلغ %s %s للفاتورة %s.', number_format($amount, 2), $updated['currency'], $updated['invoice_number']),
            'payment_recorded',
            'invoice',
            $invoiceId
        );

        if ($updated['status'] === 'paid') {
            Notifier::notifyRoles(
                ['Admin', 'Finance'],
                'تم سداد الفاتورة بالكامل',
                sprintf('تم سداد الفاتورة %s بالكامل.', $updated['invoice_number']),
                'invoice_paid',
                'invoice',
                $invoiceId
            );
        }

        AuditLog::record('payment.created', 'payment', $paymentId, sprintf(
            'Recorded payment of %s for invoice %s', number_format($amount, 2), $invoice['invoice_number']
        ));

        return $paymentId;
    }

    /** Aggregate financial summary for one customer, computed live from the DB. */
    public static function customerSummary(PDO $pdo, int $customerId): array
    {
        $stmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS total_invoices,
                COALESCE(SUM(total_amount), 0) AS total_invoiced,
                COALESCE(SUM(paid_amount), 0) AS total_paid,
                COALESCE(SUM(CASE WHEN status != 'cancelled' THEN remaining_amount ELSE 0 END), 0) AS outstanding,
                COALESCE(SUM(CASE WHEN status = 'overdue' THEN remaining_amount ELSE 0 END), 0) AS overdue
             FROM invoices WHERE customer_id = :id AND status != 'draft'"
        );
        $stmt->execute(['id' => $customerId]);
        return $stmt->fetch();
    }

    /** Dashboard-wide financial statistics, computed live from the DB. */
    public static function dashboardStats(PDO $pdo): array
    {
        $stats = $pdo->query(
            "SELECT
                COUNT(*) AS total_invoices,
                COALESCE(SUM(CASE WHEN status != 'cancelled' THEN total_amount ELSE 0 END), 0) AS total_invoiced,
                COALESCE(SUM(paid_amount), 0) AS total_paid,
                COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','draft') THEN remaining_amount ELSE 0 END), 0) AS outstanding,
                COALESCE(SUM(CASE WHEN status = 'overdue' THEN remaining_amount ELSE 0 END), 0) AS overdue,
                COALESCE(SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END), 0) AS draft_count,
                COALESCE(SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END), 0) AS issued_count,
                COALESCE(SUM(CASE WHEN status = 'partially_paid' THEN 1 ELSE 0 END), 0) AS partially_paid_count,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count,
                COALESCE(SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END), 0) AS overdue_count,
                COALESCE(SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count
             FROM invoices"
        )->fetch();

        $customers = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE status = 'active'")->fetchColumn();

        $stats['active_customers'] = $customers;
        return $stats;
    }
}
