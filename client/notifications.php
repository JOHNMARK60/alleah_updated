<?php
session_start();
include '../config/db.php';

eventify_require_role('client');

if($_SERVER['REQUEST_METHOD'] === 'POST' && eventify_verify_csrf()) {
    eventify_mark_notifications_as_read($conn, eventify_current_user_id(), 'client');
}

if(isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    http_response_code(204);
    exit();
}

$redirect = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header("Location: $redirect");
exit();
?>
