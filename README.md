# Eventify - Event Reservation and Management System

Eventify is a PHP and MySQL event management system for event package browsing, client reservations, admin approval, payment tracking, gallery management, calendars, and event records.

## OOP Principles Applied

Eventify follows Object-Oriented Programming principles in a practical PHP project style. The system is organized around real-world objects such as users, reservations, events, packages, payments, notifications, gallery photos, and event records. Each part of the system has its own responsibility, and shared behavior is placed in reusable helper modules so the code is easier to understand, maintain, and extend.

The system applies these OOP principles:

- **Encapsulation** - Important operations are grouped into focused helper functions in `config/helpers.php`. Validation, CSRF protection, package handling, payment summaries, notifications, activity logs, and availability checking are handled in one shared place instead of being scattered everywhere.
- **Abstraction** - Complex work is hidden behind simple function calls. For example, pages can call `eventify_slot_available()`, `eventify_create_notification()`, or `eventify_reservation_payment_summary()` without needing to repeat all the SQL and business rules each time.
- **Modularity** - The project is separated into clear modules: `admin`, `client`, `auth`, `homepage`, `config`, `database`, `docs`, and `uploads`. This makes the system easier to navigate because each folder has a clear purpose.
- **Single Responsibility** - Each page focuses on one main job. Reservation pages handle booking, payment pages handle payment review, gallery pages handle gallery photos, package pages handle packages, and calendar pages handle event schedules.
- **Data Modeling** - The database represents the main objects of the system through related tables such as `users`, `reservations`, `events`, `event_packages`, `payments`, `notifications`, and `event_gallery_photos`.
- **Reusability** - Common system behavior is reused across admin and client pages. This keeps the project cleaner and avoids repeating the same validation, notification, payment, and security logic.
- **Maintainability** - Because features are organized by role and purpose, future developers can improve one area of the system without needing to rewrite the whole project.

In short, Eventify uses OOP principles to keep the system organized around clear responsibilities, reusable logic, and real entities from the event management process.

## Technology Used

- PHP
- MySQL / MariaDB
- MySQLi
- HTML
- CSS
- JavaScript
- Tailwind CSS CDN
- SweetAlert2 CDN
- Laragon local server

## Main Folders

| Folder/File | Purpose |
| --- | --- |
| `index.php` | Redirects users to the homepage. |
| `homepage/` | Public landing page, package showcase, gallery, login/register links, images, videos, CSS, and JavaScript. |
| `auth/` | Login, registration, logout, and authentication UI assets. |
| `client/` | Client dashboard, reservations, calendar, payments, notifications, and reservation editing. |
| `admin/` | Admin dashboard, reservation approval, package management, gallery management, payments, users, calendar, event records, and manual event creation. |
| `config/db.php` | Database connection, automatic database/table creation, schema upgrades, default seed data, and foreign keys. |
| `config/helpers.php` | Shared helper functions for validation, CSRF, packages, services, notifications, payments, activity logs, and conflict checking. |
| `uploads/gallery/` | Uploaded gallery photos used by the homepage gallery feature. |
| `uploads/packages/` | Uploaded package images used by admin-managed packages. |
| `database/` | phpMyAdmin Designer layout SQL. |
| `docs/` | ERD and normalization documentation. |

## User Roles

### Public Visitor

Public visitors can:

- View the Eventify homepage.
- Browse event packages.
- View package/gallery previews.
- Open the login and registration pages.

### Client

Clients can:

- Register an account.
- Log in using email and password.
- Access the client dashboard.
- Create reservation requests.
- Select package, date, time, venue, guest count, and services.
- Check venue/time availability.
- View their own reservations only.
- Edit pending reservations.
- Cancel reservations.
- View reservation status.
- Submit payments after reservation approval.
- View payment status and balance.
- Receive notifications from admin actions.
- View client calendar and approved events.

### Admin

Admins can:

- Log in to the admin dashboard.
- View reservation/user/payment summary cards.
- Search and filter reservations.
- Approve or reject pending reservations.
- Convert approved reservations into event records.
- Prevent overlapping approved events for the same venue and time.
- Manage packages and package features.
- Upload package images.
- Upload, show/hide, and delete gallery photos.
- View users.
- View admin calendar.
- Add manual events.
- Review, verify, or reject client payment submissions.
- Add event records/timeline notes.
- Receive notifications when clients submit reservations or payments.

## System Workflow

### 1. Account Registration

1. A visitor opens `auth/register.php`.
2. The system validates the name, username, email, contact number, password, and password confirmation.
3. The password is hashed using `password_hash()`.
4. A new user record is saved in the `users` table with the default `client` role.
5. The user is redirected to login.

### 2. Login

1. A user opens `auth/login.php`.
2. The system checks the submitted email.
3. The password is verified using `password_verify()`.
4. The system stores `user_id` and `role` in the session.
5. Admin users go to `admin/dashboard.php`.
6. Client users go to `client/dashboard.php`.

### 3. Client Reservation

1. A client opens `client/reservation.php`.
2. The client enters event details, date, time, venue, package, guest count, and services.
3. The system validates the data.
4. The system checks if the selected venue/time is available.
5. A booking reference is generated.
6. A reservation is saved with `Pending` status.
7. Selected services are stored in `reservation_services`.
8. Admin receives a notification.
9. Reservation status history and activity logs are saved.

### 4. Reservation Approval

1. Admin opens `admin/reservations.php`.
2. Admin reviews pending reservations.
3. If approved, the reservation is copied into the `events` table.
4. Event services are stored in `event_services`.
5. Reservation status changes to `Approved`.
6. Client receives an approval notification.
7. The approved event appears on admin/client calendars.

If rejected, the reservation status changes to `Rejected`, and the client receives a notification.

### 5. Payment Flow

1. After approval, the client can submit payment in `client/my_reservations.php`.
2. Client enters amount, method, and reference number.
3. Payment is saved as `For Review`.
4. Admin opens `admin/payments.php`.
5. Admin verifies or rejects the payment.
6. Verified payments count toward the reservation paid amount.
7. The client sees payment status, verified amount, and remaining balance.

### 6. Package Management

1. Admin opens `admin/packages.php`.
2. Admin can create, edit, disable, or delete packages.
3. Each package has name, slug, description, price, image, active status, sort order, and features.
4. Package features are stored one row at a time in `package_features`.
5. Active packages appear in client reservation forms.

### 7. Gallery Management

1. Admin opens `admin/gallery.php`.
2. Admin uploads JPG, PNG, or WebP photos.
3. Photos are grouped by event category and package tier.
4. Admin can show/hide or delete gallery photos.
5. Active photos appear in the homepage gallery/lightbox.
6. Uploaded images are stored in `uploads/gallery/`.

### 8. Event Records

1. Admin opens `admin/event_records.php`.
2. Admin selects an approved event.
3. Admin adds records such as planning notes, setup notes, payment notes, completion notes, issues, and follow-ups.
4. Records are stored in `event_logs`.
5. The event timeline can be reviewed later.

### 9. Notifications

The system stores notifications in the `notifications` table.

Notifications are created for:

- New client reservation requests.
- Reservation approval.
- Reservation rejection.
- Payment submission.
- Payment verification.
- Payment rejection.

Users can mark notifications as read from the notification widget.

### 10. Calendars

The system includes client and admin calendars.

- Admin calendar shows approved/admin-created events.
- Client calendar shows approved events and availability context.
- Calendar data is generated from the `events` table.

## Database Features

The database name is:

```text
registration_event
```

`config/db.php` automatically:

- Connects to MySQL.
- Creates the `registration_event` database if missing.
- Creates required tables if missing.
- Adds missing columns during schema upgrade.
- Adds indexes.
- Adds foreign keys.
- Seeds a default admin account.
- Seeds default packages.
- Seeds default service options.
- Syncs normalized service records.

Main tables:

- `users`
- `reservations`
- `events`
- `event_packages`
- `package_features`
- `event_gallery_photos`
- `service_options`
- `reservation_services`
- `event_services`
- `payments`
- `notifications`
- `reservation_status_history`
- `reservation_items`
- `event_logs`
- `activity_logs`

Legacy local tables may also exist:

- `packages`
- `services`

## ERD and Normalization

ERD and normalization notes are in:

```text
docs/ERD_AND_NORMALIZATION.md
docs/eventify_erd.mmd
```

The phpMyAdmin Designer layout SQL is in:

```text
database/phpmyadmin_designer_layout.sql
```

To arrange the ERD in phpMyAdmin:

1. Open phpMyAdmin.
2. Select the `registration_event` database.
3. Open Designer.
4. Run `database/phpmyadmin_designer_layout.sql` if phpMyAdmin configuration storage is enabled.
5. Refresh Designer.

## Security Features

- Passwords are hashed.
- Login verifies hashed passwords.
- Admin/client pages use role checks.
- Client reservation pages check user ownership.
- Important POST forms use CSRF tokens.
- Several SQL operations use prepared statements.
- Reservation approval checks for event conflicts.
- Payment verification only applies to payments with `For Review` status.

Security notes before deployment:

- Change or remove the default admin credentials.
- Use a real database password.
- Configure HTTPS.
- Configure a real mail sender if email notifications are required.
- Review all forms and pages before public hosting.

## Default Admin Account

The system creates a default admin account if no admin exists:

```text
Email: admin@eventify.com
Password: admin123
```

Change this before deployment.

## How To Run Locally

1. Place the project folder inside Laragon `www`.
2. Start Apache and MySQL in Laragon.
3. Open the project in the browser:

```text
http://localhost/alleah11/
```

or use the Laragon pretty URL if configured:

```text
http://alleah11.test/
```

4. The database and tables will be created automatically when the app loads.
5. Log in as admin or register a client account.

## Important Pages

| Page | Purpose |
| --- | --- |
| `homepage/home.php` | Public homepage. |
| `auth/register.php` | Client registration. |
| `auth/login.php` | Login for admin/client. |
| `client/dashboard.php` | Client dashboard. |
| `client/reservation.php` | Create reservation. |
| `client/my_reservations.php` | View reservations and submit payments. |
| `client/edit_reservation.php` | Edit pending reservation. |
| `client/calendar.php` | Client calendar. |
| `admin/dashboard.php` | Admin dashboard. |
| `admin/reservations.php` | Approve/reject reservations. |
| `admin/payments.php` | Verify/reject payments. |
| `admin/packages.php` | Manage event packages. |
| `admin/gallery.php` | Manage homepage gallery photos. |
| `admin/users.php` | View users. |
| `admin/calendar.php` | Admin calendar. |
| `admin/add_event.php` | Add manual event. |
| `admin/event_records.php` | Manage event timeline records. |

## Current Architecture Summary

Eventify is built as a complete event reservation and management system with OOP principles applied through modular folders, reusable helper functions, clear data entities, role-based pages, and a normalized relational database.

The system is easy to explain because each feature has a natural place: clients create and manage reservations, admins approve events and manage records, payments are reviewed separately, packages can be updated, and gallery photos can be controlled from the admin side. The result is a project that feels organized, understandable, and ready for future improvements.
