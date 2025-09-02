<?php

session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();

// Handle blocking times
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['block_date'])) {
        $db->query("
            INSERT INTO blocked_times (date, start_time, end_time, reason, is_full_day)
            VALUES (?, ?, ?, ?, ?)
        ", [
            $_POST['date'],
            $_POST['start_time'] ?: null,
            $_POST['end_time'] ?: null,
            $_POST['reason'],
            $_POST['is_full_day'] ?? 0
        ]);
        $success = 'Zeit erfolgreich blockiert';
    } elseif (isset($_POST['unblock'])) {
        $db->query("DELETE FROM blocked_times WHERE id = ?", [$_POST['block_id']]);
        $success = 'Blockierung aufgehoben';
    }
}

// Get current month
$month = $_GET['month'] ?? date('Y-m');
$monthTime = strtotime($month . '-01');
$daysInMonth = date('t', $monthTime);
$firstDayOfWeek = date('w', $monthTime);

// Get appointments for this month
$appointments = $db->fetchAll("
    SELECT appointment_date, COUNT(*) as count 
    FROM appointments 
    WHERE appointment_date LIKE ?
    AND status != 'cancelled'
    GROUP BY appointment_date
", [$month . '%']);

$appointmentDates = [];
foreach ($appointments as $apt) {
    $appointmentDates[$apt['appointment_date']] = $apt['count'];
}

// Get blocked times for this month
$blocked = $db->fetchAll("
    SELECT * FROM blocked_times 
    WHERE date LIKE ?
    ORDER BY date, start_time
", [$month . '%']);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5">
    <title>Kalender - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">

    <style>
        /* CSS Custom Properties - Dein Farbschema */
        :root {
            --clr-dark-a0: #000000;
            --clr-light-a0: #ffffff;
            --clr-primary-a0: #e6a309;
            --clr-primary-a10: #ebad36;
            --clr-primary-a20: #f0b753;
            --clr-primary-a30: #f4c16c;
            --clr-primary-a40: #f8cb85;
            --clr-primary-a50: #fbd59d;
            --clr-surface-a0: #141414;
            --clr-surface-a05: #1f1f1f;
            --clr-surface-a10: #292929;
            --clr-surface-a20: #404040;
            --clr-surface-a30: #585858;
            --clr-surface-a40: #727272;
            --clr-surface-a50: #8c8c8c;
            --clr-surface-a60: #a6a6a6;
            --clr-surface-a70: #c0c0c0;
            --clr-surface-a80: #d9d9d9;
            --clr-surface-tonal-a0: #272017;
            --clr-surface-tonal-a10: #3c352c;
            --clr-surface-tonal-a20: #514b43;
            --clr-surface-tonal-a30: #68625b;
            --clr-surface-tonal-a40: #7f7a74;
            --clr-surface-tonal-a50: #98938e;
            --clr-success: #22c55e;
            --clr-error: #ef4444;
            --clr-warning: #fbbf24;
            --clr-info: #3b82f6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--clr-surface-a0);
            color: var(--clr-light-a0);
            line-height: 1.6;
        }

        /* Layout */
        .app-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background-color: var(--clr-surface-a10);
            border-right: 1px solid var(--clr-surface-a20);
            padding: 20px 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            flex-shrink: 0;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--clr-surface-a20);
            margin-bottom: 20px;
            text-align: center;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 15px;
            text-decoration: none;
            transition: transform 0.2s ease;
        }

        .sidebar-logo:hover {
            transform: scale(1.02);
        }

        .sidebar-logo-image {
            width: 100px;
            height: 100px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
        }

        .sidebar-welcome {
            color: var(--clr-surface-a50);
            font-size: 14px;
            margin: 0;
        }

        .sidebar-nav {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .sidebar-nav li {
            margin-bottom: 5px;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--clr-surface-a50);
            text-decoration: none;
            transition: all 0.3s ease;
            border-right: 3px solid transparent;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background-color: var(--clr-surface-tonal-a10);
            color: var(--clr-primary-a20);
            border-right: 3px solid var(--clr-primary-a0);
        }

        .sidebar-nav a i {
            margin-right: 12px;
            width: 20px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
            background-color: var(--clr-surface-a0);
            min-height: 100vh;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .page-header h1 {
            color: var(--clr-primary-a20);
            margin-bottom: 5px;
        }

        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-success {
            background-color: rgba(34, 197, 94, 0.1);
            border: 1px solid #22c55e;
            color: #86efac;
        }

        .alert-info {
            background-color: rgba(59, 130, 246, 0.1);
            border: 1px solid #3b82f6;
            color: #93c5fd;
        }

        /* Calendar Navigation */
        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 8px;
            padding: 20px;
        }

        .calendar-nav h2 {
            color: var(--clr-primary-a20);
            margin: 0;
        }

        /* Calendar Grid */
        .calendar-admin {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            background: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .calendar-cell-header {
            padding: 10px;
            text-align: center;
            font-weight: 600;
            color: var(--clr-primary-a20);
            background: var(--clr-surface-tonal-a10);
            border-radius: 6px;
        }

        .calendar-cell {
            min-height: 80px;
            padding: 10px;
            background: var(--clr-surface-a05);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 6px;
            position: relative;
        }

        .day-number {
            font-weight: 600;
            color: var(--clr-surface-a70);
            margin-bottom: 5px;
        }

        .appointment-count {
            font-size: 12px;
            color: var(--clr-info);
            background: rgba(59, 130, 246, 0.2);
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 3px;
        }

        .blocked-indicator {
            font-size: 12px;
            color: var(--clr-error);
            background: rgba(239, 68, 68, 0.2);
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
        }

        /* Card */
        .card {
            background: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .card h3 {
            color: var(--clr-primary-a20);
            margin: 0;
        }

        /* Grid */
        .grid {
            display: grid;
            gap: 16px;
        }

        .grid-2 {
            grid-template-columns: 1fr 1fr;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            color: var(--clr-surface-a50);
            font-weight: 500;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            background: var(--clr-surface-a20);
            border: 1px solid var(--clr-surface-a30);
            border-radius: 6px;
            color: var(--clr-light-a0);
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--clr-primary-a0);
            background: var(--clr-surface-a30);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 20px;
            background-color: var(--clr-primary-a0);
            color: var(--clr-dark-a0);
            text-decoration: none;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn:hover {
            background-color: var(--clr-primary-a10);
            transform: translateY(-1px);
        }

        .btn-warning {
            background-color: var(--clr-warning);
            color: var(--clr-dark-a0);
        }

        .btn-danger {
            background-color: var(--clr-error);
            color: var(--clr-light-a0);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        /* Table */
        .table-responsive {
            overflow-x: auto;
        }

        .admin-table {
            width: 100%;
            background-color: var(--clr-surface-a05);
            border-radius: 8px;
            overflow: hidden;
        }

        .admin-table th,
        .admin-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .admin-table th {
            background-color: var(--clr-surface-tonal-a10);
            color: var(--clr-primary-a20);
            font-weight: 600;
            font-size: 14px;
        }

        .admin-table tr:last-child td {
            border-bottom: none;
        }

        .admin-table tr:hover {
            background-color: var(--clr-surface-a10);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .app-layout {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: static;
                border-right: none;
                border-bottom: 1px solid var(--clr-surface-a20);
                padding: 15px 0;
            }

            .sidebar-nav {
                display: flex;
                overflow-x: auto;
                padding: 0 10px;
                scrollbar-width: none;
            }

            .sidebar-nav::-webkit-scrollbar {
                display: none;
            }

            .sidebar-nav li {
                margin-bottom: 0;
                margin-right: 5px;
                flex-shrink: 0;
            }

            .sidebar-nav a {
                padding: 10px 15px;
                border-right: none;
                border-bottom: 3px solid transparent;
                white-space: nowrap;
            }

            .sidebar-nav a.active,
            .sidebar-nav a:hover {
                border-right: none;
                border-bottom: 3px solid var(--clr-primary-a0);
            }

            .main-content {
                padding: 15px;
            }

            .grid-2 {
                grid-template-columns: 1fr;
            }

            .calendar-admin {
                gap: 5px;
                padding: 10px;
            }

            .calendar-cell {
                min-height: 60px;
                padding: 5px;
            }

            .calendar-cell-header[data-short]::after {
                content: attr(data-short);
            }

            .calendar-cell-header {
                font-size: 12px;
            }

            .calendar-cell-header span {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-logo">
                    <img src="../assets/images/logo.png" alt="Logo" class="sidebar-logo-image">
                </a>
                <p class="sidebar-welcome">Admin Panel</p>
            </div>

            <nav>
                <ul class="sidebar-nav">
                    <li><a href="index.php"><i class="fa-solid fa-house"></i>Dashboard</a></li>
                    <li><a href="appointments.php"><i class="fa-solid fa-calendar-check"></i>Termine</a></li>
                    <li><a href="services.php"><i class="fa-solid fa-wrench"></i>Services</a></li>
                    <li><a href="calendar.php" class="active"><i class="fa-solid fa-calendar"></i>Kalender</a></li>
                    <li><a href="settings.php"><i class="fa-solid fa-gear"></i>Einstellungen</a></li>
                    <li><a href="../index.php" style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;"><i class="fa-solid fa-arrow-left"></i>Zum Termin Planner</a></li>
                    <li><a href="logout.php" style="color: var(--clr-error);"><i class="fa-solid fa-right-from-bracket"></i>Abmelden</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-calendar"></i> Kalender-Verwaltung</h1>
                    <p style="color: var(--clr-surface-a50);">Verwalte verfügbare Zeiten und Blockierungen</p>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Month Navigation -->
            <div class="calendar-nav">
                <a href="?month=<?php echo date('Y-m', strtotime($month . ' -1 month')); ?>" class="btn">←</a>
                <h2><?php echo date('F Y', $monthTime); ?></h2>
                <a href="?month=<?php echo date('Y-m', strtotime($month . ' +1 month')); ?>" class="btn">→</a>
            </div>

            <!-- Calendar Grid -->
            <div class="calendar-admin">
                <div class="calendar-cell-header" data-short="So">Sonntag</div>
                <div class="calendar-cell-header" data-short="Mo">Montag</div>
                <div class="calendar-cell-header" data-short="Di">Dienstag</div>
                <div class="calendar-cell-header" data-short="Mi">Mittwoch</div>
                <div class="calendar-cell-header" data-short="Do">Donnerstag</div>
                <div class="calendar-cell-header" data-short="Fr">Freitag</div>
                <div class="calendar-cell-header" data-short="Sa">Samstag</div>

                <?php
                // Empty cells before first day
                for ($i = 0; $i < $firstDayOfWeek; $i++) {
                    echo '<div class="calendar-cell"></div>';
                }

                // Days of month
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $dateStr = $month . '-' . str_pad($day, 2, '0', STR_PAD_LEFT);
                    $hasAppointments = isset($appointmentDates[$dateStr]);
                    $appointmentCount = $appointmentDates[$dateStr] ?? 0;

                    // Check if blocked
                    $isBlocked = false;
                    foreach ($blocked as $block) {
                        if ($block['date'] === $dateStr) {
                            $isBlocked = true;
                            break;
                        }
                    }

                    echo '<div class="calendar-cell">';
                    echo '<div class="day-number">' . $day . '</div>';

                    if ($hasAppointments) {
                        echo '<div class="appointment-count">' . $appointmentCount . ' Termin(e)</div>';
                    }

                    if ($isBlocked) {
                        echo '<div class="blocked-indicator">Blockiert</div>';
                    }

                    echo '</div>';
                }
                ?>
            </div>

            <!-- Block Time Form -->
            <div class="card">
                <h3 style="margin-bottom: 1.5rem;">Zeit blockieren</h3>

                <form method="POST">
                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Datum</label>
                            <input type="date" name="date" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Grund</label>
                            <input type="text" name="reason" class="form-control" placeholder="z.B. Urlaub, Krankheit">
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <input type="checkbox" name="is_full_day" value="1" onchange="toggleTimeInputs(this)">
                                Ganzer Tag
                            </label>
                        </div>

                        <div></div>

                        <div class="form-group" id="time-inputs">
                            <label class="form-label">Von</label>
                            <input type="time" name="start_time" class="form-control">
                        </div>

                        <div class="form-group" id="time-inputs-2">
                            <label class="form-label">Bis</label>
                            <input type="time" name="end_time" class="form-control">
                        </div>
                    </div>

                    <button type="submit" name="block_date" class="btn btn-warning">Zeit blockieren</button>
                </form>
            </div>

            <!-- Blocked Times List -->
            <div class="card">
                <h3 style="margin-bottom: 1.5rem;">Blockierte Zeiten</h3>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Zeit</th>
                                <th>Grund</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($blocked as $block): ?>
                                <tr>
                                    <td>
                                        <?php echo date('d.m.Y', strtotime($block['date'])); ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($block['is_full_day']) {
                                            echo 'Ganzer Tag';
                                        } else {
                                            echo $block['start_time'] . ' - ' . $block['end_time'];
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($block['reason'] ?: '-'); ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="block_id" value="<?php echo $block['id']; ?>">
                                            <button type="submit" name="unblock" class="btn btn-danger btn-sm">
                                                Aufheben
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (empty($blocked)): ?>
                    <p style="text-align: center; padding: 2rem; color: var(--clr-surface-a40);">
                        Keine blockierten Zeiten vorhanden.
                    </p>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function toggleTimeInputs(checkbox) {
            const timeInputs = document.getElementById('time-inputs');
            const timeInputs2 = document.getElementById('time-inputs-2');

            if (checkbox.checked) {
                timeInputs.style.display = 'none';
                timeInputs2.style.display = 'none';
                timeInputs.querySelector('input').required = false;
                timeInputs2.querySelector('input').required = false;
            } else {
                timeInputs.style.display = 'block';
                timeInputs2.style.display = 'block';
                timeInputs.querySelector('input').required = true;
                timeInputs2.querySelector('input').required = true;
            }
        }
    </script>
</body>

</html>