<?php
if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function eventify_slugify($value) {
    $slug = strtolower(trim((string) $value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'package-' . bin2hex(random_bytes(3));
}

function eventify_generate_booking_reference($conn) {
    do {
        $reference = 'EVT-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $conn->prepare("SELECT id FROM reservations WHERE booking_reference=? LIMIT 1");
        $stmt->bind_param("s", $reference);
        $stmt->execute();
        $exists = (bool) $stmt->get_result()->fetch_assoc();
    } while ($exists);

    return $reference;
}

function eventify_get_active_packages($conn) {
    $result = $conn->query("
        SELECT *
        FROM event_packages
        WHERE is_active=1 AND deleted_at IS NULL
        ORDER BY sort_order ASC, name ASC
    ");

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function eventify_get_packages($conn, $include_inactive = true) {
    $where = $include_inactive ? "deleted_at IS NULL" : "is_active=1 AND deleted_at IS NULL";
    $result = $conn->query("
        SELECT p.*,
               COUNT(f.id) AS feature_count
        FROM event_packages p
        LEFT JOIN package_features f ON f.package_id = p.id
        WHERE $where
        GROUP BY p.id
        ORDER BY p.sort_order ASC, p.name ASC
    ");

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function eventify_get_package($conn, $package_id, $active_only = false) {
    $package_id = (int) $package_id;
    $sql = "
        SELECT *
        FROM event_packages
        WHERE id=? AND deleted_at IS NULL
    ";

    if ($active_only) {
        $sql .= " AND is_active=1";
    }

    $sql .= " LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $package_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
}

function eventify_get_package_features($conn, $package_id) {
    $stmt = $conn->prepare("
        SELECT *
        FROM package_features
        WHERE package_id=?
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->bind_param("i", $package_id);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function eventify_package_budget($conn, $package_id) {
    $package = eventify_get_package($conn, $package_id, true);

    return $package ? (float) $package['price'] : null;
}

function eventify_package_name($conn, $package_id) {
    $package = eventify_get_package($conn, $package_id);

    return $package ? $package['name'] : '';
}

function eventify_package_options_html($conn, $selected_id = 0) {
    $packages = eventify_get_active_packages($conn);
    $html = '<option value="">Select Package</option>';

    foreach ($packages as $package) {
        $selected = (int) $selected_id === (int) $package['id'] ? ' selected' : '';
        $html .= '<option value="' . (int) $package['id'] . '"' . $selected . '>';
        $html .= htmlspecialchars($package['name']) . ' - PHP ' . number_format((float) $package['price'], 2);
        $html .= '</option>';
    }

    return $html;
}

function eventify_marketing_package_cards() {
    return [
        ['event' => 'Wedding', 'tier' => 'Basic', 'price' => '5,000', 'image' => '../homepage/assets/images/wedding-basic.jpg', 'description' => 'Simple decoration for intimate wedding celebrations and small events.', 'features' => ['Simple decoration', 'Good for small events', 'Basic event setup'], 'popular' => false],
        ['event' => 'Wedding', 'tier' => 'Standard', 'price' => '10,000', 'image' => '../homepage/assets/images/wedding-standard.jpg', 'description' => 'Enhanced decoration for polished wedding events with stronger production support.', 'features' => ['Enhanced decoration', 'Includes sound system', 'Standard event setup'], 'popular' => false],
        ['event' => 'Wedding', 'tier' => 'Premium', 'price' => '20,000', 'image' => '../homepage/assets/images/wedding-premium.jpg', 'description' => 'Full decoration setup for elegant wedding celebrations with complete media coverage.', 'features' => ['Full decoration setup', 'Photo and video package', 'Premium event setup'], 'popular' => true],
        ['event' => 'Birthday', 'tier' => 'Basic', 'price' => '5,000', 'image' => '../homepage/assets/images/birthday-basic.jpg', 'description' => 'Simple decoration for intimate birthday celebrations and small events.', 'features' => ['Simple decoration', 'Good for small events', 'Basic event setup'], 'popular' => false],
        ['event' => 'Birthday', 'tier' => 'Standard', 'price' => '10,000', 'image' => '../homepage/assets/images/birthday-standard.jpg', 'description' => 'Enhanced decoration for polished birthday parties with stronger production support.', 'features' => ['Enhanced decoration', 'Includes sound system', 'Standard event setup'], 'popular' => false],
        ['event' => 'Birthday', 'tier' => 'Premium', 'price' => '20,000', 'image' => '../homepage/assets/images/birthday-premium.jpg', 'description' => 'Full decoration setup for larger birthday parties with complete media coverage.', 'features' => ['Full decoration setup', 'Photo and video package', 'Premium event setup'], 'popular' => true],
    ];
}

function eventify_package_image_for_reservation($event_type, $package_type) {
    $event = strtolower((string) $event_type);
    $tier = strtolower((string) $package_type);
    $event_slug = strpos($event, 'birthday') !== false ? 'birthday' : 'wedding';

    if (strpos($tier, 'premium') !== false) {
        $tier_slug = 'premium';
    } elseif (strpos($tier, 'standard') !== false) {
        $tier_slug = 'standard';
    } else {
        $tier_slug = 'basic';
    }

    return '../homepage/assets/images/' . $event_slug . '-' . $tier_slug . '.jpg';
}

function eventify_gallery_defaults() {
    return [
        [
            'category' => 'Wedding',
            'description' => 'Elegant package designs for intimate ceremonies, refined receptions, and premium celebrations.',
            'items' => [
                [
                    'key' => 'wedding_basic',
                    'tier' => 'Basic',
                    'title' => 'Wedding Basic Design',
                    'images' => [
                        ['src' => 'assets/images/wedding-basic.jpg', 'label' => 'Wedding Basic Main Design'],
                        ['src' => 'assets/images/wedding.jpg', 'label' => 'Wedding Basic Venue Inspiration'],
                    ],
                ],
                [
                    'key' => 'wedding_standard',
                    'tier' => 'Standard',
                    'title' => 'Wedding Standard Design',
                    'images' => [
                        ['src' => 'assets/images/wedding-standard.jpg', 'label' => 'Wedding Standard Main Design'],
                        ['src' => 'assets/images/wedding.jpg', 'label' => 'Wedding Standard Venue Inspiration'],
                    ],
                ],
                [
                    'key' => 'wedding_premium',
                    'tier' => 'Premium',
                    'title' => 'Wedding Premium Design',
                    'images' => [
                        ['src' => 'assets/images/wedding-premium.jpg', 'label' => 'Wedding Premium Main Design'],
                        ['src' => 'assets/images/wedding.jpg', 'label' => 'Wedding Premium Venue Inspiration'],
                    ],
                ],
            ],
        ],
        [
            'category' => 'Birthday',
            'description' => 'Playful, polished package designs for joyful birthdays from simple parties to full celebrations.',
            'items' => [
                [
                    'key' => 'birthday_basic',
                    'tier' => 'Basic',
                    'title' => 'Birthday Basic Design',
                    'images' => [
                        ['src' => 'assets/images/birthday-basic.jpg', 'label' => 'Birthday Basic Main Design'],
                        ['src' => 'assets/images/birthday.jpg', 'label' => 'Birthday Basic Party Inspiration'],
                    ],
                ],
                [
                    'key' => 'birthday_standard',
                    'tier' => 'Standard',
                    'title' => 'Birthday Standard Design',
                    'images' => [
                        ['src' => 'assets/images/birthday-standard.jpg', 'label' => 'Birthday Standard Main Design'],
                        ['src' => 'assets/images/birthday.jpg', 'label' => 'Birthday Standard Party Inspiration'],
                    ],
                ],
                [
                    'key' => 'birthday_premium',
                    'tier' => 'Premium',
                    'title' => 'Birthday Premium Design',
                    'images' => [
                        ['src' => 'assets/images/birthday-premium.jpg', 'label' => 'Birthday Premium Main Design'],
                        ['src' => 'assets/images/birthday.jpg', 'label' => 'Birthday Premium Party Inspiration'],
                    ],
                ],
            ],
        ],
    ];
}

function eventify_gallery_showcase($conn) {
    $showcase = eventify_gallery_defaults();
    $photos_by_key = [];

    $result = $conn->query("
        SELECT *
        FROM event_gallery_photos
        WHERE is_active=1
        ORDER BY event_category ASC, package_tier ASC, sort_order ASC, id ASC
    ");

    if ($result) {
        while ($photo = $result->fetch_assoc()) {
            $category = strtolower($photo['event_category']);
            $tier = strtolower($photo['package_tier']);
            $key = $category . '_' . $tier;
            $photos_by_key[$key][] = [
                'src' => '../' . ltrim($photo['image_path'], '/'),
                'label' => $photo['title'],
            ];
        }
    }

    foreach ($showcase as &$group) {
        foreach ($group['items'] as &$item) {
            if (!empty($photos_by_key[$item['key']])) {
                $item['images'] = $photos_by_key[$item['key']];
            }
        }
    }
    unset($group, $item);

    return $showcase;
}

function eventify_get_gallery_photos($conn) {
    $result = $conn->query("
        SELECT *
        FROM event_gallery_photos
        ORDER BY event_category ASC, package_tier ASC, sort_order ASC, id DESC
    ");

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function eventify_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function eventify_csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(eventify_csrf_token(), ENT_QUOTES) . '">';
}

function eventify_verify_csrf() {
    $submitted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';

    return $submitted !== '' && $stored !== '' && hash_equals($stored, $submitted);
}

function eventify_page_url($page) {
    $params = $_GET;
    $params['page'] = max(1, (int) $page);

    return '?' . http_build_query($params);
}

function eventify_set_flash($icon, $title, $text = '') {
    $_SESSION['eventify_flash'] = [
        'icon' => $icon,
        'title' => $title,
        'text' => $text,
    ];
}

function eventify_prepare_email_notification($to, $subject, $message) {
    // External mail transport is intentionally not configured here.
    // Keep the payload in session so the app has a single place to wire mail later.
    $_SESSION['eventify_last_email'] = [
        'to' => $to,
        'subject' => $subject,
        'message' => $message,
        'prepared_at' => date('Y-m-d H:i:s'),
    ];
}

function eventify_create_notification($conn, $user_id, $role, $title, $message) {
    // Notifications are stored separately from reservations so read/unread state can be per audience.
    $user_id = $user_id ? (int) $user_id : null;
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, role, title, message)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $user_id, $role, $title, $message);
    $stmt->execute();
}

function eventify_notification_scope_sql($role) {
    return $role === 'admin'
        ? "role='admin' AND user_id IS NULL"
        : "role='client' AND user_id=?";
}

function eventify_get_unread_notification_count($conn, $user_id, $role) {
    if ($role === 'admin') {
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE " . eventify_notification_scope_sql($role) . " AND is_read=0");
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM notifications WHERE " . eventify_notification_scope_sql($role) . " AND is_read=0");
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();

    return (int) $stmt->get_result()->fetch_assoc()['total'];
}

function eventify_get_notifications($conn, $user_id, $role, $limit = 8) {
    if ($role === 'admin') {
        $stmt = $conn->prepare("
            SELECT *
            FROM notifications
            WHERE " . eventify_notification_scope_sql($role) . "
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("i", $limit);
    } else {
        $stmt = $conn->prepare("
            SELECT *
            FROM notifications
            WHERE " . eventify_notification_scope_sql($role) . "
            ORDER BY created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $user_id, $limit);
    }
    $stmt->execute();

    return $stmt->get_result();
}

function eventify_mark_notifications_as_read($conn, $user_id, $role) {
    if ($role === 'admin') {
        $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE " . eventify_notification_scope_sql($role));
    } else {
        $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE " . eventify_notification_scope_sql($role));
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
}

function eventify_notification_widget($conn, $role, $dashboard_url = 'dashboard.php', $action_url = 'notifications.php') {
    $user_id = eventify_current_user_id();
    $unread_count = eventify_get_unread_notification_count($conn, $user_id, $role);
    $notifications = eventify_get_notifications($conn, $user_id, $role);
    ob_start();
    ?>
    <div class="relative flex items-center gap-2" data-notification-root>
        <button type="button" class="relative grid h-11 w-11 place-items-center rounded-2xl border border-purple-100 bg-white text-primary shadow-sm hover:bg-purple-50" data-notification-toggle aria-label="Open notifications">
            <span class="text-xl leading-none">&#128276;</span>
            <?php if($unread_count > 0): ?>
                <span class="absolute right-2 top-2 h-3 w-3 rounded-full bg-red-500 ring-2 ring-white" data-notification-dot></span>
            <?php endif; ?>
        </button>
        <a href="<?php echo htmlspecialchars($dashboard_url, ENT_QUOTES); ?>" class="grid h-11 w-11 place-items-center rounded-2xl border border-purple-100 bg-white text-primary shadow-sm hover:bg-purple-50" aria-label="Dashboard">
            <span class="text-xl leading-none">&#9638;</span>
        </a>
        <a href="../auth/logout.php" class="grid h-11 w-11 place-items-center rounded-2xl border border-purple-100 bg-white font-bold text-primary shadow-sm hover:bg-purple-50" aria-label="Account">
            <?php echo $role === 'admin' ? 'A' : 'U'; ?>
        </a>

        <div class="absolute right-0 top-14 z-50 hidden w-80 overflow-hidden rounded-3xl border border-purple-100 bg-white shadow-soft" data-notification-menu>
            <div class="flex items-center justify-between gap-3 border-b border-purple-100 bg-indigo-50 p-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Notifications</p>
                <p class="text-sm text-slate-600"><span data-notification-unread-count><?php echo $unread_count; ?></span> unread</p>
                </div>
                <form method="POST" action="<?php echo htmlspecialchars($action_url, ENT_QUOTES); ?>" data-notification-read-form>
                    <?php echo eventify_csrf_field(); ?>
                    <button type="submit" name="notification_action" value="mark_all_read" class="rounded-xl bg-white px-3 py-2 text-xs font-bold text-primary hover:bg-purple-50">Mark all</button>
                </form>
            </div>
            <div class="max-h-96 overflow-y-auto p-3">
                <?php if($notifications && $notifications->num_rows > 0): ?>
                    <div class="space-y-2">
                        <?php while($notification = $notifications->fetch_assoc()): ?>
                            <article class="rounded-2xl border <?php echo (int) $notification['is_read'] === 0 ? 'border-purple-200 bg-purple-50' : 'border-slate-100 bg-white'; ?> p-3">
                                <div class="flex items-start gap-2">
                                    <?php if((int) $notification['is_read'] === 0): ?>
                                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-red-500"></span>
                                    <?php endif; ?>
                                    <div>
                                        <h3 class="text-sm font-bold text-dark"><?php echo htmlspecialchars($notification['title']); ?></h3>
                                        <p class="mt-1 text-sm leading-5 text-slate-600"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <p class="mt-2 text-xs font-semibold text-slate-400"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($notification['created_at']))); ?></p>
                                    </div>
                                </div>
                            </article>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="rounded-2xl bg-indigo-50 p-5 text-center text-sm font-semibold text-slate-600">No notifications yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function eventify_sweetalert_assets() {
    return '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
}

function eventify_sweetalert_flash() {
    if (empty($_SESSION['eventify_flash'])) {
        return '';
    }

    $flash = $_SESSION['eventify_flash'];
    unset($_SESSION['eventify_flash']);

    return '<script>
document.addEventListener("DOMContentLoaded", function () {
    if (window.Swal) {
        Swal.fire(' . json_encode($flash) . ');
    }
});
</script>';
}

function eventify_require_role($role, $redirect = '../auth/login.php') {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        eventify_set_flash('error', 'Access denied', 'Please log in with the correct account.');
        header("Location: $redirect");
        exit();
    }
}

function eventify_current_user_id() {
    return (int) ($_SESSION['user_id'] ?? 0);
}

function eventify_valid_contact($contact) {
    return (bool) preg_match('/^[0-9+\-\s()]{7,20}$/', $contact);
}

function eventify_service_options() {
    return ['Sound', 'Decoration', 'Lights'];
}

function eventify_clean_services($services) {
    if (is_string($services)) {
        $services = explode(',', $services);
    }

    if (!is_array($services)) {
        return '';
    }

    $allowed = eventify_service_options();
    $clean = array_values(array_intersect(array_map('trim', $services), $allowed));

    return implode(',', $clean);
}

function eventify_service_names_from_value($services) {
    $clean = eventify_clean_services($services);

    if ($clean === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $clean))));
}

function eventify_service_option_id($conn, $service_name) {
    $service_name = trim((string) $service_name);

    if ($service_name === '') {
        return 0;
    }

    $stmt = $conn->prepare("SELECT id FROM service_options WHERE name=? LIMIT 1");
    $stmt->bind_param("s", $service_name);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        return (int) $row['id'];
    }

    $sort_order = 99;
    $stmt = $conn->prepare("INSERT INTO service_options (name, sort_order) VALUES (?, ?)");
    $stmt->bind_param("si", $service_name, $sort_order);
    $stmt->execute();

    return (int) $conn->insert_id;
}

function eventify_sync_reservation_services($conn, $reservation_id, $services) {
    $reservation_id = (int) $reservation_id;

    if ($reservation_id <= 0) {
        return;
    }

    $stmt = $conn->prepare("DELETE FROM reservation_services WHERE reservation_id=?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();

    $stmt = $conn->prepare("
        INSERT IGNORE INTO reservation_services (reservation_id, service_id)
        VALUES (?, ?)
    ");

    foreach (eventify_service_names_from_value($services) as $service_name) {
        $service_id = eventify_service_option_id($conn, $service_name);

        if ($service_id > 0) {
            $stmt->bind_param("ii", $reservation_id, $service_id);
            $stmt->execute();
        }
    }
}

function eventify_sync_event_services($conn, $event_id, $services) {
    $event_id = (int) $event_id;

    if ($event_id <= 0) {
        return;
    }

    $stmt = $conn->prepare("DELETE FROM event_services WHERE event_id=?");
    $stmt->bind_param("i", $event_id);
    $stmt->execute();

    $stmt = $conn->prepare("
        INSERT IGNORE INTO event_services (event_id, service_id)
        VALUES (?, ?)
    ");

    foreach (eventify_service_names_from_value($services) as $service_name) {
        $service_id = eventify_service_option_id($conn, $service_name);

        if ($service_id > 0) {
            $stmt->bind_param("ii", $event_id, $service_id);
            $stmt->execute();
        }
    }
}

function eventify_validate_reservation_data($conn, $data) {
    $errors = [];
    $today = date('Y-m-d');
    $budget = eventify_package_budget($conn, (int) ($data['package_id'] ?? 0));

    if (trim($data['event_name'] ?? '') === '') {
        $errors[] = 'Event name is required.';
    }

    if (trim($data['event_type'] ?? '') === '') {
        $errors[] = 'Event type is required.';
    }

    $event_date = trim($data['event_date'] ?? '');
    $parsed_date = DateTime::createFromFormat('Y-m-d', $event_date);

    if ($event_date === '') {
        $errors[] = 'Event date is required.';
    } elseif (!$parsed_date || $parsed_date->format('Y-m-d') !== $event_date) {
        $errors[] = 'Event date is invalid.';
    } elseif ($event_date < $today) {
        $errors[] = 'Event date must not be in the past.';
    }

    $start_time = trim($data['start_time'] ?? $data['event_time'] ?? '');
    $end_time = trim($data['end_time'] ?? '');

    if ($start_time === '') {
        $errors[] = 'Start time is required.';
    }

    if ($end_time === '') {
        $errors[] = 'End time is required.';
    }

    if ($start_time !== '' && $end_time !== '' && $end_time <= $start_time) {
        $errors[] = 'End time must be later than the start time.';
    }

    if (trim($data['venue'] ?? '') === '') {
        $errors[] = 'Venue is required.';
    }

    if ((int) ($data['guests'] ?? 0) <= 0) {
        $errors[] = 'Guest count must be positive.';
    }

    if ((int) ($data['package_id'] ?? 0) <= 0 || $budget === null) {
        $errors[] = 'Please select an active event package.';
    }

    if (isset($data['budget']) && $data['budget'] !== '' && $budget !== null && (float) $data['budget'] !== (float) $budget) {
        $errors[] = 'Budget must match the selected event or package.';
    }

    if (trim($data['client_name'] ?? '') === '') {
        $errors[] = 'Client name is required.';
    }

    if (!eventify_valid_contact(trim($data['client_contact'] ?? ''))) {
        $errors[] = 'Contact number format is invalid.';
    }

    return $errors;
}

function eventify_reservation_payload_from_post() {
    $package_id = (int) ($_POST['package_id'] ?? $_POST['package_type'] ?? 0);

    return [
        'event_name' => trim($_POST['event_name'] ?? ''),
        'event_type' => trim($_POST['event_type'] ?? ''),
        'event_date' => trim($_POST['event_date'] ?? ''),
        'event_time' => trim($_POST['start_time'] ?? $_POST['event_time'] ?? ''),
        'start_time' => trim($_POST['start_time'] ?? $_POST['event_time'] ?? ''),
        'end_time' => trim($_POST['end_time'] ?? ''),
        'venue' => trim($_POST['venue'] ?? ''),
        'guests' => (int) ($_POST['guests'] ?? 0),
        'client_name' => trim($_POST['client_name'] ?? ''),
        'client_contact' => trim($_POST['client_contact'] ?? ''),
        'package_id' => $package_id,
        'budget' => $_POST['budget'] ?? '',
        'services' => eventify_clean_services($_POST['services'] ?? []),
    ];
}

function eventify_prepare_reservation_payload($conn, $payload) {
    $package = eventify_get_package($conn, (int) ($payload['package_id'] ?? 0), true);
    $payload['package_type'] = $package['name'] ?? '';
    $payload['calculated_budget'] = $package ? (float) $package['price'] : null;

    return $payload;
}

function eventify_event_conflict_exists($conn, $event_date, $start_time, $end_time, $venue, $exclude_event_id = 0) {
    $end_time = $end_time ?: $start_time;

    if ($exclude_event_id > 0) {
        $stmt = $conn->prepare("
            SELECT id
            FROM events
            WHERE event_date=?
              AND LOWER(TRIM(venue)) = LOWER(TRIM(?))
              AND event_time < ?
              AND COALESCE(end_time, event_time) > ?
              AND id<>?
            LIMIT 1
        ");
        $stmt->bind_param("ssssi", $event_date, $venue, $end_time, $start_time, $exclude_event_id);
    } else {
        $stmt = $conn->prepare("
            SELECT id
            FROM events
            WHERE event_date=?
              AND LOWER(TRIM(venue)) = LOWER(TRIM(?))
              AND event_time < ?
              AND COALESCE(end_time, event_time) > ?
            LIMIT 1
        ");
        $stmt->bind_param("ssss", $event_date, $venue, $end_time, $start_time);
    }

    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_assoc();
}

function eventify_reservation_conflict_exists($conn, $event_date, $start_time, $end_time, $venue, $exclude_reservation_id = 0) {
    $end_time = $end_time ?: $start_time;
    $stmt = $conn->prepare("
        SELECT id
        FROM reservations
        WHERE event_date=?
          AND LOWER(TRIM(venue)) = LOWER(TRIM(?))
          AND event_time < ?
          AND COALESCE(end_time, event_time) > ?
          AND status IN ('Pending', 'Approved')
          AND id<>?
        LIMIT 1
    ");
    $stmt->bind_param("ssssi", $event_date, $venue, $end_time, $start_time, $exclude_reservation_id);
    $stmt->execute();

    return (bool) $stmt->get_result()->fetch_assoc();
}

function eventify_slot_available($conn, $event_date, $start_time, $end_time, $venue, $exclude_reservation_id = 0) {
    return !eventify_event_conflict_exists($conn, $event_date, $start_time, $end_time, $venue)
        && !eventify_reservation_conflict_exists($conn, $event_date, $start_time, $end_time, $venue, $exclude_reservation_id);
}

function eventify_record_status_history($conn, $reservation_id, $old_status, $new_status, $note = '') {
    $user_id = eventify_current_user_id();
    $stmt = $conn->prepare("
        INSERT INTO reservation_status_history (reservation_id, old_status, new_status, note, changed_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isssi", $reservation_id, $old_status, $new_status, $note, $user_id);
    $stmt->execute();
}

function eventify_log_activity($conn, $action, $details = '') {
    $user_id = eventify_current_user_id();
    $role = $_SESSION['role'] ?? null;
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, role, action, details)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $user_id, $role, $action, $details);
    $stmt->execute();
}

function eventify_payment_methods() {
    return ['Cash', 'GCash', 'Bank Transfer', 'Card', 'Other'];
}

function eventify_payment_status_class($status) {
    $status = strtolower((string) $status);

    if ($status === 'paid' || $status === 'verified') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if ($status === 'partial paid') {
        return 'bg-sky-100 text-sky-700';
    }

    if ($status === 'for review') {
        return 'bg-amber-100 text-amber-700';
    }

    if ($status === 'rejected') {
        return 'bg-red-100 text-red-700';
    }

    return 'bg-slate-200 text-slate-600';
}

function eventify_reservation_payment_summary($conn, $reservation_id, $budget = 0) {
    $reservation_id = (int) $reservation_id;
    $budget = max(0, (float) $budget);

    $stmt = $conn->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN status='Verified' THEN amount ELSE 0 END), 0) AS verified_amount,
            COALESCE(SUM(CASE WHEN status='For Review' THEN amount ELSE 0 END), 0) AS review_amount,
            COALESCE(SUM(CASE WHEN status='Rejected' THEN amount ELSE 0 END), 0) AS rejected_amount,
            COUNT(*) AS payment_count,
            MAX(created_at) AS latest_payment_at
        FROM payments
        WHERE reservation_id=?
    ");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];

    $verified = (float) ($row['verified_amount'] ?? 0);
    $review = (float) ($row['review_amount'] ?? 0);
    $rejected = (float) ($row['rejected_amount'] ?? 0);
    $payment_count = (int) ($row['payment_count'] ?? 0);
    $balance = max($budget - $verified, 0);
    $payable = max($budget - $verified - $review, 0);

    if ($budget > 0 && $verified >= $budget) {
        $label = 'Paid';
    } elseif ($verified > 0) {
        $label = 'Partial Paid';
    } elseif ($review > 0) {
        $label = 'For Review';
    } elseif ($payment_count > 0 && $rejected > 0) {
        $label = 'Rejected';
    } else {
        $label = 'Unpaid';
    }

    return [
        'verified_amount' => $verified,
        'review_amount' => $review,
        'rejected_amount' => $rejected,
        'payment_count' => $payment_count,
        'latest_payment_at' => $row['latest_payment_at'] ?? null,
        'balance_amount' => $balance,
        'payable_amount' => $payable,
        'label' => $label,
    ];
}

function eventify_package_price_script($conn) {
    $packages = eventify_get_active_packages($conn);
    $prices = [];
    $details = [];

    foreach ($packages as $package) {
        $prices[(string) $package['id']] = (float) $package['price'];
        $details[(string) $package['id']] = [
            'id' => (int) $package['id'],
            'name' => $package['name'],
            'description' => $package['description'],
            'price' => (float) $package['price'],
            'image_path' => $package['image_path'],
        ];
    }

    return '<script>
window.eventifyPackagePrices = ' . json_encode($prices) . ';
window.eventifyPackages = ' . json_encode($details) . ';
</script>';
}

function eventify_column_exists($conn, $table, $column) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();

    return (int) $stmt->get_result()->fetch_assoc()['total'] > 0;
}

function eventify_ensure_column($conn, $table, $column, $definition) {
    if (!eventify_column_exists($conn, $table, $column)) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function eventify_column_has_index($conn, $table, $column) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();

    return (int) $stmt->get_result()->fetch_assoc()['total'] > 0;
}

function eventify_ensure_column_index($conn, $table, $column, $index_name) {
    if (!eventify_column_has_index($conn, $table, $column)) {
        $conn->query("ALTER TABLE `$table` ADD INDEX `$index_name` (`$column`)");
    }
}

function eventify_index_exists($conn, $table, $index_name) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND INDEX_NAME = ?
    ");
    $stmt->bind_param("ss", $table, $index_name);
    $stmt->execute();

    return (int) $stmt->get_result()->fetch_assoc()['total'] > 0;
}

function eventify_ensure_unique_column_index($conn, $table, $column, $index_name) {
    if (!eventify_index_exists($conn, $table, $index_name)) {
        $conn->query("ALTER TABLE `$table` ADD UNIQUE INDEX `$index_name` (`$column`)");
    }
}

function eventify_foreign_key_exists($conn, $table, $constraint_name) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND CONSTRAINT_NAME = ?
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ");
    $stmt->bind_param("ss", $table, $constraint_name);
    $stmt->execute();

    return (int) $stmt->get_result()->fetch_assoc()['total'] > 0;
}

function eventify_ensure_foreign_key($conn, $table, $constraint_name, $definition) {
    if (!eventify_foreign_key_exists($conn, $table, $constraint_name)) {
        $conn->query("ALTER TABLE `$table` ADD CONSTRAINT `$constraint_name` $definition");
    }
}

function eventify_ensure_varchar_length($conn, $table, $column, $length) {
    $stmt = $conn->prepare("
        SELECT CHARACTER_MAXIMUM_LENGTH AS max_length
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row && (int) $row['max_length'] < (int) $length) {
        $conn->query("ALTER TABLE `$table` MODIFY `$column` VARCHAR($length) DEFAULT NULL");
    }
}
?>
