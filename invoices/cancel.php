<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('invoices.cancel');

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

if (!in_array($invoice['status'], ['draft', 'issued', 'overdue'], true)) {
    flash('warning', 'لا يمكن إلغاء هذه الفاتورة من حالتها الحالية.');
    redirect(base_path('invoices/view.php?id=' . $id));
}

if ((float)$invoice['paid_amount'] > 0) {
    flash('danger', 'لا يمكن إلغاء فاتورة تحتوي على مدفوعات مسجلة، حفاظًا على السجل المالي.');
    redirect(base_path('invoices/view.php?id=' . $id));
}

$pdo->prepare("UPDATE invoices SET status = 'cancelled' WHERE id = :id")->execute(['id' => $id]);
AuditLog::record('invoice.cancelled', 'invoice', $id, 'تم إلغاء الفاتورة ' . $invoice['invoice_number']);

flash('success', 'تم إلغاء الفاتورة.');
redirect(base_path('invoices/view.php?id=' . $id));
