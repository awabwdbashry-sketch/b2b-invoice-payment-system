<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('audit_logs.view');
$pdo = Database::get();

$page = max(1, (int)($_GET['page'] ?? 1));
$filters = [
    'user_id' => (int)($_GET['user_id'] ?? 0) ?: null,
    'action' => clean_str($_GET['action'] ?? '') ?: null,
    'from' => clean_str($_GET['from'] ?? '') ?: null,
    'to' => clean_str($_GET['to'] ?? '') ?: null,
];

$result = AuditLog::paginate($pdo, $page, 25, $filters);
$totalPages = max(1, (int)ceil($result['total'] / 25));

$users = $pdo->query('SELECT id, full_name FROM users ORDER BY full_name')->fetchAll();

$pageTitle = 'سجل التدقيق';
$activeMenu = 'audit';
require __DIR__ . '/../includes/layout_header.php';
?>
<style>.filters select, .filters input{ max-width:170px; }</style>

<form class="d-flex flex-wrap gap-2 filters mb-3" method="get">
  <select name="user_id" class="form-select">
    <option value="">كل المستخدمين</option>
    <?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= (int)($_GET['user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?>
  </select>
  <input type="text" name="action" class="form-control" placeholder="يحتوي على…" value="<?= e($_GET['action'] ?? '') ?>">
  <input type="date" name="from" class="form-control" value="<?= e($_GET['from'] ?? '') ?>">
  <input type="date" name="to" class="form-control" value="<?= e($_GET['to'] ?? '') ?>">
  <button class="btn btn-outline-primary"><i class="fa-solid fa-filter me-1"></i>تصفية</button>
</form>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light"><tr><th>الوقت</th><th>المستخدم</th><th>الإجراء</th><th>الكيان</th><th>الوصف</th><th>IP</th></tr></thead>
      <tbody>
        <?php foreach ($result['rows'] as $r): ?>
          <tr>
            <td class="small text-nowrap"><?= e(format_datetime($r['created_at'])) ?></td>
            <td class="small"><?= e($r['user_name'] ?? 'النظام') ?></td>
            <td class="small"><span class="badge bg-light text-dark border"><?= e($r['action']) ?></span></td>
            <td class="small"><?= e($r['entity_type']) ?><?= $r['entity_id'] ? ' #' . $r['entity_id'] : '' ?></td>
            <td class="small"><?= e($r['description']) ?></td>
            <td class="small text-muted"><?= e($r['ip_address']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$result['rows']): ?><tr><td colspan="6" class="text-center text-muted py-4">لا توجد سجلات تدقيق.</td></tr><?php endif; ?>
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
