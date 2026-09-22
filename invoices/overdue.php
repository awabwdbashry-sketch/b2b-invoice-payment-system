<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('invoices.view');
$pdo = Database::get();
InvoiceService::refreshOverdueStatuses($pdo);

$rows = $pdo->query(
    "SELECT i.*, c.company_name FROM invoices i JOIN customers c ON c.id = i.customer_id
     WHERE i.status = 'overdue' ORDER BY i.due_date ASC"
)->fetchAll();

$totalOverdue = array_sum(array_column($rows, 'remaining_amount'));

$pageTitle = 'الحسابات المتأخرة';
$activeMenu = 'overdue';
require __DIR__ . '/../includes/layout_header.php';
?>

<div class="alert alert-danger d-flex justify-content-between align-items-center">
  <span><i class="fa-solid fa-triangle-exclamation me-2"></i>إجمالي الرصيد المتأخر المستحق</span>
  <span class="fs-5 fw-bold"><?= e(format_money($totalOverdue)) ?></span>
</div>

<div class="card border-0 shadow-sm">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>العميل</th><th>رقم الفاتورة</th><th>تاريخ الفاتورة</th><th>تاريخ الاستحقاق</th><th>أيام التأخير</th><th>الإجمالي</th><th>المدفوع</th><th>المتبقي</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): $days = (int)((strtotime(date('Y-m-d')) - strtotime($r['due_date'])) / 86400); ?>
          <tr style="cursor:pointer" onclick="window.location='<?= e(base_path('invoices/view.php?id=' . $r['id'])) ?>'">
            <td><?= e($r['company_name']) ?></td>
            <td class="fw-semibold"><?= e($r['invoice_number']) ?></td>
            <td><?= e(format_date($r['invoice_date'])) ?></td>
            <td><?= e(format_date($r['due_date'])) ?></td>
            <td><span class="badge bg-danger"><?= $days ?> يوم</span></td>
            <td><?= e(format_money($r['total_amount'])) ?></td>
            <td><?= e(format_money($r['paid_amount'])) ?></td>
            <td class="fw-semibold text-danger"><?= e(format_money($r['remaining_amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">لا توجد فواتير متأخرة. 🎉</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
