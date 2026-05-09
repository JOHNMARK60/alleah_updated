<?php
include '../config/db.php';

$errors = [];

if($_SERVER['REQUEST_METHOD'] === 'POST'){

    if(!eventify_verify_csrf()) {
        $errors['general'] = "Security check failed. Please try again.";
        eventify_set_flash('error', 'Registration failed', $errors['general']);
    } else {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $contact = trim($_POST['contact'] ?? '');
        $pass = trim($_POST['password'] ?? '');
        $confirm = trim($_POST['confirm'] ?? '');

        // Validation
        if($name === "" || !preg_match('/^[A-Za-z ]+$/', $name)){
            $errors['name'] = "Please enter your full name";
        }

        if($username === ""){
            $errors['username'] = "Please enter your username";
        }

        if($email === ""){
            $errors['email'] = "Please enter your email address";
        } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $errors['email'] = "Please enter a valid email address";
        }

        if($contact === "" || !eventify_valid_contact($contact)){
            $errors['contact'] = "Please enter a valid contact number";
        }

        if(strlen($pass) < 6){
            $errors['password'] = "Password must be at least 6 characters";
        }

        if($pass !== $confirm){
            $errors['confirm'] = "Passwords do not match";
        }

        if(!empty($errors)) {
            eventify_set_flash('error', 'Registration failed', reset($errors));
        }

        // Save new user
        if(empty($errors)){

            $hashed = password_hash($pass, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users(name,username,email,contact,password) VALUES(?,?,?,?,?)");
            $stmt->bind_param("sssss", $name, $username, $email, $contact, $hashed);

            try {
                $saved = $stmt->execute();
            } catch (mysqli_sql_exception $error) {
                $saved = false;
            }

            if($saved){
                eventify_set_flash('success', 'Account created', 'Please sign in to continue.');
                header("Location: login.php");
                exit();
            } else {
                $errors['general'] = "Registration failed (maybe duplicate email)";
                eventify_set_flash('error', 'Registration failed', $errors['general']);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Eventify</title>
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
            <a href="login.php" class="rounded-xl px-5 py-2.5 text-sm font-bold text-primary hover:bg-purple-50">Login</a>
        </div>
    </header>

    <main class="grid min-h-[calc(100vh-73px)] place-items-center bg-dark/35 px-4 py-10">
        <section class="w-full max-w-2xl rounded-[2rem] border border-white/80 bg-white p-6 shadow-soft sm:p-8" role="dialog" aria-modal="true">
            <div class="text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Create Account</p>
                <h1 class="mt-3 text-4xl font-semibold tracking-tight sm:text-5xl">Register</h1>
                <p class="mt-3 text-slate-600">Start planning and managing reservations in Eventify.</p>
            </div>

            <div class="mt-8 grid grid-cols-2 rounded-2xl bg-indigo-50 p-1">
                <a href="login.php" class="rounded-xl py-3 text-center font-semibold text-slate-500 hover:text-primary">Login</a>
                <a href="register.php" class="rounded-xl bg-white py-3 text-center font-semibold text-primary shadow-sm">Register</a>
            </div>

            <?php if(isset($errors['general'])): ?>
                <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="mt-7 grid gap-5 sm:grid-cols-2">
                <?php echo eventify_csrf_field(); ?>
                <div>
                    <label for="name" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Full Name</label>
                    <input id="name" type="text" name="name" placeholder="Full Name" value="<?php echo htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES); ?>" required class="mt-2 w-full rounded-2xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none transition focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                    <?php if(isset($errors['name'])) echo "<p class='mt-2 text-sm font-semibold text-red-600'>".htmlspecialchars($errors['name'])."</p>"; ?>
                </div>

                <div>
                    <label for="username" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Username</label>
                    <input id="username" type="text" name="username" placeholder="Username" value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES); ?>" required class="mt-2 w-full rounded-2xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none transition focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                    <?php if(isset($errors['username'])) echo "<p class='mt-2 text-sm font-semibold text-red-600'>".htmlspecialchars($errors['username'])."</p>"; ?>
                </div>

                <div>
                    <label for="email" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Email</label>
                    <input id="email" type="email" name="email" placeholder="Email Address" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES); ?>" required class="mt-2 w-full rounded-2xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none transition focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                    <?php if(isset($errors['email'])) echo "<p class='mt-2 text-sm font-semibold text-red-600'>".htmlspecialchars($errors['email'])."</p>"; ?>
                </div>

                <div>
                    <label for="contact" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Contact Number</label>
                    <input id="contact" type="text" name="contact" placeholder="Contact Number" value="<?php echo htmlspecialchars($_POST['contact'] ?? '', ENT_QUOTES); ?>" required class="mt-2 w-full rounded-2xl border border-purple-100 bg-indigo-50 px-4 py-4 outline-none transition focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                    <?php if(isset($errors['contact'])) echo "<p class='mt-2 text-sm font-semibold text-red-600'>".htmlspecialchars($errors['contact'])."</p>"; ?>
                </div>

                <div>
                    <label for="password" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Password</label>
                    <div class="relative mt-2">
                        <input id="password" type="password" name="password" placeholder="Password" required class="w-full rounded-2xl border border-purple-100 bg-indigo-50 px-4 py-4 pr-16 outline-none transition focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-xl px-3 py-2 text-sm font-bold text-primary hover:bg-purple-50" data-password-toggle data-target="password">Show</button>
                    </div>
                    <?php if(isset($errors['password'])) echo "<p class='mt-2 text-sm font-semibold text-red-600'>".htmlspecialchars($errors['password'])."</p>"; ?>
                </div>

                <div>
                    <label for="confirm" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Confirm Password</label>
                    <div class="relative mt-2">
                        <input id="confirm" type="password" name="confirm" placeholder="Confirm Password" required class="w-full rounded-2xl border border-purple-100 bg-indigo-50 px-4 py-4 pr-16 outline-none transition focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-xl px-3 py-2 text-sm font-bold text-primary hover:bg-purple-50" data-password-toggle data-target="confirm">Show</button>
                    </div>
                    <?php if(isset($errors['confirm'])) echo "<p class='mt-2 text-sm font-semibold text-red-600'>".htmlspecialchars($errors['confirm'])."</p>"; ?>
                </div>

                <div class="sm:col-span-2">
                    <button type="submit" class="w-full rounded-2xl bg-gradient-to-r from-primary to-secondary px-5 py-4 text-base font-semibold text-white shadow-soft hover:scale-[1.01]">
                        Create Account
                    </button>
                </div>
            </form>
        </section>
    </main>

    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/auth.js"></script>
</body>
</html>
