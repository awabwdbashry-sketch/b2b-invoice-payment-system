<?php
// Local development helper ONLY. Not part of the production deployment flow.
declare(strict_types=1);

$root = dirname(__DIR__);
$dbPath = $root . '/database/dev.sqlite';
if (file_exists($dbPath)) unlink($dbPath);

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON');

$schema = file_get_contents($root . '/database/schema.sqlite.sql');
$pdo->exec($schema);

// ---- Roles ----
$pdo->exec("INSERT INTO roles (id, name, description, is_system) VALUES
 (1,'Admin','Full system access',1),
 (2,'Finance','Manages invoices, payments and reports',1),
 (3,'Staff','Limited view / data entry access',1)");

// ---- Permissions ----
$perms = [
 ['users.view','users','View users'],['users.create','users','Create users'],
 ['users.edit','users','Edit users'],['users.delete','users','Deactivate/delete users'],
 ['customers.view','customers','View customers'],['customers.create','customers','Create customers'],
 ['customers.edit','customers','Edit customers'],['customers.delete','customers','Archive customers'],
 ['invoices.view','invoices','View invoices'],['invoices.create','invoices','Create invoices'],
 ['invoices.edit','invoices','Edit invoices'],['invoices.delete','invoices','Delete draft invoices'],
 ['invoices.issue','invoices','Issue invoices'],['invoices.cancel','invoices','Cancel invoices'],
 ['payments.view','payments','View payments'],['payments.create','payments','Record payments'],
 ['payments.edit','payments','Edit payments'],['payments.delete','payments','Delete payments'],
 ['reports.view','reports','View reports'],['reports.export','reports','Export reports'],
 ['settings.manage','settings','Manage company settings'],
 ['audit_logs.view','audit_logs','View audit logs'],
 ['notifications.view','notifications','View notifications'],
];
$ins = $pdo->prepare('INSERT INTO permissions (key, module, description) VALUES (?,?,?)');
foreach ($perms as $p) $ins->execute($p);

$permIds = $pdo->query('SELECT key, id FROM permissions')->fetchAll(PDO::FETCH_KEY_PAIR);

$rp = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)');
foreach ($permIds as $key => $id) { $rp->execute([1, $id]); } // Admin: all
foreach ($permIds as $key => $id) { if (!str_starts_with($key,'users.')) $rp->execute([2, $id]); } // Finance
$staffKeys = ['customers.view','customers.create','customers.edit','invoices.view','invoices.create','payments.view','notifications.view'];
foreach ($staffKeys as $key) { $rp->execute([3, $permIds[$key]]); }

// ---- Users ----
$pdo->exec("INSERT INTO users (id, role_id, full_name, username, email, password_hash, status) VALUES
 (1,1,'System Administrator','admin','admin@example.com','" . password_hash('Admin@12345', PASSWORD_BCRYPT) . "','active'),
 (2,2,'Finance Officer','finance','finance@example.com','" . password_hash('Finance@12345', PASSWORD_BCRYPT) . "','active'),
 (3,3,'Staff Member','staff','staff@example.com','" . password_hash('Staff@12345', PASSWORD_BCRYPT) . "','active')");

// ---- Settings ----
$settings = [
 'company_name'=>'شركة الحلول المتقدمة','company_address'=>'123 شارع الأعمال، الطابق 4، القاهرة، مصر',
 'company_email'=>'billing@acmesolutions.example','company_phone'=>'+20 100 000 0000',
 'company_website'=>'https://acmesolutions.example','company_tax_number'=>'TAX-1234567',
 'company_logo'=>'', 'default_currency'=>'USD','invoice_prefix'=>'INV',
 'default_tax_percent'=>'14','invoice_due_days'=>'30',
];
$si = $pdo->prepare('INSERT INTO settings (key, value) VALUES (?,?)');
foreach ($settings as $k=>$v) $si->execute([$k,$v]);

// ---- Customers ----
$pdo->exec("INSERT INTO customers (id, company_name, contact_person, email, phone, address, tax_number, registration_number, status, created_by) VALUES
 (1,'شركة الأهرام للتقنية','سارة مصطفى','sara@abctech.example','+20 100 111 2222','12 شارع التحرير، القاهرة','TX-1001','RC-5001','active',1),
 (2,'شركة النيل للتجارة','محمد عادل','madel@niletraders.example','+20 122 333 4444','45 شارع الكورنيش، الجيزة','TX-1002','RC-5002','active',1),
 (3,'شركة الدلتا للخدمات اللوجستية','هبة فتحي','heba@deltalog.example','+20 155 666 7777','9 المنطقة الصناعية، الإسكندرية','TX-1003','RC-5003','active',2)");

$pdo->exec("INSERT INTO invoice_number_sequences (year, last_number) VALUES (2026, 3)");

// Invoice 1: fully paid
$pdo->exec("INSERT INTO invoices (id, invoice_number, customer_id, invoice_date, due_date, currency, subtotal, discount_amount, tax_amount, total_amount, paid_amount, remaining_amount, status, created_by) VALUES
 (1,'INV-2026-0001',1,'2026-06-01','2026-06-15','USD',2000,0,0,2000,2000,0,'paid',1)");
$pdo->exec("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, discount_amount, tax_percent, line_total, sort_order) VALUES
 (1,'Website Development',1,1500,0,0,1500,1),
 (1,'Hosting (annual)',2,200,0,0,400,2),
 (1,'Maintenance Package',1,100,0,0,100,3)");
$pdo->exec("INSERT INTO payments (invoice_id, customer_id, amount, payment_date, payment_method, reference_number, created_by) VALUES
 (1,1,2000,'2026-06-10','bank_transfer','TRX-0001',2)");

// Invoice 2: partially paid, not yet due
$pdo->exec("INSERT INTO invoices (id, invoice_number, customer_id, invoice_date, due_date, currency, subtotal, discount_amount, tax_amount, total_amount, paid_amount, remaining_amount, status, created_by) VALUES
 (2,'INV-2026-0002',2,'2026-09-01','2026-10-01','USD',5000,200,672,5472,2000,3472,'partially_paid',2)");
$pdo->exec("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, discount_amount, tax_percent, line_total, sort_order) VALUES
 (2,'ERP Implementation - Phase 1',1,5000,200,14,5472,1)");
$pdo->exec("INSERT INTO payments (invoice_id, customer_id, amount, payment_date, payment_method, reference_number, created_by) VALUES
 (2,2,2000,'2026-09-05','cheque','CHQ-4521',2)");

// Invoice 3: overdue, unpaid
$pdo->exec("INSERT INTO invoices (id, invoice_number, customer_id, invoice_date, due_date, currency, subtotal, discount_amount, tax_amount, total_amount, paid_amount, remaining_amount, status, created_by) VALUES
 (3,'INV-2026-0003',3,'2026-07-15','2026-08-01','USD',1500,0,0,1500,0,1500,'overdue',1)");
$pdo->exec("INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, discount_amount, tax_percent, line_total, sort_order) VALUES
 (3,'Freight Consulting Services',1,1500,0,0,1500,1)");

$pdo->exec("INSERT INTO notifications (user_id, title, message, type, related_entity_type, related_entity_id, is_read) VALUES
 (1,'Invoice Fully Paid','Invoice INV-2026-0001 has been fully paid.','invoice_paid','invoice',1,0),
 (2,'Payment Recorded','A payment of 2000.00 USD was recorded for INV-2026-0002.','payment_recorded','invoice',2,0),
 (1,'Invoice Overdue','Invoice INV-2026-0003 is overdue.','invoice_overdue','invoice',3,0)");

$pdo->exec("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES
 (1,'invoice.created','invoice',1,'Created invoice INV-2026-0001 for شركة الأهرام للتقنية','127.0.0.1'),
 (2,'payment.created','payment',1,'Recorded payment of 2000.00 for INV-2026-0001','127.0.0.1'),
 (1,'invoice.created','invoice',3,'Created invoice INV-2026-0003 for شركة الدلتا للخدمات اللوجستية','127.0.0.1')");

echo "SQLite dev database initialized at $dbPath\n";
