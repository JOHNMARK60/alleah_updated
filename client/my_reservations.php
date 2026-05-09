<?php
session_start();
include '../config/db.php';

eventify_require_role('client');

$user_id = eventify_current_user_id();
$stmt = $conn->prepare("SELECT * FROM reservations WHERE user_id=? ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$reservations = [];
$activeBookings = 0;
$waitlisted = 0;
$totalSpent = 0;
$today = date('Y-m-d');

while($row = $result->fetch_assoc()){
    $reservations[] = $row;
    $status = strtolower($row['status']);

    if($status === 'pending'){
        $waitlisted++;
    }

    if($row['event_date'] >= $today && $status !== 'cancelled' && $status !== 'rejected'){
        $activeBookings++;
    }

    $totalSpent += (float) $row['budget'];
}

function reservation_badge_class($status) {
    $status = strtolower($status);

    if($status === 'approved') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if($status === 'pending') {
        return 'bg-amber-100 text-amber-700';
    }

    if($status === 'cancelled' || $status === 'rejected') {
        return 'bg-slate-200 text-slate-600';
    }

    return 'bg-purple-100 text-primary';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations | Eventify</title>
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
            <a href="dashboard.php" class="font-semibold text-primary">Menu</a>
            <a href="dashboard.php" class="text-xl font-semibold">Eventify</a>
            <?php echo eventify_notification_widget($conn, 'client'); ?>
        </div>
    </header>

    <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="max-w-2xl">
            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Bookings</p>
            <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-6xl">My Reservations</h1>
            <p class="mt-4 text-lg leading-8 text-slate-600">Manage upcoming event experiences, review status updates, and view booking details.</p>
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_320px]">
            <section>
                <div class="grid max-w-md grid-cols-2 rounded-2xl bg-indigo-50 p-1">
                    <button type="button" class="rounded-xl bg-white py-3 font-semibold text-primary shadow-sm" data-reservation-tab="upcoming">Upcoming</button>
                    <button type="button" class="rounded-xl py-3 font-semibold text-slate-500" data-reservation-tab="past">Past Events</button>
                </div>

                <div class="mt-6 grid gap-6 md:grid-cols-2">
                    <?php foreach($reservations as $index => $row): ?>
                        <?php
                        $isPast = $row['event_date'] < $today;
                        $image = eventify_package_image_for_reservation($row['event_type'] ?? '', $row['package_type'] ?? '');
                        ?>
                        <article class="reservation-card <?php echo $isPast ? 'hidden' : ''; ?> rounded-[2rem] bg-white p-5 shadow-soft" data-reservation-group="<?php echo $isPast ? 'past' : 'upcoming'; ?>">
                            <img class="h-48 w-full rounded-2xl object-cover <?php echo $isPast ? 'grayscale' : ''; ?>" src="<?php echo $image; ?>" alt="Event reservation">
                            <div class="mt-5 flex items-start justify-between gap-3">
                                <h2 class="text-2xl font-semibold leading-tight"><?php echo htmlspecialchars($row['event_name']); ?></h2>
                                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold <?php echo reservation_badge_class($row['status']); ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-2 gap-4 text-sm text-slate-600">
                                <p><span class="block font-semibold text-dark">Date</span><?php echo htmlspecialchars($row['event_date']); ?></p>
                                <p><span class="block font-semibold text-dark">Venue</span><?php echo htmlspecialchars($row['venue']); ?></p>
                            </div>

                            <div class="mt-6 flex items-center gap-3">
                                <button type="button"
                                    class="rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-white"
                                    data-reservation-view
                                    data-title="<?php echo htmlspecialchars($row['event_name'], ENT_QUOTES); ?>"
                                    data-type="<?php echo htmlspecialchars($row['event_type'], ENT_QUOTES); ?>"
                                    data-date="<?php echo htmlspecialchars($row['event_date'], ENT_QUOTES); ?>"
                                    data-time="<?php echo htmlspecialchars($row['event_time'], ENT_QUOTES); ?>"
                                    data-venue="<?php echo htmlspecialchars($row['venue'], ENT_QUOTES); ?>"
                                    data-guests="<?php echo htmlspecialchars($row['guest'], ENT_QUOTES); ?>"
                                    data-client="<?php echo htmlspecialchars($row['client_name'], ENT_QUOTES); ?>"
                                    data-contact="<?php echo htmlspecialchars($row['client_contact'], ENT_QUOTES); ?>"
                                    data-package="<?php echo htmlspecialchars($row['package_type'], ENT_QUOTES); ?>"
                                    data-budget="<?php echo htmlspecialchars($row['budget'], ENT_QUOTES); ?>"
                                    data-services="<?php echo htmlspecialchars(eventify_clean_services($row['services']), ENT_QUOTES); ?>"
                                    data-status="<?php echo htmlspecialchars($row['status'], ENT_QUOTES); ?>"
                                    data-created="<?php echo htmlspecialchars($row['created_at'] ?? '', ENT_QUOTES); ?>"
                                    data-approved="<?php echo htmlspecialchars($row['approved_at'] ?? '', ENT_QUOTES); ?>"
                                    data-rejected="<?php echo htmlspecialchars($row['rejected_at'] ?? '', ENT_QUOTES); ?>"
                                    data-cancelled="<?php echo htmlspecialchars($row['cancelled_at'] ?? '', ENT_QUOTES); ?>"
                                    data-updated="<?php echo htmlspecialchars($row['updated_at'] ?? '', ENT_QUOTES); ?>">
                                    View Details
                                </button>
                                <?php if(strtolower($row['status']) === 'pending'): ?>
                                    <a href="edit_reservation.php?id=<?php echo (int) $row['id']; ?>" class="grid h-11 w-11 place-items-center rounded-xl border border-purple-100 font-semibold text-primary hover:bg-purple-50" title="Edit reservation">Edit</a>
                                <?php endif; ?>
                                <?php if(!in_array(strtolower($row['status']), ['cancelled', 'rejected'], true)): ?>
                                    <form method="POST" action="cancel.php" data-confirm-form data-confirm-message="Cancel this reservation?">
                                        <?php echo eventify_csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="grid h-11 w-11 place-items-center rounded-xl border border-red-100 font-semibold text-red-600 hover:bg-red-50" title="Cancel reservation">X</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <?php if(empty($reservations)): ?>
                    <div class="mt-6 rounded-[2rem] bg-white p-8 text-center shadow-soft">
                        <h2 class="text-2xl font-semibold">No reservations yet</h2>
                        <p class="mt-2 text-slate-600">Your bookings will appear here after you submit a request.</p>
                        <a href="reservation.php" class="mt-5 inline-flex rounded-2xl bg-primary px-5 py-3 font-semibold text-white">Create Reservation</a>
                    </div>
                <?php endif; ?>
            </section>

            <aside class="space-y-6">
                <section class="rounded-[2rem] bg-white p-6 shadow-soft">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Reservation Summary</p>
                    <div class="mt-5 space-y-4 text-sm">
                        <div class="flex justify-between"><span>Active Bookings</span><strong><?php echo str_pad($activeBookings, 2, '0', STR_PAD_LEFT); ?></strong></div>
                        <div class="flex justify-between"><span>Waitlisted</span><strong><?php echo str_pad($waitlisted, 2, '0', STR_PAD_LEFT); ?></strong></div>
                        <div class="border-t border-purple-100 pt-4 flex justify-between"><span>Total Spent</span><strong class="text-primary">&#8369;<?php echo number_format((float) $totalSpent, 2); ?></strong></div>
                    </div>
                </section>

                <section class="relative overflow-hidden rounded-[2rem] bg-primary p-6 text-white shadow-soft">
                    <img class="absolute inset-0 h-full w-full object-cover opacity-45" src="https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?auto=format&fit=crop&w=900&q=80" alt="Recommended event">
                    <div class="relative min-h-80 content-end">
                        <p class="text-xs font-semibold uppercase tracking-[0.25em] text-white/80">Recommended for you</p>
                        <h2 class="mt-2 text-2xl font-semibold">Art and Design Expo</h2>
                        <a href="reservation.php" class="mt-5 inline-flex rounded-2xl bg-white px-5 py-3 font-semibold text-primary">Book Now</a>
                    </div>
                </section>
            </aside>
        </div>
    </main>

    <div id="reservationModal" class="fixed inset-0 z-50 hidden grid place-items-center overflow-y-auto bg-dark/50 p-4">
        <div class="mx-auto w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-[2rem] bg-white p-6 pb-10 shadow-soft">
            <div class="sticky top-0 z-10 flex items-start justify-between gap-4 bg-white pb-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Reservation Details</p>
                    <h2 id="modalTitle" class="mt-2 text-3xl font-semibold"></h2>
                </div>
                <button type="button" class="rounded-xl px-3 py-2 font-semibold text-primary hover:bg-purple-50" data-reservation-close>Close</button>
            </div>
            <div id="modalDetails" class="mt-6 grid gap-4 sm:grid-cols-2"></div>
        </div>
    </div>

    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/client.js"></script>
</body>
</html>
