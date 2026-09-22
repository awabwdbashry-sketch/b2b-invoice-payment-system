<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/phpmailer/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/SMTP.php';
require_once __DIR__ . '/InvoicePdfBuilder.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Handles emailing an invoice (with its PDF attached) to the customer.
 *
 * Design guarantees required by the spec:
 * - Never changes invoice status or payment state, whether it succeeds or fails.
 * - Never reports success unless the SMTP transaction actually succeeded.
 * - Refuses clearly (no silent failure) when the customer has no valid email
 *   or when SMTP isn't configured.
 * - Credentials only ever come from environment variables (config.php), never
 *   hardcoded here or committed to the repo.
 */
final class Mailer
{
    /**
     * Send one invoice (as a PDF attachment) to its customer's email.
     * Returns ['ok' => bool, 'error' => string|null].
     */
    public static function sendInvoice(PDO $pdo, array $invoice): array
    {
        $to = trim((string)($invoice['email'] ?? ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'لا يوجد بريد إلكتروني صالح لهذا العميل. يرجى تحديث بيانات العميل أولاً.'];
        }

        if (!MAIL_CONFIGURED) {
            return ['ok' => false, 'error' => 'لم يتم إعداد خدمة البريد الإلكتروني (SMTP) على الخادم بعد. يرجى مراجعة مدير النظام.'];
        }

        $settings = $pdo->query('SELECT `key`, `value` FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
        $companyName = $settings['company_name'] ?? APP_NAME_AR;

        try {
            $pdfContent = InvoicePdfBuilder::buildString($pdo, $invoice);
        } catch (Throwable $e) {
            error_log('Invoice PDF build failed before emailing: ' . $e->getMessage());
            return ['ok' => false, 'error' => 'تعذر إنشاء ملف PDF للفاتورة، لم يتم الإرسال.'];
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->Port = MAIL_PORT;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            if (MAIL_ENCRYPTION === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif (MAIL_ENCRYPTION === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAutoTLS = false;
            }
            $mail->CharSet = 'UTF-8';

            $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            $mail->addAddress($to, $invoice['company_name'] ?? '');
            $replyTo = $settings['company_email'] ?? '';
            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo, $companyName);
            }

            $mail->Subject = sprintf('فاتورة %s من %s', $invoice['invoice_number'], $companyName);

            [, $statusLabel] = invoice_status_badge($invoice['status']);
            $mail->isHTML(true);
            $mail->Body = self::buildBodyHtml($invoice, $companyName, $statusLabel);
            $mail->AltBody = self::buildBodyPlain($invoice, $companyName, $statusLabel);

            $mail->addStringAttachment($pdfContent, InvoicePdfBuilder::filename($invoice), 'base64', 'application/pdf');

            $mail->send();

            AuditLog::record('invoice.emailed', 'invoice', (int)$invoice['id'], 'تم إرسال الفاتورة ' . $invoice['invoice_number'] . ' بالبريد إلى ' . $to);

            return ['ok' => true, 'error' => null];
        } catch (PHPMailerException | Throwable $e) {
            error_log('Invoice email failed: ' . $e->getMessage());
            AuditLog::record('invoice.email_failed', 'invoice', (int)$invoice['id'], 'فشل إرسال الفاتورة ' . $invoice['invoice_number'] . ' إلى ' . $to);
            return ['ok' => false, 'error' => 'تعذر إرسال البريد الإلكتروني. يرجى المحاولة لاحقًا أو مراجعة إعدادات SMTP.'];
        }
    }

    private static function buildBodyHtml(array $invoice, string $companyName, string $statusLabel): string
    {
        $remaining = number_format((float)$invoice['remaining_amount'], 2);
        $total = number_format((float)$invoice['total_amount'], 2);
        return '
        <div dir="rtl" style="font-family:Cairo,Segoe UI,sans-serif;color:#1f2937;max-width:560px;margin:0 auto;">
          <div style="background:#0f4c81;padding:20px 24px;border-radius:10px 10px 0 0;">
            <h2 style="color:#fff;margin:0;font-size:18px;">' . e($companyName) . '</h2>
          </div>
          <div style="border:1px solid #e5e9f0;border-top:none;border-radius:0 0 10px 10px;padding:24px;">
            <p>مرحبًا ' . e($invoice['contact_person'] ?? '') . '،</p>
            <p>نرفق لكم الفاتورة رقم <strong>' . e($invoice['invoice_number']) . '</strong> بصيغة PDF.</p>
            <table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;">
              <tr><td style="padding:6px 0;color:#6b7280;">حالة الفاتورة</td><td style="padding:6px 0;font-weight:600;">' . e($statusLabel) . '</td></tr>
              <tr><td style="padding:6px 0;color:#6b7280;">إجمالي الفاتورة</td><td style="padding:6px 0;font-weight:600;">' . e($total) . ' ' . e($invoice['currency']) . '</td></tr>
              <tr><td style="padding:6px 0;color:#6b7280;">المبلغ المتبقي</td><td style="padding:6px 0;font-weight:600;">' . e($remaining) . ' ' . e($invoice['currency']) . '</td></tr>
              <tr><td style="padding:6px 0;color:#6b7280;">تاريخ الاستحقاق</td><td style="padding:6px 0;font-weight:600;">' . e(format_date($invoice['due_date'])) . '</td></tr>
            </table>
            <p style="color:#6b7280;font-size:13px;">لأي استفسار بخصوص هذه الفاتورة، يرجى الرد على هذا البريد.</p>
            <p style="margin-top:24px;">شكرًا لتعاملكم معنا.<br><strong>' . e($companyName) . '</strong></p>
          </div>
        </div>';
    }

    private static function buildBodyPlain(array $invoice, string $companyName, string $statusLabel): string
    {
        $remaining = number_format((float)$invoice['remaining_amount'], 2);
        $total = number_format((float)$invoice['total_amount'], 2);
        return "فاتورة {$invoice['invoice_number']} من {$companyName}\n\n"
            . "الحالة: {$statusLabel}\n"
            . "الإجمالي: {$total} {$invoice['currency']}\n"
            . "المتبقي: {$remaining} {$invoice['currency']}\n"
            . "تاريخ الاستحقاق: " . format_date($invoice['due_date']) . "\n\n"
            . "مرفق ملف PDF للفاتورة.\n\n{$companyName}";
    }
}
