<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';

Auth::requireLogin();

$pdo = Database::get();

// Opportunistically refresh overdue statuses (no persistent cron in this environment).
InvoiceService::refreshOverdueStatuses($pdo);

$stats = InvoiceService::dashboardStats($pdo);

$recentInvoices = $pdo->query(
    "SELECT i.*, c.company_name FROM invoices i
     JOIN customers c ON c.id = i.customer_id
     ORDER BY i.created_at DESC LIMIT 6"
)->fetchAll();

$recentPayments = $pdo->query(
    "SELECT p.*, i.invoice_number, c.company_name FROM payments p
     JOIN invoices i ON i.id = p.invoice_id
     JOIN customers c ON c.id = p.customer_id
     ORDER BY p.created_at DESC LIMIT 6"
)->fetchAll();

$overdueInvoices = $pdo->query(
    "SELECT i.*, c.company_name FROM invoices i
     JOIN customers c ON c.id = i.customer_id
     WHERE i.status = 'overdue'
     ORDER BY i.due_date ASC LIMIT 6"
)->fetchAll();

// Last 6 months payment activity, from real payment records.
$monthlyPayments = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("-$i months", strtotime(date('Y-m-01')));
    $ymPrefix = date('Y-m', $ts);
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE substr(payment_date,1,7) = :ym");
    $stmt->execute(['ym' => $ymPrefix]);
    $monthlyPayments[] = ['label' => date('M', $ts), 'total' => (float)$stmt->fetchColumn()];
}

$currency = $pdo->query("SELECT value FROM settings WHERE `key`='default_currency'")->fetchColumn() ?: 'USD';

$pageTitle = 'لوحة التحكم';
$activeMenu = 'dashboard';
require __DIR__ . '/includes/layout_header.php';
?>
<style>
  .stat-card{ padding:1.15rem; height:100%; }
  .stat-card .icon-badge{ width:48px; height:48px; border-radius:13px; display:flex; align-items:center; justify-content:center; font-size:1.15rem; flex-shrink:0; }
  .status-pill{ font-size:.78rem; padding:.4em .8em; }
  .quick-actions .btn{ display:flex; align-items:center; gap:.5rem; justify-content:flex-start; }
  .chart-card{ padding:1.25rem; }
  #statusChart, #paymentsChart{ max-height:230px; }
</style>

<!-- Quick actions -->
<div class="quick-actions d-flex flex-wrap gap-2 mb-3">
  <?php if (Auth::can('invoices.create')): ?>
    <a href="<?= e(base_path('invoices/create.php')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> فاتورة جديدة</a>
  <?php endif; ?>
  <?php if (Auth::can('customers.create')): ?>
    <a href="<?= e(base_path('customers/create.php')) ?>" class="btn btn-outline-primary"><i class="fa-solid fa-building"></i> عميل جديد</a>
  <?php endif; ?>
  <?php if (Auth::can('invoices.view')): ?>
    <a href="<?= e(base_path('invoices/overdue.php')) ?>" class="btn btn-outline-danger"><i class="fa-solid fa-triangle-exclamation"></i> الحسابات المتأخرة</a>
  <?php endif; ?>
  <?php if (Auth::can('reports.view')): ?>
    <a href="<?= e(base_path('reports/index.php')) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-chart-line"></i> التقارير</a>
  <?php endif; ?>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="card stat-card hover-lift">
      <div class="d-flex align-items-center gap-3">
        <div class="icon-badge bg-primary bg-opacity-10 text-primary"><i class="fa-solid fa-file-invoice"></i></div>
        <div>
          <div class="text-muted small">إجمالي الفواتير</div>
          <div class="fs-4 fw-bold"><?= (int)$stats['total_invoices'] ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card hover-lift">
      <div class="d-flex align-items-center gap-3">
        <div class="icon-badge bg-success bg-opacity-10 text-success"><i class="fa-solid fa-sack-dollar"></i></div>
        <div>
          <div class="text-muted small">إجمالي المفوتر</div>
          <div class="fs-5 fw-bold"><?= e(format_money($stats['total_invoiced'], $currency)) ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card hover-lift">
      <div class="d-flex align-items-center gap-3">
        <div class="icon-badge bg-info bg-opacity-10 text-info"><i class="fa-solid fa-hand-holding-dollar"></i></div>
        <div>
          <div class="text-muted small">إجمالي المدفوع</div>
          <div class="fs-5 fw-bold"><?= e(format_money($stats['total_paid'], $currency)) ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card hover-lift">
      <div class="d-flex align-items-center gap-3">
        <div class="icon-badge bg-warning bg-opacity-10 text-warning"><i class="fa-solid fa-scale-unbalanced"></i></div>
        <div>
          <div class="text-muted small">الرصيد المستحق</div>
          <div class="fs-5 fw-bold"><?= e(format_money($stats['outstanding'], $currency)) ?></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3">
    <div class="card stat-card hover-lift">
      <div class="d-flex align-items-center gap-3">
        <div class="icon-badge bg-danger bg-opacity-10 text-danger"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div>
          <div class="text-muted small">المبلغ المتأخر</div>
          <div class="fs-5 fw-bold text-danger"><?= e(format_money($stats['overdue'], $currency)) ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card stat-card hover-lift">
      <div class="d-flex align-items-center gap-3">
        <div class="icon-badge bg-secondary bg-opacity-10 text-secondary"><i class="fa-solid fa-building"></i></div>
        <div>
          <div class="text-muted small">العملاء النشطون</div>
          <div class="fs-4 fw-bold"><?= (int)$stats['active_customers'] ?></div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-6">
    <div class="card stat-card">
      <div class="text-muted small mb-2">ملخص حالات الفواتير</div>
      <div class="d-flex flex-wrap gap-2">
        <span class="badge status-pill bg-secondary">مسودة: <?= (int)$stats['draft_count'] ?></span>
        <span class="badge status-pill bg-primary">صادرة: <?= (int)$stats['issued_count'] ?></span>
        <span class="badge status-pill bg-warning text-dark">مدفوعة جزئيًا: <?= (int)$stats['partially_paid_count'] ?></span>
        <span class="badge status-pill bg-success">مدفوعة: <?= (int)$stats['paid_count'] ?></span>
        <span class="badge status-pill bg-danger">متأخرة: <?= (int)$stats['overdue_count'] ?></span>
        <span class="badge status-pill bg-dark">ملغاة: <?= (int)$stats['cancelled_count'] ?></span>
      </div>
    </div>
  </div>
</div>

<!-- Visual summaries (real data) -->
<div class="row g-3 mb-3">
  <div class="col-12 col-lg-5">
    <div class="card chart-card h-100">
      <div class="text-muted small mb-2 fw-semibold">توزيع حالات الفواتير</div>
      <canvas id="statusChart"></canvas>
    </div>
  </div>
  <div class="col-12 col-lg-7">
    <div class="card chart-card h-100">
      <div class="text-muted small mb-2 fw-semibold">نشاط المدفوعات (آخر 6 أشهر)</div>
      <canvas id="paymentsChart"></canvas>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-xl-4">
    <div class="card stat-card h-100 p-0">
      <div class="card-header"><i class="fa-solid fa-file-invoice text-primary me-1"></i> أحدث الفواتير</div>
      <div class="list-group list-group-flush">
        <?php foreach ($recentInvoices as $inv): [$badge, $label] = invoice_status_badge($inv['status']); ?>
          <a href="<?= e(base_path('invoices/view.php?id=' . $inv['id'])) ?>" class="list-group-item list-group-item-action">
            <div class="d-flex justify-content-between">
              <span class="fw-semibold"><?= e($inv['invoice_number']) ?></span>
              <span class="badge bg-<?= $badge ?>"><?= $label ?></span>
            </div>
            <div class="small text-muted"><?= e($inv['company_name']) ?> · <?= e(format_money($inv['total_amount'], $inv['currency'])) ?></div>
          </a>
        <?php endforeach; ?>
        <?php if (!$recentInvoices): ?>
          <div class="empty-state"><i class="fa-solid fa-file-invoice"></i>لا توجد فواتير بعد.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-4">
    <div class="card stat-card h-100 p-0">
      <div class="card-header"><i class="fa-solid fa-money-bill-wave text-success me-1"></i> أحدث المدفوعات</div>
      <div class="list-group list-group-flush">
        <?php foreach ($recentPayments as $p): ?>
          <a href="<?= e(base_path('invoices/view.php?id=' . $p['invoice_id'])) ?>" class="list-group-item list-group-item-action">
            <div class="d-flex justify-content-between">
              <span class="fw-semibold"><?= e($p['invoice_number']) ?></span>
              <span class="text-success fw-semibold">+<?= e(format_money($p['amount'])) ?></span>
            </div>
            <div class="small text-muted"><?= e($p['company_name']) ?> · <?= e(payment_method_label($p['payment_method'])) ?> · <?= e(format_date($p['payment_date'])) ?></div>
          </a>
        <?php endforeach; ?>
        <?php if (!$recentPayments): ?>
          <div class="empty-state"><i class="fa-solid fa-money-bill-wave"></i>لا توجد مدفوعات بعد.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-4">
    <div class="card stat-card h-100 p-0">
      <div class="card-header"><i class="fa-solid fa-triangle-exclamation text-danger me-1"></i> الفواتير المتأخرة</div>
      <div class="list-group list-group-flush">
        <?php foreach ($overdueInvoices as $inv): ?>
          <a href="<?= e(base_path('invoices/view.php?id=' . $inv['id'])) ?>" class="list-group-item list-group-item-action">
            <div class="d-flex justify-content-between">
              <span class="fw-semibold"><?= e($inv['invoice_number']) ?></span>
              <span class="text-danger fw-semibold"><?= e(format_money($inv['remaining_amount'])) ?></span>
            </div>
            <div class="small text-muted"><?= e($inv['company_name']) ?> · تاريخ الاستحقاق <?= e(format_date($inv['due_date'])) ?></div>
          </a>
        <?php endforeach; ?>
        <?php if (!$overdueInvoices): ?>
          <div class="empty-state"><i class="fa-solid fa-circle-check"></i>لا توجد فواتير متأخرة. 🎉</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
  var isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
  var gridColor = isDark ? 'rgba(255,255,255,.08)' : 'rgba(16,24,40,.06)';
  var textColor = isDark ? '#96a0b5' : '#667085';
  Chart.defaults.font.family = "'Cairo','Segoe UI',sans-serif";
  Chart.defaults.color = textColor;

  new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
      labels: ['مسودة', 'صادرة', 'مدفوعة جزئيًا', 'مدفوعة', 'متأخرة', 'ملغاة'],
      datasets: [{
        data: [
          <?= (int)$stats['draft_count'] ?>, <?= (int)$stats['issued_count'] ?>, <?= (int)$stats['partially_paid_count'] ?>,
          <?= (int)$stats['paid_count'] ?>, <?= (int)$stats['overdue_count'] ?>, <?= (int)$stats['cancelled_count'] ?>
        ],
        backgroundColor: ['#98a2b3', '#1a5fa8', '#f79009', '#12b76a', '#f04438', '#344054'],
        borderWidth: 0,
      }]
    },
    options: { plugins: { legend: { position: 'bottom', rtl: true, labels: { padding: 14, usePointStyle: true } } }, cutout: '62%' }
  });

  new Chart(document.getElementById('paymentsChart'), {
    type: 'bar',
    data: {
      labels: [<?php foreach ($monthlyPayments as $m) echo "'" . e($m['label']) . "',"; ?>],
      datasets: [{
        label: 'المدفوعات',
        data: [<?php foreach ($monthlyPayments as $m) echo $m['total'] . ','; ?>],
        backgroundColor: '#1a5fa8', borderRadius: 6, maxBarThickness: 42,
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false } },
        y: { grid: { color: gridColor }, beginAtZero: true }
      }
    }
  });
})();
</script>

<?php require __DIR__ . '/includes/layout_footer.php'; ?>
