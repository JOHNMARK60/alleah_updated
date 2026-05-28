<?php
session_start();
include '../config/db.php';

eventify_require_role('admin');

$current_admin_id = eventify_current_user_id();
$selected_client_id = (int) ($_GET['client_id'] ?? ($_POST['client_id'] ?? 0));
$form_errors = [];

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message_action = $_POST['message_action'] ?? '';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $message_id = (int) ($_POST['message_id'] ?? 0);

    if(!eventify_verify_csrf()) {
        $form_errors[] = 'Security check failed. Please try again.';
    }

    if($message_action === 'send_message') {
        $selected_client_id = (int) ($_POST['client_id'] ?? 0);

        if($selected_client_id <= 0) {
            $form_errors[] = 'Please choose a client.';
        }
    }

    if($message_action === 'send_message' || $message_action === 'edit_message') {
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

    $client = null;
    if(!$form_errors && $message_action === 'send_message') {
        $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE id=? AND role='client' LIMIT 1");
        $stmt->bind_param("i", $selected_client_id);
        $stmt->execute();
        $client = $stmt->get_result()->fetch_assoc();

        if(!$client) {
            $form_errors[] = 'Selected client was not found.';
        }
    }

    if(!$form_errors && $message_action === 'send_message') {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("
                INSERT INTO admin_client_messages (admin_id, client_id, sender_role, subject, message)
                VALUES (?, ?, 'admin', ?, ?)
            ");
            $stmt->bind_param("iiss", $current_admin_id, $selected_client_id, $subject, $message);
            $stmt->execute();

            $notification_title = substr('Admin message: ' . $subject, 0, 255);
            eventify_create_notification(
                $conn,
                $selected_client_id,
                'client',
                $notification_title,
                $message
            );
            eventify_log_activity($conn, 'client.message.sent', 'Message sent to client #' . $selected_client_id);

            $conn->commit();
            eventify_set_flash('success', 'Message sent', 'Your message was sent to ' . $client['name'] . '.');
            header("Location: messages.php?client_id=" . $selected_client_id);
            exit();
        } catch (mysqli_sql_exception $error) {
            $conn->rollback();
            $form_errors[] = 'Message could not be sent. Please try again.';
        }
    } elseif(!$form_errors && $message_action === 'edit_message') {
        $in_transaction = false;
        try {
            $stmt = $conn->prepare("
                SELECT client_id
                FROM admin_client_messages
                WHERE id=? AND admin_id=? AND sender_role='admin'
                LIMIT 1
            ");
            $stmt->bind_param("ii", $message_id, $current_admin_id);
            $stmt->execute();
            $owned_message = $stmt->get_result()->fetch_assoc();

            if(!$owned_message) {
                $form_errors[] = 'Message was not found or could not be edited.';
            } else {
                $conn->begin_transaction();
                $in_transaction = true;

                $stmt = $conn->prepare("
                    UPDATE admin_client_messages
                    SET subject=?, message=?, read_at=NULL, edited_at=NOW()
                    WHERE id=? AND admin_id=? AND sender_role='admin'
                ");
                $stmt->bind_param("ssii", $subject, $message, $message_id, $current_admin_id);
                $stmt->execute();

                $client_id = (int) $owned_message['client_id'];
                if($client_id > 0) {
                    $notification_title = substr('Admin message updated: ' . $subject, 0, 255);
                    eventify_create_notification($conn, $client_id, 'client', $notification_title, $message);
                }

                eventify_log_activity($conn, 'client.message.updated', 'Admin updated message #' . $message_id . '.');
                $conn->commit();
                eventify_set_flash('success', 'Message updated', 'The edited message was sent back to the client inbox.');
                header("Location: messages.php?client_id=" . $client_id);
                exit();
            }
        } catch (mysqli_sql_exception $error) {
            if($in_transaction) {
                $conn->rollback();
            }
            $form_errors[] = 'Message could not be edited. Please try again.';
        }
    } elseif(!$form_errors && $message_action === 'delete_message') {
        try {
            $stmt = $conn->prepare("DELETE FROM admin_client_messages WHERE id=? AND admin_id=? AND sender_role='admin'");
            $stmt->bind_param("ii", $message_id, $current_admin_id);
            $stmt->execute();

            if($stmt->affected_rows <= 0) {
                $form_errors[] = 'Message was not found or could not be deleted.';
            } else {
                eventify_log_activity($conn, 'client.message.deleted', 'Admin deleted message #' . $message_id . '.');
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

$clients = $conn->query("
    SELECT id, name, email, contact
    FROM users
    WHERE role='client'
    ORDER BY name ASC, email ASC
");

$incoming_unread_count = (int) $conn->query("
    SELECT COUNT(*) AS total
    FROM admin_client_messages
    WHERE sender_role='client' AND read_at IS NULL
")->fetch_assoc()['total'];

$recent_messages = $conn->query("
    SELECT m.*, client.name AS client_name, client.email AS client_email, admin.name AS admin_name
    FROM admin_client_messages m
    LEFT JOIN users client ON client.id = m.client_id
    LEFT JOIN users admin ON admin.id = m.admin_id
    ORDER BY m.created_at DESC
    LIMIT 20
");

if($incoming_unread_count > 0) {
    $conn->query("UPDATE admin_client_messages SET read_at=NOW() WHERE sender_role='client' AND read_at IS NULL");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | Eventify Admin</title>
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
                <a href="reports.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Sales Reports</a>
                <a href="gallery.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Gallery</a>
                <a href="users.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Users</a>
                <a href="messages.php" class="rounded-2xl bg-white/10 px-4 py-3 font-bold text-white">Messages</a>
                <a href="calendar.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Calendar</a>
                <a href="add_event.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Add Event</a>
            </nav>
            <a href="../auth/logout.php" class="mt-auto rounded-2xl border border-white/10 px-4 py-3 text-center font-bold text-white/75 hover:bg-white/10">Logout</a>
        </aside>

        <main class="flex-1 px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
            <?php echo eventify_admin_mobile_header($conn, 'messages'); ?>
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Client Communication</p>
                        <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Messages</h1>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <?php echo eventify_notification_widget($conn, 'admin'); ?>
                        <div class="rounded-2xl bg-white px-5 py-3 text-center shadow-sm">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">New client messages</p>
                            <p class="text-2xl font-semibold text-primary"><?php echo $incoming_unread_count; ?></p>
                        </div>
                        <a href="users.php" class="rounded-2xl bg-white px-5 py-3 text-center font-semibold text-primary shadow-sm hover:bg-purple-50">Client List</a>
                    </div>
                </div>

                <?php if($form_errors): ?>
                    <div class="mt-6 rounded-2xl border border-red-100 bg-red-50 p-5 text-sm font-semibold text-red-700">
                        <?php echo htmlspecialchars(implode(' ', $form_errors)); ?>
                    </div>
                <?php endif; ?>

                <div class="mt-8 grid gap-6 lg:grid-cols-[0.85fr_1.15fr]">
                    <section class="rounded-[2rem] bg-white p-6 shadow-soft">
                        <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Compose</p>
                        <h2 class="mt-1 text-2xl font-semibold">Send or Reply to Client</h2>

                        <form method="POST" class="mt-6 grid gap-5" data-loading-form>
                            <?php echo eventify_csrf_field(); ?>
                            <input type="hidden" name="message_action" value="send_message">
                            <div>
                                <label class="text-sm font-bold text-slate-600">Client</label>
                                <select name="client_id" required class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                                    <option value="">Select client</option>
                                    <?php if($clients && $clients->num_rows > 0): ?>
                                        <?php while($client = $clients->fetch_assoc()): ?>
                                            <option value="<?php echo (int) $client['id']; ?>" <?php echo $selected_client_id === (int) $client['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($client['name'] . ' - ' . $client['email']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div>
                                <label class="text-sm font-bold text-slate-600">Subject</label>
                                <input type="text" name="subject" maxlength="255" required value="<?php echo htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES); ?>" placeholder="Concern, reminder, or update" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                            </div>

                            <div>
                                <label class="text-sm font-bold text-slate-600">Message</label>
                                <textarea name="message" rows="8" required placeholder="Write the concern, instructions, or reply for the client." class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit" class="rounded-2xl bg-gradient-to-r from-primary to-secondary px-6 py-4 font-semibold text-white shadow-soft">
                                Send Message
                            </button>
                        </form>
                    </section>

                    <section class="overflow-hidden rounded-[2rem] bg-white shadow-soft">
                        <div class="border-b border-purple-100 p-6">
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">History</p>
                            <h2 class="mt-1 text-2xl font-semibold">Recent Conversation</h2>
                        </div>
                        <div class="divide-y divide-purple-50">
                            <?php if($recent_messages && $recent_messages->num_rows > 0): ?>
                                <?php while($row = $recent_messages->fetch_assoc()): ?>
                                    <?php
                                    $is_from_client = ($row['sender_role'] ?? 'admin') === 'client';
                                    $was_unread = $is_from_client && empty($row['read_at']);
                                    $can_manage_message = !$is_from_client && (int) ($row['admin_id'] ?? 0) === $current_admin_id;
                                    ?>
                                    <article class="p-6 <?php echo $was_unread ? 'bg-purple-50/60' : 'bg-white'; ?>">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                            <div>
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($row['subject']); ?></h3>
                                                    <span class="rounded-full px-3 py-1 text-xs font-semibold <?php echo $is_from_client ? 'bg-sky-100 text-sky-700' : 'bg-primary text-white'; ?>">
                                                        <?php echo $is_from_client ? 'From Client' : 'Sent by Admin'; ?>
                                                    </span>
                                                    <?php if($was_unread): ?>
                                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">New</span>
                                                    <?php elseif(!$is_from_client): ?>
                                                        <span class="rounded-full px-3 py-1 text-xs font-semibold <?php echo empty($row['read_at']) ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'; ?>">
                                                            <?php echo empty($row['read_at']) ? 'Client unread' : 'Client read'; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="mt-1 text-sm font-semibold text-slate-500">
                                                    <?php echo $is_from_client ? 'From ' : 'To '; ?><?php echo htmlspecialchars($row['client_name'] ?: 'Deleted client'); ?>
                                                    <?php if(!empty($row['client_email'])): ?>
                                                        <span class="text-slate-400">| <?php echo htmlspecialchars($row['client_email']); ?></span>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <p class="shrink-0 text-sm font-semibold text-slate-400"><?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($row['created_at']))); ?></p>
                                        </div>
                                        <p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-600"><?php echo htmlspecialchars($row['message']); ?></p>
                                        <div class="mt-4 flex flex-wrap items-center gap-3 text-xs font-semibold uppercase tracking-[0.2em]">
                                            <?php if($is_from_client): ?>
                                                <p class="<?php echo $was_unread ? 'text-primary' : 'text-slate-400'; ?>">
                                                    <?php echo $was_unread ? 'Marked read after opening admin inbox' : 'Admin read ' . htmlspecialchars(date('M d, Y h:i A', strtotime($row['read_at']))); ?>
                                                </p>
                                                <?php if(!empty($row['client_id'])): ?>
                                                    <a href="messages.php?client_id=<?php echo (int) $row['client_id']; ?>" class="text-primary">Reply</a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <p class="<?php echo empty($row['read_at']) ? 'text-amber-600' : 'text-emerald-600'; ?>">
                                                    <?php echo empty($row['read_at']) ? 'Waiting for client to read' : 'Client read ' . htmlspecialchars(date('M d, Y h:i A', strtotime($row['read_at']))); ?>
                                                </p>
                                                <p class="text-slate-400">Sent by <?php echo htmlspecialchars($row['admin_name'] ?: 'Admin'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <?php if(!empty($row['edited_at'])): ?>
                                            <p class="mt-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Edited <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($row['edited_at']))); ?></p>
                                        <?php endif; ?>
                                        <?php if($can_manage_message): ?>
                                            <div class="mt-5 grid gap-3 rounded-2xl border border-purple-100 bg-indigo-50 p-4">
                                                <details>
                                                    <summary class="cursor-pointer text-sm font-bold text-primary">Edit message</summary>
                                                    <form method="POST" class="mt-4 grid gap-3" data-loading-form>
                                                        <?php echo eventify_csrf_field(); ?>
                                                        <input type="hidden" name="message_action" value="edit_message">
                                                        <input type="hidden" name="message_id" value="<?php echo (int) $row['id']; ?>">
                                                        <input type="text" name="subject" maxlength="255" required value="<?php echo htmlspecialchars($row['subject'], ENT_QUOTES); ?>" class="w-full rounded-xl border border-purple-100 bg-white px-4 py-3 outline-none focus:border-primary focus:ring-4 focus:ring-purple-100">
                                                        <textarea name="message" rows="4" required class="w-full rounded-xl border border-purple-100 bg-white px-4 py-3 outline-none focus:border-primary focus:ring-4 focus:ring-purple-100"><?php echo htmlspecialchars($row['message']); ?></textarea>
                                                        <button type="submit" class="rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-white">Save Changes</button>
                                                    </form>
                                                </details>
                                                <form method="POST" data-confirm-form data-confirm-message="Delete this message?">
                                                    <?php echo eventify_csrf_field(); ?>
                                                    <input type="hidden" name="message_action" value="delete_message">
                                                    <input type="hidden" name="message_id" value="<?php echo (int) $row['id']; ?>">
                                                    <button type="submit" class="text-sm font-bold text-red-600">Delete message</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="p-8 text-center text-slate-500">No messages yet.</div>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <?php echo eventify_admin_mobile_sidebar('messages'); ?>

    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/admin.js"></script>
</body>
</html>
