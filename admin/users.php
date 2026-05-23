<?php
session_start();
include '../config/db.php';

eventify_require_role('admin');

$perPage = 10;
$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;
$totalRows = (int) $conn->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$totalPages = max(1, (int) ceil($totalRows / $perPage));

$stmt = $conn->prepare("SELECT * FROM users ORDER BY id DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$users = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users | Eventify Admin</title>
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
                <a href="users.php" class="rounded-2xl bg-white/10 px-4 py-3 font-bold text-white">Users</a>
                <a href="messages.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Messages</a>
                <a href="calendar.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Calendar</a>
            </nav>
            <a href="../auth/logout.php" class="mt-auto rounded-2xl border border-white/10 px-4 py-3 text-center font-bold text-white/75 hover:bg-white/10">Logout</a>
        </aside>

        <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Account Directory</p>
                        <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Users</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <?php echo eventify_notification_widget($conn, 'admin'); ?>
                        <input type="search" data-table-search data-table-target="usersTable" placeholder="Search users" class="rounded-2xl border border-purple-100 bg-white px-4 py-3 outline-none focus:border-primary focus:ring-4 focus:ring-purple-100">
                        <a href="messages.php" class="rounded-2xl bg-white px-5 py-3 text-center font-semibold text-primary shadow-sm hover:bg-purple-50">Messages</a>
                    </div>
                </div>

                <section class="mt-8 overflow-hidden rounded-[2rem] bg-white shadow-soft">
                    <div class="overflow-x-auto">
                        <table id="usersTable" class="min-w-full text-left text-sm">
                            <thead class="bg-indigo-50 text-xs uppercase tracking-widest text-slate-500">
                                <tr>
                                    <th class="px-6 py-4">ID</th>
                                    <th class="px-6 py-4">Name</th>
                                    <th class="px-6 py-4">Username</th>
                                    <th class="px-6 py-4">Email</th>
                                    <th class="px-6 py-4">Contact</th>
                                    <th class="px-6 py-4">Role</th>
                                    <th class="px-6 py-4">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-purple-50">
                                <?php if($users && $users->num_rows > 0): ?>
                                    <?php while($row = $users->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-6 py-4 font-semibold text-primary">#<?php echo htmlspecialchars($row['id']); ?></td>
                                            <td class="px-6 py-4 font-semibold"><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['username']); ?></td>
                                            <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['email']); ?></td>
                                            <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['contact']); ?></td>
                                            <td class="px-6 py-4">
                                                <span class="rounded-full bg-purple-100 px-3 py-1 text-xs font-semibold text-primary"><?php echo htmlspecialchars($row['role']); ?></span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if($row['role'] === 'client'): ?>
                                                    <a href="messages.php?client_id=<?php echo (int) $row['id']; ?>" class="font-semibold text-primary">Message</a>
                                                <?php else: ?>
                                                    <span class="text-slate-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td class="px-6 py-8 text-center text-slate-500" colspan="7">No users found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <?php if($totalPages > 1): ?>
                    <nav class="mt-6 flex flex-wrap items-center gap-2" aria-label="User pages">
                        <?php for($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="<?php echo htmlspecialchars(eventify_page_url($i), ENT_QUOTES); ?>" class="rounded-xl px-4 py-2 text-sm font-semibold <?php echo $page === $i ? 'bg-primary text-white' : 'bg-white text-slate-600 hover:text-primary'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/admin.js"></script>
</body>
</html>
