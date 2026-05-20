<?php
session_start();
include '../config/db.php';

eventify_require_role('admin');

$allowedFilters = ['all', 'review', 'verified', 'rejected'];
$filter = $_GET['filter'] ?? 'all';
$reservationFilter = max(0, (int) ($_GET['reservation_id'] ?? 0));

if(!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$statusMap = [
    'review' => 'For Review',
    'verified' => 'Verified',
    'rejected' => 'Rejected',
];

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_action'])) {
    $payment_id = (int) ($_POST['payment_id'] ?? 0);
    $action = $_POST['payment_action'] ?? '';

    if(!eventify_verify_csrf()) {
        eventify_set_flash('error', 'Payment update failed', 'Security check failed. Please try again.');
    } else {
        $stmt = $conn->prepare("
            SELECT p.*, r.event_name, r.client_name, r.user_id
            FROM payments p
            INNER JOIN reservations r ON r.id = p.reservation_id
            WHERE p.id=?
            LIMIT 1
        ");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        $payment = $stmt->get_result()->fetch_assoc();

        if(!$payment) {
            eventify_set_flash('error', 'Payment update failed', 'Payment record was not found.');
        } elseif(strtolower($payment['status']) !== 'for review') {
            eventify_set_flash('error', 'Payment update failed', 'Only payments for review can be updated.');
        } elseif($action === 'verify') {
            $status = 'Verified';
            $stmt = $conn->prepare("UPDATE payments SET status=?, paid_at=COALESCE(paid_at, NOW()) WHERE id=?");
            $stmt->bind_param("si", $status, $payment_id);
            $stmt->execute();

            if(!empty($payment['user_id'])) {
                eventify_create_notification(
                    $conn,
                    (int) $payment['user_id'],
                    'client',
                    'Payment verified',
                    'Your payment for ' . $payment['event_name'] . ' has been verified.'
                );
            }
            eventify_log_activity($conn, 'payment.verified', 'Payment #' . $payment_id);
            eventify_set_flash('success', 'Payment verified', 'The payment record is now marked as verified.');
        } elseif($action === 'reject') {
            $status = 'Rejected';
            $stmt = $conn->prepare("UPDATE payments SET status=? WHERE id=?");
            $stmt->bind_param("si", $status, $payment_id);
            $stmt->execute();

            if(!empty($payment['user_id'])) {
                eventify_create_notification(
                    $conn,
                    (int) $payment['user_id'],
                    'client',
                    'Payment rejected',
                    'Your payment for ' . $payment['event_name'] . ' was rejected. Please check the reference details.'
                );
            }
            eventify_log_activity($conn, 'payment.rejected', 'Payment #' . $payment_id);
            eventify_set_flash('success', 'Payment rejected', 'The payment record was marked as rejected.');
        } else {
            eventify_set_flash('error', 'Payment update failed', 'Unknown payment action.');
        }
    }

    $redirect = 'payments.php?filter=' . urlencode($filter);
    if($reservationFilter > 0) {
        $redirect .= '&reservation_id=' . $reservationFilter;
    }
    header("Location: $redirect");
    exit();
}

$verifiedTotal = (float) $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE status='Verified'")->fetch_assoc()['total'];
$reviewTotal = (float) $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE status='For Review'")->fetch_assoc()['total'];
$reviewCount = (int) $conn->query("SELECT COUNT(*) AS total FROM payments WHERE status='For Review'")->fetch_assoc()['total'];

$reservationTitle = '';
if($reservationFilter > 0) {
    $stmt = $conn->prepare("SELECT event_name, client_name FROM reservations WHERE id=? LIMIT 1");
    $stmt->bind_param("i", $reservationFilter);
    $stmt->execute();
    $reservation = $stmt->get_result()->fetch_assoc();

    if($reservation) {
        $reservationTitle = $reservation['event_name'] . ' by ' . $reservation['client_name'];
    }
}

$baseSql = "
    SELECT p.*, r.event_name, r.event_date, r.client_name, r.budget, r.status AS reservation_status, e.id AS event_id
    FROM payments p
    INNER JOIN reservations r ON r.id = p.reservation_id
    LEFT JOIN events e ON e.reservation_id = r.id
";

if($reservationFilter > 0 && $filter !== 'all') {
    $status = $statusMap[$filter];
    $stmt = $conn->prepare($baseSql . " WHERE p.reservation_id=? AND p.status=? ORDER BY p.created_at DESC");
    $stmt->bind_param("is", $reservationFilter, $status);
} elseif($reservationFilter > 0) {
    $stmt = $conn->prepare($baseSql . " WHERE p.reservation_id=? ORDER BY p.created_at DESC");
    $stmt->bind_param("i", $reservationFilter);
} elseif($filter !== 'all') {
    $status = $statusMap[$filter];
    $stmt = $conn->prepare($baseSql . " WHERE p.status=? ORDER BY p.created_at DESC");
    $stmt->bind_param("s", $status);
} else {
    $stmt = $conn->prepare($baseSql . " ORDER BY p.created_at DESC");
}

$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments | Eventify Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php echo eventify_sweetalert_assets(); ?>
    <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#7C00D8',
            secondary: '#A855F7',
            soft: '#F6F3FF',
            dark: '#111827'
          },
          boxShadow: {
            soft: '0 15px 35px rgba(124, 0, 216, 0.15)'
          }
        }
      }
    }
    </script>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="bg-soft text-dark">
    <div class="min-h-screen lg:flex">
        <aside class="hidden w-72 shrink-0 flex-col border-r border-purple-100 bg-dark p-6 text-white lg:flex">
            <a href="dashboard.php" class="flex items-center gap-3 text-2xl font-semibold">
                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-primary">E</span>
                Eventify Admin
            </a>
            <nav class="mt-10 grid gap-2">
                <a href="dashboard.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Dashboard</a>
                <a href="reservations.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Reservations</a>
                <a href="payments.php" class="rounded-2xl bg-white/10 px-4 py-3 font-bold text-white">Payments</a>
                <a href="event_records.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Event Records</a>
                <a href="calendar.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Calendar</a>
            </nav>
            <a href="../auth/logout.php" class="mt-auto rounded-2xl border border-white/10 px-4 py-3 text-center font-bold text-white/75 hover:bg-white/10">Logout</a>
        </aside>

        <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Admin Workspace</p>
                        <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Payments</h1>
                        <?php if($reservationTitle !== ''): ?>
                            <p class="mt-3 text-slate-600">Showing payment records for <?php echo htmlspecialchars($reservationTitle); ?>.</p>
                        <?php endif; ?>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <?php echo eventify_notification_widget($conn, 'admin'); ?>
                        <a href="reservations.php" class="rounded-2xl bg-white px-5 py-3 text-center font-semibold text-primary shadow-sm hover:bg-purple-50">Reservations</a>
                    </div>
                </div>

                <section class="mt-8 grid gap-5 sm:grid-cols-3">
                    <article class="rounded-3xl bg-white p-6 shadow-soft">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Verified Payments</p>
                        <p class="mt-4 text-4xl font-semibold text-emerald-600">&#8369;<?php echo number_format($verifiedTotal, 2); ?></p>
                    </article>
                    <article class="rounded-3xl bg-white p-6 shadow-soft">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">For Review</p>
                        <p class="mt-4 text-4xl font-semibold text-amber-600"><?php echo $reviewCount; ?></p>
                    </article>
                    <article class="rounded-3xl bg-white p-6 shadow-soft">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Review Amount</p>
                        <p class="mt-4 text-4xl font-semibold text-primary">&#8369;<?php echo number_format($reviewTotal, 2); ?></p>
                    </article>
                </section>

                <div class="mt-6 flex flex-wrap gap-3">
                    <?php foreach($allowedFilters as $item): ?>
                        <?php
                        $params = ['filter' => $item];
                        if($reservationFilter > 0) {
                            $params['reservation_id'] = $reservationFilter;
                        }
                        ?>
                        <a href="?<?php echo htmlspecialchars(http_build_query($params), ENT_QUOTES); ?>" class="rounded-2xl px-4 py-2 text-sm font-semibold <?php echo $filter === $item ? 'bg-primary text-white shadow-soft' : 'bg-white text-slate-600 hover:text-primary'; ?>">
                            <?php echo $item === 'review' ? 'For Review' : ucfirst($item); ?>
                        </a>
                    <?php endforeach; ?>
                    <?php if($reservationFilter > 0): ?>
                        <a href="payments.php" class="rounded-2xl bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:text-primary">Clear Reservation</a>
                    <?php endif; ?>
                </div>

                <section class="mt-6 overflow-hidden rounded-[2rem] bg-white shadow-soft">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-indigo-50 text-xs uppercase tracking-widest text-slate-500">
                                <tr>
                                    <th class="px-6 py-4">Payment</th>
                                    <th class="px-6 py-4">Reservation</th>
                                    <th class="px-6 py-4">Method</th>
                                    <th class="px-6 py-4">Reference</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-purple-50">
                                <?php if(!empty($payments)): ?>
                                    <?php foreach($payments as $payment): ?>
                                        <tr class="align-top">
                                            <td class="px-6 py-5">
                                                <p class="text-lg font-semibold">&#8369;<?php echo number_format((float) $payment['amount'], 2); ?></p>
                                                <p class="mt-1 text-xs font-semibold text-slate-500"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($payment['created_at']))); ?></p>
                                            </td>
                                            <td class="px-6 py-5">
                                                <p class="font-bold"><?php echo htmlspecialchars($payment['event_name']); ?></p>
                                                <p class="mt-1 text-slate-500"><?php echo htmlspecialchars($payment['client_name']); ?> | <?php echo htmlspecialchars($payment['event_date']); ?></p>
                                            </td>
                                            <td class="px-6 py-5 text-slate-600"><?php echo htmlspecialchars($payment['method'] ?: 'Not specified'); ?></td>
                                            <td class="px-6 py-5 text-slate-600"><?php echo htmlspecialchars($payment['reference_number'] ?: 'No reference'); ?></td>
                                            <td class="px-6 py-5">
                                                <span class="rounded-full px-3 py-1 text-xs font-semibold <?php echo eventify_payment_status_class($payment['status']); ?>">
                                                    <?php echo htmlspecialchars($payment['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-5">
                                                <div class="flex flex-wrap gap-2">
                                                    <a href="reservations.php?filter=all" class="rounded-xl border border-purple-100 px-3 py-2 font-semibold text-primary hover:bg-purple-50">Reservation</a>
                                                    <?php if(!empty($payment['event_id'])): ?>
                                                        <a href="event_records.php?event_id=<?php echo (int) $payment['event_id']; ?>" class="rounded-xl border border-purple-100 px-3 py-2 font-semibold text-primary hover:bg-purple-50">Event</a>
                                                    <?php endif; ?>
                                                    <?php if(strtolower($payment['status']) === 'for review'): ?>
                                                        <form method="POST" data-confirm-form data-confirm-message="Verify this payment?">
                                                            <?php echo eventify_csrf_field(); ?>
                                                            <input type="hidden" name="payment_id" value="<?php echo (int) $payment['id']; ?>">
                                                            <input type="hidden" name="payment_action" value="verify">
                                                            <button type="submit" class="rounded-xl bg-emerald-600 px-3 py-2 font-semibold text-white">Verify</button>
                                                        </form>
                                                        <form method="POST" data-confirm-form data-confirm-message="Reject this payment?">
                                                            <?php echo eventify_csrf_field(); ?>
                                                            <input type="hidden" name="payment_id" value="<?php echo (int) $payment['id']; ?>">
                                                            <input type="hidden" name="payment_action" value="reject">
                                                            <button type="submit" class="rounded-xl bg-red-600 px-3 py-2 font-semibold text-white">Reject</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td class="px-6 py-8 text-center text-slate-500" colspan="6">No payment records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/admin.js"></script>
</body>
</html>
