<?php
session_start();
include '../config/db.php';

eventify_require_role('client');

$redirect = ($_POST['redirect'] ?? '') === 'dashboard' ? 'dashboard.php' : 'my_reservations.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST' || !eventify_verify_csrf()) {
    eventify_set_flash('error', 'Cancellation failed', 'Security check failed. Please try again.');
    header("Location: $redirect");
    exit();
}

$id = (int) ($_POST['id'] ?? 0);
$user_id = eventify_current_user_id();

$stmt = $conn->prepare("
    UPDATE reservations
    SET status='Cancelled', cancelled_at=NOW()
    WHERE id=? AND user_id=? AND status NOT IN ('Cancelled', 'Rejected')
");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();

if($stmt->affected_rows > 0) {
    eventify_prepare_email_notification('admin@eventify.com', 'Reservation cancelled', 'A client cancelled an Eventify reservation.');
    eventify_set_flash('success', 'Reservation cancelled', 'Your reservation was cancelled successfully.');
} else {
    eventify_set_flash('error', 'Cancellation failed', 'Reservation was not found or cannot be cancelled.');
}

header("Location: $redirect");
exit();
?>
