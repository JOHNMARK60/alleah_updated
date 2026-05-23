<?php
session_start();
include '../config/db.php';

eventify_require_role('client');

$user_id = eventify_current_user_id();

$stmt = $conn->prepare("
    SELECT m.*, admin.name AS admin_name
    FROM admin_client_messages m
    LEFT JOIN users admin ON admin.id = m.admin_id
    WHERE m.client_id=?
    ORDER BY m.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$unread_count = 0;
foreach($messages as $message) {
    if(empty($message['read_at'])) {
        $unread_count++;
    }
}

if($unread_count > 0) {
    $stmt = $conn->prepare("UPDATE admin_client_messages SET read_at=NOW() WHERE client_id=? AND read_at IS NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Eventify</title>
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
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Client Inbox</p>
                <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-6xl">Messages</h1>
                <p class="mt-4 text-lg leading-8 text-slate-600">Review concerns, reminders, and updates sent by the Eventify admin team.</p>
            </div>
            <div class="rounded-[2rem] bg-white p-5 text-center shadow-soft">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Unread on arrival</p>
                <p class="mt-2 text-4xl font-semibold text-primary"><?php echo $unread_count; ?></p>
            </div>
        </div>

        <section class="mt-8 overflow-hidden rounded-[2rem] bg-white shadow-soft" data-messages-history>
            <div class="border-b border-purple-100 bg-indigo-50 p-6">
                <h2 class="text-2xl font-semibold">Admin Message History</h2>
                <p class="mt-2 text-sm text-slate-600"><?php echo count($messages); ?> saved message<?php echo count($messages) === 1 ? '' : 's'; ?></p>
            </div>

            <div class="divide-y divide-purple-50">
                <?php if($messages): ?>
                    <?php foreach($messages as $message): ?>
                        <?php $was_unread = empty($message['read_at']); ?>
                        <article class="p-6 <?php echo $was_unread ? 'bg-purple-50/60' : 'bg-white'; ?>">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-xl font-semibold"><?php echo htmlspecialchars($message['subject']); ?></h3>
                                        <?php if($was_unread): ?>
                                            <span class="rounded-full bg-primary px-3 py-1 text-xs font-semibold text-white">New</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mt-1 text-sm font-semibold text-slate-500">From <?php echo htmlspecialchars($message['admin_name'] ?: 'Eventify Admin'); ?></p>
                                </div>
                                <p class="shrink-0 text-sm font-semibold text-slate-400"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($message['created_at']))); ?></p>
                            </div>
                            <p class="mt-5 whitespace-pre-line text-sm leading-7 text-slate-700"><?php echo htmlspecialchars($message['message']); ?></p>
                            <p class="mt-4 text-xs font-semibold uppercase tracking-[0.2em] <?php echo $was_unread ? 'text-primary' : 'text-slate-400'; ?>">
                                <?php echo $was_unread ? 'Marked read after opening this inbox' : 'Read ' . htmlspecialchars(date('M d, Y h:i A', strtotime($message['read_at']))); ?>
                            </p>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-10 text-center">
                        <h3 class="text-2xl font-semibold">No messages yet</h3>
                        <p class="mt-2 text-slate-600">Admin messages and concerns will appear here when they are sent.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/client.js"></script>
</body>
</html>
