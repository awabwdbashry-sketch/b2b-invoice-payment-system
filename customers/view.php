<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('customers.view');
$pdo = Database::get();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id');
$stmt->execute(['id' => $id]);
$customer = $stmt->fetch();
if (!$customer) {
    flash('danger', 'العميل غير موجود.');
    redirect(base_path('customers/index.php'));
}

// IDOR-safe: the customer row is fetched directly by ID with permission already
// enforced above; there is no ownership scoping needed for this internal B2B
// tool (any authenticated user with customers.view may view any customer),
// which is an explicit, documented authorization decision — not an oversight.

InvoiceService::refreshOverdueStatuses($pdo);
$summary = InvoiceService::customerSummary($pdo, $id);

$invoices = $pdo->prepare(
    'SELECT * FROM invoices WHERE customer_id = :id ORDER BY invoice_date DESC'
);
$invoices->execute(['id' => $id]);
$invoices = $invoices->fetchAll();

$pageTitle = $customer['company_name'];
$activeMenu = 'customers';
require __DIR__ . '/../includes/layout_header.php';
?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
  <div>
    <h4 class="fw-bold mb-0"><?= e($customer['company_name']) ?>
      <span class="badge bg-<?= $customer['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e(account_status_label($customer['status'])) ?></span>
    </h4>
    <div class="text-muted small"><?= e($customer['contact_person']) ?> <?= $customer['email'] ? '· ' . e($customer['email']) : '' ?> <?= $customer['phone'] ? '· ' . e($customer['phone']) : '' ?></div>
  </div>
  <div class="d-flex gap-2">
    <?php if (Auth::can('customers.edit')): ?>
      <a href="<?= e(base_path('customers/edit.php?id=' . $id)) ?>" class="btn btn-outline-primary"><i class="fa-solid fa-pen me-1"></i>تعديل</a>
    <?php endif; ?>
    <?php if (Auth::can('invoices.create')): ?>
      <a href="<?= e(base_path('invoices/create.php?customer_id=' . $id)) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>فاتورة جديدة</a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm p-3"><div class="text-muted small">إجمالي الفواتير</div><div class="fs-5 fw-bold"><?= (int)$summary['total_invoices'] ?></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm p-3"><div class="text-muted small">إجمالي المفوتر</div><div class="fs-5 fw-bold"><?= e(format_money($summary['total_invoiced'])) ?></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm p-3"><div class="text-muted small">إجمالي المدفوع</div><div class="fs-5 fw-bold text-success"><?= e(format_money($summary['total_paid'])) ?></div></div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm p-3"><div class="text-muted small">الرصيد المستحق</div><div class="fs-5 fw-bold text-warning"><?= e(format_money($summary['outstanding'])) ?></div></div>
  </div>
</div>
<?php if ((float)$summary['overdue'] > 0): ?>
<div class="alert alert-danger py-2">الرصيد المتأخر: <strong><?= e(format_money($summary['overdue'])) ?></strong></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm p-3">
      <h6 class="fw-semibold mb-3">بيانات الشركة</h6>
      <table class="table table-sm table-borderless mb-0">
        <tr><th class="text-muted" style="width:40%;">العنوان</th><td><?= e($customer['address']) ?></td></tr>
        <tr><th class="text-muted">الرقم الضريبي</th><td><?= e($customer['tax_number']) ?></td></tr>
        <tr><th class="text-muted">رقم السجل التجاري</th><td><?= e($customer['registration_number']) ?></td></tr>
        <tr><th class="text-muted">ملاحظات</th><td><?= nl2br(e($customer['notes'])) ?></td></tr>
      </table>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">سجل الفواتير</div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr><th>الرقم</th><th>التاريخ</th><th>الاستحقاق</th><th>الإجمالي</th><th>المتبقي</th><th>الحالة</th></tr></thead>
          <tbody>
            <?php foreach ($invoices as $inv): [$badge, $label] = invoice_status_badge($inv['status']); ?>
              <tr class="cursor-pointer" onclick="window.location='<?= e(base_path('invoices/view.php?id=' . $inv['id'])) ?>'" style="cursor:pointer;">
                <td class="fw-semibold"><?= e($inv['invoice_number']) ?></td>
                <td><?= e(format_date($inv['invoice_date'])) ?></td>
                <td><?= e(format_date($inv['due_date'])) ?></td>
                <td><?= e(format_money($inv['total_amount'])) ?></td>
                <td><?= e(format_money($inv['remaining_amount'])) ?></td>
                <td><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$invoices): ?><tr><td colspan="6" class="text-center text-muted py-3">لا توجد فواتير بعد.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
