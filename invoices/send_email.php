<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('invoices.view');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_path('invoices/index.php'));
}
csrf_verify();

$pdo = Database::get();
$id = (int)($_POST['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT i.*, c.company_name, c.contact_person, c.email, c.phone, c.address, c.tax_number
     FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE i.id = :id'
);
$stmt->execute(['id' => $id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    flash('danger', 'الفاتورة غير موجودة.');
    redirect(base_path('invoices/index.php'));
}

if ($invoice['status'] === 'draft') {
    flash('warning', 'لا يمكن إرسال فاتورة في حالة مسودة. يرجى إصدارها أولاً.');
    redirect(base_path('invoices/view.php?id=' . $id));
}

require_once __DIR__ . '/../includes/Mailer.php';

// Sending never touches invoice status, paid_amount, or remaining_amount —
// this is a read-only notification action layered on top of the existing
// financial record.
$result = Mailer::sendInvoice($pdo, $invoice);

if ($result['ok']) {
    flash('success', 'تم إرسال الفاتورة بنجاح إلى ' . $invoice['email']);
} else {
    flash('danger', $result['error']);
}

redirect(base_path('invoices/view.php?id=' . $id));
