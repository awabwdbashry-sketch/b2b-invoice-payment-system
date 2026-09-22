<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('invoices.issue');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_path('invoices/index.php'));
}
csrf_verify();

$pdo = Database::get();
$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = :id');
$stmt->execute(['id' => $id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    flash('danger', 'الفاتورة غير موجودة.');
    redirect(base_path('invoices/index.php'));
}

if ($invoice['status'] !== 'draft') {
    flash('warning', 'يمكن إصدار الفواتير التي في حالة مسودة فقط.');
    redirect(base_path('invoices/view.php?id=' . $id));
}

if ((float)$invoice['total_amount'] <= 0) {
    flash('danger', 'لا يمكن إصدار فاتورة بدون أصناف أو بإجمالي صفر.');
    redirect(base_path('invoices/view.php?id=' . $id));
}

Database::transaction(function (PDO $pdo) use ($id, $invoice) {
    // Valid transition: draft -> issued (status then re-evaluated for overdue/paid by the shared status engine)
    $pdo->prepare("UPDATE invoices SET status = 'issued' WHERE id = :id")->execute(['id' => $id]);
    InvoiceService::refreshPaidAndStatus($pdo, $id);

    AuditLog::record('invoice.issued', 'invoice', $id, 'تم إصدار الفاتورة ' . $invoice['invoice_number']);

    Notifier::notifyRoles(
        ['Admin', 'Finance'],
        'تم إصدار فاتورة جديدة',
        sprintf('تم إصدار الفاتورة %s بإجمالي %s.', $invoice['invoice_number'], number_format((float)$invoice['total_amount'], 2)),
        'invoice_created',
        'invoice',
        $id
    );
});

flash('success', 'تم إصدار الفاتورة بنجاح.');
redirect(base_path('invoices/view.php?id=' . $id));
