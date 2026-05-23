<?php
session_start();
include '../config/db.php';

eventify_require_role('admin');

/* Approve or reject reservation requests. */
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservation_action'])){

    $id = (int) ($_POST['id'] ?? 0);
    $action = $_POST['reservation_action'] ?? '';

    if(!eventify_verify_csrf()) {
        eventify_set_flash('error', 'Action failed', 'Security check failed. Please try again.');
    } else {
        $stmt = $conn->prepare("
            SELECT r.*, u.email AS user_email
            FROM reservations r
            LEFT JOIN users u ON u.id = r.user_id
            WHERE r.id=?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        if(!$res) {
            eventify_set_flash('error', 'Action failed', 'Reservation was not found.');
        } elseif(strtolower($res['status']) !== 'pending') {
            eventify_set_flash('error', 'Action failed', 'Only pending reservations can be updated.');
        } elseif($action === "approve"){
            // Security: approval is POST+CSRF protected and checks the approved calendar before mutating state.
            if(eventify_event_conflict_exists($conn, $res['event_date'], $res['event_time'], $res['end_time'], $res['venue'])) {
                eventify_set_flash('error', 'Slot already booked', 'Another approved event already overlaps the same venue and time window.');
            } else {
                $conn->begin_transaction();

                $stmt = $conn->prepare("INSERT INTO events
                (reservation_id,event_name,event_type,event_date,event_time,end_time,venue,guests,client_name,client_contact,package_id,package_type,budget,services)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

                $stmt->bind_param("issssssissisds",
                    $res['id'],
                    $res['event_name'],
                    $res['event_type'],
                    $res['event_date'],
                    $res['event_time'],
                    $res['end_time'],
                    $res['venue'],
                    $res['guest'],
                    $res['client_name'],
                    $res['client_contact'],
                    $res['package_id'],
                    $res['package_type'],
                    $res['budget'],
                    $res['services']
                );
                $stmt->execute();
                $event_id = (int) $conn->insert_id;
                eventify_sync_event_services($conn, $event_id, $res['services']);

                $stmt = $conn->prepare("UPDATE reservations SET status='Approved', approved_at=NOW(), rejected_at=NULL, cancelled_at=NULL WHERE id=?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                eventify_record_status_history($conn, $id, $res['status'], 'Approved', 'Approved by admin.');
                eventify_log_activity($conn, 'reservation.approved', 'Reservation #' . $id);

                $conn->commit();
                if(!empty($res['user_id'])) {
                    eventify_create_notification(
                        $conn,
                        (int) $res['user_id'],
                        'client',
                        'Reservation approved',
                        'Your reservation for ' . $res['event_type'] . ' has been approved.'
                    );
                }
                eventify_prepare_email_notification($res['user_email'] ?: $res['client_contact'], 'Reservation approved', 'Your Eventify reservation has been approved.');
                eventify_set_flash('success', 'Reservation approved', 'The reservation was added to the event calendar.');
            }
        } elseif($action === "reject"){
            $stmt = $conn->prepare("UPDATE reservations SET status='Rejected', rejected_at=NOW() WHERE id=?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            eventify_record_status_history($conn, $id, $res['status'], 'Rejected', 'Rejected by admin.');
            eventify_log_activity($conn, 'reservation.rejected', 'Reservation #' . $id);
            if(!empty($res['user_id'])) {
                eventify_create_notification(
                    $conn,
                    (int) $res['user_id'],
                    'client',
                    'Reservation rejected',
                    'Your reservation for ' . $res['event_type'] . ' has been rejected.'
                );
            }
            eventify_prepare_email_notification($res['user_email'] ?: $res['client_contact'], 'Reservation rejected', 'Your Eventify reservation was rejected.');
            eventify_set_flash('success', 'Reservation rejected', 'The reservation was marked as rejected.');
        } else {
            eventify_set_flash('error', 'Action failed', 'Unknown reservation action.');
        }
    }

    header("Location: reservations.php?filter=pending");
    exit();
}

$filter = $_GET['filter'] ?? 'all';
$allowedFilters = ['all', 'pending', 'approved', 'rejected', 'cancelled'];

if(!in_array($filter, $allowedFilters)) {
    $filter = 'all';
}

$perPage = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

if($filter === 'all') {
    $totalRows = (int) $conn->query("SELECT COUNT(*) AS total FROM reservations")->fetch_assoc()['total'];
    $stmt = $conn->prepare("SELECT * FROM reservations ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $perPage, $offset);
} else {
    $status = ucfirst($filter);
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM reservations WHERE status=?");
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $totalRows = (int) $stmt->get_result()->fetch_assoc()['total'];

    $stmt = $conn->prepare("SELECT * FROM reservations WHERE status=? ORDER BY id DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("sii", $status, $perPage, $offset);
}
$stmt->execute();
$result = $stmt->get_result();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

function reservation_status_class($status) {
    $status = strtolower($status);

    if($status === 'approved') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if($status === 'pending') {
        return 'bg-amber-100 text-amber-700';
    }

    if($status === 'cancelled' || $status === 'rejected') {
        return 'bg-red-100 text-red-700';
    }

    return 'bg-purple-100 text-primary';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations | Eventify Admin</title>
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
                <a href="reservations.php" class="rounded-2xl bg-white/10 px-4 py-3 font-bold text-white">Reservations</a>
                <a href="packages.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Packages</a>
                <a href="gallery.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Gallery</a>
                <a href="users.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Users</a>
                <a href="messages.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Messages</a>
                <a href="calendar.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Calendar</a>
                <a href="add_event.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Add Event</a>
            </nav>
            <a href="../auth/logout.php" class="mt-auto rounded-2xl border border-white/10 px-4 py-3 text-center font-bold text-white/75 hover:bg-white/10">Logout</a>
        </aside>

        <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Admin Workspace</p>
                        <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Reservations</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <?php echo eventify_notification_widget($conn, 'admin'); ?>
                        <input type="search" data-table-search data-table-target="reservationsTable" placeholder="Search reservations" class="rounded-2xl border border-purple-100 bg-white px-4 py-3 outline-none focus:border-primary focus:ring-4 focus:ring-purple-100">
                        <a href="payments.php" class="rounded-2xl bg-white px-5 py-3 text-center font-semibold text-primary shadow-sm hover:bg-purple-50">Payments</a>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <?php foreach($allowedFilters as $item): ?>
                        <a href="?filter=<?php echo $item; ?>" class="rounded-2xl px-4 py-2 text-sm font-semibold <?php echo $filter === $item ? 'bg-primary text-white shadow-soft' : 'bg-white text-slate-600 hover:text-primary'; ?>">
                            <?php echo ucfirst($item); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <section class="mt-6 overflow-hidden rounded-[2rem] bg-white shadow-soft">
                    <div class="overflow-x-auto">
                        <table id="reservationsTable" class="min-w-full text-left text-sm">
                            <thead class="bg-indigo-50 text-xs uppercase tracking-widest text-slate-500">
                                <tr>
                                    <th class="px-6 py-4">Event</th>
                                    <th class="px-6 py-4">Client</th>
                                    <th class="px-6 py-4">Date</th>
                                    <th class="px-6 py-4">Package</th>
                                    <th class="px-6 py-4">Payment</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-purple-50">
                                <?php if($result && $result->num_rows > 0): ?>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                        <?php $paymentSummary = eventify_reservation_payment_summary($conn, (int) $row['id'], (float) $row['budget']); ?>
                                        <tr class="align-top">
                                            <td class="px-6 py-5">
                                                <p class="font-semibold"><?php echo htmlspecialchars($row['event_name']); ?></p>
                                                <p class="mt-1 text-sm text-slate-500"><?php echo htmlspecialchars($row['event_type']); ?> | <?php echo htmlspecialchars($row['venue']); ?></p>
                                            </td>
                                            <td class="px-6 py-5">
                                                <p class="font-bold"><?php echo htmlspecialchars($row['client_name']); ?></p>
                                                <p class="mt-1 text-sm text-slate-500"><?php echo htmlspecialchars($row['client_contact']); ?></p>
                                            </td>
                                            <td class="px-6 py-5 text-slate-600"><?php echo htmlspecialchars($row['event_date']); ?><br><?php echo htmlspecialchars($row['event_time']); ?></td>
                                            <td class="px-6 py-5 text-slate-600"><?php echo htmlspecialchars($row['package_type']); ?></td>
                                            <td class="px-6 py-5">
                                                <span class="rounded-full px-3 py-1 text-xs font-semibold <?php echo eventify_payment_status_class($paymentSummary['label']); ?>">
                                                    <?php echo htmlspecialchars($paymentSummary['label']); ?>
                                                </span>
                                                <p class="mt-2 text-xs font-semibold text-slate-500">&#8369;<?php echo number_format($paymentSummary['verified_amount'], 2); ?> paid</p>
                                            </td>
                                            <td class="px-6 py-5">
                                                <span class="rounded-full px-3 py-1 text-xs font-semibold <?php echo reservation_status_class($row['status']); ?>">
                                                    <?php echo htmlspecialchars($row['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-5">
                                                <div class="flex flex-wrap gap-2">
                                                    <details class="group">
                                                        <summary class="cursor-pointer rounded-xl border border-purple-100 px-3 py-2 font-semibold text-primary hover:bg-purple-50">View</summary>
                                                        <div class="absolute z-20 mt-2 w-72 rounded-2xl border border-purple-100 bg-white p-4 shadow-soft">
                                                            <p><strong>Guests:</strong> <?php echo htmlspecialchars($row['guest']); ?></p>
                                                            <p><strong>Budget:</strong> &#8369;<?php echo number_format((float) $row['budget'], 2); ?></p>
                                                            <p><strong>Payment:</strong> <?php echo htmlspecialchars($paymentSummary['label']); ?></p>
                                                            <p><strong>Balance:</strong> &#8369;<?php echo number_format($paymentSummary['balance_amount'], 2); ?></p>
                                                            <p><strong>Services:</strong> <?php echo htmlspecialchars(eventify_clean_services($row['services'])); ?></p>
                                                            <div class="mt-3 flex flex-wrap gap-2">
                                                                <a href="payments.php?reservation_id=<?php echo (int) $row['id']; ?>" class="rounded-xl bg-indigo-50 px-3 py-2 text-xs font-bold text-primary hover:bg-purple-50">Payments</a>
                                                                <?php if(strtolower($row['status']) === 'approved'): ?>
                                                                    <a href="event_records.php?reservation_id=<?php echo (int) $row['id']; ?>" class="rounded-xl bg-indigo-50 px-3 py-2 text-xs font-bold text-primary hover:bg-purple-50">Event Records</a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </details>
                                                    <?php if(strtolower($row['status']) === 'pending'): ?>
                                                        <form method="POST" data-confirm-form data-confirm-message="Approve this reservation?">
                                                            <?php echo eventify_csrf_field(); ?>
                                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                            <input type="hidden" name="reservation_action" value="approve">
                                                            <button type="submit" class="rounded-xl bg-emerald-600 px-3 py-2 font-semibold text-white">Approve</button>
                                                        </form>
                                                        <form method="POST" data-confirm-form data-confirm-message="Reject this reservation?">
                                                            <?php echo eventify_csrf_field(); ?>
                                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                            <input type="hidden" name="reservation_action" value="reject">
                                                            <button type="submit" class="rounded-xl bg-red-600 px-3 py-2 font-semibold text-white">Reject</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td class="px-6 py-8 text-center text-slate-500" colspan="7">No reservations found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <?php if($totalPages > 1): ?>
                    <nav class="mt-6 flex flex-wrap items-center gap-2" aria-label="Reservation pages">
                        <?php for($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="<?php echo htmlspecialchars(eventify_page_url($i), ENT_QUOTES); ?>" class="rounded-xl px-4 py-2 text-sm font-semibold <?php echo $page === $i ? 'bg-primary text-white' : 'bg-white text-slate-600 hover:text-primary'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/admin.js"></script>
</body>
</html>
