<?php
session_start();
include '../../config/db.php';

eventify_require_role('client', '../../auth/login.php');

header('Content-Type: application/json');

$event_date = trim($_GET['event_date'] ?? '');
$start_time = trim($_GET['start_time'] ?? $_GET['event_time'] ?? '');
$end_time = trim($_GET['end_time'] ?? '');
$venue = trim($_GET['venue'] ?? '');

if ($event_date === '' || $start_time === '' || $end_time === '' || $venue === '') {
    echo json_encode([
        'available' => null,
        'message' => 'Complete date, start time, end time, and venue to check availability.',
    ]);
    exit;
}

if ($end_time <= $start_time) {
    echo json_encode([
        'available' => false,
        'message' => 'End time must be later than the start time.',
    ]);
    exit;
}

$available = eventify_slot_available($conn, $event_date, $start_time, $end_time, $venue);

echo json_encode([
    'available' => $available,
    'message' => $available
        ? 'This venue is available for the selected time window.'
        : 'This venue already has a reservation or approved event in that time window.',
]);
