<?php
session_start();
include '../config/db.php';

eventify_require_role('admin');

$events = [];
$result = $conn->query("SELECT * FROM events ORDER BY event_date ASC");

while($row = $result->fetch_assoc()){
    $events[] = $row;
}

$upcoming = $conn->query("SELECT COUNT(*) as total FROM events WHERE event_date >= CURDATE()")
->fetch_assoc()['total'];
$completed = $conn->query("SELECT COUNT(*) as total FROM events WHERE event_date < CURDATE()")
->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Calendar | Eventify</title>
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
                <a href="packages.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Packages</a>
                <a href="gallery.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Gallery</a>
                <a href="users.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Users</a>
                <a href="calendar.php" class="rounded-2xl bg-white/10 px-4 py-3 font-bold text-white">Calendar</a>
            </nav>
            <a href="../auth/logout.php" class="mt-auto rounded-2xl border border-white/10 px-4 py-3 text-center font-bold text-white/75 hover:bg-white/10">Logout</a>
        </aside>

        <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Event Overview</p>
                        <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Calendar</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <?php echo eventify_notification_widget($conn, 'admin'); ?>
                        <a href="event_records.php" class="rounded-2xl bg-white px-5 py-3 text-center font-semibold text-primary shadow-sm hover:bg-purple-50">Event Records</a>
                        <a href="add_event.php" class="rounded-2xl bg-gradient-to-r from-primary to-secondary px-5 py-3 text-center font-semibold text-white shadow-soft">+ Add Event</a>
                    </div>
                </div>

                <div class="mt-8 grid gap-6 xl:grid-cols-[1fr_360px]">
                    <section class="rounded-[2rem] bg-white p-5 shadow-soft sm:p-8">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <h2 id="adminCalendarMonth" class="text-4xl font-semibold tracking-tight sm:text-5xl"></h2>
                            <div class="flex rounded-2xl bg-indigo-50 p-2">
                                <button type="button" class="rounded-xl px-4 py-3 text-xl font-semibold hover:bg-white" data-admin-calendar-prev>&lt;</button>
                                <button type="button" class="rounded-xl px-5 py-3 font-semibold hover:bg-white" data-admin-calendar-today>Today</button>
                                <button type="button" class="rounded-xl px-4 py-3 text-xl font-semibold hover:bg-white" data-admin-calendar-next>&gt;</button>
                            </div>
                        </div>

                        <div class="mt-8 grid grid-cols-7 gap-2 text-center text-xs font-semibold uppercase tracking-widest text-slate-400 sm:text-sm">
                            <span>Mon</span>
                            <span>Tue</span>
                            <span>Wed</span>
                            <span>Thu</span>
                            <span>Fri</span>
                            <span>Sat</span>
                            <span>Sun</span>
                        </div>
                        <div id="adminCalendarGrid" class="mt-4 grid grid-cols-7 gap-2 sm:gap-4"></div>
                    </section>

                    <aside class="space-y-6">
                        <section class="grid grid-cols-2 gap-4">
                            <div class="rounded-3xl bg-white p-5 shadow-soft">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Upcoming</p>
                                <p class="mt-3 text-4xl font-semibold text-primary"><?php echo $upcoming; ?></p>
                            </div>
                            <div class="rounded-3xl bg-white p-5 shadow-soft">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Completed</p>
                                <p class="mt-3 text-4xl font-semibold text-emerald-600"><?php echo $completed; ?></p>
                            </div>
                        </section>

                        <section class="rounded-[2rem] bg-white p-6 shadow-soft">
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Selected Date</p>
                            <h2 id="adminSelectedDateTitle" class="mt-2 text-3xl font-semibold">Events</h2>
                            <div id="adminSelectedDateEvents" class="mt-6 space-y-4"></div>
                        </section>
                    </aside>
                </div>
            </div>
        </main>
    </div>

    <script>
    window.eventifyAdminEvents = <?php echo json_encode($events); ?>;
    </script>
    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/admin.js"></script>
</body>
</html>
