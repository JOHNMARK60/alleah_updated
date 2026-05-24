<?php
session_start();
include '../config/db.php';

eventify_require_role('admin');

$recordTypes = ['Planning', 'Setup', 'Payment Note', 'Completion', 'Issue', 'Follow-up'];

if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['event_record_action'] ?? '') === 'create') {
    $event_id = (int) ($_POST['event_id'] ?? 0);
    $log_type = trim($_POST['log_type'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if(!eventify_verify_csrf()) {
        eventify_set_flash('error', 'Record failed', 'Security check failed. Please try again.');
    } elseif(!in_array($log_type, $recordTypes, true)) {
        eventify_set_flash('error', 'Record failed', 'Please select a valid record type.');
    } elseif($message === '') {
        eventify_set_flash('error', 'Record failed', 'Please enter the event record details.');
    } else {
        $stmt = $conn->prepare("SELECT id, reservation_id, event_name FROM events WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $event = $stmt->get_result()->fetch_assoc();

        if(!$event) {
            eventify_set_flash('error', 'Record failed', 'Event was not found.');
        } else {
            $created_by = eventify_current_user_id();
            $reservation_id = $event['reservation_id'] ? (int) $event['reservation_id'] : null;
            $stmt = $conn->prepare("
                INSERT INTO event_logs (reservation_id, event_id, log_type, message, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iissi", $reservation_id, $event_id, $log_type, $message, $created_by);
            $stmt->execute();

            eventify_log_activity($conn, 'event.record.created', 'Event #' . $event_id . ' - ' . $log_type);
            eventify_set_flash('success', 'Event record saved', 'The event timeline was updated.');
        }
    }

    header("Location: event_records.php?event_id=" . $event_id);
    exit();
}

$selectedEventId = (int) ($_GET['event_id'] ?? 0);
$reservationId = (int) ($_GET['reservation_id'] ?? 0);

if($reservationId > 0 && $selectedEventId <= 0) {
    $stmt = $conn->prepare("SELECT id FROM events WHERE reservation_id=? LIMIT 1");
    $stmt->bind_param("i", $reservationId);
    $stmt->execute();
    $eventFromReservation = $stmt->get_result()->fetch_assoc();
    if($eventFromReservation) {
        $selectedEventId = (int) $eventFromReservation['id'];
    } else {
        eventify_set_flash('error', 'Event not found', 'No approved event record was found for that reservation.');
        header("Location: event_records.php");
        exit();
    }
}

$result = $conn->query("
    SELECT e.*,
           (SELECT COUNT(*) FROM event_logs l WHERE l.event_id=e.id) AS record_count,
           (SELECT MAX(created_at) FROM event_logs l WHERE l.event_id=e.id) AS latest_record_at
    FROM events e
    ORDER BY e.event_date DESC, e.event_time DESC, e.id DESC
");
$events = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
$eventsById = [];

foreach($events as $event) {
    $eventsById[(int) $event['id']] = $event;
}

if($selectedEventId <= 0 && !empty($events)) {
    $selectedEventId = (int) $events[0]['id'];
}

if(!isset($eventsById[$selectedEventId])) {
    $selectedEventId = !empty($events) ? (int) $events[0]['id'] : 0;
}

$selectedEvent = $selectedEventId > 0 ? $eventsById[$selectedEventId] : null;
$selectedPaymentSummary = null;
$eventLogs = [];

if($selectedEvent) {
    $selectedPaymentSummary = eventify_reservation_payment_summary(
        $conn,
        (int) $selectedEvent['reservation_id'],
        (float) $selectedEvent['budget']
    );

    $stmt = $conn->prepare("
        SELECT l.*, u.name AS created_by_name
        FROM event_logs l
        LEFT JOIN users u ON u.id = l.created_by
        WHERE l.event_id=?
        ORDER BY l.created_at DESC, l.id DESC
    ");
    $stmt->bind_param("i", $selectedEventId);
    $stmt->execute();
    $eventLogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Records | Eventify Admin</title>
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
                <a href="payments.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Payments</a>
                <a href="reports.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Sales Reports</a>
                <a href="messages.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Messages</a>
                <a href="event_records.php" class="rounded-2xl bg-white/10 px-4 py-3 font-bold text-white">Event Records</a>
                <a href="calendar.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Calendar</a>
            </nav>
            <a href="../auth/logout.php" class="mt-auto rounded-2xl border border-white/10 px-4 py-3 text-center font-bold text-white/75 hover:bg-white/10">Logout</a>
        </aside>

        <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <?php echo eventify_admin_mobile_header($conn, 'event_records'); ?>
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Admin Workspace</p>
                        <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Event Records</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <?php echo eventify_notification_widget($conn, 'admin'); ?>
                        <a href="calendar.php" class="rounded-2xl bg-white px-5 py-3 text-center font-semibold text-primary shadow-sm hover:bg-purple-50">Calendar</a>
                        <a href="payments.php" class="rounded-2xl bg-gradient-to-r from-primary to-secondary px-5 py-3 text-center font-semibold text-white shadow-soft">Payments</a>
                    </div>
                </div>

                <?php if(empty($events)): ?>
                    <section class="mt-8 rounded-[2rem] bg-white p-8 text-center shadow-soft">
                        <h2 class="text-2xl font-semibold">No approved events yet</h2>
                        <p class="mt-2 text-slate-600">Event records will be available after a reservation is approved or an admin event is added.</p>
                        <a href="reservations.php?filter=pending" class="mt-5 inline-flex rounded-2xl bg-primary px-5 py-3 font-semibold text-white">Review Reservations</a>
                    </section>
                <?php else: ?>
                    <div class="mt-8 grid gap-6 xl:grid-cols-[380px_1fr]">
                        <section class="space-y-4">
                            <?php foreach($events as $event): ?>
                                <?php
                                $isSelected = (int) $event['id'] === $selectedEventId;
                                $recordCount = (int) $event['record_count'];
                                ?>
                                <a href="event_records.php?event_id=<?php echo (int) $event['id']; ?>" class="block rounded-[2rem] border p-5 shadow-sm transition hover:-translate-y-0.5 <?php echo $isSelected ? 'border-primary bg-white shadow-soft' : 'border-purple-100 bg-white/80 hover:bg-white'; ?>">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary"><?php echo htmlspecialchars($event['event_type'] ?: 'Event'); ?></p>
                                            <h2 class="mt-2 text-xl font-semibold"><?php echo htmlspecialchars($event['event_name']); ?></h2>
                                        </div>
                                        <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-primary"><?php echo $recordCount; ?> record<?php echo $recordCount === 1 ? '' : 's'; ?></span>
                                    </div>
                                    <p class="mt-3 text-sm text-slate-600"><?php echo htmlspecialchars($event['event_date']); ?> | <?php echo htmlspecialchars($event['event_time']); ?></p>
                                    <p class="mt-1 text-sm text-slate-600"><?php echo htmlspecialchars($event['venue'] ?: 'Venue TBA'); ?></p>
                                </a>
                            <?php endforeach; ?>
                        </section>

                        <section class="space-y-6">
                            <article class="rounded-[2rem] bg-white p-6 shadow-soft">
                                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                    <div>
                                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary"><?php echo htmlspecialchars($selectedEvent['event_type'] ?: 'Event'); ?></p>
                                        <h2 class="mt-2 text-3xl font-semibold"><?php echo htmlspecialchars($selectedEvent['event_name']); ?></h2>
                                        <p class="mt-2 text-slate-600"><?php echo htmlspecialchars($selectedEvent['client_name']); ?> | <?php echo htmlspecialchars($selectedEvent['venue'] ?: 'Venue TBA'); ?></p>
                                    </div>
                                    <span class="rounded-full px-4 py-2 text-sm font-semibold <?php echo eventify_payment_status_class($selectedPaymentSummary['label']); ?>">
                                        <?php echo htmlspecialchars($selectedPaymentSummary['label']); ?>
                                    </span>
                                </div>

                                <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                    <div class="rounded-2xl bg-indigo-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Date</p>
                                        <p class="mt-1 font-bold"><?php echo htmlspecialchars($selectedEvent['event_date']); ?></p>
                                    </div>
                                    <div class="rounded-2xl bg-indigo-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Time</p>
                                        <p class="mt-1 font-bold"><?php echo htmlspecialchars($selectedEvent['event_time']); ?> - <?php echo htmlspecialchars($selectedEvent['end_time'] ?: 'TBA'); ?></p>
                                    </div>
                                    <div class="rounded-2xl bg-indigo-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Budget</p>
                                        <p class="mt-1 font-bold">&#8369;<?php echo number_format((float) $selectedEvent['budget'], 2); ?></p>
                                    </div>
                                    <div class="rounded-2xl bg-indigo-50 p-4">
                                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Verified Paid</p>
                                        <p class="mt-1 font-bold text-emerald-600">&#8369;<?php echo number_format($selectedPaymentSummary['verified_amount'], 2); ?></p>
                                    </div>
                                </div>
                            </article>

                            <section class="rounded-[2rem] bg-white p-6 shadow-soft">
                                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">New Record</p>
                                <form method="POST" class="mt-5 grid gap-4" data-loading-form>
                                    <?php echo eventify_csrf_field(); ?>
                                    <input type="hidden" name="event_record_action" value="create">
                                    <input type="hidden" name="event_id" value="<?php echo (int) $selectedEvent['id']; ?>">
                                    <div class="grid gap-4 sm:grid-cols-[220px_1fr]">
                                        <div>
                                            <label class="text-sm font-bold text-slate-600">Record Type</label>
                                            <select name="log_type" required class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                                                <?php foreach($recordTypes as $type): ?>
                                                    <option value="<?php echo htmlspecialchars($type, ENT_QUOTES); ?>"><?php echo htmlspecialchars($type); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-sm font-bold text-slate-600">Details</label>
                                            <textarea name="message" rows="3" required class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100" placeholder="Add setup notes, completion details, issues, or follow-up reminders"></textarea>
                                        </div>
                                    </div>
                                    <button type="submit" class="justify-self-start rounded-2xl bg-primary px-6 py-3 font-semibold text-white shadow-soft">Save Record</button>
                                </form>
                            </section>

                            <section class="rounded-[2rem] bg-white p-6 shadow-soft">
                                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Timeline</p>
                                <div class="mt-6 space-y-4">
                                    <?php if(!empty($eventLogs)): ?>
                                        <?php foreach($eventLogs as $log): ?>
                                            <article class="rounded-2xl border border-purple-100 bg-indigo-50 p-5">
                                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                                    <h3 class="font-semibold"><?php echo htmlspecialchars($log['log_type']); ?></h3>
                                                    <p class="text-xs font-semibold text-slate-500"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($log['created_at']))); ?></p>
                                                </div>
                                                <p class="mt-3 leading-7 text-slate-700"><?php echo nl2br(htmlspecialchars($log['message'])); ?></p>
                                                <p class="mt-3 text-xs font-semibold text-slate-500">Recorded by <?php echo htmlspecialchars($log['created_by_name'] ?: 'Admin'); ?></p>
                                            </article>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="rounded-2xl bg-indigo-50 p-6 text-center text-sm font-semibold text-slate-600">No event records yet.</div>
                                    <?php endif; ?>
                                </div>
                            </section>
                        </section>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php echo eventify_admin_mobile_sidebar('event_records'); ?>

    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/admin.js"></script>
</body>
</html>
