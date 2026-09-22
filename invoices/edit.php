<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('invoices.edit');
$pdo = Database::get();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = :id');
$stmt->execute(['id' => $id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    flash('danger', 'الفاتورة غير موجودة.');
    redirect(base_path('invoices/index.php'));
}
if ($invoice['status'] !== 'draft') {
    flash('warning', 'يمكن تعديل الفواتير التي في حالة مسودة فقط. الفواتير الصادرة/المدفوعة/الملغاة مقفلة لحماية السجل المالي.');
    redirect(base_path('invoices/view.php?id=' . $id));
}

$customers = $pdo->query("SELECT id, company_name FROM customers WHERE status='active' ORDER BY company_name")->fetchAll();

$itemStmt = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id = :id ORDER BY sort_order ASC, id ASC');
$itemStmt->execute(['id' => $id]);
$existingItems = $itemStmt->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $customerId = (int)($_POST['customer_id'] ?? 0);
    $invoiceDate = clean_str($_POST['invoice_date'] ?? '');
    $dueDate = clean_str($_POST['due_date'] ?? '');
    $currency = clean_str($_POST['currency'] ?? 'USD') ?: 'USD';
    $notes = clean_str($_POST['notes'] ?? '');

    $descriptions = $_POST['item_description'] ?? [];
    $quantities   = $_POST['item_quantity'] ?? [];
    $prices       = $_POST['item_price'] ?? [];
    $discounts    = $_POST['item_discount'] ?? [];
    $taxes        = $_POST['item_tax'] ?? [];

    $custStmt = $pdo->prepare('SELECT id FROM customers WHERE id = :id AND status = "active"');
    $custStmt->execute(['id' => $customerId]);
    if (!$custStmt->fetch()) $errors[] = 'يرجى اختيار عميل صالح ونشط.';

    if ($invoiceDate === '' || !strtotime($invoiceDate)) $errors[] = 'تاريخ الفاتورة مطلوب ويجب أن يكون صحيحًا.';
    if ($dueDate === '' || !strtotime($dueDate)) $errors[] = 'تاريخ الاستحقاق مطلوب ويجب أن يكون صحيحًا.';
    if (!$errors && $dueDate < $invoiceDate) $errors[] = 'لا يمكن أن يكون تاريخ الاستحقاق قبل تاريخ الفاتورة.';

    $items = [];
    foreach ($descriptions as $i => $desc) {
        $desc = clean_str($desc);
        if ($desc === '') continue;
        $qty = to_money($quantities[$i] ?? 0);
        $price = to_money($prices[$i] ?? 0);
        $discount = to_money($discounts[$i] ?? 0);
        $taxPct = to_money($taxes[$i] ?? 0);

        if ($qty <= 0) { $errors[] = "الصنف \"$desc\": يجب أن تكون الكمية أكبر من صفر."; continue; }
        if ($price < 0) { $errors[] = "الصنف \"$desc\": لا يمكن أن يكون سعر الوحدة سالبًا."; continue; }
        if ($discount < 0) { $errors[] = "الصنف \"$desc\": لا يمكن أن يكون الخصم سالبًا."; continue; }
        if ($taxPct < 0 || $taxPct > 100) { $errors[] = "الصنف \"$desc\": يجب أن تكون نسبة الضريبة بين 0 و100."; continue; }

        $items[] = compact('desc', 'qty', 'price', 'discount', 'taxPct');
    }
    if (!$items) $errors[] = 'أضف صنفًا واحدًا صالحًا على الأقل.';

    if (!$errors) {
        try {
            Database::transaction(function (PDO $pdo) use ($id, $customerId, $invoiceDate, $dueDate, $currency, $notes, $items, $invoice) {
                $pdo->prepare(
                    'UPDATE invoices SET customer_id=:cust, invoice_date=:idate, due_date=:ddate, currency=:cur, notes=:notes, updated_at=:now WHERE id=:id'
                )->execute([
                    'cust' => $customerId, 'idate' => $invoiceDate, 'ddate' => $dueDate,
                    'cur' => $currency, 'notes' => $notes, 'now' => date('Y-m-d H:i:s'), 'id' => $id,
                ]);

                $pdo->prepare('DELETE FROM invoice_items WHERE invoice_id = :id')->execute(['id' => $id]);

                $itemStmt = $pdo->prepare(
                    'INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, discount_amount, tax_percent, line_total, sort_order)
                     VALUES (:inv, :desc, :qty, :price, :disc, :tax, :total, :sort)'
                );
                foreach ($items as $idx => $item) {
                    $lineTotal = InvoiceService::calculateItemTotal($item['qty'], $item['price'], $item['discount'], $item['taxPct']);
                    $itemStmt->execute([
                        'inv' => $id, 'desc' => $item['desc'], 'qty' => $item['qty'], 'price' => $item['price'],
                        'disc' => $item['discount'], 'tax' => $item['taxPct'], 'total' => $lineTotal, 'sort' => $idx,
                    ]);
                }

                InvoiceService::recalcInvoiceFromItems($pdo, $id);
                AuditLog::record('invoice.updated', 'invoice', $id, 'تم تحديث الفاتورة المسودة ' . $invoice['invoice_number']);
            });

            flash('success', 'تم تحديث الفاتورة.');
            redirect(base_path('invoices/view.php?id=' . $id));
        } catch (Throwable $e) {
            error_log('Invoice update failed: ' . $e->getMessage());
            $errors[] = 'تعذر تحديث الفاتورة بسبب خطأ في الخادم.';
        }
    } else {
        $invoice = array_merge($invoice, ['customer_id' => $customerId, 'invoice_date' => $invoiceDate, 'due_date' => $dueDate, 'currency' => $currency, 'notes' => $notes]);
        $existingItems = array_map(fn($i) => ['description' => $i['desc'], 'quantity' => $i['qty'], 'unit_price' => $i['price'], 'discount_amount' => $i['discount'], 'tax_percent' => $i['taxPct']], $items);
    }
}

$pageTitle = 'تعديل الفاتورة ' . $invoice['invoice_number'];
$activeMenu = 'invoices';
require __DIR__ . '/../includes/layout_header.php';
?>
<style>
  .items-table th, .items-table td{ vertical-align:middle; }
  @media (max-width: 767.98px){
    .items-table thead{ display:none; }
    .items-table, .items-table tbody, .items-table tr, .items-table td{ display:block; width:100%; }
    .items-table td{ padding:.25rem .5rem; }
    .items-table td::before{ content: attr(data-label); font-size:.72rem; color:#888; display:block; }
  }
</style>

<form method="post" id="invoiceForm" novalidate>
  <?= csrf_field() ?>
  <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">العميل *</label>
          <select name="customer_id" class="form-select" required>
            <?php foreach ($customers as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ((int)$invoice['customer_id'] === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['company_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">تاريخ الفاتورة *</label>
          <input type="date" name="invoice_date" id="invoiceDate" class="form-control" required value="<?= e($invoice['invoice_date']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">تاريخ الاستحقاق *</label>
          <input type="date" name="due_date" id="dueDate" class="form-control" required value="<?= e($invoice['due_date']) ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">العملة</label>
          <input type="text" name="currency" class="form-control" value="<?= e($invoice['currency']) ?>" maxlength="10">
        </div>
        <div class="col-12">
          <label class="form-label">ملاحظات</label>
          <textarea name="notes" class="form-control" rows="2"><?= e($invoice['notes']) ?></textarea>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-semibold mb-0">أصناف الفاتورة</h6>
        <button type="button" class="btn btn-sm btn-outline-primary" id="addItemBtn"><i class="fa-solid fa-plus me-1"></i>إضافة صنف</button>
      </div>
      <div class="table-responsive">
        <table class="table items-table mb-0">
          <thead class="table-light"><tr><th>الوصف</th><th style="width:90px;">الكمية</th><th style="width:120px;">سعر الوحدة</th><th style="width:110px;">الخصم</th><th style="width:100px;">الضريبة %</th><th style="width:110px;">إجمالي السطر</th><th></th></tr></thead>
          <tbody id="itemsWrapper"></tbody>
        </table>
      </div>
      <div class="text-end mt-3 border-top pt-3">
        <div class="row justify-content-end">
          <div class="col-md-4">
            <div class="d-flex justify-content-between"><span class="text-muted">المجموع الفرعي</span><span id="sumSubtotal">0.00</span></div>
            <div class="d-flex justify-content-between"><span class="text-muted">الخصم</span><span id="sumDiscount">0.00</span></div>
            <div class="d-flex justify-content-between"><span class="text-muted">الضريبة</span><span id="sumTax">0.00</span></div>
            <div class="d-flex justify-content-between fw-bold fs-5 border-top mt-2 pt-2"><span>الإجمالي</span><span id="sumTotal">0.00</span></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex gap-2 mb-4">
    <button class="btn btn-primary"><i class="fa-solid fa-check me-1"></i> حفظ التغييرات</button>
    <a href="<?= e(base_path('invoices/view.php?id=' . $id)) ?>" class="btn btn-outline-secondary">إلغاء</a>
  </div>
</form>

<template id="itemRowTemplate">
  <tr class="item-row">
    <td data-label="الوصف"><input type="text" name="item_description[]" class="form-control form-control-sm"></td>
    <td data-label="الكمية"><input type="number" name="item_quantity[]" class="form-control form-control-sm qty" value="1" min="0" step="0.01"></td>
    <td data-label="سعر الوحدة"><input type="number" name="item_price[]" class="form-control form-control-sm price" value="0" min="0" step="0.01"></td>
    <td data-label="الخصم"><input type="number" name="item_discount[]" class="form-control form-control-sm discount" value="0" min="0" step="0.01"></td>
    <td data-label="الضريبة %"><input type="number" name="item_tax[]" class="form-control form-control-sm tax" value="0" min="0" max="100" step="0.01"></td>
    <td data-label="إجمالي السطر" class="line-total fw-semibold">0.00</td>
    <td><button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="fa-solid fa-trash"></i></button></td>
  </tr>
</template>

<script>
(function () {
  const wrapper = document.getElementById('itemsWrapper');
  const template = document.getElementById('itemRowTemplate');
  const existing = <?= json_encode($existingItems, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

  function addRow(data) {
    data = data || {};
    const clone = template.content.cloneNode(true);
    wrapper.appendChild(clone);
    const row = wrapper.lastElementChild;
    if (data.description !== undefined) row.querySelector('input[name="item_description[]"]').value = data.description;
    if (data.quantity !== undefined) row.querySelector('.qty').value = data.quantity;
    if (data.unit_price !== undefined) row.querySelector('.price').value = data.unit_price;
    if (data.discount_amount !== undefined) row.querySelector('.discount').value = data.discount_amount;
    if (data.tax_percent !== undefined) row.querySelector('.tax').value = data.tax_percent;
    bindRow(row);
    recalcAll();
  }

  function bindRow(row) {
    row.querySelectorAll('input').forEach(function (input) {
      input.addEventListener('input', function () { recalcLine(row); recalcAll(); });
    });
    row.querySelector('.remove-item').addEventListener('click', function () { row.remove(); recalcAll(); });
  }

  function recalcLine(row) {
    const qty = parseFloat(row.querySelector('.qty').value) || 0;
    const price = parseFloat(row.querySelector('.price').value) || 0;
    const discount = parseFloat(row.querySelector('.discount').value) || 0;
    const taxPct = parseFloat(row.querySelector('.tax').value) || 0;
    const base = Math.max(0, (qty * price) - discount);
    const tax = base * (taxPct / 100);
    const total = base + tax;
    row.querySelector('.line-total').textContent = total.toFixed(2);
    return { base: qty * price, discount, tax, total };
  }

  function recalcAll() {
    let subtotal = 0, discount = 0, tax = 0, total = 0;
    wrapper.querySelectorAll('.item-row').forEach(function (row) {
      const vals = recalcLine(row);
      subtotal += vals.base; discount += vals.discount; tax += vals.tax; total += vals.total;
    });
    document.getElementById('sumSubtotal').textContent = subtotal.toFixed(2);
    document.getElementById('sumDiscount').textContent = discount.toFixed(2);
    document.getElementById('sumTax').textContent = tax.toFixed(2);
    document.getElementById('sumTotal').textContent = total.toFixed(2);
  }

  document.getElementById('addItemBtn').addEventListener('click', function () { addRow(); });

  if (existing.length) {
    existing.forEach(function (it) { addRow(it); });
  } else {
    addRow();
  }
})();
</script>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
