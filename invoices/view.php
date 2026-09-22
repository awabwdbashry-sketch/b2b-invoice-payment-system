<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('invoices.view');
$pdo = Database::get();

$id = (int)($_GET['id'] ?? 0);
InvoiceService::refreshOverdueStatuses($pdo);

$stmt = $pdo->prepare('SELECT i.*, c.company_name, c.contact_person, c.email, c.phone, c.address FROM invoices i JOIN customers c ON c.id = i.customer_id WHERE i.id = :id');
$stmt->execute(['id' => $id]);
$invoice = $stmt->fetch();
if (!$invoice) {
    flash('danger', 'الفاتورة غير موجودة.');
    redirect(base_path('invoices/index.php'));
}

$items = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id = :id ORDER BY sort_order ASC, id ASC');
$items->execute(['id' => $id]);
$items = $items->fetchAll();

$payments = $pdo->prepare('SELECT p.*, u.full_name AS recorded_by FROM payments p LEFT JOIN users u ON u.id = p.created_by WHERE invoice_id = :id ORDER BY payment_date DESC, id DESC');
$payments->execute(['id' => $id]);
$payments = $payments->fetchAll();

[$badge, $label] = invoice_status_badge($invoice['status']);
$daysOverdue = $invoice['status'] === 'overdue' ? (int)((strtotime(date('Y-m-d')) - strtotime($invoice['due_date'])) / 86400) : 0;

$pageTitle = $invoice['invoice_number'];
$activeMenu = 'invoices';
require __DIR__ . '/../includes/layout_header.php';
?>
<style>
  .invoice-header{ border-radius:14px; }
  .items-view-table th, .items-view-table td{ vertical-align:middle; }
  @media print{
    .app-sidebar, .app-topbar, .sidebar-backdrop, #toastStack,
    .d-flex.gap-2.flex-wrap, .no-print{ display:none !important; }
    .app-main{ margin-right:0 !important; }
    .app-content{ padding:0 !important; }
    .card{ box-shadow:none !important; border:1px solid #ddd !important; }
    body{ background:#fff !important; }
  }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h4 class="fw-bold mb-1"><?= e($invoice['invoice_number']) ?> <span class="badge bg-<?= $badge ?>"><?= $label ?></span></h4>
    <div class="text-muted small">
      <?= e($invoice['company_name']) ?> · تاريخ الإصدار <?= e(format_date($invoice['invoice_date'])) ?> · تاريخ الاستحقاق <?= e(format_date($invoice['due_date'])) ?>
      <?php if ($daysOverdue > 0): ?><span class="text-danger fw-semibold"> · <?= $daysOverdue ?> يوم تأخير</span><?php endif; ?>
    </div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>طباعة</button>
    <a href="<?= e(base_path('invoices/pdf.php?id=' . $id)) ?>" class="btn btn-outline-dark" target="_blank"><i class="fa-solid fa-file-pdf me-1"></i>PDF</a>
    <?php if ($invoice['status'] !== 'draft' && Auth::can('invoices.view')): ?>
      <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sendEmailModal"><i class="fa-solid fa-paper-plane me-1"></i>إرسال بالبريد</button>
    <?php endif; ?>
    <?php if ($invoice['status'] === 'draft' && Auth::can('invoices.edit')): ?>
      <a href="<?= e(base_path('invoices/edit.php?id=' . $id)) ?>" class="btn btn-outline-primary"><i class="fa-solid fa-pen me-1"></i>تعديل</a>
    <?php endif; ?>
    <?php if ($invoice['status'] === 'draft' && Auth::can('invoices.issue')): ?>
      <form method="post" action="<?= e(base_path('invoices/issue.php')) ?>" class="d-inline" onsubmit="return confirm('هل تريد إصدار هذه الفاتورة؟ لن يمكن تعديلها بحرية بعد ذلك.');">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i>إصدار الفاتورة</button>
      </form>
    <?php endif; ?>
    <?php if (in_array($invoice['status'], ['draft','issued','overdue'], true) && (float)$invoice['paid_amount'] == 0 && Auth::can('invoices.cancel')): ?>
      <form method="post" action="<?= e(base_path('invoices/cancel.php')) ?>" class="d-inline" onsubmit="return confirm('هل تريد إلغاء هذه الفاتورة؟ لا يمكن التراجع عن هذا الإجراء.');">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-outline-danger"><i class="fa-solid fa-ban me-1"></i>إلغاء</button>
      </form>
    <?php endif; ?>
    <?php if (!in_array($invoice['status'], ['draft','cancelled','paid'], true) && (float)$invoice['remaining_amount'] > 0 && Auth::can('payments.create')): ?>
      <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal"><i class="fa-solid fa-money-bill-wave me-1"></i>تسجيل دفعة</button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">الفاتورة إلى</div>
      <div class="card-body">
        <div class="fw-semibold"><?= e($invoice['company_name']) ?></div>
        <div class="text-muted small"><?= e($invoice['contact_person']) ?></div>
        <div class="text-muted small"><?= e($invoice['address']) ?></div>
        <div class="text-muted small"><?= e($invoice['email']) ?> <?= $invoice['phone'] ? '· ' . e($invoice['phone']) : '' ?></div>
      </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">الأصناف</div>
      <div class="table-responsive">
        <table class="table items-view-table mb-0">
          <thead class="table-light"><tr><th>الوصف</th><th class="text-end">الكمية</th><th class="text-end">سعر الوحدة</th><th class="text-end">الخصم</th><th class="text-end">الضريبة %</th><th class="text-end">إجمالي السطر</th></tr></thead>
          <tbody>
            <?php foreach ($items as $it): ?>
              <tr>
                <td><?= e($it['description']) ?></td>
                <td class="text-end"><?= e(rtrim(rtrim(number_format((float)$it['quantity'], 2), '0'), '.')) ?></td>
                <td class="text-end"><?= e(format_money($it['unit_price'])) ?></td>
                <td class="text-end"><?= e(format_money($it['discount_amount'])) ?></td>
                <td class="text-end"><?= e(number_format((float)$it['tax_percent'], 2)) ?>%</td>
                <td class="text-end fw-semibold"><?= e(format_money($it['line_total'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-body border-top">
        <div class="row justify-content-end">
          <div class="col-md-5">
            <div class="d-flex justify-content-between"><span class="text-muted">المجموع الفرعي</span><span><?= e(format_money($invoice['subtotal'])) ?></span></div>
            <div class="d-flex justify-content-between"><span class="text-muted">الخصم</span><span>-<?= e(format_money($invoice['discount_amount'])) ?></span></div>
            <div class="d-flex justify-content-between"><span class="text-muted">الضريبة</span><span><?= e(format_money($invoice['tax_amount'])) ?></span></div>
            <div class="d-flex justify-content-between fw-bold fs-5 border-top mt-2 pt-2"><span>الإجمالي</span><span><?= e(format_money($invoice['total_amount'], $invoice['currency'])) ?></span></div>
            <div class="d-flex justify-content-between text-success"><span>المدفوع</span><span><?= e(format_money($invoice['paid_amount'])) ?></span></div>
            <div class="d-flex justify-content-between fw-semibold text-warning"><span>المتبقي</span><span><?= e(format_money($invoice['remaining_amount'])) ?></span></div>
          </div>
        </div>
      </div>
    </div>

    <?php if ($invoice['notes']): ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">ملاحظات</div>
      <div class="card-body text-muted"><?= nl2br(e($invoice['notes'])) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-4">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">سجل المدفوعات</div>
      <div class="list-group list-group-flush">
        <?php foreach ($payments as $p): ?>
          <div class="list-group-item">
            <div class="d-flex justify-content-between">
              <span class="fw-semibold text-success">+<?= e(format_money($p['amount'])) ?></span>
              <span class="small text-muted"><?= e(format_date($p['payment_date'])) ?></span>
            </div>
            <div class="small text-muted"><?= e(payment_method_label($p['payment_method'])) ?><?= $p['reference_number'] ? ' · Ref: ' . e($p['reference_number']) : '' ?></div>
            <?php if ($p['notes']): ?><div class="small text-muted fst-italic"><?= e($p['notes']) ?></div><?php endif; ?>
            <div class="small text-muted">بواسطة <?= e($p['recorded_by'] ?? 'Unknown') ?></div>
          </div>
        <?php endforeach; ?>
        <?php if (!$payments): ?><div class="list-group-item text-muted small">لا توجد مدفوعات مسجلة بعد.</div><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="<?= e(base_path('payments/create.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="invoice_id" value="<?= $id ?>">
        <div class="modal-header">
          <h5 class="modal-title">تسجيل دفعة — <?= e($invoice['invoice_number']) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small">الرصيد المتبقي: <strong><?= e(format_money($invoice['remaining_amount'], $invoice['currency'])) ?></strong></p>
          <div class="mb-3">
            <label class="form-label">المبلغ *</label>
            <input type="number" step="0.01" min="0.01" max="<?= e((string)$invoice['remaining_amount']) ?>" name="amount" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">تاريخ الدفعة *</label>
            <input type="date" name="payment_date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">طريقة الدفع *</label>
            <select name="payment_method" class="form-select" required>
              <option value="bank_transfer">تحويل بنكي</option>
              <option value="cash">نقدًا</option>
              <option value="cheque">شيك</option>
              <option value="card">بطاقة</option>
              <option value="other">أخرى</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">رقم المرجع</label>
            <input type="text" name="reference_number" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">ملاحظات</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>حفظ الدفعة</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Send Invoice by Email Modal -->
<div class="modal fade" id="sendEmailModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post" action="<?= e(base_path('invoices/send_email.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="modal-header">
          <h5 class="modal-title">إرسال الفاتورة بالبريد الإلكتروني</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php if (empty($invoice['email'])): ?>
            <div class="alert alert-warning mb-0">
              لا يوجد بريد إلكتروني مسجّل لهذا العميل. يرجى
              <a href="<?= e(base_path('customers/edit.php?id=' . $invoice['customer_id'])) ?>">تحديث بيانات العميل</a>
              أولًا لإضافة بريد إلكتروني صالح.
            </div>
          <?php else: ?>
            <p class="mb-2">سيتم إرسال الفاتورة <strong><?= e($invoice['invoice_number']) ?></strong> كملف PDF إلى:</p>
            <p class="fw-semibold text-primary" style="direction:ltr; text-align:right;"><?= e($invoice['email']) ?></p>
            <p class="text-muted small mb-0">لن يتغيّر شيء في حالة الفاتورة أو المدفوعات بسبب هذا الإرسال.</p>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button>
          <?php if (!empty($invoice['email'])): ?>
            <button class="btn btn-primary"><i class="fa-solid fa-paper-plane me-1"></i>إرسال الآن</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
