<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('reports.view');
$pdo = Database::get();
InvoiceService::refreshOverdueStatuses($pdo);

$from = clean_str($_GET['from'] ?? '');
$to = clean_str($_GET['to'] ?? '');
$customerId = (int)($_GET['customer_id'] ?? 0);
$status = clean_str($_GET['status'] ?? '');

$where = [];
$params = [];
if ($from !== '') { $where[] = 'i.invoice_date >= :from'; $params['from'] = $from; }
if ($to !== '') { $where[] = 'i.invoice_date <= :to'; $params['to'] = $to; }
if ($customerId > 0) { $where[] = 'i.customer_id = :cid'; $params['cid'] = $customerId; }
if ($status !== '') { $where[] = 'i.status = :status'; $params['status'] = $status; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// --- Invoice report summary ---
$invoiceSummary = $pdo->prepare(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS invoiced,
            COALESCE(SUM(paid_amount),0) AS paid,
            COALESCE(SUM(CASE WHEN status NOT IN ('cancelled','draft') THEN remaining_amount ELSE 0 END),0) AS outstanding,
            COALESCE(SUM(CASE WHEN status='overdue' THEN remaining_amount ELSE 0 END),0) AS overdue
     FROM invoices i $whereSql"
);
$invoiceSummary->execute($params);
$invoiceSummary = $invoiceSummary->fetch();

// --- Payment report summary (by method) ---
$paymentWhere = [];
$paymentParams = [];
if ($from !== '') { $paymentWhere[] = 'p.payment_date >= :from'; $paymentParams['from'] = $from; }
if ($to !== '') { $paymentWhere[] = 'p.payment_date <= :to'; $paymentParams['to'] = $to; }
if ($customerId > 0) { $paymentWhere[] = 'p.customer_id = :cid'; $paymentParams['cid'] = $customerId; }
$paymentWhereSql = $paymentWhere ? ('WHERE ' . implode(' AND ', $paymentWhere)) : '';

$paymentsByMethod = $pdo->prepare(
    "SELECT payment_method, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
     FROM payments p $paymentWhereSql GROUP BY payment_method"
);
$paymentsByMethod->execute($paymentParams);
$paymentsByMethod = $paymentsByMethod->fetchAll();
$totalReceived = array_sum(array_column($paymentsByMethod, 'total'));

// --- Customer report ---
$customerReport = $pdo->prepare(
    "SELECT c.id, c.company_name,
            COUNT(i.id) AS total_invoices,
            COALESCE(SUM(i.total_amount),0) AS total_invoiced,
            COALESCE(SUM(i.paid_amount),0) AS total_paid,
            COALESCE(SUM(CASE WHEN i.status NOT IN ('cancelled','draft') THEN i.remaining_amount ELSE 0 END),0) AS outstanding,
            COALESCE(SUM(CASE WHEN i.status='overdue' THEN i.remaining_amount ELSE 0 END),0) AS overdue
     FROM customers c
     LEFT JOIN invoices i ON i.customer_id = c.id " . ($whereSql ? str_replace('i.customer_id = :cid', '1=1', $whereSql) : '') . "
     GROUP BY c.id, c.company_name ORDER BY total_invoiced DESC"
);
$customerReport->execute(array_diff_key($params, ['cid' => 0]));
$customerReport = $customerReport->fetchAll();

$customers = $pdo->query("SELECT id, company_name FROM customers ORDER BY company_name")->fetchAll();

// --- CSV export ---
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="customer_report.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders Arabic correctly
    fputcsv($out, ['العميل', 'عدد الفواتير', 'إجمالي المفوتر', 'إجمالي المدفوع', 'الرصيد المستحق', 'المتأخر']);
    foreach ($customerReport as $row) {
        fputcsv($out, [$row['company_name'], $row['total_invoices'], $row['total_invoiced'], $row['total_paid'], $row['outstanding'], $row['overdue']]);
    }
    fclose($out);
    AuditLog::record('report.exported', 'report', null, 'تم تصدير تقرير العملاء إلى CSV');
    exit;
}

$pageTitle = 'التقارير';
$activeMenu = 'reports';
require __DIR__ . '/../includes/layout_header.php';
?>
<style>.filters select, .filters input{ max-width:180px; }</style>

<form class="d-flex flex-wrap gap-2 filters mb-4" method="get">
  <input type="date" name="from" class="form-control" value="<?= e($from) ?>" placeholder="من">
  <input type="date" name="to" class="form-control" value="<?= e($to) ?>" placeholder="إلى">
  <select name="customer_id" class="form-select">
    <option value="">كل العملاء</option>
    <?php foreach ($customers as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $customerId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['company_name']) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="status" class="form-select">
    <option value="">كل الحالات</option>
    <?php foreach (['draft','issued','partially_paid','paid','overdue','cancelled'] as $s): [, $label] = invoice_status_badge($s); ?>
      <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $label ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn btn-outline-primary"><i class="fa-solid fa-filter me-1"></i>تصفية</button>
  <?php if (Auth::can('reports.export')): ?>
    <a class="btn btn-outline-success" href="<?= e(build_query(['export' => 'csv'])) ?>"><i class="fa-solid fa-file-csv me-1"></i>تصدير تقرير العملاء CSV</a>
  <?php endif; ?>
  <button type="button" class="btn btn-outline-dark" onclick="window.print()"><i class="fa-solid fa-print me-1"></i>طباعة</button>
</form>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="text-muted small">الفواتير</div><div class="fs-4 fw-bold"><?= (int)$invoiceSummary['cnt'] ?></div></div></div>
  <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="text-muted small">إجمالي المفوتر</div><div class="fs-5 fw-bold"><?= e(format_money($invoiceSummary['invoiced'])) ?></div></div></div>
  <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="text-muted small">المدفوع</div><div class="fs-5 fw-bold text-success"><?= e(format_money($invoiceSummary['paid'])) ?></div></div></div>
  <div class="col-md-3"><div class="card border-0 shadow-sm p-3"><div class="text-muted small">الرصيد المستحق</div><div class="fs-5 fw-bold text-warning"><?= e(format_money($invoiceSummary['outstanding'])) ?></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">المدفوعات حسب الطريقة</div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead class="table-light"><tr><th>الطريقة</th><th class="text-end">العدد</th><th class="text-end">الإجمالي</th></tr></thead>
          <tbody>
            <?php foreach ($paymentsByMethod as $m): ?>
              <tr><td><?= e(payment_method_label($m['payment_method'])) ?></td><td class="text-end"><?= (int)$m['cnt'] ?></td><td class="text-end"><?= e(format_money($m['total'])) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$paymentsByMethod): ?><tr><td colspan="3" class="text-center text-muted py-3">لا توجد مدفوعات في هذا النطاق.</td></tr><?php endif; ?>
          </tbody>
          <tfoot><tr class="fw-bold"><td>إجمالي المستلم</td><td></td><td class="text-end"><?= e(format_money($totalReceived)) ?></td></tr></tfoot>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">تقرير العملاء</div>
      <div class="table-responsive">
        <table class="table mb-0">
          <thead class="table-light"><tr><th>العميل</th><th class="text-end">الفواتير</th><th class="text-end">المفوتر</th><th class="text-end">المدفوع</th><th class="text-end">المستحق</th><th class="text-end">المتأخر</th></tr></thead>
          <tbody>
            <?php foreach ($customerReport as $c): ?>
              <tr>
                <td><a href="<?= e(base_path('customers/view.php?id=' . $c['id'])) ?>"><?= e($c['company_name']) ?></a></td>
                <td class="text-end"><?= (int)$c['total_invoices'] ?></td>
                <td class="text-end"><?= e(format_money($c['total_invoiced'])) ?></td>
                <td class="text-end"><?= e(format_money($c['total_paid'])) ?></td>
                <td class="text-end"><?= e(format_money($c['outstanding'])) ?></td>
                <td class="text-end text-danger"><?= e(format_money($c['overdue'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
