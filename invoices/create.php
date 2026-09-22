<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requirePermission('invoices.create');
$pdo = Database::get();

$settings = $pdo->query(
    'SELECT `key`, `value` FROM settings'
)->fetchAll(PDO::FETCH_KEY_PAIR);

$defaultCurrency = $settings['default_currency'] ?? 'USD';
$defaultDueDays = (int)($settings['invoice_due_days'] ?? 30);
$defaultTaxPercent = $settings['default_tax_percent'] ?? '0';
$invoicePrefix = $settings['invoice_prefix'] ?? 'INV';

$customers = $pdo->query(
    "SELECT id, company_name
     FROM customers
     WHERE status = 'active'
     ORDER BY company_name"
)->fetchAll();

$preselectedCustomer = (int)($_GET['customer_id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $customerId = (int)($_POST['customer_id'] ?? 0);
    $invoiceDate = clean_str($_POST['invoice_date'] ?? '');
    $dueDate = clean_str($_POST['due_date'] ?? '');
    $currency = clean_str(
        $_POST['currency'] ?? $defaultCurrency
    ) ?: $defaultCurrency;
    $notes = clean_str($_POST['notes'] ?? '');

    $descriptions = $_POST['item_description'] ?? [];
    $quantities = $_POST['item_quantity'] ?? [];
    $prices = $_POST['item_price'] ?? [];
    $discounts = $_POST['item_discount'] ?? [];
    $taxes = $_POST['item_tax'] ?? [];

    // ---------------------------------------------------------
    // Validation
    // ---------------------------------------------------------

    $custStmt = $pdo->prepare(
        'SELECT id
         FROM customers
         WHERE id = :id
         AND status = "active"'
    );

    $custStmt->execute([
        'id' => $customerId,
    ]);

    if (!$custStmt->fetch()) {
        $errors[] = 'يرجى اختيار عميل صالح ونشط.';
    }

    if ($invoiceDate === '' || !strtotime($invoiceDate)) {
        $errors[] = 'تاريخ الفاتورة مطلوب ويجب أن يكون صحيحًا.';
    }

    if ($dueDate === '' || !strtotime($dueDate)) {
        $errors[] = 'تاريخ الاستحقاق مطلوب ويجب أن يكون صحيحًا.';
    }

    if (!$errors && $dueDate < $invoiceDate) {
        $errors[] = 'لا يمكن أن يكون تاريخ الاستحقاق قبل تاريخ الفاتورة.';
    }

    // ---------------------------------------------------------
    // Validate invoice items
    // ---------------------------------------------------------

    $items = [];

    foreach ($descriptions as $i => $desc) {
        $desc = clean_str($desc);

        if ($desc === '') {
            continue;
        }

        $qty = to_money($quantities[$i] ?? 0);
        $price = to_money($prices[$i] ?? 0);
        $discount = to_money($discounts[$i] ?? 0);
        $taxPct = to_money($taxes[$i] ?? 0);

        if ($qty <= 0) {
            $errors[] = "الصنف \"$desc\": يجب أن تكون الكمية أكبر من صفر.";
            continue;
        }

        if ($price < 0) {
            $errors[] = "الصنف \"$desc\": لا يمكن أن يكون سعر الوحدة سالبًا.";
            continue;
        }

        if ($discount < 0) {
            $errors[] = "الصنف \"$desc\": لا يمكن أن يكون الخصم سالبًا.";
            continue;
        }

        if ($taxPct < 0 || $taxPct > 100) {
            $errors[] = "الصنف \"$desc\": يجب أن تكون نسبة الضريبة بين 0 و100.";
            continue;
        }

        $items[] = [
            'desc' => $desc,
            'qty' => $qty,
            'price' => $price,
            'discount' => $discount,
            'taxPct' => $taxPct,
        ];
    }

    if (!$items) {
        $errors[] = 'أضف صنفًا واحدًا صالحًا على الأقل.';
    }

    // ---------------------------------------------------------
    // Create invoice
    // ---------------------------------------------------------

    if (!$errors) {
        try {
            $invoiceId = Database::transaction(
                function (PDO $pdo) use (
                    $customerId,
                    $invoiceDate,
                    $dueDate,
                    $currency,
                    $notes,
                    $items,
                    $invoicePrefix
                ) {
                    $year = (int)date(
                        'Y',
                        strtotime($invoiceDate)
                    );

                    $number = InvoiceService::generateInvoiceNumber(
                        $pdo,
                        $invoicePrefix,
                        $year
                    );

                    $now = date('Y-m-d H:i:s');

                    // -------------------------------------------------
                    // Insert invoice
                    // -------------------------------------------------

                    $stmt = $pdo->prepare(
                        'INSERT INTO invoices (
                            invoice_number,
                            customer_id,
                            invoice_date,
                            due_date,
                            currency,
                            status,
                            notes,
                            created_by,
                            created_at,
                            updated_at
                        ) VALUES (
                            :num,
                            :cust,
                            :idate,
                            :ddate,
                            :cur,
                            :status,
                            :notes,
                            :uid,
                            :created_at,
                            :updated_at
                        )'
                    );

                    $stmt->execute([
                        'num' => $number,
                        'cust' => $customerId,
                        'idate' => $invoiceDate,
                        'ddate' => $dueDate,
                        'cur' => $currency,
                        'status' => 'draft',
                        'notes' => $notes,
                        'uid' => Auth::id(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $invoiceId = (int)$pdo->lastInsertId();

                    // -------------------------------------------------
                    // Insert invoice items
                    // -------------------------------------------------

                    $itemStmt = $pdo->prepare(
                        'INSERT INTO invoice_items (
                            invoice_id,
                            description,
                            quantity,
                            unit_price,
                            discount_amount,
                            tax_percent,
                            line_total,
                            sort_order
                        ) VALUES (
                            :inv,
                            :desc,
                            :qty,
                            :price,
                            :disc,
                            :tax,
                            :total,
                            :sort
                        )'
                    );

                    foreach ($items as $idx => $item) {
                        $lineTotal = InvoiceService::calculateItemTotal(
                            $item['qty'],
                            $item['price'],
                            $item['discount'],
                            $item['taxPct']
                        );

                        $itemStmt->execute([
                            'inv' => $invoiceId,
                            'desc' => $item['desc'],
                            'qty' => $item['qty'],
                            'price' => $item['price'],
                            'disc' => $item['discount'],
                            'tax' => $item['taxPct'],
                            'total' => $lineTotal,
                            'sort' => $idx,
                        ]);
                    }

                    // -------------------------------------------------
                    // Recalculate invoice totals from database items
                    // -------------------------------------------------

                    InvoiceService::recalcInvoiceFromItems(
                        $pdo,
                        $invoiceId
                    );

                    AuditLog::record(
                        'invoice.created',
                        'invoice',
                        $invoiceId,
                        "تم إنشاء فاتورة مسودة $number"
                    );

                    return $invoiceId;
                }
            );

            flash(
                'success',
                'تم إنشاء الفاتورة كمسودة. يمكنك إصدارها عند الجاهزية.'
            );

            redirect(
                base_path('invoices/view.php?id=' . $invoiceId)
            );
        } catch (Throwable $e) {
            error_log(
                'Invoice creation failed: ' . $e->getMessage()
            );

            $errors[] =
                'تعذر إنشاء الفاتورة بسبب خطأ في الخادم. يرجى المحاولة مرة أخرى.';
        }
    }
}

$pageTitle = 'فاتورة جديدة';
$activeMenu = 'invoices';

require __DIR__ . '/../includes/layout_header.php';
?>

<style>
  .items-table th,
  .items-table td {
    vertical-align: middle;
  }

  .items-table input {
    min-width: 90px;
  }

  #itemsWrapper .item-row:not(:last-child) {
    border-bottom: 1px solid #eee;
  }

  @media (max-width: 767.98px) {
    .items-table thead {
      display: none;
    }

    .items-table,
    .items-table tbody,
    .items-table tr,
    .items-table td {
      display: block;
      width: 100%;
    }

    .items-table td {
      padding: .25rem .5rem;
    }

    .items-table td::before {
      content: attr(data-label);
      font-size: .72rem;
      color: #888;
      display: block;
    }
  }
</style>

<form method="post" id="invoiceForm" novalidate>

  <?= csrf_field() ?>

  <?php foreach ($errors as $err): ?>
    <div class="alert alert-danger py-2">
      <?= e($err) ?>
    </div>
  <?php endforeach; ?>

  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body">

      <div class="row g-3">

        <div class="col-md-4">
          <label class="form-label">العميل *</label>

          <select
            name="customer_id"
            class="form-select"
            required
          >
            <option value="">اختر العميل…</option>

            <?php foreach ($customers as $c): ?>
              <option
                value="<?= $c['id'] ?>"
                <?= (
                    $preselectedCustomer === (int)$c['id']
                ) ? 'selected' : '' ?>
              >
                <?= e($c['company_name']) ?>
              </option>
            <?php endforeach; ?>

          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">تاريخ الفاتورة *</label>

          <input
            type="date"
            name="invoice_date"
            id="invoiceDate"
            class="form-control"
            required
            value="<?= e(date('Y-m-d')) ?>"
          >
        </div>

        <div class="col-md-3">
          <label class="form-label">تاريخ الاستحقاق *</label>

          <input
            type="date"
            name="due_date"
            id="dueDate"
            class="form-control"
            required
            value="<?= e(
                date(
                    'Y-m-d',
                    strtotime('+' . $defaultDueDays . ' days')
                )
            ) ?>"
          >
        </div>

        <div class="col-md-2">
          <label class="form-label">العملة</label>

          <input
            type="text"
            name="currency"
            class="form-control"
            value="<?= e($defaultCurrency) ?>"
            maxlength="10"
          >
        </div>

        <div class="col-12">
          <label class="form-label">ملاحظات</label>

          <textarea
            name="notes"
            class="form-control"
            rows="2"
          ></textarea>
        </div>

      </div>

    </div>
  </div>

  <div class="card border-0 shadow-sm mb-3">

    <div class="card-body">

      <div class="d-flex justify-content-between align-items-center mb-2">

        <h6 class="fw-semibold mb-0">
          أصناف الفاتورة
        </h6>

        <button
          type="button"
          class="btn btn-sm btn-outline-primary"
          id="addItemBtn"
        >
          <i class="fa-solid fa-plus me-1"></i>
          إضافة صنف
        </button>

      </div>

      <div class="table-responsive">

        <table class="table items-table mb-0">

          <thead class="table-light">
            <tr>
              <th>الوصف</th>
              <th style="width:90px;">الكمية</th>
              <th style="width:120px;">سعر الوحدة</th>
              <th style="width:110px;">الخصم</th>
              <th style="width:100px;">الضريبة %</th>
              <th style="width:110px;">إجمالي السطر</th>
              <th></th>
            </tr>
          </thead>

          <tbody id="itemsWrapper"></tbody>

        </table>

      </div>

      <div class="text-end mt-3 border-top pt-3">

        <div class="row justify-content-end">

          <div class="col-md-4">

            <div class="d-flex justify-content-between">
              <span class="text-muted">المجموع الفرعي</span>
              <span id="sumSubtotal">0.00</span>
            </div>

            <div class="d-flex justify-content-between">
              <span class="text-muted">الخصم</span>
              <span id="sumDiscount">0.00</span>
            </div>

            <div class="d-flex justify-content-between">
              <span class="text-muted">الضريبة</span>
              <span id="sumTax">0.00</span>
            </div>

            <div class="d-flex justify-content-between fw-bold fs-5 border-top mt-2 pt-2">
              <span>الإجمالي</span>
              <span id="sumTotal">0.00</span>
            </div>

            <small class="text-muted">
              يتم إعادة احتساب الإجماليات النهائية من قبل الخادم لضمان الدقة.
            </small>

          </div>

        </div>

      </div>

    </div>

  </div>

  <div class="d-flex gap-2 mb-4">

    <button class="btn btn-primary" type="submit">
      <i class="fa-solid fa-check me-1"></i>
      حفظ كمسودة
    </button>

    <a
      href="<?= e(base_path('invoices/index.php')) ?>"
      class="btn btn-outline-secondary"
    >
      إلغاء
    </a>

  </div>

</form>

<template id="itemRowTemplate">

  <tr class="item-row">

    <td data-label="الوصف">
      <input
        type="text"
        name="item_description[]"
        class="form-control form-control-sm"
        placeholder="مثال: تطوير موقع إلكتروني"
      >
    </td>

    <td data-label="الكمية">
      <input
        type="number"
        name="item_quantity[]"
        class="form-control form-control-sm qty"
        value="1"
        min="0"
        step="0.01"
      >
    </td>

    <td data-label="سعر الوحدة">
      <input
        type="number"
        name="item_price[]"
        class="form-control form-control-sm price"
        value="0"
        min="0"
        step="0.01"
      >
    </td>

    <td data-label="الخصم">
      <input
        type="number"
        name="item_discount[]"
        class="form-control form-control-sm discount"
        value="0"
        min="0"
        step="0.01"
      >
    </td>

    <td data-label="الضريبة %">
      <input
        type="number"
        name="item_tax[]"
        class="form-control form-control-sm tax"
        value="<?= e($defaultTaxPercent) ?>"
        min="0"
        max="100"
        step="0.01"
      >
    </td>

    <td
      data-label="إجمالي السطر"
      class="line-total fw-semibold"
    >
      0.00
    </td>

    <td>
      <button
        type="button"
        class="btn btn-sm btn-outline-danger remove-item"
      >
        <i class="fa-solid fa-trash"></i>
      </button>
    </td>

  </tr>

</template>

<script>
(function () {

  const wrapper = document.getElementById('itemsWrapper');
  const template = document.getElementById('itemRowTemplate');

  function addRow() {
    const clone = template.content.cloneNode(true);

    wrapper.appendChild(clone);

    bindRow(wrapper.lastElementChild);

    recalcAll();
  }

  function bindRow(row) {

    row.querySelectorAll('input').forEach(function (input) {

      input.addEventListener('input', function () {
        recalcLine(row);
        recalcAll();
      });

    });

    row.querySelector('.remove-item')
      .addEventListener('click', function () {

        row.remove();

        recalcAll();

      });
  }

  function recalcLine(row) {

    const qty =
      parseFloat(row.querySelector('.qty').value) || 0;

    const price =
      parseFloat(row.querySelector('.price').value) || 0;

    const discount =
      parseFloat(row.querySelector('.discount').value) || 0;

    const taxPct =
      parseFloat(row.querySelector('.tax').value) || 0;

    const base =
      Math.max(0, (qty * price) - discount);

    const tax =
      base * (taxPct / 100);

    const total =
      base + tax;

    row.querySelector('.line-total').textContent =
      total.toFixed(2);

    return {
      base: qty * price,
      discount: discount,
      tax: tax,
      total: total
    };
  }

  function recalcAll() {

    let subtotal = 0;
    let discount = 0;
    let tax = 0;
    let total = 0;

    wrapper.querySelectorAll('.item-row')
      .forEach(function (row) {

        const vals = recalcLine(row);

        subtotal += vals.base;
        discount += vals.discount;
        tax += vals.tax;
        total += vals.total;

      });

    document.getElementById('sumSubtotal')
      .textContent = subtotal.toFixed(2);

    document.getElementById('sumDiscount')
      .textContent = discount.toFixed(2);

    document.getElementById('sumTax')
      .textContent = tax.toFixed(2);

    document.getElementById('sumTotal')
      .textContent = total.toFixed(2);
  }

  document
    .getElementById('addItemBtn')
    .addEventListener('click', addRow);

  document
    .getElementById('invoiceDate')
    .addEventListener('change', function () {

      const due =
        document.getElementById('dueDate');

      if (due.value < this.value) {
        due.value = this.value;
      }

    });

  addRow();

})();
</script>

<?php require __DIR__ . '/../includes/layout_footer.php'; ?>
