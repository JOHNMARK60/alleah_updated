<?php
session_start();
include '../config/db.php';

$errors = [];
$today = date('Y-m-d');

eventify_require_role('client');

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $redirect = ($_POST['redirect'] ?? '') === 'dashboard' ? 'dashboard.php' : 'reservation.php';

    if(!eventify_verify_csrf()) {
        $errors[] = "Security check failed. Please try again.";
    } else {
        $payload = eventify_prepare_reservation_payload($conn, eventify_reservation_payload_from_post());
        $errors = eventify_validate_reservation_data($conn, $payload);
    }

    if(empty($errors) && !eventify_slot_available($conn, $payload['event_date'], $payload['start_time'], $payload['end_time'], $payload['venue'])) {
        $errors[] = "That venue is already reserved during the selected time window.";
    }

    if(!empty($errors)) {
        eventify_set_flash('error', 'Reservation failed', $errors[0]);
    } else {
        $user_id = eventify_current_user_id();
        $budget = $payload['calculated_budget'];
        $booking_reference = eventify_generate_booking_reference($conn);

        // Insert reservation request for admin approval.
        $stmt = $conn->prepare("INSERT INTO reservations
        (user_id, booking_reference, event_name, event_type, event_date, event_time, end_time, venue, guest, client_name, client_contact, package_id, package_type, budget, services)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param("isssssssissisds",
            $user_id,
            $booking_reference,
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
            $reservation_id = (int) $conn->insert_id;
            eventify_sync_reservation_services($conn, $reservation_id, $payload['services']);
            eventify_create_notification(
                $conn,
                null,
                'admin',
                'New reservation request',
                'New reservation request from ' . $payload['client_name'] . ' for ' . $payload['event_type'] . '.'
            );
            eventify_record_status_history($conn, $reservation_id, null, 'Pending', 'Reservation submitted by client.');
            eventify_log_activity($conn, 'reservation.created', 'Reference ' . $booking_reference);
            eventify_prepare_email_notification('admin@eventify.com', 'New reservation submitted', 'A client submitted a new Eventify reservation for review.');
            eventify_set_flash('success', 'Reservation submitted', 'Waiting for admin approval.');
            header("Location: $redirect");
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
    <title>New Reservation | Eventify</title>
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
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <a href="dashboard.php" class="font-semibold text-primary">Back</a>
            <h1 class="text-xl font-semibold sm:text-2xl">New Reservation</h1>
            <?php echo eventify_notification_widget($conn, 'client'); ?>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <?php if(!empty($errors)): ?>
            <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700">
                <?php echo htmlspecialchars($errors[0]); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="grid gap-6 lg:grid-cols-[1fr_0.85fr]" data-package-budget-form data-loading-form>
            <?php echo eventify_csrf_field(); ?>
            <div class="space-y-6">
                <!-- Event Info -->
                <section class="rounded-[2rem] bg-white p-6 shadow-soft">
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 place-items-center rounded-2xl bg-purple-100 font-semibold text-primary">EV</span>
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Event Info</p>
                            <h2 class="text-2xl font-semibold">Tell us about the event</h2>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="text-sm font-bold text-slate-600">Event Name</label>
                            <input type="text" name="event_name" required value="<?php echo htmlspecialchars($_POST['event_name'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Event Type</label>
                            <input type="text" name="event_type" required placeholder="Corporate gala, product launch, private celebration" value="<?php echo htmlspecialchars($_POST['event_type'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Package</label>
                            <select name="package_id" required data-package-select class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                                <?php echo eventify_package_options_html($conn, (int) ($_POST['package_id'] ?? $_POST['package_type'] ?? 0)); ?>
                            </select>
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Date</label>
                            <input type="date" name="event_date" min="<?php echo $today; ?>" required value="<?php echo htmlspecialchars($_POST['event_date'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Start Time</label>
                            <input type="time" name="start_time" required data-availability-field value="<?php echo htmlspecialchars($_POST['start_time'] ?? $_POST['event_time'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">End Time</label>
                            <input type="time" name="end_time" required data-availability-field value="<?php echo htmlspecialchars($_POST['end_time'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Venue</label>
                            <input type="text" name="venue" required data-availability-field value="<?php echo htmlspecialchars($_POST['venue'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Guests</label>
                            <input type="number" name="guests" min="1" required value="<?php echo htmlspecialchars($_POST['guests'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>
                    </div>
                </section>

                <!-- Client Info -->
                <section class="rounded-[2rem] bg-white p-6 shadow-soft">
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 place-items-center rounded-2xl bg-purple-100 font-semibold text-primary">CL</span>
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Client Info</p>
                            <h2 class="text-2xl font-semibold">Contact and budget</h2>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-5 sm:grid-cols-3">
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
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <!-- Services -->
                <section class="rounded-[2rem] bg-white p-6 shadow-soft">
                    <div class="flex items-center gap-3">
                        <span class="grid h-11 w-11 place-items-center rounded-2xl bg-purple-100 font-semibold text-primary">SV</span>
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Services</p>
                            <h2 class="text-2xl font-semibold">Select add-ons</h2>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4">
                        <label class="flex cursor-pointer items-center gap-4 rounded-2xl bg-indigo-50 p-4 font-bold hover:bg-purple-50">
                            <input type="checkbox" name="services[]" value="Sound" class="h-5 w-5 rounded border-purple-200 text-primary focus:ring-primary">
                            Sound
                        </label>
                        <label class="flex cursor-pointer items-center gap-4 rounded-2xl bg-indigo-50 p-4 font-bold hover:bg-purple-50">
                            <input type="checkbox" name="services[]" value="Decoration" class="h-5 w-5 rounded border-purple-200 text-primary focus:ring-primary">
                            Decoration
                        </label>
                        <label class="flex cursor-pointer items-center gap-4 rounded-2xl bg-indigo-50 p-4 font-bold hover:bg-purple-50">
                            <input type="checkbox" name="services[]" value="Lights" class="h-5 w-5 rounded border-purple-200 text-primary focus:ring-primary">
                            Lights
                        </label>
                    </div>
                </section>

                <section class="relative overflow-hidden rounded-[2rem] bg-dark shadow-soft">
                    <img class="h-72 w-full object-cover opacity-80" src="https://images.unsplash.com/photo-1519225421980-715cb0215aed?auto=format&fit=crop&w=900&q=80" alt="Event setup">
                    <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-dark to-transparent p-6 text-white">
                        <p class="text-xl font-semibold">Planning a polished experience for your guests.</p>
                    </div>
                </section>

                <section class="rounded-[2rem] bg-white p-6 shadow-soft">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Availability</p>
                    <div class="mt-4 rounded-2xl bg-indigo-50 p-4 text-sm font-semibold text-slate-600" data-availability-status>
                        Select a date, time range, and venue to check availability.
                    </div>
                </section>

                <button type="submit" name="reserve" class="w-full rounded-2xl bg-gradient-to-r from-primary to-secondary px-6 py-5 text-lg font-semibold text-white shadow-soft hover:scale-[1.01]">
                    Submit Reservation
                </button>
            </aside>
        </form>
    </main>
    <?php echo eventify_package_price_script($conn); ?>
    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/client.js?v=no-package-time-1"></script>
</body>
</html>
