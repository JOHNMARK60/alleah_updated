<?php
session_start();
include '../config/db.php';

eventify_require_role('admin');

$errors = [];
$categories = ['Wedding', 'Birthday'];
$tiers = ['Basic', 'Standard', 'Premium'];

function eventify_gallery_upload_file($file) {
    if (empty($file['name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('A gallery photo failed to upload.');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($file['tmp_name']);

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Gallery photos must be JPG, PNG, or WebP files.');
    }

    if ((int) $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Each gallery photo must be 5MB or smaller.');
    }

    $directory = __DIR__ . '/../uploads/gallery';
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $filename = 'gallery-' . date('YmdHis') . '-' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
    $target = $directory . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Gallery photo could not be saved.');
    }

    return 'uploads/gallery/' . $filename;
}

function eventify_gallery_uploaded_files($files) {
    $normalized = [];

    foreach (($files['name'] ?? []) as $index => $name) {
        $normalized[] = [
            'name' => $name,
            'type' => $files['type'][$index] ?? '',
            'tmp_name' => $files['tmp_name'][$index] ?? '',
            'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$index] ?? 0,
        ];
    }

    return $normalized;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['gallery_action'] ?? '';

    if (!eventify_verify_csrf()) {
        $errors[] = 'Security check failed. Please try again.';
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare("SELECT image_path FROM event_gallery_photos WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $photo = $stmt->get_result()->fetch_assoc();

        $stmt = $conn->prepare("DELETE FROM event_gallery_photos WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($photo && !empty($photo['image_path'])) {
            $path = realpath(__DIR__ . '/../' . $photo['image_path']);
            $uploads = realpath(__DIR__ . '/../uploads/gallery');

            if ($path && $uploads && strpos($path, $uploads) === 0 && is_file($path)) {
                unlink($path);
            }
        }

        eventify_log_activity($conn, 'gallery.deleted', 'Gallery photo #' . $id);
        eventify_set_flash('success', 'Photo deleted', 'The gallery photo was removed.');
        header('Location: gallery.php');
        exit();
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE event_gallery_photos SET is_active = IF(is_active=1, 0, 1) WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        eventify_log_activity($conn, 'gallery.toggled', 'Gallery photo #' . $id);
        eventify_set_flash('success', 'Photo updated', 'The gallery visibility was changed.');
        header('Location: gallery.php');
        exit();
    } else {
        $category = trim($_POST['event_category'] ?? '');
        $tier = trim($_POST['package_tier'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $sort_order = (int) ($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (!in_array($category, $categories, true)) {
            $errors[] = 'Please select a valid event category.';
        }

        if (!in_array($tier, $tiers, true)) {
            $errors[] = 'Please select a valid package tier.';
        }

        if ($title === '') {
            $errors[] = 'Photo title is required.';
        }

        $uploaded_files = eventify_gallery_uploaded_files($_FILES['photos'] ?? []);
        $uploaded_files = array_filter($uploaded_files, function ($file) {
            return (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
        });

        if (empty($uploaded_files)) {
            $errors[] = 'Please choose at least one gallery photo.';
        }

        if (empty($errors)) {
            try {
                $conn->begin_transaction();
                $stmt = $conn->prepare("
                    INSERT INTO event_gallery_photos (event_category, package_tier, title, image_path, sort_order, is_active)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                foreach ($uploaded_files as $index => $file) {
                    $image_path = eventify_gallery_upload_file($file);
                    $photo_title = count($uploaded_files) > 1 ? $title . ' ' . ($index + 1) : $title;
                    $photo_order = $sort_order + $index;
                    $stmt->bind_param("ssssii", $category, $tier, $photo_title, $image_path, $photo_order, $is_active);
                    $stmt->execute();
                }

                $conn->commit();
                eventify_log_activity($conn, 'gallery.uploaded', $category . ' ' . $tier . ' gallery photos');
                eventify_set_flash('success', 'Photos uploaded', 'The gallery photos are now available on the landing page.');
                header('Location: gallery.php');
                exit();
            } catch (Throwable $error) {
                $conn->rollback();
                $errors[] = $error->getMessage();
            }
        }
    }
}

$gallery_photos = eventify_get_gallery_photos($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery | Eventify Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php echo eventify_sweetalert_assets(); ?>
    <script>
    tailwind.config = { theme: { extend: { colors: { primary: '#7C00D8', secondary: '#A855F7', soft: '#F6F3FF', dark: '#111827' }, boxShadow: { soft: '0 15px 35px rgba(124, 0, 216, 0.15)' } } } }
    </script>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="bg-soft text-dark">
    <div class="min-h-screen lg:flex">
        <aside class="hidden w-72 shrink-0 flex-col border-r border-purple-100 bg-dark p-6 text-white lg:flex">
            <a href="dashboard.php" class="flex items-center gap-3 text-2xl font-semibold"><span class="grid h-10 w-10 place-items-center rounded-2xl bg-primary">E</span>Eventify Admin</a>
            <nav class="mt-10 grid gap-2">
                <a href="dashboard.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10">Dashboard</a>
                <a href="reservations.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10">Reservations</a>
                <a href="packages.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10">Packages</a>
                <a href="gallery.php" class="rounded-2xl bg-white/10 px-4 py-3 font-bold text-white">Gallery</a>
                <a href="users.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10">Users</a>
                <a href="calendar.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10">Calendar</a>
                <a href="add_event.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10">Add Event</a>
            </nav>
            <a href="../auth/logout.php" class="mt-auto rounded-2xl border border-white/10 px-4 py-3 text-center font-bold text-white/75 hover:bg-white/10">Logout</a>
        </aside>

        <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Landing Page</p>
                        <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Gallery Management</h1>
                    </div>
                    <?php echo eventify_notification_widget($conn, 'admin'); ?>
                </div>

                <?php if(!empty($errors)): ?>
                    <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700"><?php echo htmlspecialchars($errors[0]); ?></div>
                <?php endif; ?>

                <div class="mt-8 grid gap-6 xl:grid-cols-[420px_1fr]">
                    <form method="POST" enctype="multipart/form-data" class="rounded-[2rem] bg-white p-6 shadow-soft" data-loading-form>
                        <?php echo eventify_csrf_field(); ?>
                        <input type="hidden" name="gallery_action" value="upload">
                        <h2 class="text-2xl font-semibold">Upload Photos</h2>

                        <div class="mt-6 space-y-5">
                            <div>
                                <label class="text-sm font-bold text-slate-600">Event Category</label>
                                <select name="event_category" required class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                                    <?php foreach($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category, ENT_QUOTES); ?>"><?php echo htmlspecialchars($category); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-bold text-slate-600">Package Tier</label>
                                <select name="package_tier" required class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                                    <?php foreach($tiers as $tier): ?>
                                        <option value="<?php echo htmlspecialchars($tier, ENT_QUOTES); ?>"><?php echo htmlspecialchars($tier); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm font-bold text-slate-600">Photo Title</label>
                                <input name="title" required placeholder="Wedding Premium Reception" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                            </div>
                            <div>
                                <label class="text-sm font-bold text-slate-600">Photos</label>
                                <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 text-sm">
                                <p class="mt-2 text-xs font-semibold text-slate-500">You can upload many photos at once. JPG, PNG, or WebP only. Max 5MB each.</p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="flex items-center gap-3 rounded-2xl bg-indigo-50 p-4 font-bold"><input type="checkbox" name="is_active" value="1" checked class="h-5 w-5 rounded border-purple-200 text-primary focus:ring-primary">Active</label>
                                <input type="number" name="sort_order" value="0" class="rounded-2xl border border-purple-100 bg-indigo-50 px-4 py-4" aria-label="Sort order">
                            </div>
                        </div>

                        <button type="submit" class="mt-6 w-full rounded-2xl bg-gradient-to-r from-primary to-secondary px-6 py-4 font-semibold text-white shadow-soft">Upload Gallery Photos</button>
                    </form>

                    <section class="overflow-hidden rounded-[2rem] bg-white shadow-soft">
                        <div class="border-b border-purple-100 p-6">
                            <h2 class="text-2xl font-semibold">Current Gallery Photos</h2>
                        </div>

                        <?php if(!empty($gallery_photos)): ?>
                            <div class="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
                                <?php foreach($gallery_photos as $photo): ?>
                                    <article class="overflow-hidden rounded-3xl border border-purple-100 bg-white shadow-sm">
                                        <div class="h-48 bg-indigo-50">
                                            <img src="../<?php echo htmlspecialchars($photo['image_path'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($photo['title'], ENT_QUOTES); ?>" class="h-full w-full object-cover">
                                        </div>
                                        <div class="p-5">
                                            <div class="flex flex-wrap gap-2">
                                                <span class="rounded-full bg-purple-100 px-3 py-1 text-xs font-bold text-primary"><?php echo htmlspecialchars($photo['event_category']); ?></span>
                                                <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-slate-600"><?php echo htmlspecialchars($photo['package_tier']); ?></span>
                                                <span class="rounded-full px-3 py-1 text-xs font-bold <?php echo (int) $photo['is_active'] === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'; ?>"><?php echo (int) $photo['is_active'] === 1 ? 'Active' : 'Hidden'; ?></span>
                                            </div>
                                            <h3 class="mt-4 text-lg font-semibold"><?php echo htmlspecialchars($photo['title']); ?></h3>
                                            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Sort <?php echo (int) $photo['sort_order']; ?></p>
                                            <div class="mt-5 flex flex-wrap gap-2">
                                                <form method="POST">
                                                    <?php echo eventify_csrf_field(); ?>
                                                    <input type="hidden" name="gallery_action" value="toggle">
                                                    <input type="hidden" name="id" value="<?php echo (int) $photo['id']; ?>">
                                                    <button type="submit" class="rounded-xl border border-purple-100 px-4 py-3 font-semibold text-primary hover:bg-purple-50"><?php echo (int) $photo['is_active'] === 1 ? 'Hide' : 'Show'; ?></button>
                                                </form>
                                                <form method="POST" data-confirm-form data-confirm-message="Delete this gallery photo?">
                                                    <?php echo eventify_csrf_field(); ?>
                                                    <input type="hidden" name="gallery_action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo (int) $photo['id']; ?>">
                                                    <button type="submit" class="rounded-xl bg-red-600 px-4 py-3 font-semibold text-white">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="p-8 text-center text-slate-600">No uploaded gallery photos yet. The homepage will use the default sample photos until you upload some.</div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/admin.js"></script>
</body>
</html>
