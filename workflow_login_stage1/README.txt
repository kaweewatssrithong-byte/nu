Workflow Management System
==========================

Prerequisites
- XAMPP with PHP 8.0+ and MySQL/MariaDB
- PHP extensions pdo_mysql and openssl enabled

Setup
1. Start Apache and MySQL in XAMPP.
2. For a new installation, import database.sql through phpMyAdmin (http://localhost/phpmyadmin). The script creates workflow_system and its roles, departments, users, and tasks tables.
   For this legacy database, run upgrade_workflow.sql once. It adds the missing task category, assignee, assigned department, and evidence fields, carries each task's existing department_id into assigned_department_id, and preserves existing due dates and task records. Then run upgrade_audit_log.sql once to enable administrator access history, and upgrade_notifications.sql once to enable the in-app notification inbox, and upgrade_google_login.sql once to enable secure Google account linking.
3. If your MySQL credentials differ from XAMPP defaults, set DB_HOST, DB_NAME, DB_USER, and DB_PASSWORD in the PHP/Apache environment. Defaults are localhost, workflow_system, root, and an empty password.
4. Create the first administrator from a terminal in this folder. Set ADMIN_INITIAL_PASSWORD to a unique password of at least 12 characters, then run: php create_admin.php [username] [display name]. In PowerShell:
   $env:ADMIN_INITIAL_PASSWORD = 'replace-with-a-unique-password'
   php create_admin.php admin 'System Administrator'
   Remove-Item Env:ADMIN_INITIAL_PASSWORD
5. Open http://localhost/workflow_login_stage1/login.php

The login page verifies password hashes and active account status. The Dashboard uses responsive layouts for mobile and desktop, colored task statuses, friendly empty states, an in-app notification inbox for task assignments and status changes, workload cards, date/category/person filters, a 14-day chart, a staff summary, and recent work. The profile page lets users update their display name/email and securely change their password after confirming the current password. The separate documents page at documents.php searches evidence files and links and only serves uploaded files after checking the signed-in user's task access. All roles can create tasks and edit tasks they are allowed to see. Staff see their own/assigned tasks; Supervisors see tasks in their department; Directors see organization-wide reports; Admins manage users, roles, departments, and access history at admin_logs.php. Audit history records sign-ins, page views, task/account/department actions, and document downloads with timestamp, username, IP address, browser, and limited event details; it never records passwords or submitted form contents. Tasks support optional due dates, evidence links, and evidence uploads (PDF, images, Word, Excel; maximum 10 MB). Reports can be exported as Excel-compatible CSV or printed/saved to PDF in the browser. Logout uses a CSRF-protected POST request.

Troubleshooting
- Check test_db.php to confirm MySQL connection after importing the schema.
- Check the PHP error log if a database query fails.
- Never publish database credentials or the initial administrator password.
- Use HTTPS and set DB credentials through the server environment before exposing the system beyond localhost.

The login page uses the campus image in assets/images/naresuan-campus.png as a local background, so it loads without an image network request. Google Fonts are optional and fall back to installed system fonts.

Google sign-in setup
--------------------
1. Create a Web application OAuth client in Google Cloud Console and configure the OAuth consent screen.
2. Add the exact callback URL as an Authorized redirect URI, for example: http://localhost/workflow_login_stage1/google_login.php?action=callback
3. Set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and GOOGLE_REDIRECT_URI in the web server environment. Keep the client secret private. The redirect URI must match Google Cloud exactly.
4. Import upgrade_google_login.sql into workflow_system. Google sign-in only works when a verified Google email matches exactly one active user account; add/link that email through user management first.

Google OAuth uses OpenID Connect (openid, email, profile), validates the state, nonce, PKCE verifier, token signature, issuer, audience, and expiry, and does not create accounts automatically. Add Google account emails as OAuth test users if the Google consent project requires it. For an internet deployment, use HTTPS and register that deployment's callback URL as well.
