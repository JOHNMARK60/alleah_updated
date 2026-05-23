<?php
session_start();
include '../config/db.php';

eventify_require_role('admin');

$errors = [];
$edit_id = (int) ($_GET['edit'] ?? 0);
$editing = $edit_id > 0 ? eventify_get_package($conn, $edit_id) : null;

function eventify_package_upload_path($file) {
    if (empty($file['name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Package image upload failed.');
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = mime_content_type($file['tmp_name']);

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Package image must be a JPG, PNG, or WebP file.');
    }

    if ((int) $file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('Package image must be 3MB or smaller.');
    }

    $directory = __DIR__ . '/../uploads/packages';
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $filename = 'package-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target = $directory . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Package image could not be saved.');
    }

    return 'uploads/packages/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['package_action'] ?? '';

    if (!eventify_verify_csrf()) {
        $errors[] = 'Security check failed. Please try again.';
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare("UPDATE event_packages SET deleted_at=NOW(), is_active=0 WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        eventify_log_activity($conn, 'package.deleted', 'Package #' . $id);
        eventify_set_flash('success', 'Package deleted', 'The package was removed from client booking.');
        header('Location: packages.php');
        exit();
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $sort_order = (int) ($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $features = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $_POST['features'] ?? ''))));

        if ($name === '') {
            $errors[] = 'Package name is required.';
        }

        if ($price <= 0) {
            $errors[] = 'Package price must be greater than zero.';
        }

        try {
            $image_path = eventify_package_upload_path($_FILES['image'] ?? []);
        } catch (RuntimeException $error) {
            $errors[] = $error->getMessage();
        }

        if (empty($errors)) {
            $slug = eventify_slugify($name);

            if ($id > 0) {
                $current = eventify_get_package($conn, $id);
                $image_path = $image_path ?: ($current['image_path'] ?? null);
                $stmt = $conn->prepare("
                    UPDATE event_packages
                    SET name=?, slug=?, description=?, price=?, image_path=?, is_active=?, sort_order=?
                    WHERE id=?
                ");
                $stmt->bind_param("sssdsiii", $name, $slug, $description, $price, $image_path, $is_active, $sort_order, $id);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO event_packages (name, slug, description, price, image_path, is_active, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssdsii", $name, $slug, $description, $price, $image_path, $is_active, $sort_order);
            }

            try {
                $conn->begin_transaction();
                $stmt->execute();
                $package_id = $id > 0 ? $id : $conn->insert_id;

                $stmt = $conn->prepare("DELETE FROM package_features WHERE package_id=?");
                $stmt->bind_param("i", $package_id);
                $stmt->execute();

                $stmt = $conn->prepare("INSERT INTO package_features (package_id, feature_text, sort_order) VALUES (?, ?, ?)");
                foreach ($features as $index => $feature) {
                    $order = $index + 1;
                    $stmt->bind_param("isi", $package_id, $feature, $order);
                    $stmt->execute();
                }

                $conn->commit();
                eventify_log_activity($conn, $id > 0 ? 'package.updated' : 'package.created', 'Package #' . $package_id);
                eventify_set_flash('success', $id > 0 ? 'Package updated' : 'Package created', 'Package details were saved.');
                header('Location: packages.php');
                exit();
            } catch (mysqli_sql_exception $error) {
                $conn->rollback();
                $errors[] = 'Package could not be saved. Use a unique package name.';
            }
        }
    }
}

$packages = eventify_get_packages($conn);
$feature_text = '';
if ($editing) {
    $feature_lines = [];
    foreach (eventify_get_package_features($conn, $edit_id) as $feature) {
        $feature_lines[] = $feature['feature_text'];
    }
    $feature_text = implode("\n", $feature_lines);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packages | Eventify Admin</title>
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
                <a href="packages.php" class="rounded-2xl bg-white/10 px-4 py-3 font-bold text-white">Packages</a>
                <a href="gallery.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10">Gallery</a>
                <a href="users.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10">Users</a>
                <a href="messages.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10">Messages</a>
                <a href="calendar.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10">Calendar</a>
                <a href="add_event.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10">Add Event</a>
            </nav>
            <a href="../auth/logout.php" class="mt-auto rounded-2xl border border-white/10 px-4 py-3 text-center font-bold text-white/75 hover:bg-white/10">Logout</a>
        </aside>

        <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Catalog</p>
                        <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Package Management</h1>
                    </div>
                    <?php echo eventify_notification_widget($conn, 'admin'); ?>
                </div>

                <?php if(!empty($errors)): ?>
                    <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-semibold text-red-700"><?php echo htmlspecialchars($errors[0]); ?></div>
                <?php endif; ?>

                <div class="mt-8 grid gap-6 xl:grid-cols-[420px_1fr]">
                    <form method="POST" enctype="multipart/form-data" class="rounded-[2rem] bg-white p-6 shadow-soft" data-loading-form>
                        <?php echo eventify_csrf_field(); ?>
                        <input type="hidden" name="package_action" value="save">
                        <input type="hidden" name="id" value="<?php echo (int) ($editing['id'] ?? 0); ?>">
                        <h2 class="text-2xl font-semibold"><?php echo $editing ? 'Edit Package' : 'Create Package'; ?></h2>

                        <div class="mt-6 space-y-5">
                            <div>
                                <label class="text-sm font-bold text-slate-600">Name</label>
                                <input name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? $editing['name'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                            </div>
                            <div>
                                <label class="text-sm font-bold text-slate-600">Price</label>
                                <input type="number" step="0.01" min="1" name="price" required value="<?php echo htmlspecialchars($_POST['price'] ?? $editing['price'] ?? '', ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                            </div>
                            <div>
                                <label class="text-sm font-bold text-slate-600">Description</label>
                                <textarea name="description" rows="4" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100"><?php echo htmlspecialchars($_POST['description'] ?? $editing['description'] ?? ''); ?></textarea>
                            </div>
                            <div>
                                <label class="text-sm font-bold text-slate-600">Features / Inclusions</label>
                                <textarea name="features" rows="6" placeholder="One inclusion per line" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100"><?php echo htmlspecialchars($_POST['features'] ?? $feature_text); ?></textarea>
                            </div>
                            <div>
                                <label class="text-sm font-bold text-slate-600">Image</label>
                                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 text-sm">
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="flex items-center gap-3 rounded-2xl bg-indigo-50 p-4 font-bold"><input type="checkbox" name="is_active" value="1" <?php echo (int) ($_POST['is_active'] ?? $editing['is_active'] ?? 1) === 1 ? 'checked' : ''; ?> class="h-5 w-5 rounded border-purple-200 text-primary focus:ring-primary">Active</label>
                                <input type="number" name="sort_order" value="<?php echo htmlspecialchars($_POST['sort_order'] ?? $editing['sort_order'] ?? 0, ENT_QUOTES); ?>" class="rounded-2xl border border-purple-100 bg-indigo-50 px-4 py-4" aria-label="Sort order">
                            </div>
                        </div>

                        <button type="submit" class="mt-6 w-full rounded-2xl bg-gradient-to-r from-primary to-secondary px-6 py-4 font-semibold text-white shadow-soft">Save Package</button>
                        <?php if($editing): ?>
                            <a href="packages.php" class="mt-3 block rounded-2xl border border-purple-100 px-6 py-4 text-center font-semibold text-primary">Cancel Edit</a>
                        <?php endif; ?>
                    </form>

                    <section class="overflow-hidden rounded-[2rem] bg-white shadow-soft">
                        <div class="border-b border-purple-100 p-6">
                            <h2 class="text-2xl font-semibold">Packages</h2>
                        </div>
                        <div class="divide-y divide-purple-50">
                            <?php if(!empty($packages)): ?>
                                <?php foreach($packages as $package): ?>
                                    <article class="grid gap-4 p-6 md:grid-cols-[96px_1fr_auto] md:items-center">
                                        <div class="h-24 w-24 overflow-hidden rounded-2xl bg-indigo-50">
                                            <?php if(!empty($package['image_path'])): ?>
                                                <img src="../<?php echo htmlspecialchars($package['image_path'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($package['name'], ENT_QUOTES); ?>" class="h-full w-full object-cover">
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="flex flex-wrap items-center gap-3">
                                                <h3 class="text-xl font-semibold"><?php echo htmlspecialchars($package['name']); ?></h3>
                                                <span class="rounded-full px-3 py-1 text-xs font-semibold <?php echo (int) $package['is_active'] === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600'; ?>"><?php echo (int) $package['is_active'] === 1 ? 'Active' : 'Disabled'; ?></span>
                                            </div>
                                            <p class="mt-1 font-bold text-primary">&#8369;<?php echo number_format((float) $package['price'], 2); ?></p>
                                            <p class="mt-2 text-sm text-slate-600"><?php echo htmlspecialchars($package['description'] ?: 'No description yet.'); ?></p>
                                            <p class="mt-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400"><?php echo (int) $package['feature_count']; ?> features</p>
                                        </div>
                                        <div class="flex flex-wrap gap-2 md:justify-end">
                                            <a href="packages.php?edit=<?php echo (int) $package['id']; ?>" class="rounded-xl border border-purple-100 px-4 py-3 font-semibold text-primary hover:bg-purple-50">Edit</a>
                                            <form method="POST" data-confirm-form data-confirm-message="Delete this package? Existing reservations keep their saved package name.">
                                                <?php echo eventify_csrf_field(); ?>
                                                <input type="hidden" name="package_action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int) $package['id']; ?>">
                                                <button type="submit" class="rounded-xl bg-red-600 px-4 py-3 font-semibold text-white">Delete</button>
                                            </form>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-8 text-center text-slate-600">No packages yet. Create one to unlock reservations.</div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/admin.js"></script>
</body>
</html>
