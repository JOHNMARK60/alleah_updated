<?php
session_start();
include '../config/db.php';

eventify_require_role('admin');

/* Admin dashboard counts */
$total_reservations = $conn->query("SELECT COUNT(*) as total FROM reservations")
->fetch_assoc()['total'];

$total_users = $conn->query("SELECT COUNT(*) as total FROM users")
->fetch_assoc()['total'];

$pending_reservations = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status='Pending'")
->fetch_assoc()['total'];

$approved_events = $conn->query("SELECT COUNT(*) as total FROM events")
->fetch_assoc()['total'];

$recent = $conn->query("SELECT * FROM reservations ORDER BY id DESC LIMIT 6");

$monthly_reports = $conn->query("
    SELECT DATE_FORMAT(event_date, '%Y-%m') AS month_label, COUNT(*) AS total
    FROM reservations
    GROUP BY month_label
    ORDER BY month_label DESC
    LIMIT 6
");

$budget_report = $conn->query("
    SELECT COALESCE(SUM(budget), 0) AS total_budget
    FROM reservations
    WHERE status IN ('Approved', 'Pending')
")->fetch_assoc();

$popular_types = $conn->query("
    SELECT COALESCE(NULLIF(event_type, ''), 'Unspecified') AS event_type, COUNT(*) AS total
    FROM reservations
    GROUP BY event_type
    ORDER BY total DESC
    LIMIT 5
");

$package_usage = $conn->query("
    SELECT COALESCE(NULLIF(package_type, ''), 'Unspecified') AS package_type, COUNT(*) AS total
    FROM reservations
    GROUP BY package_type
    ORDER BY total DESC
    LIMIT 5
");

function admin_badge_class($status) {
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
    <title>Admin Dashboard | Eventify</title>
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
        <aside class="hidden shrink-0 flex-col border-r border-purple-100 bg-dark p-6 text-white lg:flex lg:w-64 xl:w-72">
            <a href="dashboard.php" class="flex items-center gap-3 text-2xl font-semibold">
                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-primary">E</span>
                Eventify Admin
            </a>

            <nav class="mt-10 grid gap-2">
                <a href="dashboard.php" class="rounded-2xl bg-white/10 px-4 py-3 font-bold text-white">Dashboard</a>
                <a href="reservations.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Reservations</a>
                <a href="reports.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Sales Reports</a>
                <a href="payments.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Payments</a>
                <a href="packages.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Packages</a>
                <a href="gallery.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Gallery</a>
                <a href="users.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Users</a>
                <a href="messages.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Messages</a>
                <a href="calendar.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Calendar</a>
                <a href="add_event.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Add Event</a>
                <a href="event_records.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Event Records</a>
            </nav>

            <a href="../auth/logout.php" class="mt-auto rounded-2xl border border-white/10 px-4 py-3 text-center font-bold text-white/75 hover:bg-white/10 hover:text-white">Logout</a>
        </aside>

        <main class="min-w-0 flex-1 overflow-x-hidden">
            <header class="sticky top-0 z-30 flex items-center justify-between border-b border-purple-100 bg-white/90 px-4 py-4 backdrop-blur lg:hidden">
                <button type="button" class="rounded-xl p-2 text-primary" data-admin-sidebar-button aria-label="Open navigation">
                    <span class="block h-0.5 w-6 bg-current"></span>
                    <span class="mt-1.5 block h-0.5 w-6 bg-current"></span>
                    <span class="mt-1.5 block h-0.5 w-6 bg-current"></span>
                </button>
                <a href="dashboard.php" class="text-xl font-semibold">Eventify</a>
                <?php echo eventify_notification_widget($conn, 'admin'); ?>
            </header>

            <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
                <div class="flex flex-col gap-4 2xl:flex-row 2xl:items-center 2xl:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Admin Overview</p>
                        <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Dashboard</h1>
                    </div>
                    <div class="flex w-full min-w-0 flex-wrap items-center gap-3 2xl:w-auto 2xl:justify-end">
                        <?php echo eventify_notification_widget($conn, 'admin'); ?>
                        <input type="search" data-table-search data-table-target="recentReservations" placeholder="Search reservations" class="min-w-0 flex-1 rounded-2xl border border-purple-100 bg-white px-4 py-3 outline-none focus:border-primary focus:ring-4 focus:ring-purple-100 sm:min-w-[16rem] 2xl:w-64 2xl:flex-none">
                        <a href="reservations.php?filter=pending" class="rounded-2xl bg-gradient-to-r from-primary to-secondary px-5 py-3 text-center font-semibold text-white shadow-soft">Review Pending</a>
                        <a href="reports.php" class="rounded-2xl bg-white px-5 py-3 text-center font-semibold text-primary shadow-sm hover:bg-purple-50">Reports</a>
                        <a href="messages.php" class="rounded-2xl bg-white px-5 py-3 text-center font-semibold text-primary shadow-sm hover:bg-purple-50">Messages</a>
                        <a href="payments.php" class="rounded-2xl bg-white px-5 py-3 text-center font-semibold text-primary shadow-sm hover:bg-purple-50">Payments</a>
                        <a href="event_records.php" class="rounded-2xl bg-white px-5 py-3 text-center font-semibold text-primary shadow-sm hover:bg-purple-50">Event Records</a>
                    </div>
                </div>

                <div class="mt-8 grid gap-5 sm:grid-cols-2 2xl:grid-cols-4">
                    <article class="rounded-3xl bg-white p-6 shadow-soft">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Total Reservations</p>
                        <p class="mt-4 text-5xl font-semibold"><?php echo $total_reservations; ?></p>
                    </article>
                    <article class="rounded-3xl bg-white p-6 shadow-soft">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Total Users</p>
                        <p class="mt-4 text-5xl font-semibold"><?php echo $total_users; ?></p>
                    </article>
                    <article class="rounded-3xl bg-white p-6 shadow-soft">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Pending Reservations</p>
                        <p class="mt-4 text-5xl font-semibold text-amber-600"><?php echo $pending_reservations; ?></p>
                    </article>
                    <article class="rounded-3xl bg-white p-6 shadow-soft">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Approved Events</p>
                        <p class="mt-4 text-5xl font-semibold text-primary"><?php echo $approved_events; ?></p>
                    </article>
                </div>

                <section class="mt-8 overflow-hidden rounded-[2rem] bg-white shadow-soft">
                    <div class="flex items-center justify-between border-b border-purple-100 p-6">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Latest Requests</p>
                            <h2 class="mt-1 text-2xl font-semibold">Recent Reservations</h2>
                        </div>
                        <a href="reservations.php" class="font-semibold text-primary">View all</a>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="recentReservations" class="min-w-full text-left text-sm">
                            <thead class="bg-indigo-50 text-xs uppercase tracking-widest text-slate-500">
                                <tr>
                                    <th class="px-6 py-4">Event</th>
                                    <th class="px-6 py-4">Client</th>
                                    <th class="px-6 py-4">Date</th>
                                    <th class="px-6 py-4">Status</th>
                                    <th class="px-6 py-4">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-purple-50">
                                <?php if($recent && $recent->num_rows > 0): ?>
                                    <?php while($row = $recent->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($row['event_name']); ?></td>
                                            <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['client_name']); ?></td>
                                            <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['event_date']); ?></td>
                                            <td class="px-6 py-4">
                                                <span class="rounded-full px-3 py-1 text-xs font-semibold <?php echo admin_badge_class($row['status']); ?>">
                                                    <?php echo htmlspecialchars($row['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4"><a class="font-semibold text-primary" href="reservations.php">View</a></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td class="px-6 py-6 text-center text-slate-500" colspan="5">No reservations yet.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="mt-8 rounded-[2rem] bg-white p-6 shadow-soft">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Reports</p>
                            <h2 class="mt-1 text-2xl font-semibold">Reservation Insights</h2>
                        </div>
                        <p class="text-sm font-semibold text-slate-500">Estimated pipeline: &#8369;<?php echo number_format((float) $budget_report['total_budget'], 2); ?></p>
                    </div>

                    <div class="mt-6 grid gap-6 lg:grid-cols-3">
                        <article class="rounded-3xl bg-indigo-50 p-5">
                            <h3 class="font-semibold">Monthly Reservations</h3>
                            <div class="mt-4 space-y-3 text-sm">
                                <?php if($monthly_reports && $monthly_reports->num_rows > 0): ?>
                                    <?php while($row = $monthly_reports->fetch_assoc()): ?>
                                        <div class="flex justify-between gap-4"><span><?php echo htmlspecialchars($row['month_label']); ?></span><strong><?php echo (int) $row['total']; ?></strong></div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-slate-600">No reservation data yet.</p>
                                <?php endif; ?>
                            </div>
                        </article>

                        <article class="rounded-3xl bg-indigo-50 p-5">
                            <h3 class="font-semibold">Popular Event Types</h3>
                            <div class="mt-4 space-y-3 text-sm">
                                <?php if($popular_types && $popular_types->num_rows > 0): ?>
                                    <?php while($row = $popular_types->fetch_assoc()): ?>
                                        <div class="flex justify-between gap-4"><span><?php echo htmlspecialchars($row['event_type']); ?></span><strong><?php echo (int) $row['total']; ?></strong></div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-slate-600">No event type data yet.</p>
                                <?php endif; ?>
                            </div>
                        </article>

                        <article class="rounded-3xl bg-indigo-50 p-5">
                            <h3 class="font-semibold">Package Usage</h3>
                            <div class="mt-4 space-y-3 text-sm">
                                <?php if($package_usage && $package_usage->num_rows > 0): ?>
                                    <?php while($row = $package_usage->fetch_assoc()): ?>
                                        <div class="flex justify-between gap-4"><span><?php echo htmlspecialchars($row['package_type']); ?></span><strong><?php echo (int) $row['total']; ?></strong></div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-slate-600">No package data yet.</p>
                                <?php endif; ?>
                            </div>
                        </article>
                    </div>
                </section>
            </section>
        </main>
    </div>

    <div class="fixed inset-0 z-40 hidden bg-dark/50 lg:hidden" data-admin-sidebar>
        <aside class="h-full w-80 max-w-[86vw] overflow-y-auto bg-dark p-6 text-white shadow-soft">
            <div class="flex items-center justify-between">
                <span class="text-2xl font-semibold">Eventify Admin</span>
                <button type="button" class="rounded-xl px-3 py-2 font-semibold text-white" data-admin-sidebar-close>Close</button>
            </div>
            <nav class="mt-8 grid gap-2">
                <a href="dashboard.php" class="rounded-2xl bg-white/10 px-4 py-3 font-bold">Dashboard</a>
                <a href="reservations.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Reservations</a>
                <a href="reports.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Sales Reports</a>
                <a href="payments.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Payments</a>
                <a href="packages.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Packages</a>
                <a href="gallery.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Gallery</a>
                <a href="users.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Users</a>
                <a href="messages.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Messages</a>
                <a href="calendar.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Calendar</a>
                <a href="add_event.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Add Event</a>
                <a href="event_records.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Event Records</a>
                <a href="../auth/logout.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Logout</a>
            </nav>
        </aside>
    </div>

    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/admin.js"></script>
</body>
</html>
