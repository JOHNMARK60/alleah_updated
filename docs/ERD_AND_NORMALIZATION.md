# Eventify ERD and Normalization

## Database

Database name: `registration_event`

Eventify uses a relational database for users, reservations, approved events, packages, payments, notifications, gallery photos, and event records. The implemented design now includes primary keys, foreign keys, and junction tables for multi-value services.

## Main Entities

| Table | Primary Key | Purpose |
| --- | --- | --- |
| `users` | `id` | Stores admin and client accounts. |
| `event_packages` | `id` | Stores package names, prices, and package metadata. |
| `package_features` | `id` | Stores one feature per package. |
| `reservations` | `id` | Stores client reservation requests. |
| `events` | `id` | Stores approved or admin-created events. |
| `service_options` | `id` | Stores allowed services such as Sound, Decoration, and Lights. |
| `reservation_services` | `reservation_id`, `service_id` | Junction table for reservation services. |
| `event_services` | `event_id`, `service_id` | Junction table for approved event services. |
| `payments` | `id` | Stores payment submissions and verification status. |
| `reservation_status_history` | `id` | Stores reservation status changes. |
| `event_logs` | `id` | Stores event records/timeline notes. |
| `notifications` | `id` | Stores admin/client notifications. |
| `activity_logs` | `id` | Stores user activity audit logs. |
| `reservation_items` | `id` | Stores line items for a reservation. |
| `event_gallery_photos` | `id` | Stores gallery images for packages/categories. |

## Relationships

| Relationship | Type |
| --- | --- |
| `users.id` to `reservations.user_id` | One user can have many reservations. |
| `event_packages.id` to `reservations.package_id` | One package can be selected by many reservations. |
| `event_packages.id` to `events.package_id` | One package can be used by many events. |
| `event_packages.id` to `package_features.package_id` | One package can have many features. |
| `reservations.id` to `events.reservation_id` | One approved reservation can become an event. |
| `reservations.id` to `payments.reservation_id` | One reservation can have many payment records. |
| `reservations.id` to `reservation_status_history.reservation_id` | One reservation can have many status history rows. |
| `reservations.id` to `reservation_items.reservation_id` | One reservation can have many item rows. |
| `reservations.id` to `reservation_services.reservation_id` | One reservation can have many selected services. |
| `service_options.id` to `reservation_services.service_id` | One service can be used in many reservations. |
| `events.id` to `event_services.event_id` | One event can have many services. |
| `service_options.id` to `event_services.service_id` | One service can be used in many events. |
| `events.id` to `event_logs.event_id` | One event can have many records/timeline notes. |
| `users.id` to `notifications.user_id` | One user can receive many notifications. |
| `users.id` to `activity_logs.user_id` | One user can create many activity logs. |
| `users.id` to `event_logs.created_by` | One admin/user can create many event records. |

## Normalization

### First Normal Form (1NF)

1NF requires each table cell to hold a single atomic value and each row to be unique.

Applied in Eventify:
- Each table has a primary key.
- Package features are stored one row at a time in `package_features`.
- Services are normalized into `service_options`, `reservation_services`, and `event_services`.
- Payment entries are stored one payment per row in `payments`.
- Event records are stored one record per row in `event_logs`.

Note: `reservations.services` and `events.services` remain as display snapshots for backward compatibility with existing pages. The normalized source for ERD purposes is the junction-table design.

### Second Normal Form (2NF)

2NF requires all non-key attributes to depend on the whole primary key.

Applied in Eventify:
- Tables with single-column primary keys store attributes that depend on that row only.
- Junction tables `reservation_services` and `event_services` use composite keys, and their rows depend on the full pair of IDs.
- Package feature text depends on `package_features.id`, while its package ownership is represented by `package_id`.
- Payment amount, method, reference number, and status depend on the payment row, not on a partial key.

### Third Normal Form (3NF)

3NF requires non-key attributes to depend only on the key and not on another non-key attribute.

Applied in Eventify:
- User details are stored in `users`, while reservations reference users through `user_id`.
- Package details are stored in `event_packages`, while reservations and events reference packages through `package_id`.
- Services are stored in `service_options`, while service selections are stored in junction tables.
- Payment data is separate from reservation data in `payments`.
- Event timeline data is separate from event data in `event_logs`.
- Status history is separate from the current reservation status in `reservation_status_history`.

Controlled snapshot fields:
- `reservations.package_type`, `events.package_type`, `reservations.budget`, and `events.budget` preserve the package name and price at booking time.
- This is intentional historical/audit data, because package names and prices may change later.

## phpMyAdmin ERD Arrangement

Open phpMyAdmin:

1. Select the `registration_event` database.
2. Open **Designer**.
3. If foreign-key lines do not appear, open the app once in the browser so `config/db.php` runs and applies schema upgrades.
4. To arrange the diagram automatically, run `database/phpmyadmin_designer_layout.sql` in phpMyAdmin if phpMyAdmin configuration storage is enabled.

Recommended manual layout:

- Left: `users`, `notifications`, `activity_logs`
- Center: `reservations`
- Right: `events`, `event_logs`
- Top: `event_packages`, `package_features`
- Bottom: `payments`, `reservation_status_history`, `reservation_items`
- Service junctions between reservation/event and `service_options`
