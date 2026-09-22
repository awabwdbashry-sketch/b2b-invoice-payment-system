-- ============================================================
-- Seed Data (development/demo use only)
-- ============================================================
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Roles
-- ------------------------------------------------------------
INSERT INTO roles (id, name, description, is_system) VALUES
 (1, 'Admin', 'Full system access', 1),
 (2, 'Finance', 'Manages invoices, payments and reports', 1),
 (3, 'Staff', 'Limited view / data entry access', 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ------------------------------------------------------------
-- Permissions
-- ------------------------------------------------------------
INSERT INTO permissions (`key`, module, description) VALUES
('users.view','users','View users'),
('users.create','users','Create users'),
('users.edit','users','Edit users'),
('users.delete','users','Deactivate/delete users'),

('customers.view','customers','View customers'),
('customers.create','customers','Create customers'),
('customers.edit','customers','Edit customers'),
('customers.delete','customers','Archive customers'),

('invoices.view','invoices','View invoices'),
('invoices.create','invoices','Create invoices'),
('invoices.edit','invoices','Edit invoices'),
('invoices.delete','invoices','Delete draft invoices'),
('invoices.issue','invoices','Issue invoices'),
('invoices.cancel','invoices','Cancel invoices'),

('payments.view','payments','View payments'),
('payments.create','payments','Record payments'),
('payments.edit','payments','Edit payments'),
('payments.delete','payments','Delete payments'),

('reports.view','reports','View reports'),
('reports.export','reports','Export reports'),

('settings.manage','settings','Manage company settings'),
('audit_logs.view','audit_logs','View audit logs'),
('notifications.view','notifications','View notifications')
ON DUPLICATE KEY UPDATE module = VALUES(module);

-- ------------------------------------------------------------
-- Role -> Permission mapping
-- ------------------------------------------------------------
-- Admin: all permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Finance: everything except user management
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions
WHERE `key` NOT LIKE 'users.%'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Staff: view-only + create customers/invoices (no issue/cancel/delete/financial edit)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions
WHERE `key` IN (
  'customers.view','customers.create','customers.edit',
  'invoices.view','invoices.create',
  'payments.view',
  'notifications.view'
)
ON DUPLICATE KEY UPDATE role_id = role_id;

-- ------------------------------------------------------------
-- Development Users
-- Passwords (dev only, CHANGE IN PRODUCTION):
--   admin@example.com   / Admin@12345
--   finance@example.com / Finance@12345
--   staff@example.com   / Staff@12345
-- ------------------------------------------------------------
INSERT INTO users (id, role_id, full_name, username, email, password_hash, status) VALUES
(1, 1, 'System Administrator', 'admin',   'admin@example.com',   '$2y$10$t93chH.NbV6dpDXEkEM.qux9l0LMR3riYOKxFzLeFOFsxp9w09pk2', 'active'),
(2, 2, 'Finance Officer',      'finance', 'finance@example.com', '$2y$10$befqqvxeZ.B5tOK9o2FK4erjgbS6xN5u5tQwYlw16XzTfiXvkJhti', 'active'),
(3, 3, 'Staff Member',         'staff',   'staff@example.com',   '$2y$10$RR0JgtJFT6wpT.fSC2tDIexzktLDKuw.Bj9khEg7zCxYn8usgIkpW', 'active')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- ------------------------------------------------------------
-- Settings
-- ------------------------------------------------------------
INSERT INTO settings (`key`, `value`) VALUES
('company_name', 'شركة الحلول المتقدمة'),
('company_address', '123 شارع الأعمال، الطابق 4، القاهرة، مصر'),
('company_email', 'billing@acmesolutions.example'),
('company_phone', '+20 100 000 0000'),
('company_website', 'https://acmesolutions.example'),
('company_tax_number', 'TAX-1234567'),
('company_logo', ''),
('default_currency', 'USD'),
('invoice_prefix', 'INV'),
('default_tax_percent', '14'),
('invoice_due_days', '30')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- ------------------------------------------------------------
-- Sample Customers
-- ------------------------------------------------------------
INSERT INTO customers (id, company_name, contact_person, email, phone, address, tax_number, registration_number, status, created_by) VALUES
(1, 'شركة الأهرام للتقنية', 'سارة مصطفى', 'sara@abctech.example', '+20 100 111 2222', '12 شارع التحرير، القاهرة', 'TX-1001', 'RC-5001', 'active', 1),
(2, 'شركة النيل للتجارة', 'محمد عادل', 'madel@niletraders.example', '+20 122 333 4444', '45 شارع الكورنيش، الجيزة', 'TX-1002', 'RC-5002', 'active', 1),
(3, 'شركة الدلتا للخدمات اللوجستية', 'هبة فتحي', 'heba@deltalog.example', '+20 155 666 7777', '9 المنطقة الصناعية، الإسكندرية', 'TX-1003', 'RC-5003', 'active', 2)
ON DUPLICATE KEY UPDATE company_name = VALUES(company_name);

-- ------------------------------------------------------------
-- Invoice number sequence starting point for current year batch below
-- ------------------------------------------------------------
INSERT INTO invoice_number_sequences (year, last_number) VALUES (2026, 3)
ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, VALUES(last_number));

-- ------------------------------------------------------------
-- Sample Invoice 1: شركة الأهرام للتقنية - fully paid
-- ------------------------------------------------------------
INSERT INTO invoices (id, invoice_number, customer_id, invoice_date, due_date, currency, subtotal, discount_amount, tax_amount, total_amount, paid_amount, remaining_amount, status, created_by) VALUES
(1, 'INV-2026-0001', 1, '2026-06-01', '2026-06-15', 'USD', 2000.00, 0.00, 0.00, 2000.00, 2000.00, 0.00, 'paid', 1);

INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, discount_amount, tax_percent, line_total, sort_order) VALUES
(1, 'Website Development', 1, 1500.00, 0.00, 0.00, 1500.00, 1),
(1, 'Hosting (annual)', 2, 200.00, 0.00, 0.00, 400.00, 2),
(1, 'Maintenance Package', 1, 100.00, 0.00, 0.00, 100.00, 3);

INSERT INTO payments (invoice_id, customer_id, amount, payment_date, payment_method, reference_number, created_by) VALUES
(1, 1, 2000.00, '2026-06-10', 'bank_transfer', 'TRX-0001', 2);

-- ------------------------------------------------------------
-- Sample Invoice 2: شركة النيل للتجارة - partially paid, not yet due
-- ------------------------------------------------------------
INSERT INTO invoices (id, invoice_number, customer_id, invoice_date, due_date, currency, subtotal, discount_amount, tax_amount, total_amount, paid_amount, remaining_amount, status, created_by) VALUES
(2, 'INV-2026-0002', 2, '2026-09-01', '2026-10-01', 'USD', 5000.00, 200.00, 672.00, 5472.00, 2000.00, 3472.00, 'partially_paid', 2);

INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, discount_amount, tax_percent, line_total, sort_order) VALUES
(2, 'ERP Implementation - Phase 1', 1, 5000.00, 200.00, 14.00, 5472.00, 1);

INSERT INTO payments (invoice_id, customer_id, amount, payment_date, payment_method, reference_number, created_by) VALUES
(2, 2, 2000.00, '2026-09-05', 'cheque', 'CHQ-4521', 2);

-- ------------------------------------------------------------
-- Sample Invoice 3: شركة الدلتا للخدمات اللوجستية - overdue (unpaid, due date passed)
-- ------------------------------------------------------------
INSERT INTO invoices (id, invoice_number, customer_id, invoice_date, due_date, currency, subtotal, discount_amount, tax_amount, total_amount, paid_amount, remaining_amount, status, created_by) VALUES
(3, 'INV-2026-0003', 3, '2026-07-15', '2026-08-01', 'USD', 1500.00, 0.00, 0.00, 1500.00, 0.00, 1500.00, 'overdue', 1);

INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, discount_amount, tax_percent, line_total, sort_order) VALUES
(3, 'Freight Consulting Services', 1, 1500.00, 0.00, 0.00, 1500.00, 1);

-- ------------------------------------------------------------
-- Sample Notifications
-- ------------------------------------------------------------
INSERT INTO notifications (user_id, title, message, type, related_entity_type, related_entity_id, is_read) VALUES
(1, 'Invoice Fully Paid', 'Invoice INV-2026-0001 has been fully paid.', 'invoice_paid', 'invoice', 1, 0),
(2, 'Payment Recorded', 'A payment of 2000.00 USD was recorded for INV-2026-0002.', 'payment_recorded', 'invoice', 2, 0),
(1, 'Invoice Overdue', 'Invoice INV-2026-0003 is overdue.', 'invoice_overdue', 'invoice', 3, 0);

-- ------------------------------------------------------------
-- Sample Audit Logs
-- ------------------------------------------------------------
INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES
(1, 'invoice.created', 'invoice', 1, 'Created invoice INV-2026-0001 for شركة الأهرام للتقنية', '127.0.0.1'),
(2, 'payment.created', 'payment', 1, 'Recorded payment of 2000.00 for INV-2026-0001', '127.0.0.1'),
(1, 'invoice.created', 'invoice', 3, 'Created invoice INV-2026-0003 for شركة الدلتا للخدمات اللوجستية', '127.0.0.1');
