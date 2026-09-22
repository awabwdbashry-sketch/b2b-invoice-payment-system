-- ============================================================
-- Safe UTF-8/utf8mb4 migration for EXISTING deployments
-- ============================================================
-- Run this ONLY if you already deployed this system before the Arabic
-- localization update and your database/tables were not created with
-- utf8mb4. This does NOT drop or recreate anything and does NOT touch
-- existing rows — it only converts the storage charset/collation so
-- Arabic (and other Unicode) text is stored and compared correctly.
--
-- Safe to run multiple times (idempotent). Existing data is preserved.
--
-- Usage:
--   mysql -u root -p b2b_invoice < database/migrate_utf8mb4.sql

ALTER DATABASE b2b_invoice CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE roles                      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE permissions                CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE role_permissions           CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE users                      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE customers                  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE invoice_number_sequences   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE invoices                   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE invoice_items              CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE payments                   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE notifications              CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE audit_logs                 CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE settings                   CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
