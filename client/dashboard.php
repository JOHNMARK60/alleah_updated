<?php
session_start();
include '../config/db.php';

eventify_require_role('client');

$user_id = eventify_current_user_id();
$today = date('Y-m-d');
$active_packages = eventify_get_active_packages($conn);

/* Dashboard totals */
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM reservations WHERE user_id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_reservations = $stmt->get_result()->fetch_assoc()['total'];

$upcoming = $conn->query("
    SELECT COUNT(*) as total
    FROM events
    WHERE event_date >= CURDATE()
")->fetch_assoc()['total'];

$completed = $conn->query("
    SELECT COUNT(*) as total
    FROM events
    WHERE event_date < CURDATE()
")->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT * FROM reservations WHERE user_id=? ORDER BY id DESC LIMIT 4");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent = $stmt->get_result();

$stmt = $conn->prepare("SELECT * FROM reservations WHERE user_id=? ORDER BY id DESC LIMIT 4");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$dashboard_modal_recent = $stmt->get_result();

$highlight = $conn->query("
    SELECT * FROM events
    WHERE event_date >= CURDATE()
    ORDER BY event_date ASC
    LIMIT 1
");
$highlight_event = $highlight ? $highlight->fetch_assoc() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard | Eventify</title>
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
    <div class="min-h-screen lg:flex">
        <!-- Desktop sidebar -->
        <aside class="hidden w-72 shrink-0 flex-col border-r border-purple-100 bg-white p-6 lg:flex">
            <a href="dashboard.php" class="flex items-center gap-3 text-2xl font-semibold">
                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-primary text-white">E</span>
                Eventify
            </a>

            <nav class="mt-10 grid gap-2">
                <button type="button" data-dashboard-modal-open="dashboard" data-modal-target="dashboardModal" class="rounded-2xl bg-purple-50 px-4 py-3 text-left font-bold text-primary">Dashboard</button>
                <button type="button" data-dashboard-modal-open="calendar" data-modal-target="calendarModal" class="rounded-2xl px-4 py-3 text-left font-bold text-slate-600 hover:bg-purple-50 hover:text-primary">Events Calendar</button>
                <button type="button" data-dashboard-modal-open="reservation" data-modal-target="reservationModal" class="rounded-2xl px-4 py-3 text-left font-bold text-slate-600 hover:bg-purple-50 hover:text-primary">New Reservation</button>
                <button type="button" data-dashboard-modal-open="reservations" data-modal-target="myReservationsModal" class="rounded-2xl px-4 py-3 text-left font-bold text-slate-600 hover:bg-purple-50 hover:text-primary">My Reservations</button>
                <button type="button" data-dashboard-modal-open="pricing" data-modal-target="pricingModal" class="rounded-2xl px-4 py-3 text-left font-bold text-slate-600 hover:bg-purple-50 hover:text-primary">Event Pricing</button>
            </nav>

            <a href="../auth/logout.php" class="mt-auto rounded-2xl border border-purple-100 px-4 py-3 text-center font-bold text-slate-600 hover:bg-purple-50 hover:text-primary">Logout</a>
        </aside>

        <main class="flex-1 pb-24 lg:pb-0">
            <!-- Mobile header -->
            <header class="sticky top-0 z-30 flex items-center justify-between border-b border-purple-100 bg-white/90 px-4 py-4 backdrop-blur lg:hidden">
                <button type="button" class="rounded-xl p-2 text-primary" data-sidebar-button aria-label="Open navigation">
                    <span class="block h-0.5 w-6 bg-current"></span>
                    <span class="mt-1.5 block h-0.5 w-6 bg-current"></span>
                    <span class="mt-1.5 block h-0.5 w-6 bg-current"></span>
                </button>
                <a href="dashboard.php" class="text-xl font-semibold">Eventify</a>
                <?php echo eventify_notification_widget($conn, 'client'); ?>
            </header>

            <section class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-10">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Client Portal</p>
                        <h1 class="mt-2 text-3xl font-semibold tracking-tight sm:text-5xl">Dashboard</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <?php echo eventify_notification_widget($conn, 'client'); ?>
                        <button type="button" data-dashboard-modal-open="reservation" class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-primary to-secondary px-5 py-3 font-semibold text-white shadow-soft">
                            + New Reservation
                        </button>
                    </div>
                </div>

                <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <button type="button" data-dashboard-modal-open="calendar" class="rounded-3xl bg-white p-6 text-left shadow-soft transition hover:-translate-y-0.5">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-violet-100 font-semibold text-violet-600">C</span>
                        <span class="mt-5 block text-xl font-semibold">Event Calendar</span>
                        <span class="mt-2 block text-sm text-slate-600">Check event dates and venue availability.</span>
                    </button>
                    <button type="button" data-dashboard-modal-open="reservation" class="rounded-3xl bg-white p-6 text-left shadow-soft transition hover:-translate-y-0.5">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-purple-100 font-semibold text-primary">+</span>
                        <span class="mt-5 block text-xl font-semibold">New Reservation</span>
                        <span class="mt-2 block text-sm text-slate-600">Submit a new event request for admin approval.</span>
                    </button>
                    <button type="button" data-dashboard-modal-open="reservations" class="rounded-3xl bg-white p-6 text-left shadow-soft transition hover:-translate-y-0.5">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-emerald-100 font-semibold text-emerald-600">R</span>
                        <span class="mt-5 block text-xl font-semibold">My Reservation</span>
                        <span class="mt-2 block text-sm text-slate-600">Review status, details, and cancellation options.</span>
                    </button>
                    <button type="button" data-dashboard-modal-open="pricing" class="rounded-3xl bg-white p-6 text-left shadow-soft transition hover:-translate-y-0.5">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-amber-100 font-semibold text-amber-600">&#8369;</span>
                        <span class="mt-5 block text-xl font-semibold">Event Pricing</span>
                        <span class="mt-2 block text-sm text-slate-600">Compare available event packages.</span>
                    </button>
                </div>

                <div class="mt-8 grid gap-5 md:grid-cols-3">
                    <article class="rounded-3xl bg-white p-6 shadow-soft">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Total Reservations</p>
                                <p class="mt-3 text-5xl font-semibold"><?php echo $my_reservations; ?></p>
                            </div>
                            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-purple-100 font-semibold text-primary">R</span>
                        </div>
                        <p class="mt-5 text-sm font-bold text-emerald-600">8% since last month</p>
                    </article>

                    <article class="rounded-3xl bg-white p-6 shadow-soft">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Upcoming Events</p>
                                <p class="mt-3 text-5xl font-semibold"><?php echo $upcoming; ?></p>
                            </div>
                            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-violet-100 font-semibold text-violet-600">C</span>
                        </div>
                        <p class="mt-5 text-sm text-slate-600">Next event is shown below.</p>
                    </article>

                    <article class="rounded-3xl bg-white p-6 shadow-soft">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Completed</p>
                                <p class="mt-3 text-5xl font-semibold"><?php echo $completed; ?></p>
                            </div>
                            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-indigo-100 font-semibold text-indigo-600">OK</span>
                        </div>
                        <p class="mt-5 text-sm font-bold text-primary">100% satisfaction focus</p>
                    </article>
                </div>

                <div class="mt-8 grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
                    <section class="rounded-3xl bg-white shadow-soft">
                        <div class="flex items-center justify-between border-b border-purple-100 p-6">
                            <h2 class="text-2xl font-semibold">Recent Activity</h2>
                            <a href="my_reservations.php" class="font-bold text-primary">View all</a>
                        </div>
                        <div class="divide-y divide-purple-50">
                            <?php if($recent && $recent->num_rows > 0): ?>
                                <?php while($row = $recent->fetch_assoc()): ?>
                                    <article class="flex items-center gap-4 p-6">
                                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-indigo-50 font-semibold text-primary">EV</span>
                                        <div class="min-w-0 flex-1">
                                            <h3 class="font-semibold"><?php echo htmlspecialchars($row['event_name']); ?></h3>
                                            <p class="text-sm text-slate-600"><?php echo htmlspecialchars($row['event_date']); ?> | <?php echo htmlspecialchars($row['event_type']); ?></p>
                                        </div>
                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700"><?php echo htmlspecialchars($row['status']); ?></span>
                                    </article>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="p-6">
                                    <h3 class="font-semibold">No reservations yet</h3>
                                    <p class="mt-1 text-sm text-slate-600">Start by creating your first booking.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <aside class="space-y-6">
                        <article class="relative overflow-hidden rounded-3xl bg-dark p-6 text-white shadow-soft">
                            <img class="absolute inset-0 h-full w-full object-cover opacity-35" src="https://images.unsplash.com/photo-1505236858219-8359eb29e329?auto=format&fit=crop&w=900&q=80" alt="Event highlight">
                            <div class="relative min-h-64 content-end">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-white/70">Upcoming Highlight</p>
                                <?php if($highlight_event): ?>
                                    <h3 class="mt-3 text-2xl font-semibold"><?php echo htmlspecialchars($highlight_event['event_name']); ?></h3>
                                    <p class="mt-3 text-sm text-white/80"><?php echo htmlspecialchars($highlight_event['event_date']); ?> | <?php echo htmlspecialchars($highlight_event['venue']); ?></p>
                                <?php else: ?>
                                    <h3 class="mt-3 text-2xl font-semibold">Plan your next event</h3>
                                    <p class="mt-3 text-sm text-white/80">No upcoming event is scheduled yet.</p>
                                <?php endif; ?>
                            </div>
                        </article>

                        <article class="rounded-3xl bg-white p-6 shadow-soft">
                            <div class="flex items-center justify-between">
                                <h2 class="font-semibold">Event Locations</h2>
                                <span class="font-semibold text-primary">Map</span>
                            </div>
                            <div class="mt-5 h-44 rounded-2xl bg-gradient-to-br from-slate-500 to-slate-700 p-6 text-white">
                                <div class="mx-auto mt-8 h-20 w-20 rounded-full bg-rose-300 shadow-soft"></div>
                                <div class="mx-auto -mt-12 h-5 w-5 rounded-full bg-primary ring-4 ring-white"></div>
                            </div>
                        </article>
                    </aside>
                </div>
            </section>

            <!-- Mobile bottom navigation -->
            <nav class="fixed bottom-0 left-0 right-0 z-30 grid grid-cols-5 border-t border-purple-100 bg-white px-2 py-2 text-center text-xs font-bold text-slate-500 lg:hidden">
                <button type="button" data-dashboard-modal-open="dashboard" data-modal-target="dashboardModal" class="rounded-2xl px-2 py-2 text-primary">Home</button>
                <button type="button" data-dashboard-modal-open="calendar" data-modal-target="calendarModal" class="rounded-2xl px-2 py-2">Events</button>
                <button type="button" data-dashboard-modal-open="reservation" data-modal-target="reservationModal" class="-mt-6 mx-auto grid h-14 w-14 place-items-center rounded-full bg-primary text-2xl text-white shadow-soft">+</button>
                <button type="button" data-dashboard-modal-open="reservations" data-modal-target="myReservationsModal" class="rounded-2xl px-2 py-2">Bookings</button>
                <button type="button" data-dashboard-modal-open="pricing" data-modal-target="pricingModal" class="rounded-2xl px-2 py-2">Pricing</button>
            </nav>
        </main>
    </div>

    <div class="fixed inset-0 z-40 hidden bg-dark/40 lg:hidden" data-sidebar>
        <aside class="h-full w-80 bg-white p-6 shadow-soft">
            <div class="flex items-center justify-between">
                <span class="text-2xl font-semibold">Eventify</span>
                <button type="button" class="rounded-xl px-3 py-2 font-semibold text-primary" data-sidebar-close>Close</button>
            </div>
            <nav class="mt-8 grid gap-2">
                <button type="button" data-dashboard-modal-open="dashboard" data-modal-target="dashboardModal" class="rounded-2xl bg-purple-50 px-4 py-3 text-left font-bold text-primary">Dashboard</button>
                <button type="button" data-dashboard-modal-open="calendar" data-modal-target="calendarModal" class="rounded-2xl px-4 py-3 text-left font-bold text-slate-600 hover:bg-purple-50">Events Calendar</button>
                <button type="button" data-dashboard-modal-open="reservation" data-modal-target="reservationModal" class="rounded-2xl px-4 py-3 text-left font-bold text-slate-600 hover:bg-purple-50">New Reservation</button>
                <button type="button" data-dashboard-modal-open="reservations" data-modal-target="myReservationsModal" class="rounded-2xl px-4 py-3 text-left font-bold text-slate-600 hover:bg-purple-50">My Reservations</button>
                <button type="button" data-dashboard-modal-open="pricing" data-modal-target="pricingModal" class="rounded-2xl px-4 py-3 text-left font-bold text-slate-600 hover:bg-purple-50">Event Pricing</button>
                <a href="../auth/logout.php" class="rounded-2xl px-4 py-3 font-bold text-slate-600 hover:bg-purple-50">Logout</a>
            </nav>
        </aside>
    </div>

    <div id="dashboardModal" class="dashboard-modal fixed inset-0 z-50 hidden overflow-y-auto bg-dark/60 p-4 backdrop-blur-sm" data-dashboard-modal="dashboard" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="dashboardModalTitle" tabindex="-1">
        <div class="dashboard-modal-panel mx-auto my-6 w-full max-w-6xl rounded-[2rem] bg-white p-5 shadow-soft sm:p-8" data-modal-panel>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Client Portal</p>
                    <h2 id="dashboardModalTitle" class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Dashboard</h2>
                </div>
                <button type="button" class="rounded-xl px-3 py-2 font-semibold text-primary hover:bg-purple-50" data-dashboard-modal-close>Close</button>
            </div>

            <div class="mt-6 grid gap-5 md:grid-cols-3">
                <article class="rounded-3xl bg-indigo-50 p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Total Reservations</p>
                    <p class="mt-3 text-5xl font-semibold"><?php echo $my_reservations; ?></p>
                </article>
                <article class="rounded-3xl bg-indigo-50 p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Upcoming Events</p>
                    <p class="mt-3 text-5xl font-semibold"><?php echo $upcoming; ?></p>
                </article>
                <article class="rounded-3xl bg-indigo-50 p-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Completed</p>
                    <p class="mt-3 text-5xl font-semibold"><?php echo $completed; ?></p>
                </article>
            </div>

            <section class="mt-6 overflow-hidden rounded-[2rem] border border-purple-100">
                <div class="flex items-center justify-between border-b border-purple-100 bg-indigo-50 p-5">
                    <h3 class="text-xl font-semibold">Recent Activity</h3>
                    <button type="button" data-dashboard-modal-switch="reservations" class="font-bold text-primary">View all</button>
                </div>
                <div class="divide-y divide-purple-50">
                    <?php if($dashboard_modal_recent && $dashboard_modal_recent->num_rows > 0): ?>
                        <?php while($row = $dashboard_modal_recent->fetch_assoc()): ?>
                            <article class="flex items-center gap-4 p-5">
                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-purple-100 font-semibold text-primary">EV</span>
                                <div class="min-w-0 flex-1">
                                    <h4 class="font-semibold"><?php echo htmlspecialchars($row['event_name']); ?></h4>
                                    <p class="text-sm text-slate-600"><?php echo htmlspecialchars($row['event_date']); ?> | <?php echo htmlspecialchars($row['event_type']); ?></p>
                                </div>
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700"><?php echo htmlspecialchars($row['status']); ?></span>
                            </article>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-5">
                            <h3 class="font-semibold">No reservations yet</h3>
                            <p class="mt-1 text-sm text-slate-600">Start by creating your first booking.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <div id="reservationModal" class="dashboard-modal fixed inset-0 z-50 hidden overflow-y-auto bg-dark/60 p-4 backdrop-blur-sm" data-dashboard-modal="reservation" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="reservationModalTitle" tabindex="-1">
        <div class="dashboard-modal-panel mx-auto my-6 w-full max-w-5xl rounded-[2rem] bg-white p-5 shadow-soft sm:p-8" data-modal-panel>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">New Reservation</p>
                    <h2 id="reservationModalTitle" class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Plan an Event</h2>
                </div>
                <button type="button" class="rounded-xl px-3 py-2 font-semibold text-primary hover:bg-purple-50" data-dashboard-modal-close>Close</button>
            </div>

            <form method="POST" action="reservation.php" class="mt-6 grid gap-6 lg:grid-cols-[1fr_0.85fr]" data-package-budget-form data-loading-form>
                <?php echo eventify_csrf_field(); ?>
                <input type="hidden" name="redirect" value="dashboard">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-sm font-bold text-slate-600">Event Name</label>
                        <input type="text" name="event_name" required class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                    </div>
                    <div>
                        <label class="text-sm font-bold text-slate-600">Event Type</label>
                        <input type="text" name="event_type" required placeholder="Corporate gala, product launch, private celebration" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                    </div>
                    <div>
                        <label class="text-sm font-bold text-slate-600">Package</label>
                        <select name="package_id" required data-package-select class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                            <?php echo eventify_package_options_html($conn); ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-bold text-slate-600">Date</label>
                        <input type="date" name="event_date" min="<?php echo $today; ?>" required class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                    </div>
                    <div>
                        <label class="text-sm font-bold text-slate-600">Start Time</label>
                        <input type="time" name="start_time" required data-availability-field class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                    </div>
                    <div>
                        <label class="text-sm font-bold text-slate-600">End Time</label>
                        <input type="time" name="end_time" required data-availability-field class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                    </div>
                    <div>
                        <label class="text-sm font-bold text-slate-600">Venue</label>
                        <input type="text" name="venue" required data-availability-field class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                    </div>
                    <div>
                        <label class="text-sm font-bold text-slate-600">Guests</label>
                        <input type="number" name="guests" min="1" required class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                    </div>
                    <div>
                        <label class="text-sm font-bold text-slate-600">Client Name</label>
                        <input type="text" name="client_name" required class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                    </div>
                    <div>
                        <label class="text-sm font-bold text-slate-600">Client Contact</label>
                        <input type="text" name="client_contact" pattern="[0-9+\-\s()]{7,20}" required class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-sm font-bold text-slate-600">Budget</label>
                        <input type="number" name="budget" readonly data-budget-input class="mt-2 w-full rounded-xl border border-purple-100 bg-slate-100 px-4 py-4 text-slate-600 outline-none">
                    </div>
                </div>

                <aside class="space-y-5">
                    <section class="rounded-[2rem] bg-indigo-50 p-5">
                        <p class="text-sm font-bold text-slate-600">Services</p>
                        <div class="mt-3 grid gap-3">
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl bg-white p-4 font-bold"><input type="checkbox" name="services[]" value="Sound" class="h-5 w-5 rounded border-purple-200 text-primary focus:ring-primary">Sound</label>
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl bg-white p-4 font-bold"><input type="checkbox" name="services[]" value="Decoration" class="h-5 w-5 rounded border-purple-200 text-primary focus:ring-primary">Decoration</label>
                            <label class="flex cursor-pointer items-center gap-3 rounded-2xl bg-white p-4 font-bold"><input type="checkbox" name="services[]" value="Lights" class="h-5 w-5 rounded border-purple-200 text-primary focus:ring-primary">Lights</label>
                        </div>
                    </section>
                    <section class="rounded-[2rem] bg-white p-5 shadow-sm">
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Availability</p>
                        <div class="mt-4 rounded-2xl bg-indigo-50 p-4 text-sm font-semibold text-slate-600" data-availability-status>
                            Select a date, time range, and venue to check availability.
                        </div>
                    </section>
                    <button type="submit" name="reserve" class="w-full rounded-2xl bg-gradient-to-r from-primary to-secondary px-6 py-5 text-lg font-semibold text-white shadow-soft">
                        Submit Reservation
                    </button>
                </aside>
            </form>
        </div>
    </div>

    <div id="calendarModal" class="dashboard-modal fixed inset-0 z-50 hidden overflow-y-auto bg-dark/60 p-4 backdrop-blur-sm" data-dashboard-modal="calendar" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="calendarModalTitle" tabindex="-1">
        <div class="dashboard-modal-panel mx-auto my-6 w-full max-w-7xl rounded-[2rem] bg-white p-5 shadow-soft sm:p-8" data-modal-panel>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Availability</p>
                    <h2 id="calendarModalTitle" class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Event Calendar</h2>
                </div>
                <button type="button" class="rounded-xl px-3 py-2 font-semibold text-primary hover:bg-purple-50" data-dashboard-modal-close>Close</button>
            </div>

            <div class="mt-6 rounded-2xl bg-indigo-50 p-5 text-center text-sm font-semibold text-primary" data-calendar-loading>
                Loading calendar...
            </div>
            <section class="mt-6 grid gap-6 xl:grid-cols-[1fr_360px]">
                <div>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <h3 id="calendarMonth" class="text-3xl font-semibold tracking-tight sm:text-5xl"></h3>
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

                <aside class="rounded-[2rem] bg-indigo-50 p-6">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Selected Date</p>
                    <h3 id="selectedDateTitle" class="mt-2 text-3xl font-semibold">Events</h3>
                    <div id="selectedDateEvents" class="mt-6 space-y-4"></div>
                </aside>
            </section>
        </div>
    </div>

    <div id="myReservationsModal" class="dashboard-modal fixed inset-0 z-50 hidden overflow-y-auto bg-dark/60 p-4 backdrop-blur-sm" data-dashboard-modal="reservations" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="reservationsModalTitle" tabindex="-1">
        <div class="dashboard-modal-panel mx-auto my-6 w-full max-w-6xl rounded-[2rem] bg-white p-5 shadow-soft sm:p-8" data-modal-panel>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Bookings</p>
                    <h2 id="reservationsModalTitle" class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">My Reservation</h2>
                </div>
                <button type="button" class="rounded-xl px-3 py-2 font-semibold text-primary hover:bg-purple-50" data-dashboard-modal-close>Close</button>
            </div>

            <div class="mt-6 rounded-2xl bg-indigo-50 p-5 text-center text-sm font-semibold text-primary" data-reservations-loading>
                Loading reservations...
            </div>
            <div data-reservations-content></div>
        </div>
    </div>

    <div id="pricingModal" class="dashboard-modal fixed inset-0 z-50 hidden overflow-y-auto bg-dark/60 p-4 backdrop-blur-sm" data-dashboard-modal="pricing" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="pricingModalTitle" tabindex="-1">
        <div class="dashboard-modal-panel mx-auto my-6 w-full max-w-6xl rounded-[2rem] bg-white p-5 shadow-soft sm:p-8" data-modal-panel>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Event Pricing</p>
                    <h2 id="pricingModalTitle" class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl">Choose an Event Package</h2>
                    <p class="mt-3 max-w-2xl text-slate-600">View inclusions, sample menus, and starting prices before creating your reservation.</p>
                </div>
                <button type="button" class="rounded-xl px-3 py-2 font-semibold text-primary hover:bg-purple-50" data-dashboard-modal-close>Close</button>
            </div>

            <?php
            $package_ids_by_tier = [];
            foreach($active_packages as $package) {
                $package_ids_by_tier[strtolower($package['name'])] = (int) $package['id'];
            }
            ?>
            <div class="mt-8 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                <?php foreach(eventify_marketing_package_cards() as $card): ?>
                    <?php $package_id = $package_ids_by_tier[strtolower($card['tier'])] ?? 0; ?>
                    <article class="overflow-hidden rounded-[2rem] <?php echo $card['popular'] ? 'border-2 border-primary bg-white shadow-soft' : 'border border-purple-100 bg-white shadow-sm'; ?>">
                        <div class="relative h-44 overflow-hidden bg-indigo-50">
                            <img class="h-full w-full object-cover" src="<?php echo htmlspecialchars($card['image'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($card['event'] . ' ' . $card['tier'], ENT_QUOTES); ?>">
                            <span class="absolute left-4 top-4 rounded-full bg-white/90 px-3 py-1 text-xs font-bold uppercase tracking-wider text-primary shadow-sm"><?php echo htmlspecialchars($card['event']); ?></span>
                        </div>
                        <div class="bg-indigo-50 p-6">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary"><?php echo htmlspecialchars($card['tier']); ?></p>
                                <?php if($card['popular']): ?>
                                    <span class="rounded-full bg-primary px-3 py-1 text-xs font-bold uppercase tracking-wider text-white">Most Popular</span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-4 flex flex-wrap items-end gap-2">
                                <span class="text-4xl font-semibold text-dark">&#8369;<?php echo htmlspecialchars($card['price']); ?></span>
                                <span class="pb-1 text-sm text-slate-500">package price</span>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-slate-600"><?php echo htmlspecialchars($card['description']); ?></p>
                        </div>
                        <div class="p-6">
                            <h3 class="text-xl font-semibold">Inclusions</h3>
                            <ul class="mt-4 space-y-3 text-sm text-slate-600">
                                <?php foreach($card['features'] as $feature): ?>
                                    <li><?php echo htmlspecialchars($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <div class="border-t border-purple-100 p-6">
                            <button type="button" data-pricing-choice="<?php echo (int) $package_id; ?>" data-pricing-event="<?php echo htmlspecialchars($card['event'], ENT_QUOTES); ?>" class="w-full rounded-2xl bg-gradient-to-r from-primary to-secondary px-5 py-4 font-semibold text-white shadow-soft">Choose Package</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="reservationDetailsModal" class="dashboard-modal fixed inset-0 z-[70] hidden grid place-items-center overflow-y-auto bg-dark/50 p-4" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="reservationDetailsTitle" tabindex="-1">
        <div class="dashboard-modal-panel mx-auto w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-[2rem] bg-white p-6 pb-10 shadow-soft" data-modal-panel>
            <div class="sticky top-0 z-10 flex items-start justify-between gap-4 bg-white pb-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Reservation Details</p>
                    <h2 id="reservationDetailsTitle" class="mt-2 text-3xl font-semibold"></h2>
                </div>
                <button type="button" class="rounded-xl px-3 py-2 font-semibold text-primary hover:bg-purple-50" data-reservation-close>Close</button>
            </div>
            <div id="modalDetails" class="mt-6 grid gap-4 sm:grid-cols-2"></div>
        </div>
    </div>

    <?php echo eventify_package_price_script($conn); ?>
    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/client.js?v=no-package-time-1"></script>
</body>
</html>
