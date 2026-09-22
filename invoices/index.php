<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('invoices.view');
$pdo = Database::get();
InvoiceService::refreshOverdueStatuses($pdo);

$search = clean_str($_GET['q'] ?? '');
$status = clean_str($_GET['status'] ?? '');
$customerId = (int)($_GET['customer_id'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(i.invoice_number LIKE :q OR c.company_name LIKE :q)';
    $params['q'] = "%$search%";
}
if ($status !== '' && in_array($status, ['draft','issued','partially_paid','paid','overdue','cancelled'], true)) {
    $where[] = 'i.status = :status';
    $params['status'] = $status;
}
if ($customerId > 0) {
    $where[] = 'i.customer_id = :cid';
    $params['cid'] = $customerId;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM invoices i JOIN customers c ON c.id = i.customer_id $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT i.*, c.company_name FROM invoices i JOIN customers c ON c.id = i.customer_id
     $whereSql ORDER BY i.created_at DESC LIMIT :lim OFFSET :off"
);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue('lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue('off', $offset, PDO::PARAM_INT);
$stmt->execute();
$invoices = $stmt->fetchAll();

$customers = $pdo->query("SELECT id, company_name FROM customers WHERE status='active' ORDER BY company_name")->fetchAll();

$pageTitle = 'الفواتير';
$activeMenu = 'invoices';
require __DIR__ . '/../includes/layout_header.php';
?>
<style>.search-bar select, .search-bar input{ max-width:200px; }</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <form class="d-flex flex-wrap gap-2 search-bar" method="get">
    <input type="text" name="q" class="form-control" placeholder="رقم الفاتورة أو الشركة" value="<?= e($search) ?>">
    <select name="status" class="form-select">
      <option value="">كل الحالات</option>
      <?php foreach (['draft','issued','partially_paid','paid','overdue','cancelled'] as $s): [, $label] = invoice_status_badge($s); ?>
        <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <select name="customer_id" class="form-select">
      <option value="">كل العملاء</option>
      <?php foreach ($customers as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $customerId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['company_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
  </form>
  <?php if (Auth::can('invoices.create')): ?>
    <a href="<?= e(base_path('invoices/create.php')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>فاتورة جديدة</a>
  <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>رقم الفاتورة</th><th>العميل</th><th>التاريخ</th><th>الاستحقاق</th><th>الإجمالي</th><th>المدفوع</th><th>المتبقي</th><th>الحالة</th></tr>
      </thead>
      <tbody>
        <?php foreach ($invoices as $inv): [$badge, $label] = invoice_status_badge($inv['status']); ?>
          <tr style="cursor:pointer" onclick="window.location='<?= e(base_path('invoices/view.php?id=' . $inv['id'])) ?>'">
            <td class="fw-semibold"><?= e($inv['invoice_number']) ?></td>
            <td><?= e($inv['company_name']) ?></td>
            <td><?= e(format_date($inv['invoice_date'])) ?></td>
            <td><?= e(format_date($inv['due_date'])) ?></td>
            <td><?= e(format_money($inv['total_amount'], $inv['currency'])) ?></td>
            <td><?= e(format_money($inv['paid_amount'])) ?></td>
            <td><?= e(format_money($inv['remaining_amount'])) ?></td>
            <td><span class="badge bg-<?= $badge ?>"><?= $label ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$invoices): ?><tr><td colspan="8" class="text-center text-muted py-4">لا توجد فواتير.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3">
  <ul class="pagination justify-content-center flex-wrap">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= e(build_query(['page' => $i])) ?>"><?= $i ?></a></li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
