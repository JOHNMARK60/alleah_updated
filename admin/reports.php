<?php
session_start();
include '../config/db.php';
require_once __DIR__ . '/report_helpers.php';

eventify_require_role('admin');

$filters = eventify_reports_normalize_filters($_GET);
$options = eventify_reports_options($conn);
$report = eventify_reports_data($conn, $filters);
$admin = eventify_reports_current_admin($conn);
$reportTitle = eventify_reports_title($filters);
$dateLabel = eventify_reports_date_label($filters);
$generatedAt = date('M d, Y h:i A');
$exportParams = $filters;
$csvQuery = http_build_query(array_merge($exportParams, ['format' => 'csv']));
$pdfQuery = http_build_query(array_merge($exportParams, ['format' => 'pdf']));
$printButtonLabel = $filters['report_type'] === 'daily'
    ? 'Print Daily Report'
    : ($filters['report_type'] === 'monthly' ? 'Print Monthly Report' : 'Print Report');

if (!in_array($filters['year'], $options['years'], true)) {
    $options['years'][] = $filters['year'];
    rsort($options['years']);
}

function eventify_reports_money($amount) {
    return '&#8369;' . number_format((float) $amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Reports | Eventify Admin</title>
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
        <aside class="no-print hidden shrink-0 flex-col border-r border-purple-100 bg-dark p-6 text-white lg:flex lg:w-64 xl:w-72">
            <a href="dashboard.php" class="flex items-center gap-3 text-2xl font-semibold">
                <span class="grid h-10 w-10 place-items-center rounded-2xl bg-primary">E</span>
                Eventify Admin
            </a>

            <nav class="mt-10 grid gap-2">
                <a href="dashboard.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Dashboard</a>
                <a href="reservations.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Reservations</a>
                <a href="reports.php" class="rounded-2xl bg-white/10 px-4 py-3 font-bold text-white">Sales Reports</a>
                <a href="payments.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Payments</a>
                <a href="packages.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Packages</a>
                <a href="gallery.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Gallery</a>
                <a href="users.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Users</a>
                <a href="messages.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Messages</a>
                <a href="calendar.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Calendar</a>
                <a href="add_event.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Add Event</a>
                <a href="event_records.php" class="rounded-2xl px-4 py-3 font-bold text-white/70 hover:bg-white/10 hover:text-white">Event Records</a>
            </nav>

            <a href="../auth/logout.php" class="mt-auto rounded-2xl border border-white/10 px-4 py-3 text-center font-bold text-white/75 hover:bg-white/10 hover:text-white">Logout</a>
        </aside>

        <main class="min-w-0 flex-1 overflow-x-hidden">
            <header class="no-print sticky top-0 z-30 flex items-center justify-between border-b border-purple-100 bg-white/90 px-4 py-4 backdrop-blur lg:hidden">
                <button type="button" class="rounded-xl p-2 text-primary" data-admin-sidebar-button aria-label="Open navigation">
                    <span class="block h-0.5 w-6 bg-current"></span>
                    <span class="mt-1.5 block h-0.5 w-6 bg-current"></span>
                    <span class="mt-1.5 block h-0.5 w-6 bg-current"></span>
                </button>
                <a href="dashboard.php" class="text-xl font-semibold">Eventify</a>
                <?php echo eventify_notification_widget($conn, 'admin'); ?>
            </header>

            <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
                <div class="no-print flex flex-col gap-4 2xl:flex-row 2xl:items-center 2xl:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold uppercase tracking-[0.25em] text-primary">Admin Reports</p>
                        <h1 class="mt-2 text-4xl font-semibold tracking-tight sm:text-5xl">Sales Reports</h1>
                    </div>
                    <div class="flex w-full min-w-0 flex-wrap items-center gap-3 2xl:w-auto 2xl:justify-end">
                        <?php echo eventify_notification_widget($conn, 'admin'); ?>
                        <a href="reports_export.php?<?php echo htmlspecialchars($csvQuery, ENT_QUOTES); ?>" class="rounded-2xl bg-white px-5 py-3 text-center font-semibold text-primary shadow-sm hover:bg-purple-50">Export CSV</a>
                        <a href="reports_export.php?<?php echo htmlspecialchars($pdfQuery, ENT_QUOTES); ?>" class="rounded-2xl bg-white px-5 py-3 text-center font-semibold text-primary shadow-sm hover:bg-purple-50">Export PDF</a>
                        <?php if($filters['report_type'] === 'monthly'): ?>
                            <a href="reports_export.php?<?php echo htmlspecialchars($pdfQuery, ENT_QUOTES); ?>" class="rounded-2xl bg-gradient-to-r from-primary to-secondary px-5 py-3 font-semibold text-white shadow-soft"><?php echo htmlspecialchars($printButtonLabel); ?></a>
                        <?php else: ?>
                            <button type="button" onclick="window.print()" class="rounded-2xl bg-gradient-to-r from-primary to-secondary px-5 py-3 font-semibold text-white shadow-soft"><?php echo htmlspecialchars($printButtonLabel); ?></button>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="GET" class="no-print mt-8 rounded-[2rem] bg-white p-6 shadow-soft">
                    <div class="grid gap-5 md:grid-cols-2 2xl:grid-cols-4">
                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Report Type</label>
                            <select name="report_type" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-3 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                                <?php foreach(['daily' => 'Daily', 'monthly' => 'Monthly', 'yearly' => 'Yearly', 'custom' => 'Custom Date Range'] as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo $filters['report_type'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Date Picker</label>
                            <input type="date" name="date" value="<?php echo htmlspecialchars($filters['date'], ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-3 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Month</label>
                            <select name="month" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-3 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                                <?php foreach(eventify_reports_months() as $monthNumber => $monthName): ?>
                                    <option value="<?php echo $monthNumber; ?>" <?php echo (int) $filters['month'] === (int) $monthNumber ? 'selected' : ''; ?>><?php echo htmlspecialchars($monthName); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Year</label>
                            <select name="year" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-3 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                                <?php foreach($options['years'] as $year): ?>
                                    <option value="<?php echo (int) $year; ?>" <?php echo (int) $filters['year'] === (int) $year ? 'selected' : ''; ?>><?php echo (int) $year; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Start Date</label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($filters['start_date'], ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-3 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">End Date</label>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($filters['end_date'], ENT_QUOTES); ?>" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-3 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Event Type / Package</label>
                            <select name="category" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-3 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                                <option value="">All Events and Packages</option>
                                <?php if(!empty($options['event_types'])): ?>
                                    <optgroup label="Event Types">
                                        <?php foreach($options['event_types'] as $eventType): ?>
                                            <?php $value = 'event:' . $eventType; ?>
                                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES); ?>" <?php echo $filters['category'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($eventType); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if(!empty($options['packages'])): ?>
                                    <optgroup label="Packages">
                                        <?php foreach($options['packages'] as $package): ?>
                                            <?php $value = 'package:' . $package; ?>
                                            <option value="<?php echo htmlspecialchars($value, ENT_QUOTES); ?>" <?php echo $filters['category'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($package); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Client Name</label>
                            <input type="search" name="client_name" value="<?php echo htmlspecialchars($filters['client_name'], ENT_QUOTES); ?>" placeholder="Search client" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-3 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Payment Method</label>
                            <select name="payment_method" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-3 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                                <option value="">All Methods</option>
                                <?php foreach($options['payment_methods'] as $method): ?>
                                    <option value="<?php echo htmlspecialchars($method, ENT_QUOTES); ?>" <?php echo $filters['payment_method'] === $method ? 'selected' : ''; ?>><?php echo htmlspecialchars($method); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Reservation Status</label>
                            <select name="reservation_status" class="mt-2 w-full rounded-xl border border-purple-100 bg-indigo-50 px-4 py-3 outline-none focus:border-primary focus:bg-white focus:ring-4 focus:ring-purple-100">
                                <option value="">All Report-Eligible Statuses</option>
                                <?php foreach($options['statuses'] as $status): ?>
                                    <option value="<?php echo htmlspecialchars($status, ENT_QUOTES); ?>" <?php echo $filters['reservation_status'] === $status ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <button type="submit" class="rounded-2xl bg-primary px-5 py-3 font-semibold text-white shadow-soft">Apply Filters</button>
                        <a href="reports.php" class="rounded-2xl bg-indigo-50 px-5 py-3 font-semibold text-primary hover:bg-purple-50">Reset Filters</a>
                    </div>
                </form>

                <section class="report-document mt-8 space-y-8">
                    <div class="rounded-[2rem] bg-white p-6 shadow-soft">
                        <div class="flex flex-col gap-3 border-b border-purple-100 pb-5 md:flex-row md:items-start md:justify-between">
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Eventify Event Reservation and Management System</p>
                                <h2 class="mt-2 text-3xl font-semibold tracking-tight"><?php echo htmlspecialchars($reportTitle); ?></h2>
                                <p class="mt-2 text-sm font-semibold text-slate-500">Selected Date Range: <?php echo htmlspecialchars($dateLabel); ?></p>
                            </div>
                            <div class="text-sm text-slate-600 md:text-right">
                                <p><strong>Generated By:</strong> <?php echo htmlspecialchars($admin['name']); ?></p>
                                <p><strong>Date Generated:</strong> <?php echo htmlspecialchars($generatedAt); ?></p>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                            <article class="rounded-3xl bg-indigo-50 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total Approved Transactions</p>
                                <p class="mt-3 text-3xl font-semibold text-primary"><?php echo number_format($report['totals']['approved_transactions']); ?></p>
                            </article>
                            <article class="rounded-3xl bg-indigo-50 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total Reservations</p>
                                <p class="mt-3 text-3xl font-semibold"><?php echo number_format($report['totals']['total_reservations']); ?></p>
                            </article>
                            <article class="rounded-3xl bg-indigo-50 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total Events Booked</p>
                                <p class="mt-3 text-3xl font-semibold"><?php echo number_format($report['totals']['total_events_booked']); ?></p>
                            </article>
                            <article class="rounded-3xl bg-indigo-50 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total Sales Amount</p>
                                <p class="mt-3 text-3xl font-semibold text-primary"><?php echo eventify_reports_money($report['totals']['total_sales']); ?></p>
                            </article>
                            <article class="rounded-3xl bg-indigo-50 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total Paid Amount</p>
                                <p class="mt-3 text-3xl font-semibold text-emerald-600"><?php echo eventify_reports_money($report['totals']['total_paid']); ?></p>
                            </article>
                            <article class="rounded-3xl bg-indigo-50 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Remaining Balance</p>
                                <p class="mt-3 text-3xl font-semibold text-amber-600"><?php echo eventify_reports_money($report['totals']['remaining_balance']); ?></p>
                            </article>
                            <article class="rounded-3xl bg-indigo-50 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Cancelled Transactions Count</p>
                                <p class="mt-3 text-3xl font-semibold text-red-600"><?php echo number_format($report['totals']['cancelled_count']); ?></p>
                            </article>
                            <article class="rounded-3xl bg-indigo-50 p-5">
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Rejected Transactions Count</p>
                                <p class="mt-3 text-3xl font-semibold text-red-600"><?php echo number_format($report['totals']['rejected_count']); ?></p>
                            </article>
                        </div>
                    </div>

                    <section class="overflow-hidden rounded-[2rem] bg-white shadow-soft">
                        <div class="border-b border-purple-100 p-6">
                            <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Detailed Table</p>
                            <h2 class="mt-1 text-2xl font-semibold">Approved Transactions</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-left text-sm">
                                <thead class="bg-indigo-50 text-xs uppercase tracking-widest text-slate-500">
                                    <tr>
                                        <th class="px-5 py-4">Receipt/Reference Number</th>
                                        <th class="px-5 py-4">Client Name</th>
                                        <th class="px-5 py-4">Event Type/Package</th>
                                        <th class="px-5 py-4">Event Date</th>
                                        <th class="px-5 py-4">Reservation Date</th>
                                        <th class="px-5 py-4">Total Amount</th>
                                        <th class="px-5 py-4">Amount Paid</th>
                                        <th class="px-5 py-4">Remaining Balance</th>
                                        <th class="px-5 py-4">Payment Method</th>
                                        <th class="px-5 py-4">Reservation Status</th>
                                        <th class="px-5 py-4">Approved By</th>
                                        <th class="px-5 py-4">Date Approved</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-purple-50">
                                    <?php if(!empty($report['rows'])): ?>
                                        <?php foreach($report['rows'] as $row): ?>
                                            <tr class="align-top">
                                                <td class="px-5 py-4 font-semibold">
                                                    <?php echo htmlspecialchars($row['display_reference']); ?>
                                                    <?php if($row['display_payment_references'] !== ''): ?>
                                                        <span class="mt-1 block text-xs font-semibold text-slate-500">Payment: <?php echo htmlspecialchars($row['display_payment_references']); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-5 py-4 text-slate-700"><?php echo htmlspecialchars($row['display_client_name']); ?></td>
                                                <td class="px-5 py-4 text-slate-700"><?php echo htmlspecialchars($row['display_event_package']); ?></td>
                                                <td class="px-5 py-4 text-slate-700"><?php echo htmlspecialchars(eventify_reports_format_date($row['event_date'])); ?></td>
                                                <td class="px-5 py-4 text-slate-700"><?php echo htmlspecialchars(eventify_reports_format_date($row['created_at'])); ?></td>
                                                <td class="px-5 py-4 font-semibold"><?php echo eventify_reports_money($row['total_amount']); ?></td>
                                                <td class="px-5 py-4 font-semibold text-emerald-600"><?php echo eventify_reports_money($row['paid_amount']); ?></td>
                                                <td class="px-5 py-4 font-semibold text-amber-600"><?php echo eventify_reports_money($row['remaining_balance']); ?></td>
                                                <td class="px-5 py-4 text-slate-700"><?php echo htmlspecialchars($row['display_payment_method']); ?></td>
                                                <td class="px-5 py-4">
                                                    <span class="rounded-full px-3 py-1 text-xs font-semibold <?php echo eventify_reports_status_class($row['status']); ?>">
                                                        <?php echo htmlspecialchars($row['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-5 py-4 text-slate-700"><?php echo htmlspecialchars($row['approved_by']); ?></td>
                                                <td class="px-5 py-4 text-slate-700"><?php echo htmlspecialchars(eventify_reports_format_datetime($row['date_approved'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td class="px-6 py-8 text-center text-slate-500" colspan="12">No approved and verified paid transactions found for this report.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="grid gap-8 xl:grid-cols-2">
                        <div class="overflow-hidden rounded-[2rem] bg-white shadow-soft">
                            <div class="border-b border-purple-100 p-6">
                                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Summary Table</p>
                                <h2 class="mt-1 text-2xl font-semibold">Payment Summary</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="bg-indigo-50 text-xs uppercase tracking-widest text-slate-500">
                                        <tr>
                                            <th class="px-5 py-4">Payment Method</th>
                                            <th class="px-5 py-4">Number of Transactions</th>
                                            <th class="px-5 py-4">Total Sales</th>
                                            <th class="px-5 py-4">Total Paid</th>
                                            <th class="px-5 py-4">Total Remaining Balance</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-purple-50">
                                        <?php if(!empty($report['payment_summary'])): ?>
                                            <?php foreach($report['payment_summary'] as $summary): ?>
                                                <tr>
                                                    <td class="px-5 py-4 font-semibold"><?php echo htmlspecialchars($summary['payment_method']); ?></td>
                                                    <td class="px-5 py-4 text-slate-700"><?php echo number_format($summary['transactions']); ?></td>
                                                    <td class="px-5 py-4 font-semibold"><?php echo eventify_reports_money($summary['total_sales']); ?></td>
                                                    <td class="px-5 py-4 font-semibold text-emerald-600"><?php echo eventify_reports_money($summary['total_paid']); ?></td>
                                                    <td class="px-5 py-4 font-semibold text-amber-600"><?php echo eventify_reports_money($summary['remaining_balance']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td class="px-6 py-8 text-center text-slate-500" colspan="5">No payment summary available.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-[2rem] bg-white shadow-soft">
                            <div class="border-b border-purple-100 p-6">
                                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-primary">Summary Table</p>
                                <h2 class="mt-1 text-2xl font-semibold">Event Category Summary</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-left text-sm">
                                    <thead class="bg-indigo-50 text-xs uppercase tracking-widest text-slate-500">
                                        <tr>
                                            <th class="px-5 py-4">Event Type/Package</th>
                                            <th class="px-5 py-4">Number of Approved Reservations</th>
                                            <th class="px-5 py-4">Total Sales</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-purple-50">
                                        <?php if(!empty($report['category_summary'])): ?>
                                            <?php foreach($report['category_summary'] as $summary): ?>
                                                <tr>
                                                    <td class="px-5 py-4 font-semibold"><?php echo htmlspecialchars($summary['event_package']); ?></td>
                                                    <td class="px-5 py-4 text-slate-700"><?php echo number_format($summary['reservations']); ?></td>
                                                    <td class="px-5 py-4 font-semibold"><?php echo eventify_reports_money($summary['total_sales']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td class="px-6 py-8 text-center text-slate-500" colspan="3">No category summary available.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </section>
            </section>
        </main>
    </div>

    <div class="no-print fixed inset-0 z-40 hidden bg-dark/50 lg:hidden" data-admin-sidebar>
        <aside class="h-full w-80 max-w-[86vw] overflow-y-auto bg-dark p-6 text-white shadow-soft">
            <div class="flex items-center justify-between">
                <span class="text-2xl font-semibold">Eventify Admin</span>
                <button type="button" class="rounded-xl px-3 py-2 font-semibold text-white" data-admin-sidebar-close>Close</button>
            </div>
            <nav class="mt-8 grid gap-2">
                <a href="dashboard.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Dashboard</a>
                <a href="reservations.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Reservations</a>
                <a href="reports.php" class="rounded-2xl bg-white/10 px-4 py-3 font-bold">Sales Reports</a>
                <a href="payments.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Payments</a>
                <a href="packages.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Packages</a>
                <a href="gallery.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Gallery</a>
                <a href="users.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Users</a>
                <a href="messages.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Messages</a>
                <a href="calendar.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Calendar</a>
                <a href="add_event.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Add Event</a>
                <a href="event_records.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Event Records</a>
                <a href="../auth/logout.php" class="rounded-2xl px-4 py-3 font-bold text-white/75 hover:bg-white/10">Logout</a>
            </nav>
        </aside>
    </div>

    <?php echo eventify_sweetalert_flash(); ?>
    <script src="assets/js/admin.js"></script>
</body>
</html>
