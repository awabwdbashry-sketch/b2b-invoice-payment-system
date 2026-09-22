<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('invoices.view');
$pdo = Database::get();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT i.*, c.company_name, c.contact_person, c.email, c.phone, c.address, c.tax_number FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE i.id = :id');
$stmt->execute(['id' => $id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    http_response_code(404);
    die('الفاتورة غير موجودة.');
}

AuditLog::record('invoice.pdf_generated', 'invoice', $id, 'تم إنشاء PDF للفاتورة ' . $invoice['invoice_number']);

require_once __DIR__ . '/../includes/InvoicePdfBuilder.php';
$pdf = InvoicePdfBuilder::build($pdo, $invoice);
$pdf->Output(InvoicePdfBuilder::filename($invoice), 'I');
