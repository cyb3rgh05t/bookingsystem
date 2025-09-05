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
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
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
                    <li><a href="lexware-export.php"><i class="fa-solid fa-file-export"></i>Lexware Export</a></li>
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