<?php
require_once __DIR__ . '/helpers.php';

$host = "localhost";
$username = "root";
$password = "";
$database = "registration_event";

if(function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

try {
    $conn = new mysqli($host, $username, $password);
    $conn->set_charset("utf8mb4");

    // Create the project database automatically when it is missing.
    $conn->query("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $conn->select_db($database);

    // Users table for login and registration.
    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            username VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            contact VARCHAR(30) NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'client',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Client reservation requests. Keep `guest` singular because the existing PHP uses that column.
    $conn->query("
        CREATE TABLE IF NOT EXISTS reservations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            booking_reference VARCHAR(32) DEFAULT NULL,
            event_name VARCHAR(150) NOT NULL,
            event_type VARCHAR(80) DEFAULT NULL,
            event_date DATE NOT NULL,
            event_time TIME NOT NULL,
            end_time TIME DEFAULT NULL,
            venue VARCHAR(150) DEFAULT NULL,
            guest INT DEFAULT 0,
            client_name VARCHAR(120) NOT NULL,
            client_contact VARCHAR(50) DEFAULT NULL,
            package_id INT DEFAULT NULL,
            package_type VARCHAR(50) DEFAULT NULL,
            budget DECIMAL(10,2) DEFAULT 0.00,
            services TEXT DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Pending',
            approved_at DATETIME DEFAULT NULL,
            rejected_at DATETIME DEFAULT NULL,
            cancelled_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Approved/admin-created events. Keep `guests` plural because the existing admin PHP uses that column.
    $conn->query("
        CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reservation_id INT DEFAULT NULL,
            event_name VARCHAR(150) NOT NULL,
            event_type VARCHAR(80) DEFAULT NULL,
            event_date DATE NOT NULL,
            event_time TIME NOT NULL,
            end_time TIME DEFAULT NULL,
            venue VARCHAR(150) DEFAULT NULL,
            guests INT DEFAULT 0,
            client_name VARCHAR(120) NOT NULL,
            client_contact VARCHAR(50) DEFAULT NULL,
            package_id INT DEFAULT NULL,
            package_type VARCHAR(50) DEFAULT NULL,
            budget DECIMAL(10,2) DEFAULT 0.00,
            services TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            role ENUM('admin','client') NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS admin_client_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT DEFAULT NULL,
            client_id INT DEFAULT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            read_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_client_messages_admin_id (admin_id),
            INDEX idx_admin_client_messages_client_id (client_id),
            INDEX idx_admin_client_messages_read_at (read_at),
            INDEX idx_admin_client_messages_created_at (created_at)
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS event_packages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(140) NOT NULL UNIQUE,
            description TEXT DEFAULT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            image_path VARCHAR(255) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            deleted_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS package_features (
            id INT AUTO_INCREMENT PRIMARY KEY,
            package_id INT NOT NULL,
            feature_text VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_package_features_package_id (package_id),
            CONSTRAINT fk_package_features_package
                FOREIGN KEY (package_id) REFERENCES event_packages(id)
                ON DELETE CASCADE
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS event_gallery_photos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_category VARCHAR(40) NOT NULL,
            package_tier VARCHAR(40) NOT NULL,
            title VARCHAR(150) NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_gallery_package (event_category, package_tier),
            INDEX idx_gallery_active (is_active)
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS service_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(80) NOT NULL UNIQUE,
            description VARCHAR(255) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS reservation_services (
            reservation_id INT NOT NULL,
            service_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (reservation_id, service_id),
            INDEX idx_reservation_services_service_id (service_id),
            CONSTRAINT fk_reservation_services_reservation
                FOREIGN KEY (reservation_id) REFERENCES reservations(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_reservation_services_service
                FOREIGN KEY (service_id) REFERENCES service_options(id)
                ON DELETE RESTRICT
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS event_services (
            event_id INT NOT NULL,
            service_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (event_id, service_id),
            INDEX idx_event_services_service_id (service_id),
            CONSTRAINT fk_event_services_event
                FOREIGN KEY (event_id) REFERENCES events(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_event_services_service
                FOREIGN KEY (service_id) REFERENCES service_options(id)
                ON DELETE RESTRICT
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS reservation_status_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reservation_id INT NOT NULL,
            old_status VARCHAR(30) DEFAULT NULL,
            new_status VARCHAR(30) NOT NULL,
            note TEXT DEFAULT NULL,
            changed_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reservation_status_history_reservation_id (reservation_id)
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS reservation_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reservation_id INT NOT NULL,
            package_id INT DEFAULT NULL,
            item_name VARCHAR(150) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reservation_items_reservation_id (reservation_id)
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reservation_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            method VARCHAR(60) DEFAULT NULL,
            reference_number VARCHAR(120) DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Unpaid',
            paid_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_payments_reservation_id (reservation_id),
            INDEX idx_payments_status (status)
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS event_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reservation_id INT DEFAULT NULL,
            event_id INT DEFAULT NULL,
            log_type VARCHAR(80) NOT NULL,
            message TEXT NOT NULL,
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event_logs_reservation_id (reservation_id),
            INDEX idx_event_logs_event_id (event_id)
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            role VARCHAR(30) DEFAULT NULL,
            action VARCHAR(120) NOT NULL,
            details TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_activity_logs_user_id (user_id),
            INDEX idx_activity_logs_action (action)
        )
    ");

    upgrade_eventify_schema($conn);
    seed_default_admin($conn);
    seed_default_packages($conn);
    sync_default_package_prices($conn);
    seed_default_services($conn);
    sync_normalized_services($conn);
} catch (mysqli_sql_exception $error) {
    die("Database setup failed: " . $error->getMessage());
}

function upgrade_eventify_schema($conn) {
    eventify_ensure_column($conn, 'reservations', 'user_id', 'INT DEFAULT NULL AFTER id');
    eventify_ensure_column($conn, 'reservations', 'booking_reference', 'VARCHAR(32) DEFAULT NULL AFTER user_id');
    eventify_ensure_column($conn, 'reservations', 'end_time', 'TIME DEFAULT NULL AFTER event_time');
    eventify_ensure_column($conn, 'reservations', 'package_id', 'INT DEFAULT NULL AFTER client_contact');
    eventify_ensure_column($conn, 'reservations', 'approved_at', 'DATETIME DEFAULT NULL AFTER status');
    eventify_ensure_column($conn, 'reservations', 'rejected_at', 'DATETIME DEFAULT NULL AFTER approved_at');
    eventify_ensure_column($conn, 'reservations', 'cancelled_at', 'DATETIME DEFAULT NULL AFTER rejected_at');
    eventify_ensure_column($conn, 'reservations', 'updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');

    eventify_ensure_column($conn, 'events', 'reservation_id', 'INT DEFAULT NULL AFTER id');
    eventify_ensure_column($conn, 'events', 'end_time', 'TIME DEFAULT NULL AFTER event_time');
    eventify_ensure_column($conn, 'events', 'package_id', 'INT DEFAULT NULL AFTER client_contact');
    eventify_ensure_varchar_length($conn, 'reservations', 'package_type', 120);
    eventify_ensure_varchar_length($conn, 'events', 'package_type', 120);
    eventify_ensure_column($conn, 'admin_client_messages', 'read_at', 'DATETIME DEFAULT NULL AFTER message');

    eventify_ensure_column_index($conn, 'users', 'email', 'idx_users_email');
    eventify_ensure_column_index($conn, 'users', 'role', 'idx_users_role');
    eventify_ensure_column_index($conn, 'reservations', 'booking_reference', 'idx_reservations_booking_reference');
    eventify_ensure_column_index($conn, 'reservations', 'package_id', 'idx_reservations_package_id');
    eventify_ensure_column_index($conn, 'reservations', 'status', 'idx_reservations_status');
    eventify_ensure_column_index($conn, 'reservations', 'event_date', 'idx_reservations_event_date');
    eventify_ensure_column_index($conn, 'reservations', 'user_id', 'idx_reservations_user_id');
    eventify_ensure_column_index($conn, 'events', 'reservation_id', 'idx_events_reservation_id');
    eventify_ensure_column_index($conn, 'events', 'package_id', 'idx_events_package_id');
    eventify_ensure_column_index($conn, 'events', 'event_date', 'idx_events_event_date');
    eventify_ensure_column_index($conn, 'notifications', 'user_id', 'idx_notifications_user_id');
    eventify_ensure_column_index($conn, 'notifications', 'role', 'idx_notifications_role');
    eventify_ensure_column_index($conn, 'notifications', 'is_read', 'idx_notifications_is_read');
    eventify_ensure_column_index($conn, 'admin_client_messages', 'admin_id', 'idx_admin_client_messages_admin_id');
    eventify_ensure_column_index($conn, 'admin_client_messages', 'client_id', 'idx_admin_client_messages_client_id');
    eventify_ensure_column_index($conn, 'admin_client_messages', 'read_at', 'idx_admin_client_messages_read_at');
    eventify_ensure_column_index($conn, 'admin_client_messages', 'created_at', 'idx_admin_client_messages_created_at');
    eventify_ensure_column_index($conn, 'event_gallery_photos', 'is_active', 'idx_gallery_active');
    eventify_ensure_column_index($conn, 'reservation_status_history', 'changed_by', 'idx_status_history_changed_by');
    eventify_ensure_column_index($conn, 'reservation_items', 'package_id', 'idx_reservation_items_package_id');
    eventify_ensure_column_index($conn, 'event_logs', 'created_by', 'idx_event_logs_created_by');
    eventify_ensure_column_index($conn, 'activity_logs', 'user_id', 'idx_activity_logs_user_id');

    prepare_eventify_foreign_key_data($conn);

    eventify_ensure_foreign_key($conn, 'reservations', 'fk_reservations_user', 'FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL');
    eventify_ensure_foreign_key($conn, 'reservations', 'fk_reservations_package', 'FOREIGN KEY (`package_id`) REFERENCES `event_packages`(`id`) ON DELETE SET NULL');
    eventify_ensure_foreign_key($conn, 'events', 'fk_events_reservation', 'FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`id`) ON DELETE SET NULL');
    eventify_ensure_foreign_key($conn, 'events', 'fk_events_package', 'FOREIGN KEY (`package_id`) REFERENCES `event_packages`(`id`) ON DELETE SET NULL');
    eventify_ensure_foreign_key($conn, 'notifications', 'fk_notifications_user', 'FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL');
    eventify_ensure_foreign_key($conn, 'admin_client_messages', 'fk_admin_client_messages_admin', 'FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE SET NULL');
    eventify_ensure_foreign_key($conn, 'admin_client_messages', 'fk_admin_client_messages_client', 'FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE SET NULL');
    eventify_ensure_foreign_key($conn, 'reservation_status_history', 'fk_status_history_reservation', 'FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`id`) ON DELETE CASCADE');
    eventify_ensure_foreign_key($conn, 'reservation_status_history', 'fk_status_history_changed_by', 'FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL');
    eventify_ensure_foreign_key($conn, 'reservation_items', 'fk_reservation_items_reservation', 'FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`id`) ON DELETE CASCADE');
    eventify_ensure_foreign_key($conn, 'reservation_items', 'fk_reservation_items_package', 'FOREIGN KEY (`package_id`) REFERENCES `event_packages`(`id`) ON DELETE SET NULL');
    eventify_ensure_foreign_key($conn, 'payments', 'fk_payments_reservation', 'FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`id`) ON DELETE CASCADE');
    eventify_ensure_foreign_key($conn, 'event_logs', 'fk_event_logs_reservation', 'FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`id`) ON DELETE SET NULL');
    eventify_ensure_foreign_key($conn, 'event_logs', 'fk_event_logs_event', 'FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE SET NULL');
    eventify_ensure_foreign_key($conn, 'event_logs', 'fk_event_logs_created_by', 'FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL');
    eventify_ensure_foreign_key($conn, 'activity_logs', 'fk_activity_logs_user', 'FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL');
    eventify_ensure_foreign_key($conn, 'reservation_services', 'fk_reservation_services_reservation', 'FOREIGN KEY (`reservation_id`) REFERENCES `reservations`(`id`) ON DELETE CASCADE');
    eventify_ensure_foreign_key($conn, 'reservation_services', 'fk_reservation_services_service', 'FOREIGN KEY (`service_id`) REFERENCES `service_options`(`id`) ON DELETE RESTRICT');
    eventify_ensure_foreign_key($conn, 'event_services', 'fk_event_services_event', 'FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE');
    eventify_ensure_foreign_key($conn, 'event_services', 'fk_event_services_service', 'FOREIGN KEY (`service_id`) REFERENCES `service_options`(`id`) ON DELETE RESTRICT');
}

function prepare_eventify_foreign_key_data($conn) {
    $conn->query("UPDATE reservations r LEFT JOIN users u ON u.id = r.user_id SET r.user_id = NULL WHERE r.user_id IS NOT NULL AND u.id IS NULL");
    $conn->query("UPDATE reservations r LEFT JOIN event_packages p ON p.id = r.package_id SET r.package_id = NULL WHERE r.package_id IS NOT NULL AND p.id IS NULL");
    $conn->query("UPDATE events e LEFT JOIN reservations r ON r.id = e.reservation_id SET e.reservation_id = NULL WHERE e.reservation_id IS NOT NULL AND r.id IS NULL");
    $conn->query("UPDATE events e LEFT JOIN event_packages p ON p.id = e.package_id SET e.package_id = NULL WHERE e.package_id IS NOT NULL AND p.id IS NULL");
    $conn->query("UPDATE notifications n LEFT JOIN users u ON u.id = n.user_id SET n.user_id = NULL WHERE n.user_id IS NOT NULL AND u.id IS NULL");
    $conn->query("UPDATE admin_client_messages m LEFT JOIN users u ON u.id = m.admin_id SET m.admin_id = NULL WHERE m.admin_id IS NOT NULL AND u.id IS NULL");
    $conn->query("UPDATE admin_client_messages m LEFT JOIN users u ON u.id = m.client_id SET m.client_id = NULL WHERE m.client_id IS NOT NULL AND u.id IS NULL");
    $conn->query("DELETE h FROM reservation_status_history h LEFT JOIN reservations r ON r.id = h.reservation_id WHERE r.id IS NULL");
    $conn->query("UPDATE reservation_status_history h LEFT JOIN users u ON u.id = h.changed_by SET h.changed_by = NULL WHERE h.changed_by IS NOT NULL AND u.id IS NULL");
    $conn->query("DELETE i FROM reservation_items i LEFT JOIN reservations r ON r.id = i.reservation_id WHERE r.id IS NULL");
    $conn->query("UPDATE reservation_items i LEFT JOIN event_packages p ON p.id = i.package_id SET i.package_id = NULL WHERE i.package_id IS NOT NULL AND p.id IS NULL");
    $conn->query("DELETE p FROM payments p LEFT JOIN reservations r ON r.id = p.reservation_id WHERE r.id IS NULL");
    $conn->query("UPDATE event_logs l LEFT JOIN reservations r ON r.id = l.reservation_id SET l.reservation_id = NULL WHERE l.reservation_id IS NOT NULL AND r.id IS NULL");
    $conn->query("UPDATE event_logs l LEFT JOIN events e ON e.id = l.event_id SET l.event_id = NULL WHERE l.event_id IS NOT NULL AND e.id IS NULL");
    $conn->query("UPDATE event_logs l LEFT JOIN users u ON u.id = l.created_by SET l.created_by = NULL WHERE l.created_by IS NOT NULL AND u.id IS NULL");
    $conn->query("UPDATE activity_logs a LEFT JOIN users u ON u.id = a.user_id SET a.user_id = NULL WHERE a.user_id IS NOT NULL AND u.id IS NULL");
}

function seed_default_admin($conn) {
    $result = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role='admin'");
    $admin_count = $result->fetch_assoc()['total'];

    if((int) $admin_count > 0) {
        return;
    }

    $name = "System Admin";
    $username = "admin";
    $email = "admin@eventify.com";
    $contact = "0000000000";
    $role = "admin";
    $hashed = password_hash("admin123", PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users(name, username, email, contact, password, role) VALUES(?,?,?,?,?,?)");
    $stmt->bind_param("ssssss", $name, $username, $email, $contact, $hashed, $role);
    $stmt->execute();
}

function seed_default_packages($conn) {
    $result = $conn->query("SELECT COUNT(*) AS total FROM event_packages WHERE deleted_at IS NULL");
    $package_count = (int) $result->fetch_assoc()['total'];

    if ($package_count > 0) {
        return;
    }

    $packages = [
        [
            'name' => 'Basic',
            'slug' => 'basic',
            'description' => 'Simple decoration, good for small events.',
            'price' => 5000.00,
            'sort_order' => 1,
            'features' => ['Simple decoration', 'Good for small events', 'Basic event setup'],
        ],
        [
            'name' => 'Standard',
            'slug' => 'standard',
            'description' => 'Enhanced decoration with sound system.',
            'price' => 10000.00,
            'sort_order' => 2,
            'features' => ['Enhanced decoration', 'Includes sound system', 'Standard event setup'],
        ],
        [
            'name' => 'Premium',
            'slug' => 'premium',
            'description' => 'Full decoration setup with photo and video package.',
            'price' => 20000.00,
            'sort_order' => 3,
            'features' => ['Full decoration setup', 'Photo and video package', 'Premium event setup'],
        ],
    ];

    $stmt = $conn->prepare("
        INSERT INTO event_packages (name, slug, description, price, is_active, sort_order)
        VALUES (?, ?, ?, ?, 1, ?)
    ");
    $feature_stmt = $conn->prepare("
        INSERT INTO package_features (package_id, feature_text, sort_order)
        VALUES (?, ?, ?)
    ");

    foreach ($packages as $package) {
        $stmt->bind_param(
            "sssdi",
            $package['name'],
            $package['slug'],
            $package['description'],
            $package['price'],
            $package['sort_order']
        );
        $stmt->execute();
        $package_id = $conn->insert_id;

        foreach ($package['features'] as $index => $feature) {
            $order = $index + 1;
            $feature_stmt->bind_param("isi", $package_id, $feature, $order);
            $feature_stmt->execute();
        }
    }
}

function sync_default_package_prices($conn) {
    $packages = [
        'basic' => [
            'description' => 'Simple decoration, good for small events.',
            'price' => 5000.00,
            'features' => ['Simple decoration', 'Good for small events', 'Basic event setup'],
        ],
        'standard' => [
            'description' => 'Enhanced decoration with sound system.',
            'price' => 10000.00,
            'features' => ['Enhanced decoration', 'Includes sound system', 'Standard event setup'],
        ],
        'premium' => [
            'description' => 'Full decoration setup with photo and video package.',
            'price' => 20000.00,
            'features' => ['Full decoration setup', 'Photo and video package', 'Premium event setup'],
        ],
    ];

    $select_stmt = $conn->prepare("SELECT id FROM event_packages WHERE slug=? AND deleted_at IS NULL LIMIT 1");
    $update_stmt = $conn->prepare("UPDATE event_packages SET description=?, price=? WHERE id=?");
    $delete_stmt = $conn->prepare("DELETE FROM package_features WHERE package_id=?");
    $feature_stmt = $conn->prepare("INSERT INTO package_features (package_id, feature_text, sort_order) VALUES (?, ?, ?)");

    foreach ($packages as $slug => $package) {
        $select_stmt->bind_param("s", $slug);
        $select_stmt->execute();
        $row = $select_stmt->get_result()->fetch_assoc();

        if (!$row) {
            continue;
        }

        $package_id = (int) $row['id'];
        $update_stmt->bind_param("sdi", $package['description'], $package['price'], $package_id);
        $update_stmt->execute();

        $delete_stmt->bind_param("i", $package_id);
        $delete_stmt->execute();

        foreach ($package['features'] as $index => $feature) {
            $order = $index + 1;
            $feature_stmt->bind_param("isi", $package_id, $feature, $order);
            $feature_stmt->execute();
        }
    }
}

function seed_default_services($conn) {
    $services = [
        ['name' => 'Sound', 'description' => 'Sound system and audio support.', 'sort_order' => 1],
        ['name' => 'Decoration', 'description' => 'Venue decoration and styling.', 'sort_order' => 2],
        ['name' => 'Lights', 'description' => 'Lighting setup and effects.', 'sort_order' => 3],
    ];

    $stmt = $conn->prepare("
        INSERT INTO service_options (name, description, sort_order)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            description = VALUES(description),
            sort_order = VALUES(sort_order),
            is_active = 1
    ");

    foreach ($services as $service) {
        $stmt->bind_param("ssi", $service['name'], $service['description'], $service['sort_order']);
        $stmt->execute();
    }
}

function sync_normalized_services($conn) {
    $reservation_stmt = $conn->prepare("
        INSERT IGNORE INTO reservation_services (reservation_id, service_id)
        VALUES (?, ?)
    ");
    $result = $conn->query("SELECT id, services FROM reservations WHERE services IS NOT NULL AND services<>''");

    while ($row = $result->fetch_assoc()) {
        $reservation_id = (int) $row['id'];

        foreach (eventify_service_names_from_value($row['services']) as $service_name) {
            $service_id = eventify_service_option_id($conn, $service_name);
            if ($service_id > 0) {
                $reservation_stmt->bind_param("ii", $reservation_id, $service_id);
                $reservation_stmt->execute();
            }
        }
    }

    $event_stmt = $conn->prepare("
        INSERT IGNORE INTO event_services (event_id, service_id)
        VALUES (?, ?)
    ");
    $result = $conn->query("SELECT id, services FROM events WHERE services IS NOT NULL AND services<>''");

    while ($row = $result->fetch_assoc()) {
        $event_id = (int) $row['id'];

        foreach (eventify_service_names_from_value($row['services']) as $service_name) {
            $service_id = eventify_service_option_id($conn, $service_name);
            if ($service_id > 0) {
                $event_stmt->bind_param("ii", $event_id, $service_id);
                $event_stmt->execute();
            }
        }
    }
}
?>
