<?php
session_start();
include '../config/db.php';

$errors = [];

eventify_require_role('admin');

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    if(!eventify_verify_csrf()) {
        $errors[] = "Security check failed. Please try again.";
    } else {
        $payload = eventify_prepare_reservation_payload($conn, eventify_reservation_payload_from_post());
        $errors = eventify_validate_reservation_data($conn, $payload);
    }

    if(!empty($errors)) {
        eventify_set_flash('error', 'Event was not saved', $errors[0]);
    } elseif(eventify_event_conflict_exists($conn, $payload['event_date'], $payload['start_time'], $payload['end_time'], $payload['venue'])) {
        $errors[] = "Another approved event already overlaps this venue and time window.";
        eventify_set_flash('error', 'Slot already booked', $errors[0]);
    } else {
        $budget = $payload['calculated_budget'];

        $stmt = $conn->prepare("INSERT INTO events
        (event_name, event_type, event_date, event_time, end_time, venue, guests, client_name, client_contact, package_id, package_type, budget, services)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("ssssssissisds",
            $payload['event_name'],
            $payload['event_type'],
            $payload['event_date'],
            $payload['start_time'],
            $payload['end_time'],
            $payload['venue'],
            $payload['guests'],
            $payload['client_name'],
            $payload['client_contact'],
            $payload['package_id'],
            $payload['package_type'],
            $budget,
            $payload['services']
        );

        if($stmt->execute()){
            eventify_sync_event_services($conn, (int) $conn->insert_id, $payload['services']);
            eventify_set_flash('success', 'Event added', 'The event was saved successfully.');
            header("Location: add_event.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Event | Eventify Admin</title>
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
                <a href="calendar.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Calendar</a>
                <a href="add_event.php" class="rounded-2xl bg-white/10 px-4 py-3 font-bold text-white">Add Event</a>
            </nav>
            <a href="../auth/logout.php" class="mt-auto rounded-2xl border border-white/10 px-4 py-3 text-center font-bold text-white/75 hover:bg-white/10">Logout</a>
        </aside>

        <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <div class="mx-auto max-w-5xl">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Admin Scheduler</p>
                        <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Add Event</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <?php echo eventify_notification_widget($conn, 'admin'); ?>
                        <a href="calendar.php" class="rounded-2xl border border-purple-100 bg-white px-5 py-3 font-semibold text-primary hover:bg-purple-50">View Calendar</a>
                    </div>
                </div>

                <?php if(!empty($errors)): ?>
                    <div class="mt-8 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
                        <?php echo htmlspecialchars($errors[0]); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="mt-8 rounded-[2rem] bg-white p-6 shadow-soft sm:p-8" data-package-budget-form data-loading-form>
                    <?php echo eventify_csrf_field(); ?>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="text-sm font-bold text-slate-600">Event Name</label>
                            <input type="text" name="event_name" required value="<?php echo htmlspecialchars($_POST['event_name'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Event Type</label>
                            <input type="text" name="event_type" required value="<?php echo htmlspecialchars($_POST['event_type'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Package</label>
                            <select name="package_id" required data-package-select class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                                <?php echo eventify_package_options_html($conn, (int) ($_POST['package_id'] ?? $_POST['package_type'] ?? 0)); ?>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Date</label>
                            <input type="date" name="event_date" min="<?php echo date('Y-m-d'); ?>" required value="<?php echo htmlspecialchars($_POST['event_date'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Start Time</label>
                            <input type="time" name="start_time" required value="<?php echo htmlspecialchars($_POST['start_time'] ?? $_POST['event_time'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">End Time</label>
                            <input type="time" name="end_time" required value="<?php echo htmlspecialchars($_POST['end_time'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Venue</label>
                            <input type="text" name="venue" required value="<?php echo htmlspecialchars($_POST['venue'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Guests</label>
                            <input type="number" name="guests" min="1" required value="<?php echo htmlspecialchars($_POST['guests'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Client Name</label>
                            <input type="text" name="client_name" required value="<?php echo htmlspecialchars($_POST['client_name'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Client Contact</label>
                            <input type="text" name="client_contact" pattern="[0-9+\-\s()]{7,20}" required value="<?php echo htmlspecialchars($_POST['client_contact'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Budget</label>
                            <input type="number" name="budget" readonly data-budget-input value="<?php echo htmlspecialchars($_POST['budget'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-slate-100 px-4 py-4 text-slate-600 outline-none">
                        </div>

                        <div class="sm:col-span-2">
                            <p class="text-sm font-bold text-slate-600">Services</p>
                            <div class="mt-3 grid gap-3 sm:grid-cols-4">
                                <label class="flex cursor-pointer items-center gap-3 rounded-2xl bg-indigo-50 p-4 font-bold hover:bg-purple-50"><input type="checkbox" name="services[]" value="Sound" class="h-5 w-5 rounded border-purple-200 text-primary focus:ring-primary">Sound</label>
                                <label class="flex cursor-pointer items-center gap-3 rounded-2xl bg-indigo-50 p-4 font-bold hover:bg-purple-50"><input type="checkbox" name="services[]" value="Decoration" class="h-5 w-5 rounded border-purple-200 text-primary focus:ring-primary">Decoration</label>
                                <label class="flex cursor-pointer items-center gap-3 rounded-2xl bg-indigo-50 p-4 font-bold hover:bg-purple-50"><input type="checkbox" name="services[]" value="Lights" class="h-5 w-5 rounded border-purple-200 text-primary focus:ring-primary">Lights</label>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="save_event" class="mt-8 w-full rounded-2xl bg-gradient-to-r from-primary to-secondary px-6 py-4 text-lg font-semibold text-white shadow-soft">
                        Save Event
                    </button>
                </form>
            </div>
        </main>
    </div>
    <?php echo eventify_package_price_script($conn); ?>
    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/admin.js"></script>
</body>
</html>
