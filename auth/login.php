<?php
session_start();
include '../config/db.php';

$error = "";

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    if(!eventify_verify_csrf()) {
        $error = "Security check failed. Please try again.";
        eventify_set_flash('error', 'Login failed', $error);
    } else {
        $email = strtolower(trim($_POST['email']));
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if($result->num_rows > 0){

            $user = $result->fetch_assoc();

            if(password_verify($password, $user['password'])){

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                eventify_set_flash('success', 'Welcome back', 'You are now signed in.');

                if($user['role'] === "admin"){
                    header("Location: ../admin/dashboard.php");
                } else {
                    header("Location: ../client/dashboard.php");
                }

                exit();

            } else {
                $error = "Wrong password";
                eventify_set_flash('error', 'Login failed', $error);
            }

        } else {
            $error = "Email not found";
            eventify_set_flash('error', 'Login failed', $error);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Eventify</title>
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
    <link rel="stylesheet" href="assets/css/auth.css">
</head>
<body class="min-h-screen bg-[radial-gradient(circle_at_top,#eadcff_0,#f6f3ff_42%,#ffffff_100%)] text-dark">
    <header class="border-b border-white/70 bg-white/70 backdrop-blur">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
            <a href="../homepage/home.php" class="flex items-center gap-3 text-xl font-semibold">
                <span class="grid h-9 w-9 place-items-center rounded-2xl bg-primary text-white">E</span>
                Eventify
            </a>
            <a href="register.php" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-soft">Sign Up</a>
        </div>
    </header>

    <main class="grid min-h-[calc(100vh-73px)] place-items-center bg-dark/35 px-4 py-10">
        <section class="w-full max-w-md rounded-[2rem] border border-white/80 bg-white p-6 shadow-soft sm:p-8" role="dialog" aria-modal="true">
            <div class="text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Welcome Back</p>
                <h1 class="mt-3 text-4xl font-semibold tracking-tight sm:text-5xl">Sign In</h1>
                <p class="mt-3 text-slate-600">Manage your premium events with Eventify.</p>
            </div>

            <div class="mt-8 grid grid-cols-2 rounded-2xl bg-indigo-50 p-1">
                <a href="login.php" class="rounded-xl bg-white py-3 text-center font-semibold text-primary shadow-sm">Login</a>
                <a href="register.php" class="rounded-xl py-3 text-center font-semibold text-slate-500 hover:text-primary">Register</a>
            </div>

            <?php if(!empty($error)) { ?>
                <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php } ?>

            <form method="POST" class="mt-7 space-y-5">
                <?php echo eventify_csrf_field(); ?>
                <div>
                    <label for="email" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Email Address</label>
                    <input id="email" type="email" name="email" placeholder="alex@eventify.com" required class="mt-2 w-full rounded-2xl border border-purple-100 bg-indigo-50 px-4 py-4 text-base outline-none transition focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <label for="password" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Password</label>
                        <a href="#" class="text-sm font-bold text-primary">Forgot Password?</a>
                    </div>
                    <div class="relative mt-2">
                        <input id="password" type="password" name="password" placeholder="Password" required class="w-full rounded-2xl border border-purple-100 bg-indigo-50 px-4 py-4 pr-16 text-base outline-none transition focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-xl px-3 py-2 text-sm font-bold text-primary hover:bg-purple-50" data-password-toggle data-target="password">Show</button>
                    </div>
                </div>

                <button type="submit" class="w-full rounded-2xl bg-gradient-to-r from-primary to-secondary px-5 py-4 text-base font-semibold text-white shadow-soft hover:scale-[1.01]">
                    Sign In
                </button>
            </form>

            <div class="mt-8 border-t border-purple-100 pt-6 text-center text-slate-600">
                Don't have an account?
                <a href="register.php" class="font-semibold text-primary">Register</a>
            </div>
        </section>
    </main>

    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/auth.js"></script>
</body>
</html>
