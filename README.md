B2B Invoice & Payment Tracking System

A modern web-based system for managing B2B customers, invoices, payments, due dates, PDF invoices, email delivery, notifications, reports, and audit logs.

🇬🇧 English

📌 Overview

B2B Invoice & Payment Tracking System is a web-based invoice and payment management application built with PHP, MySQL, and Bootstrap 5.



The system is designed to help businesses manage their customers, create and track invoices, record full or partial payments, monitor overdue invoices, generate PDF invoices, send invoices by email, and maintain a detailed audit trail.



The application uses a clean Arabic RTL interface while keeping internal system status codes and technical identifiers in English.

✨ Features

👥 Customer management

🧾 Invoice creation and management

📦 Invoice line items

💰 Full and partial payments

📅 Invoice due-date tracking

⚠️ Automatic overdue detection

📄 PDF invoice generation

📧 Send invoices by email using SMTP

🔔 Notifications

📊 Dashboard and financial reports

📥 CSV report export

👤 Role-Based Access Control (RBAC)

🔐 Permission-based authorization

📝 Audit trail and activity logging

⚙️ Application settings

🌐 Arabic / RTL user interface

🗄️ MySQL database with UTF-8 / utf8mb4

📱 Responsive Bootstrap 5 interface

🛠️ Technology Stack

TechnologyPurpose

PHP 8.3+

Backend / Server-side application

MySQL

Database

Bootstrap 5

User interface

Bootstrap RTL

Arabic / RTL layout

TCPDF

PDF invoice generation

SMTP

Email delivery

HTML5

Application structure

CSS3

Styling

JavaScript

Client-side interactions

🏗️ Project Structure

b2b-invoice-payment-system/
│
├── app/
│   ├── auth/
│   ├── customers/
│   ├── invoices/
│   ├── payments/
│   ├── reports/
│   ├── notifications/
│   ├── admin/
│   ├── settings/
│   ├── config/
│   ├── database/
│   ├── includes/
│   └── ...
│
├── vendor/
│   └── tcpdf/
│
├── .env.example
├── .gitignore
└── README.md


🗄️ Main Database Modules

The application uses MySQL with the following main areas:



users

roles

permissions

role_permissions

customers

invoices

invoice_items

payments

invoice_number_sequences

notifications

audit_logs

settings



The database uses utf8mb4 to properly support Arabic and multilingual data.

🔐 Authentication & Authorization

The system includes role-based access control with separate permissions for different types of users.



Default application roles include:



Admin

Finance

Staff



Permissions control access to application areas and actions instead of relying only on the user's role name.

💳 Invoice & Payment Tracking

Invoices support:



Multiple line items

Automatic totals

Tax calculation

Due dates

Partial payments

Multiple payment records

Remaining balance tracking

Payment status

Overdue detection



This allows the system to track an invoice throughout its complete payment lifecycle.

📄 PDF Invoices

Invoices can be generated as PDF documents using TCPDF.



The PDF functionality supports Arabic invoice content and is designed to be used for both downloading and email delivery.

📧 Email Invoice Delivery

The system supports sending invoices by email through a standard SMTP server.



SMTP configuration is stored in the local .env file.



Example:

MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="B2B Invoice & Payment Tracking System"


⚠️ Never commit the real .env file or SMTP credentials to GitHub.

⚙️ Local Installation

1. Clone the repository

git clone https://github.com/YOUR_USERNAME/YOUR_REPOSITORY.git


2. Open the project in your local web server

For WAMP, place the project inside:

C:\wamp64\www\


For example:

C:\wamp64\www\b2b-invoice-payment-system


3. Create the database

Create a MySQL database named:

b2b_invoice


Then import the project's database schema and seed files using phpMyAdmin.

4. Configure environment variables

Copy:

.env.example


to:

.env


Then configure the database and mail settings.



Example:

APP_ENV=local
APP_NAME="B2B Invoice & Payment Tracking System"
APP_URL=http://localhost/b2b-invoice-payment-system/app
APP_TIMEZONE=Africa/Cairo

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=b2b_invoice
DB_USER=root
DB_PASS=

MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="B2B Invoice & Payment Tracking System"


5. Open the application

Open the application through your local WAMP server.



Example:

http://localhost/b2b-invoice-payment-system/app


🔒 Security

Before deploying the application:



Never commit .env

Never commit SMTP passwords

Never commit Gmail App Passwords

Never expose database credentials

Use HTTPS in production

Use strong production passwords

Change demo credentials before production deployment

Keep dependencies updated

📋 Current Status

The core application includes:



Customer management

Invoice management

Payment tracking

Partial payments

PDF generation

Email invoice delivery

Notifications

Reports

CSV export

RBAC

Audit logging

Arabic RTL interface

MySQL database integration

🚀 Project Status

Development / Portfolio Project



The system is being developed as a practical B2B financial management application with a focus on invoice tracking, payment management, Arabic RTL support, and real-world business workflows.

🇸🇩 العربية

📌 نبذة عن المشروع

نظام إدارة ومتابعة فواتير ومدفوعات الشركات (B2B Invoice & Payment Tracking System) هو تطبيق ويب لإدارة العملاء والفواتير والمدفوعات ومتابعة المبالغ المستحقة والمتأخرة.



تم بناء النظام باستخدام PHP وMySQL وBootstrap 5، مع واجهة مستخدم عربية تدعم اتجاه RTL.



يسمح النظام بإنشاء الفواتير وإدارتها، تسجيل المدفوعات الكاملة والجزئية، إنشاء فواتير PDF، إرسال الفواتير عبر البريد الإلكتروني، متابعة الفواتير المتأخرة، وإدارة الصلاحيات وسجل العمليات.

✨ المميزات

👥 إدارة العملاء

🧾 إنشاء وإدارة الفواتير

📦 إضافة عناصر متعددة داخل الفاتورة

💰 تسجيل المدفوعات الكاملة والجزئية

📅 متابعة تواريخ استحقاق الفواتير

⚠️ اكتشاف الفواتير المتأخرة

📄 إنشاء فواتير PDF

📧 إرسال الفواتير عبر البريد الإلكتروني

🔔 نظام إشعارات

📊 لوحة تحكم وتقارير مالية

📥 تصدير التقارير بصيغة CSV

👤 نظام صلاحيات مبني على الأدوار RBAC

🔐 صلاحيات تفصيلية للمستخدمين

📝 سجل كامل للعمليات Audit Log

⚙️ إعدادات النظام

🌐 واجهة عربية RTL

🗄️ قاعدة بيانات MySQL تدعم utf8mb4

📱 تصميم متجاوب مع الشاشات المختلفة

🛠️ التقنيات المستخدمة

التقنيةالاستخدام

PHP 8.3+

البرمجة الخلفية

MySQL

قاعدة البيانات

Bootstrap 5

واجهة المستخدم

Bootstrap RTL

دعم اللغة العربية واتجاه RTL

TCPDF

إنشاء ملفات PDF

SMTP

إرسال البريد الإلكتروني

HTML5

هيكلة الصفحات

CSS3

التنسيق

JavaScript

التفاعلات داخل الواجهة

🏗️ هيكل المشروع

b2b-invoice-payment-system/
│
├── app/
│   ├── auth/
│   ├── customers/
│   ├── invoices/
│   ├── payments/
│   ├── reports/
│   ├── notifications/
│   ├── admin/
│   ├── settings/
│   ├── config/
│   ├── database/
│   ├── includes/
│   └── ...
│
├── vendor/
│   └── tcpdf/
│
├── .env.example
├── .gitignore
└── README.md


🗄️ قاعدة البيانات

يعتمد النظام على MySQL، ومن أهم الجداول:



users

roles

permissions

role_permissions

customers

invoices

invoice_items

payments

invoice_number_sequences

notifications

audit_logs

settings



تم إعداد قاعدة البيانات باستخدام utf8mb4 لدعم اللغة العربية والبيانات متعددة اللغات.

🔐 تسجيل الدخول والصلاحيات

يحتوي النظام على نظام Role-Based Access Control (RBAC) لإدارة صلاحيات المستخدمين.



الأدوار الأساسية:



Admin — مدير النظام

Finance — المالية

Staff — الموظف



ويتم التحكم في الوصول إلى الوظائف المختلفة من خلال الصلاحيات المحددة لكل مستخدم.

💳 إدارة الفواتير والمدفوعات

يدعم النظام:



إضافة عدة عناصر للفاتورة

حساب إجمالي الفاتورة

حساب الضرائب

تحديد تاريخ الاستحقاق

تسجيل دفعات جزئية

تسجيل أكثر من دفعة لنفس الفاتورة

حساب الرصيد المتبقي

متابعة حالة الدفع

اكتشاف الفواتير المتأخرة



وبذلك يمكن متابعة الفاتورة من لحظة إنشائها وحتى إتمام سدادها.

📄 فواتير PDF

يمكن للنظام إنشاء الفواتير بصيغة PDF باستخدام مكتبة TCPDF.



كما يدعم محتوى الفواتير باللغة العربية، ويمكن استخدام ملفات PDF الناتجة للتحميل أو الإرسال عبر البريد الإلكتروني.

📧 إرسال الفواتير عبر البريد الإلكتروني

يدعم النظام إرسال الفواتير مباشرة عبر SMTP.



يتم حفظ إعدادات البريد في ملف .env المحلي.



مثال:

MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="B2B Invoice & Payment Tracking System"


⚠️ مهم جدًا: لا تقم برفع ملف .env الحقيقي إلى GitHub، ولا ترفع كلمات مرور SMTP أو Gmail App Password.

⚙️ تشغيل المشروع محليًا

1. تحميل المشروع

git clone https://github.com/YOUR_USERNAME/YOUR_REPOSITORY.git


2. وضع المشروع داخل WAMP

ضع المشروع داخل:

C:\wamp64\www\


مثال:

C:\wamp64\www\b2b-invoice-payment-system


3. إنشاء قاعدة البيانات

أنشئ قاعدة بيانات باسم:

b2b_invoice


ثم قم باستيراد ملفات قاعدة البيانات باستخدام phpMyAdmin.

4. إعداد ملف البيئة

انسخ:

.env.example


إلى:

.env


ثم أدخل إعدادات قاعدة البيانات والبريد الخاصة بك.

5. فتح النظام

بعد تشغيل WAMP، افتح:

http://localhost/b2b-invoice-payment-system/app



📋 حالة المشروع الحالية

يحتوي النظام حاليًا على:



إدارة العملاء

إدارة الفواتير

متابعة المدفوعات

المدفوعات الجزئية

إنشاء PDF

إرسال الفواتير عبر البريد الإلكتروني

الإشعارات

التقارير

تصدير CSV

نظام RBAC

سجل العمليات Audit Log

واجهة عربية RTL

تكامل فعلي مع MySQL

🚀 حالة المشروع

مشروع تطوير / Portfolio Project



تم تطوير النظام كتطبيق عملي لإدارة العمليات المالية الخاصة بالشركات، مع التركيز على إدارة الفواتير، متابعة المدفوعات، دعم اللغة العربية، وإدارة دورة الفاتورة بشكل متكامل.

👨‍💻 Developer

Awab Ibrahim Bashry



Software Developer | Web & Flutter Developer | AI Developer



Building modern web, mobile, and AI applications.

📄 License

This project is intended for learning, development, and portfolio purposes.



If you plan to use it commercially, review and define an appropriate license and deployment policy for your use case.