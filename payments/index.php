<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('payments.view');
$pdo = Database::get();

$search = clean_str($_GET['q'] ?? '');
$method = clean_str($_GET['method'] ?? '');
$from = clean_str($_GET['from'] ?? '');
$to = clean_str($_GET['to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(i.invoice_number LIKE :q OR c.company_name LIKE :q OR p.reference_number LIKE :q)';
    $params['q'] = "%$search%";
}
if ($method !== '' && in_array($method, ['cash','bank_transfer','cheque','card','other'], true)) {
    $where[] = 'p.payment_method = :method';
    $params['method'] = $method;
}
if ($from !== '') { $where[] = 'p.payment_date >= :from'; $params['from'] = $from; }
if ($to !== '') { $where[] = 'p.payment_date <= :to'; $params['to'] = $to; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM payments p JOIN invoices i ON i.id=p.invoice_id JOIN customers c ON c.id=p.customer_id $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT p.*, i.invoice_number, c.company_name, u.full_name AS recorded_by
     FROM payments p JOIN invoices i ON i.id = p.invoice_id JOIN customers c ON c.id = p.customer_id
     LEFT JOIN users u ON u.id = p.created_by
     $whereSql ORDER BY p.payment_date DESC, p.id DESC LIMIT :lim OFFSET :off"
);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue('lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue('off', $offset, PDO::PARAM_INT);
$stmt->execute();
$payments = $stmt->fetchAll();

$sumStmt = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN invoices i ON i.id=p.invoice_id JOIN customers c ON c.id=p.customer_id $whereSql");
$sumStmt->execute($params);
$totalReceived = (float)$sumStmt->fetchColumn();

$pageTitle = 'المدفوعات';
$activeMenu = 'payments';
require __DIR__ . '/../includes/layout_header.php';
?>
<style>.search-bar input, .search-bar select{ max-width:180px; }</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <form class="d-flex flex-wrap gap-2 search-bar" method="get">
    <input type="text" name="q" class="form-control" placeholder="رقم الفاتورة أو الشركة أو المرجع" value="<?= e($search) ?>">
    <select name="method" class="form-select">
      <option value="">كل الطرق</option>
      <?php foreach (['cash','bank_transfer','cheque','card','other'] as $m): ?>
        <option value="<?= $m ?>" <?= $method === $m ? 'selected' : '' ?>><?= e(payment_method_label($m)) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <button class="btn btn-outline-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
  </form>
  <div class="fw-semibold">الإجمالي: <span class="text-success"><?= e(format_money($totalReceived)) ?></span></div>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light"><tr><th>التاريخ</th><th>رقم الفاتورة</th><th>العميل</th><th>المبلغ</th><th>الطريقة</th><th>المرجع</th><th>سجّلها</th></tr></thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
          <tr style="cursor:pointer" onclick="window.location='<?= e(base_path('invoices/view.php?id=' . $p['invoice_id'])) ?>'">
            <td><?= e(format_date($p['payment_date'])) ?></td>
            <td class="fw-semibold"><?= e($p['invoice_number']) ?></td>
            <td><?= e($p['company_name']) ?></td>
            <td class="text-success fw-semibold"><?= e(format_money($p['amount'])) ?></td>
            <td><?= e(payment_method_label($p['payment_method'])) ?></td>
            <td><?= e($p['reference_number']) ?></td>
            <td><?= e($p['recorded_by']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$payments): ?><tr><td colspan="7" class="text-center text-muted py-4">لا توجد مدفوعات.</td></tr><?php endif; ?>
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
