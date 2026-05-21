-- Eventify phpMyAdmin Designer layout
-- Run this in phpMyAdmin only if configuration storage is enabled.
-- It arranges the Designer page for the `registration_event` database.

SET @eventify_db_name = 'registration_event';
SET @eventify_page = 'Eventify ERD';

INSERT INTO phpmyadmin.pma__pdf_pages (db_name, page_descr)
SELECT @eventify_db_name, @eventify_page
WHERE NOT EXISTS (
    SELECT 1
    FROM phpmyadmin.pma__pdf_pages
    WHERE db_name = @eventify_db_name
      AND page_descr = @eventify_page
);

SET @eventify_page_nr = (
    SELECT page_nr
    FROM phpmyadmin.pma__pdf_pages
    WHERE db_name = @eventify_db_name
      AND page_descr = @eventify_page
    ORDER BY page_nr DESC
    LIMIT 1
);

DELETE FROM phpmyadmin.pma__table_coords
WHERE db_name = @eventify_db_name
  AND pdf_page_number = @eventify_page_nr;

INSERT INTO phpmyadmin.pma__table_coords (db_name, table_name, pdf_page_number, x, y) VALUES
-- Users and audit trail.
(@eventify_db_name, 'users', @eventify_page_nr, 60, 420),
(@eventify_db_name, 'notifications', @eventify_page_nr, 60, 720),
(@eventify_db_name, 'activity_logs', @eventify_page_nr, 60, 980),

-- Package and gallery setup.
(@eventify_db_name, 'event_packages', @eventify_page_nr, 460, 40),
(@eventify_db_name, 'package_features', @eventify_page_nr, 840, 40),
(@eventify_db_name, 'event_gallery_photos', @eventify_page_nr, 1220, 40),

-- Main booking flow.
(@eventify_db_name, 'reservations', @eventify_page_nr, 460, 420),
(@eventify_db_name, 'events', @eventify_page_nr, 1220, 420),
(@eventify_db_name, 'event_logs', @eventify_page_nr, 1600, 720),

-- Services and junction tables.
(@eventify_db_name, 'reservation_services', @eventify_page_nr, 840, 420),
(@eventify_db_name, 'event_services', @eventify_page_nr, 1600, 420),
(@eventify_db_name, 'service_options', @eventify_page_nr, 1220, 260),

-- Reservation detail tables.
(@eventify_db_name, 'reservation_items', @eventify_page_nr, 840, 660),
(@eventify_db_name, 'payments', @eventify_page_nr, 460, 920),
(@eventify_db_name, 'reservation_status_history', @eventify_page_nr, 840, 920),

-- Legacy tables still present in older local databases.
(@eventify_db_name, 'packages', @eventify_page_nr, 1600, 40),
(@eventify_db_name, 'services', @eventify_page_nr, 1600, 260);
