<?php

function eventify_reports_valid_date($value) {
    $value = trim((string) $value);
    $date = DateTime::createFromFormat('!Y-m-d', $value);

    return $date && $date->format('Y-m-d') === $value;
}

function eventify_reports_months() {
    return [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ];
}

function eventify_reports_normalize_filters($source) {
    $allowed_types = ['daily', 'monthly', 'yearly', 'custom'];
    $report_type = strtolower(trim($source['report_type'] ?? 'monthly'));

    if (!in_array($report_type, $allowed_types, true)) {
        $report_type = 'monthly';
    }

    $today = date('Y-m-d');
    $current_year = (int) date('Y');
    $date = eventify_reports_valid_date($source['date'] ?? '') ? $source['date'] : $today;
    $month = min(12, max(1, (int) ($source['month'] ?? date('n'))));
    $year = (int) ($source['year'] ?? $current_year);

    if ($year < 2000 || $year > 2100) {
        $year = $current_year;
    }

    $start_date = eventify_reports_valid_date($source['start_date'] ?? '') ? $source['start_date'] : $date;
    $end_date = eventify_reports_valid_date($source['end_date'] ?? '') ? $source['end_date'] : $start_date;

    if ($report_type === 'daily') {
        $start_date = $date;
        $end_date = $date;
    } elseif ($report_type === 'monthly') {
        $start = DateTime::createFromFormat('!Y-n-j', $year . '-' . $month . '-1');
        $start_date = $start->format('Y-m-d');
        $end_date = $start->modify('last day of this month')->format('Y-m-d');
    } elseif ($report_type === 'yearly') {
        $start_date = $year . '-01-01';
        $end_date = $year . '-12-31';
    } elseif ($end_date < $start_date) {
        $temporary = $start_date;
        $start_date = $end_date;
        $end_date = $temporary;
    }

    return [
        'report_type' => $report_type,
        'date' => $date,
        'month' => $month,
        'year' => $year,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'category' => trim((string) ($source['category'] ?? '')),
        'client_name' => trim((string) ($source['client_name'] ?? '')),
        'payment_method' => trim((string) ($source['payment_method'] ?? '')),
        'reservation_status' => trim((string) ($source['reservation_status'] ?? '')),
    ];
}

function eventify_reports_date_label($filters) {
    $start = DateTime::createFromFormat('!Y-m-d', $filters['start_date']);
    $end = DateTime::createFromFormat('!Y-m-d', $filters['end_date']);

    if ($filters['report_type'] === 'daily') {
        return $start->format('F j, Y');
    }

    if ($filters['report_type'] === 'monthly') {
        return eventify_reports_months()[(int) $filters['month']] . ' ' . $filters['year'];
    }

    if ($filters['report_type'] === 'yearly') {
        return (string) $filters['year'];
    }

    return $start->format('F j, Y') . ' to ' . $end->format('F j, Y');
}

function eventify_reports_title($filters) {
    $titles = [
        'daily' => 'Daily Sales Report',
        'monthly' => 'Monthly Sales Report',
        'yearly' => 'Yearly Sales Report',
        'custom' => 'Custom Sales Report',
    ];

    return $titles[$filters['report_type']] ?? 'Sales Report';
}

function eventify_reports_format_datetime($value) {
    if (!$value) {
        return 'Not recorded';
    }

    return date('M d, Y h:i A', strtotime($value));
}

function eventify_reports_format_date($value) {
    if (!$value) {
        return 'Not recorded';
    }

    return date('M d, Y', strtotime($value));
}

function eventify_reports_status_class($status) {
    $status = strtolower((string) $status);

    if ($status === 'approved' || $status === 'paid' || $status === 'verified' || $status === 'completed') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if ($status === 'pending' || $status === 'for review' || $status === 'partial paid') {
        return 'bg-amber-100 text-amber-700';
    }

    if ($status === 'cancelled' || $status === 'rejected' || $status === 'unpaid') {
        return 'bg-red-100 text-red-700';
    }

    return 'bg-slate-200 text-slate-600';
}

function eventify_reports_bind_params($stmt, $types, &$params) {
    if ($types === '') {
        return;
    }

    $bind_types = $types;
    $references = [&$bind_types];

    foreach ($params as $index => &$value) {
        $references[] = &$value;
    }

    call_user_func_array([$stmt, 'bind_param'], $references);
}

function eventify_reports_options($conn) {
    $event_types = [];
    $packages = [];
    $statuses = ['Approved', 'Pending', 'Cancelled', 'Rejected'];
    $payment_methods = eventify_payment_methods();
    $years = [(int) date('Y')];

    $result = $conn->query("
        SELECT DISTINCT event_type
        FROM reservations
        WHERE event_type IS NOT NULL AND event_type<>''
        ORDER BY event_type ASC
    ");

    while ($result && $row = $result->fetch_assoc()) {
        $event_types[] = $row['event_type'];
    }

    $result = $conn->query("
        SELECT DISTINCT package_type
        FROM reservations
        WHERE package_type IS NOT NULL AND package_type<>''
        ORDER BY package_type ASC
    ");

    while ($result && $row = $result->fetch_assoc()) {
        $packages[] = $row['package_type'];
    }

    $result = $conn->query("
        SELECT name
        FROM event_packages
        WHERE deleted_at IS NULL
        ORDER BY sort_order ASC, name ASC
    ");

    while ($result && $row = $result->fetch_assoc()) {
        if (!in_array($row['name'], $packages, true)) {
            $packages[] = $row['name'];
        }
    }

    $result = $conn->query("
        SELECT DISTINCT status
        FROM reservations
        WHERE status IS NOT NULL AND status<>''
        ORDER BY status ASC
    ");

    while ($result && $row = $result->fetch_assoc()) {
        if (!in_array($row['status'], $statuses, true)) {
            $statuses[] = $row['status'];
        }
    }

    $result = $conn->query("
        SELECT DISTINCT method
        FROM payments
        WHERE method IS NOT NULL AND method<>''
        ORDER BY method ASC
    ");

    while ($result && $row = $result->fetch_assoc()) {
        if (!in_array($row['method'], $payment_methods, true)) {
            $payment_methods[] = $row['method'];
        }
    }

    $result = $conn->query("
        SELECT DISTINCT YEAR(COALESCE(approved_at, created_at)) AS report_year
        FROM reservations
        WHERE COALESCE(approved_at, created_at) IS NOT NULL
        ORDER BY report_year DESC
    ");

    while ($result && $row = $result->fetch_assoc()) {
        $year = (int) $row['report_year'];

        if ($year > 0 && !in_array($year, $years, true)) {
            $years[] = $year;
        }
    }

    rsort($years);

    return [
        'event_types' => $event_types,
        'packages' => $packages,
        'statuses' => $statuses,
        'payment_methods' => $payment_methods,
        'years' => $years,
    ];
}

function eventify_reports_apply_category_filter(&$where, &$types, &$params, $category) {
    if ($category === '') {
        return;
    }

    $parts = explode(':', $category, 2);

    if (count($parts) !== 2) {
        return;
    }

    if ($parts[0] === 'event') {
        $where[] = "LOWER(TRIM(COALESCE(r.event_type, ''))) = LOWER(TRIM(?))";
        $types .= 's';
        $params[] = $parts[1];
    } elseif ($parts[0] === 'package') {
        $where[] = "LOWER(TRIM(COALESCE(NULLIF(r.package_type, ''), ep.name, ''))) = LOWER(TRIM(?))";
        $types .= 's';
        $params[] = $parts[1];
    }
}

function eventify_reports_apply_client_filter(&$where, &$types, &$params, $client_name) {
    if ($client_name === '') {
        return;
    }

    $term = '%' . $client_name . '%';
    $where[] = "(r.client_name LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $types .= 'sss';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

function eventify_reports_rows($conn, $filters) {
    $where = [
        "LOWER(r.status)='approved'",
        "COALESCE(pay.verified_amount, 0) > 0",
        "DATE(COALESCE(r.approved_at, h.created_at, r.created_at)) BETWEEN ? AND ?",
    ];
    $types = 'ss';
    $params = [$filters['start_date'], $filters['end_date']];

    eventify_reports_apply_category_filter($where, $types, $params, $filters['category']);
    eventify_reports_apply_client_filter($where, $types, $params, $filters['client_name']);

    if ($filters['payment_method'] !== '') {
        $where[] = "
            EXISTS (
                SELECT 1
                FROM payments pm
                WHERE pm.reservation_id = r.id
                  AND pm.status='Verified'
                  AND pm.method=?
            )
        ";
        $types .= 's';
        $params[] = $filters['payment_method'];
    }

    if ($filters['reservation_status'] !== '') {
        $where[] = 'r.status=?';
        $types .= 's';
        $params[] = $filters['reservation_status'];
    }

    /*
     * Sales rows are intentionally built from one aggregated payment row per reservation.
     * This keeps package sales counted once even when a client made several verified payments.
     */
    $sql = "
        SELECT
            r.id,
            r.booking_reference,
            r.event_name,
            r.event_type,
            r.event_date,
            r.client_name,
            r.client_contact,
            r.package_type,
            r.budget,
            r.status,
            r.approved_at,
            r.created_at,
            r.user_id,
            u.name AS account_name,
            u.email AS account_email,
            u.address AS account_address,
            ep.name AS package_name,
            e.id AS event_id,
            pay.verified_amount,
            pay.payment_methods,
            pay.payment_references,
            pay.latest_paid_at,
            h.created_at AS history_approved_at,
            admin.name AS approved_by_name
        FROM reservations r
        LEFT JOIN users u ON u.id = r.user_id
        LEFT JOIN event_packages ep ON ep.id = r.package_id
        LEFT JOIN events e ON e.reservation_id = r.id
        LEFT JOIN (
            SELECT
                reservation_id,
                COALESCE(SUM(CASE WHEN status='Verified' THEN amount ELSE 0 END), 0) AS verified_amount,
                GROUP_CONCAT(DISTINCT CASE WHEN status='Verified' AND method IS NOT NULL AND method<>'' THEN method END ORDER BY method SEPARATOR ', ') AS payment_methods,
                GROUP_CONCAT(DISTINCT CASE WHEN status='Verified' AND reference_number IS NOT NULL AND reference_number<>'' THEN reference_number END ORDER BY reference_number SEPARATOR ', ') AS payment_references,
                MAX(CASE WHEN status='Verified' THEN COALESCE(paid_at, created_at) END) AS latest_paid_at
            FROM payments
            GROUP BY reservation_id
        ) pay ON pay.reservation_id = r.id
        LEFT JOIN (
            SELECT reservation_id, MAX(id) AS approved_history_id
            FROM reservation_status_history
            WHERE new_status='Approved'
            GROUP BY reservation_id
        ) history_pick ON history_pick.reservation_id = r.id
        LEFT JOIN reservation_status_history h ON h.id = history_pick.approved_history_id
        LEFT JOIN users admin ON admin.id = h.changed_by
        WHERE " . implode(' AND ', $where) . "
        ORDER BY COALESCE(r.approved_at, h.created_at, r.created_at) DESC, r.id DESC
    ";

    $stmt = $conn->prepare($sql);
    eventify_reports_bind_params($stmt, $types, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $total_amount = (float) $row['budget'];
        $paid_amount = (float) $row['verified_amount'];
        $remaining_balance = max($total_amount - $paid_amount, 0);
        $payment_methods = trim((string) ($row['payment_methods'] ?? ''));
        $payment_references = trim((string) ($row['payment_references'] ?? ''));
        $event_type = trim((string) ($row['event_type'] ?? ''));
        $package = trim((string) ($row['package_type'] ?: $row['package_name'] ?: ''));

        $row['display_client_name'] = trim((string) ($row['account_name'] ?: $row['client_name']));
        $row['display_event_package'] = trim(($event_type !== '' ? $event_type : 'Unspecified') . ' / ' . ($package !== '' ? $package : 'Package'));
        $row['display_payment_method'] = $payment_methods !== '' ? $payment_methods : 'Not specified';
        $row['payment_summary_group'] = strpos($row['display_payment_method'], ',') !== false ? 'Multiple Methods' : $row['display_payment_method'];
        $row['display_reference'] = $row['booking_reference'] ?: ($payment_references !== '' ? $payment_references : 'Reservation #' . $row['id']);
        $row['display_payment_references'] = $payment_references;
        $row['total_amount'] = $total_amount;
        $row['paid_amount'] = $paid_amount;
        $row['remaining_balance'] = $remaining_balance;
        $row['date_approved'] = $row['approved_at'] ?: ($row['history_approved_at'] ?: $row['created_at']);
        $row['approved_by'] = $row['approved_by_name'] ?: 'System Admin';

        $rows[] = $row;
    }

    return $rows;
}

function eventify_reports_status_counts($conn, $filters) {
    $where = [
        "r.status IN ('Cancelled', 'Rejected')",
        "DATE(COALESCE(r.cancelled_at, r.rejected_at, r.created_at)) BETWEEN ? AND ?",
    ];
    $types = 'ss';
    $params = [$filters['start_date'], $filters['end_date']];

    eventify_reports_apply_category_filter($where, $types, $params, $filters['category']);
    eventify_reports_apply_client_filter($where, $types, $params, $filters['client_name']);

    $sql = "
        SELECT
            COALESCE(SUM(CASE WHEN r.status='Cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count,
            COALESCE(SUM(CASE WHEN r.status='Rejected' THEN 1 ELSE 0 END), 0) AS rejected_count
        FROM reservations r
        LEFT JOIN users u ON u.id = r.user_id
        LEFT JOIN event_packages ep ON ep.id = r.package_id
        WHERE " . implode(' AND ', $where);

    $stmt = $conn->prepare($sql);
    eventify_reports_bind_params($stmt, $types, $params);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc() ?: ['cancelled_count' => 0, 'rejected_count' => 0];
}

function eventify_reports_data($conn, $filters) {
    $rows = eventify_reports_rows($conn, $filters);
    $status_counts = eventify_reports_status_counts($conn, $filters);
    $totals = [
        'approved_transactions' => 0,
        'total_reservations' => 0,
        'total_events_booked' => 0,
        'total_sales' => 0.0,
        'total_paid' => 0.0,
        'remaining_balance' => 0.0,
        'cancelled_count' => (int) ($status_counts['cancelled_count'] ?? 0),
        'rejected_count' => (int) ($status_counts['rejected_count'] ?? 0),
    ];
    $event_keys = [];
    $payment_summary = [];
    $category_summary = [];

    foreach ($rows as $row) {
        $totals['approved_transactions']++;
        $totals['total_reservations']++;
        $totals['total_sales'] += $row['total_amount'];
        $totals['total_paid'] += $row['paid_amount'];
        $totals['remaining_balance'] += $row['remaining_balance'];

        $event_key = !empty($row['event_id']) ? 'event-' . $row['event_id'] : 'reservation-' . $row['id'];
        $event_keys[$event_key] = true;

        $payment_group = $row['payment_summary_group'];
        if (!isset($payment_summary[$payment_group])) {
            $payment_summary[$payment_group] = [
                'payment_method' => $payment_group,
                'transactions' => 0,
                'total_sales' => 0.0,
                'total_paid' => 0.0,
                'remaining_balance' => 0.0,
            ];
        }
        $payment_summary[$payment_group]['transactions']++;
        $payment_summary[$payment_group]['total_sales'] += $row['total_amount'];
        $payment_summary[$payment_group]['total_paid'] += $row['paid_amount'];
        $payment_summary[$payment_group]['remaining_balance'] += $row['remaining_balance'];

        $category = $row['display_event_package'];
        if (!isset($category_summary[$category])) {
            $category_summary[$category] = [
                'event_package' => $category,
                'reservations' => 0,
                'total_sales' => 0.0,
            ];
        }
        $category_summary[$category]['reservations']++;
        $category_summary[$category]['total_sales'] += $row['total_amount'];
    }

    $totals['total_events_booked'] = count($event_keys);

    ksort($payment_summary);
    ksort($category_summary);

    return [
        'rows' => $rows,
        'payment_summary' => array_values($payment_summary),
        'category_summary' => array_values($category_summary),
        'totals' => $totals,
    ];
}

function eventify_reports_aggregate_template_rows($rows) {
    $totals = [
        'reservation_count' => 0,
        'event_count' => 0,
        'total_sales' => 0.0,
        'total_paid' => 0.0,
        'remaining_balance' => 0.0,
    ];
    $event_keys = [];

    foreach ($rows as $row) {
        $totals['reservation_count']++;
        $event_key = !empty($row['event_id']) ? 'event-' . $row['event_id'] : 'reservation-' . $row['id'];
        $event_keys[$event_key] = true;
        $totals['total_sales'] += (float) $row['total_amount'];
        $totals['total_paid'] += (float) $row['paid_amount'];
        $totals['remaining_balance'] += (float) $row['remaining_balance'];
    }

    $totals['event_count'] = count($event_keys);

    return $totals;
}

function eventify_reports_monthly_template_data($report, $filters) {
    $full_paid_rows = [];
    $partial_paid_rows = [];

    foreach ($report['rows'] as $row) {
        if ((float) $row['remaining_balance'] <= 0.01) {
            $full_paid_rows[] = $row;
        } else {
            $partial_paid_rows[] = $row;
        }
    }

    $start = DateTime::createFromFormat('!Y-m-d', $filters['start_date']);
    $days_in_month = (int) $start->format('t');
    $sales_by_day = [];

    for ($day = 1; $day <= $days_in_month; $day++) {
        $sales_by_day[$day] = [
            'label' => $start->format('M') . ' ' . $day,
            'count' => 0,
            'sales' => 0.0,
        ];
    }

    foreach ($report['rows'] as $row) {
        $date_approved = $row['date_approved'] ?: $row['created_at'];
        $day = (int) date('j', strtotime($date_approved));

        if (!isset($sales_by_day[$day])) {
            continue;
        }

        $sales_by_day[$day]['count']++;
        $sales_by_day[$day]['sales'] += (float) $row['total_amount'];
    }

    return [
        'sales_performance' => [
            [
                'label' => 'Approved Paid Reservations',
                'values' => eventify_reports_aggregate_template_rows($report['rows']),
            ],
            [
                'label' => 'Fully Paid Reservations',
                'values' => eventify_reports_aggregate_template_rows($full_paid_rows),
            ],
            [
                'label' => 'Partial Paid Reservations',
                'values' => eventify_reports_aggregate_template_rows($partial_paid_rows),
            ],
            [
                'label' => 'Totals',
                'values' => eventify_reports_aggregate_template_rows($report['rows']),
                'is_total' => true,
            ],
        ],
        'sales_by_day' => array_values($sales_by_day),
        'activity_summary' => [
            ['label' => 'Approved Transactions', 'value' => number_format($report['totals']['approved_transactions'])],
            ['label' => 'Total Reservations', 'value' => number_format($report['totals']['total_reservations'])],
            ['label' => 'Total Events Booked', 'value' => number_format($report['totals']['total_events_booked'])],
            ['label' => 'Total Sales Amount', 'value' => 'PHP ' . number_format((float) $report['totals']['total_sales'], 2)],
            ['label' => 'Total Paid Amount', 'value' => 'PHP ' . number_format((float) $report['totals']['total_paid'], 2)],
            ['label' => 'Remaining Balance', 'value' => 'PHP ' . number_format((float) $report['totals']['remaining_balance'], 2)],
            ['label' => 'Cancelled Transactions', 'value' => number_format($report['totals']['cancelled_count'])],
            ['label' => 'Rejected Transactions', 'value' => number_format($report['totals']['rejected_count'])],
        ],
    ];
}

function eventify_reports_current_admin($conn) {
    $user_id = eventify_current_user_id();
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    return $admin ?: ['name' => 'Admin', 'email' => ''];
}

?>
