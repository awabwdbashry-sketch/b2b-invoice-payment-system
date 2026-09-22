<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/tcpdf/tcpdf.php';

if (!class_exists('InvoicePDF')) {
    class InvoicePDF extends TCPDF
    {
        public string $companyName = '';
        public function Header()
        {
            $this->SetFont('aealarabiya', 'B', 16);
            $this->SetTextColor(15, 76, 129);
            $this->SetXY(-15 - 80, 12);
            $this->Cell(80, 8, $this->companyName, 0, 1, 'R');
        }
        public function Footer()
        {
            $this->SetY(-15);
            $this->SetFont('aealarabiya', '', 8);
            $this->SetTextColor(120, 120, 120);
            $this->Cell(0, 10, 'صفحة ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'C');
        }
    }
}

/**
 * Builds the Arabic/RTL invoice PDF from live database data.
 * Single source of truth for PDF content — used by invoices/pdf.php
 * (browser download/print) and by Mailer::sendInvoice() (email attachment)
 * so the two never drift out of sync.
 */
final class InvoicePdfBuilder
{
    /** Build and return the finished TCPDF document for one invoice. */
    public static function build(PDO $pdo, array $invoice): InvoicePDF
    {
        $items = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id = :id ORDER BY sort_order ASC, id ASC');
        $items->execute(['id' => $invoice['id']]);
        $items = $items->fetchAll();

        $payments = $pdo->prepare('SELECT * FROM payments WHERE invoice_id = :id ORDER BY payment_date ASC');
        $payments->execute(['id' => $invoice['id']]);
        $payments = $payments->fetchAll();

        $settings = $pdo->query('SELECT `key`, `value` FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);

        $pdf = new InvoicePDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->companyName = $settings['company_name'] ?? APP_NAME_AR;
        $pdf->SetCreator(APP_NAME_AR);
        $pdf->SetAuthor($settings['company_name'] ?? APP_NAME_AR);
        $pdf->SetTitle('فاتورة ' . $invoice['invoice_number']);

        // ---- Arabic RTL setup ----
        $pdf->setRTL(true);
        $pdf->SetFont('aealarabiya', '', 10);

        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);
        $pdf->SetMargins(15, 26, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        [, $statusLabel] = invoice_status_badge($invoice['status']);

        $html = '
<style>
  .co-block{font-size:9pt;color:#555;}
  .bill-title{font-size:10pt;font-weight:bold;color:#0f4c81;}
  table.items{width:100%;border-collapse:collapse;}
  table.items th{padding:5px;font-size:9pt;color:#ffffff;}
  table.items td{padding:5px;border-bottom:1px solid #eee;font-size:9pt;}
  .totals td{padding:3px 5px;font-size:9pt;}
  .status-badge{display:inline;padding:3px 8px;color:#ffffff;font-size:9pt;}
</style>

<div dir="rtl">
<table cellpadding="4" dir="rtl"><tr>
  <td width="50%">
    <span class="bill-title">من</span><br>
    <div class="co-block">
      <strong>' . e($settings['company_name'] ?? '') . '</strong><br>
      ' . nl2br(e($settings['company_address'] ?? '')) . '<br>
      ' . e($settings['company_email'] ?? '') . '<br>
      ' . e($settings['company_phone'] ?? '') . '<br>
      ' . (!empty($settings['company_tax_number']) ? 'الرقم الضريبي: ' . e($settings['company_tax_number']) : '') . '
    </div>
  </td>
  <td width="50%" align="left">
    <span class="bill-title">فاتورة</span><br>
    <div class="co-block">
      <strong>' . e($invoice['invoice_number']) . '</strong><br>
      تاريخ الفاتورة: ' . e(format_date($invoice['invoice_date'])) . '<br>
      تاريخ الاستحقاق: ' . e(format_date($invoice['due_date'])) . '<br>
      الحالة: <span class="status-badge" bgcolor="#0f4c81">&nbsp;' . e($statusLabel) . '&nbsp;</span>
    </div>
  </td>
</tr></table>

<br>
<span class="bill-title">إلى</span><br>
<div class="co-block">
  <strong>' . e($invoice['company_name']) . '</strong><br>
  ' . e($invoice['contact_person']) . '<br>
  ' . e($invoice['address']) . '<br>
  ' . e($invoice['email']) . ' ' . ($invoice['phone'] ? '· ' . e($invoice['phone']) : '') . '<br>
  ' . (!empty($invoice['tax_number']) ? 'الرقم الضريبي: ' . e($invoice['tax_number']) : '') . '
</div>

<br><br>
<table class="items" dir="rtl">
  <tr bgcolor="#0f4c81"><th align="right">الوصف</th><th align="center">الكمية</th><th align="center">سعر الوحدة</th><th align="center">الخصم</th><th align="center">الضريبة %</th><th align="center">إجمالي السطر</th></tr>';

        foreach ($items as $it) {
            $html .= '<tr>
        <td align="right">' . e($it['description']) . '</td>
        <td align="center">' . e(rtrim(rtrim(number_format((float)$it['quantity'], 2), '0'), '.')) . '</td>
        <td align="center">' . e(number_format((float)$it['unit_price'], 2)) . '</td>
        <td align="center">' . e(number_format((float)$it['discount_amount'], 2)) . '</td>
        <td align="center">' . e(number_format((float)$it['tax_percent'], 2)) . '%</td>
        <td align="center">' . e(number_format((float)$it['line_total'], 2)) . '</td>
    </tr>';
        }

        $html .= '</table>
<br>
<table width="100%" dir="rtl"><tr><td width="40%">
  <table class="totals" width="100%">
    <tr><td>المجموع الفرعي</td><td align="left">' . e(number_format((float)$invoice['subtotal'], 2)) . ' ' . e($invoice['currency']) . '</td></tr>
    <tr><td>الخصم</td><td align="left">-' . e(number_format((float)$invoice['discount_amount'], 2)) . '</td></tr>
    <tr><td>الضريبة</td><td align="left">' . e(number_format((float)$invoice['tax_amount'], 2)) . '</td></tr>
    <tr><td><strong>الإجمالي</strong></td><td align="left"><strong>' . e(number_format((float)$invoice['total_amount'], 2)) . ' ' . e($invoice['currency']) . '</strong></td></tr>
    <tr><td>المدفوع</td><td align="left">' . e(number_format((float)$invoice['paid_amount'], 2)) . '</td></tr>
    <tr><td><strong>المتبقي</strong></td><td align="left"><strong>' . e(number_format((float)$invoice['remaining_amount'], 2)) . '</strong></td></tr>
  </table>
</td><td width="60%"></td></tr></table>';

        if ($payments) {
            $html .= '<br><span class="bill-title">سجل المدفوعات</span><br><table class="items" width="100%" dir="rtl">
        <tr bgcolor="#0f4c81"><th align="right">التاريخ</th><th align="right">طريقة الدفع</th><th align="right">المرجع</th><th align="center">المبلغ</th></tr>';
            foreach ($payments as $p) {
                $html .= '<tr>
            <td align="right">' . e(format_date($p['payment_date'])) . '</td>
            <td align="right">' . e(payment_method_label($p['payment_method'])) . '</td>
            <td align="right">' . e($p['reference_number'] ?? '') . '</td>
            <td align="center">' . e(number_format((float)$p['amount'], 2)) . '</td>
        </tr>';
            }
            $html .= '</table>';
        }

        if (!empty($invoice['notes'])) {
            $html .= '<br><span class="bill-title">ملاحظات</span><br><div class="co-block">' . nl2br(e($invoice['notes'])) . '</div>';
        }

        $html .= '</div>';

        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf;
    }

    /** Convenience: return the raw PDF bytes as a string (for email attachments). */
    public static function buildString(PDO $pdo, array $invoice): string
    {
        return self::build($pdo, $invoice)->Output('', 'S');
    }

    public static function filename(array $invoice): string
    {
        return 'فاتورة-' . $invoice['invoice_number'] . '.pdf';
    }
}
