<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('customers.view');
$pdo = Database::get();

$search = clean_str($_GET['q'] ?? '');
$status = clean_str($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(company_name LIKE :q OR contact_person LIKE :q OR email LIKE :q)';
    $params['q'] = "%$search%";
}
if ($status !== '' && in_array($status, ['active', 'inactive'], true)) {
    $where[] = 'status = :status';
    $params['status'] = $status;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM customers $whereSql");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM customers $whereSql ORDER BY company_name ASC LIMIT :lim OFFSET :off");
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue('lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue('off', $offset, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll();

$pageTitle = 'العملاء';
$activeMenu = 'customers';
require __DIR__ . '/../includes/layout_header.php';
?>
<style>
  .search-bar .form-control, .search-bar .form-select{ max-width:220px; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <form class="d-flex flex-wrap gap-2 search-bar" method="get">
    <input type="text" name="q" class="form-control" placeholder="بحث بالشركة أو جهة الاتصال أو البريد" value="<?= e($search) ?>">
    <select name="status" class="form-select">
      <option value="">كل الحالات</option>
      <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>نشط</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>غير نشط</option>
    </select>
    <button class="btn btn-outline-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
  </form>
  <?php if (Auth::can('customers.create')): ?>
    <a href="<?= e(base_path('customers/create.php')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>عميل جديد</a>
  <?php endif; ?>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>الشركة</th><th>جهة الاتصال</th><th>البريد الإلكتروني</th><th>الهاتف</th><th>الحالة</th><th class="text-end">إجراءات</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($customers as $c): ?>
          <tr>
            <td class="fw-semibold"><a class="text-decoration-none" href="<?= e(base_path('customers/view.php?id=' . $c['id'])) ?>"><?= e($c['company_name']) ?></a></td>
            <td><?= e($c['contact_person']) ?></td>
            <td><?= e($c['email']) ?></td>
            <td><?= e($c['phone']) ?></td>
            <td><span class="badge bg-<?= $c['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e(account_status_label($c['status'])) ?></span></td>
            <td class="text-end">
              <a href="<?= e(base_path('customers/view.php?id=' . $c['id'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-eye"></i></a>
              <?php if (Auth::can('customers.edit')): ?>
                <a href="<?= e(base_path('customers/edit.php?id=' . $c['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-pen"></i></a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$customers): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">لا يوجد عملاء.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-3">
  <ul class="pagination justify-content-center flex-wrap">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="<?= e(build_query(['page' => $i])) ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
