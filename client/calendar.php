<?php
session_start();
include '../config/db.php';

eventify_require_role('client');

/* Calendar data */
$events = [];
$result = $conn->query("SELECT event_name, event_type, event_date, event_time, venue FROM events ORDER BY event_date ASC");

while($row = $result->fetch_assoc()){
    $events[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events Calendar | Eventify</title>
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
    <link rel="stylesheet" href="assets/css/client.css">
</head>
<body class="bg-soft text-dark">
    <header class="sticky top-0 z-30 border-b border-purple-100 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="dashboard.php" class="font-semibold text-primary">Menu</a>
            <h1 class="text-xl font-semibold sm:text-2xl">Events Calendar</h1>
            <div class="flex items-center gap-3">
                <?php echo eventify_notification_widget($conn, 'client'); ?>
                <a href="reservation.php" class="rounded-2xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-soft">+ New Event</a>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <section class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <div class="rounded-[2rem] bg-white p-5 shadow-soft sm:p-8">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Availability</p>
                        <h2 id="calendarMonth" class="mt-2 text-4xl font-semibold tracking-tight sm:text-6xl"></h2>
                    </div>
                    <div class="flex rounded-2xl bg-indigo-50 p-2">
                        <button type="button" class="rounded-xl px-4 py-3 text-xl font-semibold hover:bg-white" data-calendar-prev aria-label="Previous month">&lt;</button>
                        <button type="button" class="rounded-xl px-5 py-3 font-semibold hover:bg-white" data-calendar-today>Today</button>
                        <button type="button" class="rounded-xl px-4 py-3 text-xl font-semibold hover:bg-white" data-calendar-next aria-label="Next month">&gt;</button>
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

                <div id="calendarGrid" class="mt-4 grid grid-cols-7 gap-2 sm:gap-4"></div>
            </div>

            <aside class="rounded-[2rem] bg-white p-6 shadow-soft">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Selected Date</p>
                        <h2 id="selectedDateTitle" class="mt-2 text-3xl font-semibold">Events</h2>
                    </div>
                    <a href="reservation.php" class="grid h-14 w-14 place-items-center rounded-2xl bg-primary text-3xl font-light text-white shadow-soft">+</a>
                </div>

                <div id="selectedDateEvents" class="mt-6 space-y-4"></div>
            </aside>
        </section>
    </main>

    <script>
    window.eventifyCalendarEvents = <?php echo json_encode($events); ?>;
    </script>
    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/client.js"></script>
</body>
</html>
