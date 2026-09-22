<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('notifications.view');
$pdo = Database::get();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'mark_all_read') {
        Notifier::markAllRead(Auth::id());
    } elseif (!empty($_POST['notification_id'])) {
        Notifier::markRead(Auth::id(), (int)$_POST['notification_id']);
    }
    redirect(base_path('notifications/index.php'));
}

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 100');
$stmt->execute(['uid' => Auth::id()]);
$notifications = $stmt->fetchAll();

$typeIcon = function (string $type): string {
    return match ($type) {
        'invoice_created' => 'fa-file-invoice text-primary',
        'payment_recorded' => 'fa-money-bill-wave text-success',
        'invoice_overdue' => 'fa-triangle-exclamation text-danger',
        'invoice_paid' => 'fa-circle-check text-success',
        'invoice_due_soon' => 'fa-clock text-warning',
        default => 'fa-bell text-secondary',
    };
};

$pageTitle = 'الإشعارات';
$activeMenu = 'notifications';
require __DIR__ . '/../includes/layout_header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h6 class="mb-0 text-muted"><?= count($notifications) ?> إشعار</h6>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_all_read">
    <button class="btn btn-sm btn-outline-secondary">تعليم الكل كمقروء</button>
  </form>
</div>

<div class="card border-0 shadow-sm">
  <div class="list-group list-group-flush">
    <?php foreach ($notifications as $n): ?>
      <div class="list-group-item d-flex justify-content-between align-items-start <?= $n['is_read'] ? '' : 'bg-light' ?>">
        <div class="d-flex gap-3">
          <i class="fa-solid <?= $typeIcon($n['type']) ?> fs-5 mt-1"></i>
          <div>
            <div class="fw-semibold"><?= e($n['title']) ?></div>
            <div class="text-muted small"><?= e($n['message']) ?></div>
            <div class="text-muted small"><?= e(format_datetime($n['created_at'])) ?></div>
            <?php if ($n['related_entity_type'] === 'invoice' && $n['related_entity_id']): ?>
              <a class="small" href="<?= e(base_path('invoices/view.php?id=' . $n['related_entity_id'])) ?>">عرض الفاتورة ←</a>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!$n['is_read']): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
            <button class="btn btn-sm btn-link">تعليم كمقروء</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$notifications): ?><div class="list-group-item text-muted text-center py-4">لا توجد إشعارات بعد.</div><?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
