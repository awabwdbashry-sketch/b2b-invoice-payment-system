<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('payments.create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(base_path('payments/index.php'));
}
csrf_verify();

$pdo = Database::get();
$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$amount = to_money($_POST['amount'] ?? 0);
$paymentDate = clean_str($_POST['payment_date'] ?? '');
$method = clean_str($_POST['payment_method'] ?? '');
$reference = clean_str($_POST['reference_number'] ?? '') ?: null;
$notes = clean_str($_POST['notes'] ?? '') ?: null;

$allowedMethods = ['cash', 'bank_transfer', 'cheque', 'card', 'other'];

$errors = [];
if ($paymentDate === '' || !strtotime($paymentDate)) $errors[] = 'يجب إدخال تاريخ دفعة صحيح.';
if (!in_array($method, $allowedMethods, true)) $errors[] = 'طريقة الدفع غير صالحة.';
if ($amount <= 0) $errors[] = 'يجب أن يكون مبلغ الدفعة أكبر من صفر.';

if ($errors) {
    flash('danger', implode(' ', $errors));
    redirect(base_path('invoices/view.php?id=' . $invoiceId));
}

try {
    Database::transaction(function (PDO $pdo) use ($invoiceId, $amount, $paymentDate, $method, $reference, $notes) {
        InvoiceService::applyPayment($pdo, $invoiceId, $amount, $paymentDate, $method, $reference, $notes, Auth::id());
    });
    flash('success', 'تم تسجيل الدفعة بنجاح.');
} catch (RuntimeException $e) {
    flash('danger', $e->getMessage());
} catch (Throwable $e) {
    error_log('Payment failed: ' . $e->getMessage());
    flash('danger', 'تعذر تسجيل الدفعة بسبب خطأ في الخادم.');
}

redirect(base_path('invoices/view.php?id=' . $invoiceId));
