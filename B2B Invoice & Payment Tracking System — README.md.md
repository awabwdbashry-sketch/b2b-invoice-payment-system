# 🧾 B2B Invoice & Payment Tracking System

**نظام متكامل لإدارة الفواتير والمدفوعات للشركات (B2B)**

[![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](#-الترخيص)

---

## 🇸🇩 العربية

### 📌 عن المشروع

**B2B Invoice & Payment Tracking System** هو نظام ويب لإدارة الفواتير والمدفوعات بين الشركات، تم تطويره باستخدام **PHP وMySQL وBootstrap 5** بدون Framework أو Build Step.

النظام مصمم لتوفير طريقة منظمة لإدارة:

- 🏢 العملاء والشركات
- 🧾 الفواتير
- 💰 المدفوعات الكاملة والجزئية
- 📅 تواريخ الاستحقاق
- ⚠️ الفواتير المتأخرة
- 📄 إنشاء فواتير PDF
- 📧 إرسال الفواتير عبر البريد الإلكتروني
- 🔔 الإشعارات
- 📊 التقارير
- 👥 المستخدمين والصلاحيات
- 📝 سجل العمليات والتدقيق Audit Trail
- ⚙️ إعدادات النظام

---

### ✨ أهم المميزات

#### 🧾 إدارة الفواتير
- إنشاء وتعديل وعرض الفواتير.
- إضافة أكثر من منتج أو خدمة للفاتورة.
- حساب الإجمالي والضريبة تلقائيًا.
- دعم أرقام فواتير منظمة.
- إصدار وإلغاء الفواتير.
- متابعة حالة الفاتورة.
- اكتشاف الفواتير المتأخرة تلقائيًا.

#### 💰 إدارة المدفوعات
- تسجيل المدفوعات.
- دعم المدفوعات الجزئية.
- دعم أكثر من دفعة للفاتورة الواحدة.
- حساب المبلغ المدفوع والمتبقي.
- تحديث حالة الفاتورة بناءً على المدفوعات.

#### 📄 فواتير PDF
- إنشاء نسخة PDF من الفاتورة.
- دعم اللغة العربية وRTL.
- استخدام **TCPDF** لإنشاء ملفات PDF.
- إمكانية تحميل الفاتورة أو استخدامها للإرسال عبر البريد.

#### 📧 إرسال الفواتير عبر البريد
- إرسال الفاتورة مباشرة للعميل.
- إرفاق نسخة PDF من الفاتورة.
- دعم SMTP.
- يعمل مع Gmail وOutlook وSendGrid وMailgun وغيرها.

#### 👥 المستخدمون والصلاحيات
النظام يحتوي على نظام **Role-Based Access Control (RBAC)**.

الأدوار الأساسية:

- 👑 **Admin**
- 💼 **Finance**
- 👤 **Staff**

مع نظام صلاحيات مستقل للتحكم في العمليات التي يستطيع كل مستخدم تنفيذها.

#### 📊 التقارير
- تقارير الفواتير.
- تقارير المدفوعات.
- متابعة المبالغ المستحقة.
- متابعة الفواتير المتأخرة.
- تصدير البيانات بصيغة CSV.
- دعم البيانات العربية باستخدام UTF-8 BOM.

#### 🔔 الإشعارات
- إشعارات مرتبطة بالفواتير والمدفوعات.
- تنبيهات للفواتير المستحقة والمتأخرة.

#### 📝 Audit Trail
يتم تسجيل العمليات المهمة داخل النظام لمتابعة:

- المستخدم الذي نفذ العملية.
- نوع العملية.
- التاريخ والوقت.
- وصف العملية.

---

### 🛠️ التقنيات المستخدمة

| التقنية | الاستخدام |
|---|---|
| 🐘 PHP 8.3+ | Backend |
| 🗄️ MySQL | قاعدة البيانات |
| 🎨 Bootstrap 5 RTL | واجهة المستخدم |
| 📄 TCPDF | إنشاء ملفات PDF |
| 📧 PHPMailer | إرسال البريد الإلكتروني |
| 🔐 PHP Sessions | Authentication |
| 🛡️ RBAC | إدارة الصلاحيات |
| 🌍 UTF-8 / utf8mb4 | دعم اللغة العربية |

---

### 📁 هيكل المشروع

```text
b2b-invoice-payment-system/
│
├── admin/                  # 👑 إدارة المستخدمين وسجل العمليات
├── auth/                   # 🔐 تسجيل الدخول والخروج
├── config/                 # ⚙️ إعدادات النظام وملف البيئة
├── customers/              # 🏢 إدارة العملاء
├── database/               # 🗄️ ملفات قاعدة البيانات
├── includes/               # 🧠 الخدمات والمنطق المشترك
├── invoices/               # 🧾 إدارة الفواتير
├── notifications/          # 🔔 الإشعارات
├── payments/               # 💰 إدارة المدفوعات
├── reports/                # 📊 التقارير
├── scripts/                # 🛠️ Scripts مساعدة
├── settings/               # ⚙️ إعدادات النظام
├── uploads/                # 📂 ملفات الرفع المحلية
├── vendor/
│   ├── phpmailer/          # 📧 PHPMailer
│   └── tcpdf/              # 📄 TCPDF
│
├── .gitignore
├── index.php
└── README.md
```

---

### 🗄️ قاعدة البيانات

يحتوي المشروع على ملفات SQL جاهزة:

```text
database/
├── schema.sql
├── seed.sql
├── migrate_utf8mb4.sql
└── schema.sqlite.sql
```

#### MySQL

الاستخدام الأساسي للنظام يكون مع:

```text
Database: b2b_invoice
```

ويحتوي النظام على جداول رئيسية مثل:

- `users`
- `roles`
- `permissions`
- `role_permissions`
- `customers`
- `invoices`
- `invoice_items`
- `payments`
- `notifications`
- `audit_logs`
- `settings`

---

### 🚀 تشغيل المشروع محليًا

#### 1️⃣ المتطلبات

تأكد من توفر:

- PHP 8.3 أو أحدث
- MySQL 8 أو متوافق
- Apache / WAMP / XAMPP
- متصفح حديث

---

#### 2️⃣ إنشاء قاعدة البيانات

من phpMyAdmin قم بإنشاء:

```text
b2b_invoice
```

ثم قم باستيراد:

```text
database/schema.sql
```

وبعدها:

```text
database/seed.sql
```

---

#### 3️⃣ إعداد البيئة

انسخ:

```text
config/.env.example
```

إلى:

```text
.env
```

ثم ضع إعدادات قاعدة البيانات الخاصة بك.

مثال:

```env
APP_ENV=local
APP_NAME="B2B Invoice & Payment Tracking System"
APP_URL=http://localhost/b2b-invoice
APP_TIMEZONE=Africa/Cairo

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=b2b_invoice
DB_USER=root
DB_PASS=
```

> 🔐 **مهم:** لا ترفع ملف `.env` إلى GitHub. استخدم `config/.env.example` كقالب فقط.

---

### 📧 إعداد البريد الإلكتروني

لتمكين إرسال الفواتير عبر البريد، أضف إعدادات SMTP داخل `.env`.

مثال عام:

```env
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-email@example.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@example.com
MAIL_FROM_NAME="B2B Invoice & Payment Tracking System"
```

> 🔐 لا تضع كلمات المرور أو App Passwords الحقيقية داخل GitHub.

---

### 🌐 فتح النظام

بعد تشغيل Apache وMySQL:

```text
http://localhost/b2b-invoice-payment-system/app
```

قد يختلف الرابط حسب مكان وضع المشروع داخل WAMP.

---

### 🔐 الأمان

تم تجهيز المشروع مع مراعاة عدد من ممارسات الأمان، منها:

- 🔒 عدم رفع `.env` إلى Git.
- 🔑 استخدام Password Hashing للمستخدمين.
- 🛡️ نظام صلاحيات RBAC.
- 🧹 Server-side validation.
- 🗄️ استخدام MySQL وPrepared Statements.
- 📝 تسجيل العمليات المهمة في Audit Log.
- 🔐 حماية العمليات حسب صلاحيات المستخدم.

---

### 📌 حالة المشروع

**Status: Active Development 🚧**

المشروع يعمل محليًا ويحتوي على الوظائف الأساسية لإدارة:

> العملاء → الفواتير → المدفوعات → التقارير → PDF → البريد الإلكتروني → الصلاحيات → سجل العمليات

---

### 👨‍💻 المطور

**[Awab Bashary | AwabBuilds](https://awabdev.byethost12.com/?i=1#top)**

💻 Software Developer  
🌐 Web & Flutter Developer  
🤖 AI Developer  
📱 Building Modern Web, Mobile & AI Applications

---

### 📜 الترخيص

هذا المشروع مرخص بموجب **MIT License**.

---

# 🇬🇧 English

## 📌 About

**B2B Invoice & Payment Tracking System** is a web-based application designed to manage business-to-business invoices and payments.

The system is built with **PHP, MySQL, Bootstrap 5, PHPMailer, and TCPDF**, without a framework or build step.

It provides an organized workflow for managing:

- 🏢 Customers
- 🧾 Invoices
- 💰 Full and partial payments
- 📅 Due dates
- ⚠️ Overdue invoices
- 📄 PDF invoices
- 📧 Email invoice delivery
- 🔔 Notifications
- 📊 Reports
- 👥 Users and permissions
- 📝 Audit logs
- ⚙️ System settings

---

## ✨ Features

### 🧾 Invoice Management

- Create, edit, view, issue, and cancel invoices.
- Add multiple line items.
- Automatic subtotal, tax, and total calculations.
- Structured invoice numbering.
- Invoice status tracking.
- Automatic overdue detection.

### 💰 Payment Tracking

- Record payments.
- Support partial payments.
- Support multiple payments per invoice.
- Track paid and remaining amounts.
- Automatically update invoice payment status.

### 📄 PDF Invoices

- Generate invoice PDFs.
- Arabic / RTL support.
- TCPDF-based PDF generation.
- Download and email PDF invoices.

### 📧 Email Delivery

- Send invoices directly to customers.
- Attach generated PDF invoices.
- SMTP support.
- Compatible with Gmail, Outlook, SendGrid, Mailgun, and other SMTP providers.

### 👥 Role-Based Access Control

The system includes role-based permissions with:

- 👑 **Admin**
- 💼 **Finance**
- 👤 **Staff**

Each role can have its own set of permissions.

### 📊 Reports

- Invoice reports.
- Payment reports.
- Outstanding amounts.
- Overdue invoices.
- CSV export.
- UTF-8 support for Arabic data.

### 🔔 Notifications

The system provides notifications related to invoices, payments, due dates, and overdue items.

### 📝 Audit Trail

Important actions are recorded with:

- User
- Action
- Date and time
- Description

---

## 🛠️ Tech Stack

| Technology | Purpose |
|---|---|
| 🐘 PHP 8.3+ | Backend |
| 🗄️ MySQL | Database |
| 🎨 Bootstrap 5 RTL | User Interface |
| 📄 TCPDF | PDF Generation |
| 📧 PHPMailer | Email / SMTP |
| 🔐 PHP Sessions | Authentication |
| 🛡️ RBAC | Authorization |
| 🌍 UTF-8 / utf8mb4 | Arabic Support |

---

## 📁 Project Structure

```text
b2b-invoice-payment-system/
│
├── admin/                  # 👑 Administration
├── auth/                   # 🔐 Authentication
├── config/                 # ⚙️ Configuration
├── customers/              # 🏢 Customer management
├── database/               # 🗄️ Database scripts
├── includes/               # 🧠 Shared services
├── invoices/               # 🧾 Invoice management
├── notifications/          # 🔔 Notifications
├── payments/               # 💰 Payment management
├── reports/                # 📊 Reports
├── scripts/                # 🛠️ Utility scripts
├── settings/               # ⚙️ Settings
├── uploads/                # 📂 Local uploads
├── vendor/
│   ├── phpmailer/
│   └── tcpdf/
│
├── .gitignore
├── index.php
└── README.md
```

---

## 🗄️ Database

The project includes ready-to-use database scripts:

```text
database/
├── schema.sql
├── seed.sql
├── migrate_utf8mb4.sql
└── schema.sqlite.sql
```

The main MySQL database is:

```text
b2b_invoice
```

Main tables include:

```text
users
roles
permissions
role_permissions
customers
invoices
invoice_items
payments
notifications
audit_logs
settings
```

---

## 🚀 Local Installation

### 1️⃣ Requirements

- PHP 8.3+
- MySQL 8+ or compatible
- Apache / WAMP / XAMPP
- Modern web browser

### 2️⃣ Create the Database

Create:

```text
b2b_invoice
```

Then import:

```text
database/schema.sql
```

followed by:

```text
database/seed.sql
```

### 3️⃣ Configure Environment

Copy:

```text
config/.env.example
```

to:

```text
.env
```

Then configure your local database and SMTP settings.

> 🔐 Never commit the real `.env` file.

### 4️⃣ Run

Start Apache and MySQL, then open:

```text
http://localhost/b2b-invoice-payment-system/app
```

The URL may vary depending on your local WAMP configuration.

---

## 📧 SMTP Configuration

Example:

```env
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-email@example.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@example.com
MAIL_FROM_NAME="B2B Invoice & Payment Tracking System"
```

> 🔐 Never publish real SMTP passwords or App Passwords.

---

## 🔐 Security

The project includes:

- 🔒 Environment variable protection.
- 🔑 Password hashing.
- 🛡️ Role-based access control.
- 🧹 Server-side validation.
- 🗄️ Prepared database queries.
- 📝 Audit logging.
- 🔐 Permission-based actions.

---

## 📌 Project Status

**Status: Active Development 🚧**

The current system covers the main business workflow:

```text
Customers
   ↓
Invoices
   ↓
Payments
   ↓
Reports
   ↓
PDF
   ↓
Email
   ↓
Notifications
   ↓
Permissions & Audit Logs
```

---

## 👨‍💻 Developer

**[Awab Bashary | AwabBuilds](https://awabdev.byethost12.com/?i=1#top)**

💻 Software Developer  
🌐 Web & Flutter Developer  
🤖 AI Developer  
📱 Building Modern Web, Mobile & AI Applications

---

## 📜 License

This project is licensed under the **MIT License**.

---

<p align="center">

### 🧾 B2B Invoice & Payment Tracking System

**Built with PHP, MySQL & Bootstrap 5 ❤️**

**[Awab Bashary | AwabBuilds](https://awabdev.byethost12.com/?i=1#top)**

</p>