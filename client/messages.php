<?php
session_start();
include '../config/db.php';

eventify_require_role('client');

$user_id = eventify_current_user_id();
$form_errors = [];

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message_action = $_POST['message_action'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $message_id = (int) ($_POST['message_id'] ?? 0);

    if(!eventify_verify_csrf()) {
        $form_errors[] = 'Security check failed. Please try again.';
    }

    if($message_action === 'send_admin_message' || $message_action === 'edit_message') {
        if($subject === '') {
            $form_errors[] = 'Please enter a subject.';
        }

        if(strlen($subject) > 255) {
            $form_errors[] = 'Subject must be 255 characters or fewer.';
        }

        if($message === '') {
            $form_errors[] = 'Please enter a message.';
        }
    }

    if($message_action === 'edit_message' || $message_action === 'delete_message') {
        if($message_id <= 0) {
            $form_errors[] = 'Message was not found.';
        }
    }

    if(!$form_errors && $message_action === 'send_admin_message') {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("
                INSERT INTO admin_client_messages (client_id, sender_role, subject, message)
                VALUES (?, 'client', ?, ?)
            ");
            $stmt->bind_param("iss", $user_id, $subject, $message);
            $stmt->execute();

            $notification_title = substr('Client message: ' . $subject, 0, 255);
            eventify_create_notification(
                $conn,
                null,
                'admin',
                $notification_title,
                $message
            );
            eventify_log_activity($conn, 'admin.message.received', 'Client #' . $user_id . ' sent a message to admin.');

            $conn->commit();
            eventify_set_flash('success', 'Message sent', 'Your message was sent to the admin team.');
            header("Location: messages.php");
            exit();
        } catch (mysqli_sql_exception $error) {
            $conn->rollback();
            $form_errors[] = 'Message could not be sent. Please try again.';
        }
    } elseif(!$form_errors && $message_action === 'edit_message') {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("
                UPDATE admin_client_messages
                SET subject=?, message=?, read_at=NULL, edited_at=NOW()
                WHERE id=? AND client_id=? AND sender_role='client'
            ");
            $stmt->bind_param("ssii", $subject, $message, $message_id, $user_id);
            $stmt->execute();

            if($stmt->affected_rows <= 0) {
                $conn->rollback();
                $form_errors[] = 'Message was not found or could not be edited.';
            } else {
                $notification_title = substr('Client message updated: ' . $subject, 0, 255);
                eventify_create_notification($conn, null, 'admin', $notification_title, $message);
                eventify_log_activity($conn, 'admin.message.updated', 'Client #' . $user_id . ' updated message #' . $message_id . '.');
                $conn->commit();
                eventify_set_flash('success', 'Message updated', 'Your edited message was sent back to the admin inbox.');
                header("Location: messages.php");
                exit();
            }
        } catch (mysqli_sql_exception $error) {
            $conn->rollback();
            $form_errors[] = 'Message could not be edited. Please try again.';
        }
    } elseif(!$form_errors && $message_action === 'delete_message') {
        try {
            $stmt = $conn->prepare("DELETE FROM admin_client_messages WHERE id=? AND client_id=? AND sender_role='client'");
            $stmt->bind_param("ii", $message_id, $user_id);
            $stmt->execute();

            if($stmt->affected_rows <= 0) {
                $form_errors[] = 'Message was not found or could not be deleted.';
            } else {
                eventify_log_activity($conn, 'admin.message.deleted', 'Client #' . $user_id . ' deleted message #' . $message_id . '.');
                eventify_set_flash('success', 'Message deleted', 'Your message was deleted.');
                header("Location: messages.php");
                exit();
            }
        } catch (mysqli_sql_exception $error) {
            $form_errors[] = 'Message could not be deleted. Please try again.';
        }
    } elseif(!$form_errors) {
        $form_errors[] = 'Message action is invalid.';
    }
}

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
    if(($message['sender_role'] ?? 'admin') === 'admin' && empty($message['read_at'])) {
        $unread_count++;
    }
}

if($unread_count > 0) {
    $stmt = $conn->prepare("UPDATE admin_client_messages SET read_at=NOW() WHERE client_id=? AND sender_role='admin' AND read_at IS NULL");
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
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Client Messages</p>
                <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-6xl">Messages</h1>
                <p class="mt-4 text-lg leading-8 text-slate-600">Send questions to the Eventify admin team and review your message history.</p>
            </div>
            <div class="rounded-[2rem] bg-white p-5 text-center shadow-soft">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Unread admin messages</p>
                <p class="mt-2 text-4xl font-semibold text-primary"><?php echo $unread_count; ?></p>
            </div>
        </div>

        <div class="mt-8" data-messages-history>
            <?php if($form_errors): ?>
                <div class="mb-6 rounded-2xl border border-red-100 bg-red-50 p-5 text-sm font-semibold text-red-700">
                    <?php echo htmlspecialchars(implode(' ', $form_errors)); ?>
                </div>
            <?php endif; ?>

            <div class="grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
                <section class="rounded-[2rem] bg-white p-6 shadow-soft">
                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Compose</p>
                    <h2 class="mt-1 text-2xl font-semibold">Send Message to Admin</h2>

                    <form method="POST" action="messages.php" class="mt-6 grid gap-5" data-loading-form>
                        <?php echo eventify_csrf_field(); ?>
                        <input type="hidden" name="message_action" value="send_admin_message">
                        <div>
                            <label class="text-sm font-bold text-slate-600">Subject</label>
                            <input type="text" name="subject" maxlength="255" required value="<?php echo htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES); ?>" placeholder="Question, concern, or update" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-sm font-bold text-slate-600">Message</label>
                            <textarea name="message" rows="8" required placeholder="Write your message for the admin team." class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="rounded-2xl bg-gradient-to-r from-primary to-secondary px-6 py-4 font-semibold text-white shadow-soft">
                            Send to Admin
                        </button>
                    </form>
                </section>

                <section class="overflow-hidden rounded-[2rem] bg-white shadow-soft">
                    <div class="border-b border-purple-100 bg-indigo-50 p-6">
                        <h2 class="text-2xl font-semibold">Conversation History</h2>
                        <p class="mt-2 text-sm text-slate-600"><?php echo count($messages); ?> saved message<?php echo count($messages) === 1 ? '' : 's'; ?></p>
                    </div>

                    <div class="divide-y divide-purple-50">
                        <?php if($messages): ?>
                            <?php foreach($messages as $message): ?>
                                <?php
                                $is_from_admin = ($message['sender_role'] ?? 'admin') === 'admin';
                                $was_unread = $is_from_admin && empty($message['read_at']);
                                ?>
                                <article class="p-6 <?php echo $was_unread ? 'bg-purple-50/60' : 'bg-white'; ?>">
                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-xl font-semibold"><?php echo htmlspecialchars($message['subject']); ?></h3>
                                                <span class="rounded-full px-3 py-1 text-xs font-semibold <?php echo $is_from_admin ? 'bg-primary text-white' : 'bg-sky-100 text-sky-700'; ?>">
                                                    <?php echo $is_from_admin ? 'From Admin' : 'Sent by You'; ?>
                                                </span>
                                                <?php if($was_unread): ?>
                                                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">New</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mt-1 text-sm font-semibold text-slate-500">
                                                <?php echo $is_from_admin ? 'From ' . htmlspecialchars($message['admin_name'] ?: 'Eventify Admin') : 'To Eventify Admin'; ?>
                                            </p>
                                        </div>
                                        <p class="shrink-0 text-sm font-semibold text-slate-400"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($message['created_at']))); ?></p>
                                    </div>
                                    <p class="mt-5 whitespace-pre-line text-sm leading-7 text-slate-700"><?php echo htmlspecialchars($message['message']); ?></p>
                                    <p class="mt-4 text-xs font-semibold uppercase tracking-[0.2em] <?php echo $was_unread ? 'text-primary' : 'text-slate-400'; ?>">
                                        <?php if($is_from_admin): ?>
                                            <?php echo $was_unread ? 'Marked read after opening this inbox' : 'Read ' . htmlspecialchars(date('M d, Y h:i A', strtotime($message['read_at']))); ?>
                                        <?php else: ?>
                                            <?php echo empty($message['read_at']) ? 'Waiting for admin to read' : 'Admin read ' . htmlspecialchars(date('M d, Y h:i A', strtotime($message['read_at']))); ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if(!empty($message['edited_at'])): ?>
                                        <p class="mt-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Edited <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($message['edited_at']))); ?></p>
                                    <?php endif; ?>
                                    <?php if(!$is_from_admin): ?>
                                        <div class="mt-5 grid gap-3 rounded-2xl border border-sky-100 bg-sky-50 p-4">
                                            <details>
                                                <summary class="cursor-pointer text-sm font-bold text-primary">Edit message</summary>
                                                <form method="POST" action="messages.php" class="mt-4 grid gap-3" data-loading-form>
                                                    <?php echo eventify_csrf_field(); ?>
                                                    <input type="hidden" name="message_action" value="edit_message">
                                                    <input type="hidden" name="message_id" value="<?php echo (int) $message['id']; ?>">
                                                    <input type="text" name="subject" maxlength="255" required value="<?php echo htmlspecialchars($message['subject'], ENT_QUOTES); ?>" class="w-full rounded-xl border border-sky-100 bg-white px-4 py-3 outline-none focus:border-primary focus:ring-4 focus:ring-purple-100">
                                                    <textarea name="message" rows="4" required class="w-full rounded-xl border border-sky-100 bg-white px-4 py-3 outline-none focus:border-primary focus:ring-4 focus:ring-purple-100"><?php echo htmlspecialchars($message['message']); ?></textarea>
                                                    <button type="submit" class="rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white">Save Changes</button>
                                                </form>
                                            </details>
                                            <form method="POST" action="messages.php" onsubmit="return window.confirm('Delete this message?');">
                                                <?php echo eventify_csrf_field(); ?>
                                                <input type="hidden" name="message_action" value="delete_message">
                                                <input type="hidden" name="message_id" value="<?php echo (int) $message['id']; ?>">
                                                <button type="submit" class="text-sm font-bold text-red-600">Delete message</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-10 text-center">
                                <h3 class="text-2xl font-semibold">No messages yet</h3>
                                <p class="mt-2 text-slate-600">Send a message to admin or check back for updates.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/client.js"></script>
</body>
</html>
