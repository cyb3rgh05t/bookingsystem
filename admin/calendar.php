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
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/mobile.css">
    <style>
        .admin-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 250px;
            background: var(--clr-surface-tonal-a0);
            padding: 2rem 0;
            overflow-y: auto;
        }

        .admin-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .sidebar-link {
            display: block;
            padding: 1rem 1.5rem;
            color: var(--clr-primary-a30);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            background: var(--clr-surface-a20);
            color: var(--clr-primary-a0);
        }

        .calendar-admin {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 1px;
            background: var(--clr-surface-a30);
            border: 1px solid var(--clr-surface-a30);
        }

        .calendar-cell {
            background: var(--clr-surface-a10);
            padding: 0.5rem;
            min-height: 80px;
            position: relative;
        }

        .calendar-cell-header {
            font-weight: bold;
            text-align: center;
            padding: 1rem;
            background: var(--clr-surface-a20);
        }

        .day-number {
            font-weight: bold;
            margin-bottom: 0.25rem;
        }

        .appointment-count {
            font-size: 0.75rem;
            color: var(--clr-info);
        }

        .blocked-indicator {
            font-size: 0.75rem;
            color: var(--clr-error);
        }
    </style>
</head>

<body>
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>
    <div class="admin-sidebar">
        <h2 style="padding: 0 1.5rem; margin-bottom: 2rem; color: var(--clr-primary-a0);">
            Admin Panel
        </h2>
        <nav>
            <a href="index.php" class="sidebar-link">Dashboard</a>
            <a href="appointments.php" class="sidebar-link">Termine</a>
            <a href="services.php" class="sidebar-link">Services</a>
            <a href="calendar.php" class="sidebar-link active">Kalender</a>
            <a href="settings.php" class="sidebar-link">Einstellungen</a>
            <a href="logout.php" class="sidebar-link" style="margin-top: 2rem; color: var(--clr-error);">
                Abmelden
            </a>
        </nav>
    </div>

    <div class="admin-content">
        <h1 style="margin-bottom: 2rem;">Kalender-Verwaltung</h1>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Month Navigation -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <a href="?month=<?php echo date('Y-m', strtotime($month . ' -1 month')); ?>" class="btn">← Vorheriger Monat</a>
            <h2><?php echo date('F Y', $monthTime); ?></h2>
            <a href="?month=<?php echo date('Y-m', strtotime($month . ' +1 month')); ?>" class="btn">Nächster Monat →</a>
        </div>

        <!-- Calendar Grid -->
        <div class="calendar-admin">
            <div class="calendar-cell-header">So</div>
            <div class="calendar-cell-header">Mo</div>
            <div class="calendar-cell-header">Di</div>
            <div class="calendar-cell-header">Mi</div>
            <div class="calendar-cell-header">Do</div>
            <div class="calendar-cell-header">Fr</div>
            <div class="calendar-cell-header">Sa</div>

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
        <div class="card" style="margin-top: 3rem;">
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
        <div class="card" style="margin-top: 2rem;">
            <h3 style="margin-bottom: 1.5rem;">Blockierte Zeiten</h3>

            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--clr-surface-a30);">
                        <th style="padding: 1rem; text-align: left;">Datum</th>
                        <th style="padding: 1rem; text-align: left;">Zeit</th>
                        <th style="padding: 1rem; text-align: left;">Grund</th>
                        <th style="padding: 1rem; text-align: left;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blocked as $block): ?>
                        <tr style="border-bottom: 1px solid var(--clr-surface-a20);">
                            <td style="padding: 1rem;">
                                <?php echo date('d.m.Y', strtotime($block['date'])); ?>
                            </td>
                            <td style="padding: 1rem;">
                                <?php
                                if ($block['is_full_day']) {
                                    echo 'Ganzer Tag';
                                } else {
                                    echo $block['start_time'] . ' - ' . $block['end_time'];
                                }
                                ?>
                            </td>
                            <td style="padding: 1rem;">
                                <?php echo htmlspecialchars($block['reason'] ?: '-'); ?>
                            </td>
                            <td style="padding: 1rem;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="block_id" value="<?php echo $block['id']; ?>">
                                    <button type="submit" name="unblock" class="btn btn-danger" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                        Aufheben
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($blocked)): ?>
                <p style="text-align: center; padding: 2rem; color: var(--clr-primary-a40);">
                    Keine blockierten Zeiten vorhanden.
                </p>
            <?php endif; ?>
        </div>
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

        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            sidebar.classList.toggle('active');
        }

        // NEU: Touch-Optimierung hinzufügen
        if ('ontouchstart' in window) {
            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('touch-device');

                // Verbessere Touch-Feedback
                document.querySelectorAll('.btn, .time-slot, .calendar-day').forEach(el => {
                    el.addEventListener('touchstart', function() {
                        this.classList.add('touch-active');
                    });
                    el.addEventListener('touchend', function() {
                        setTimeout(() => this.classList.remove('touch-active'), 100);
                    });
                });
            });
        }

        // NEU: Sidebar schließen bei Klick außerhalb
        function closeSidebarOutside(e) {
            const sidebar = document.getElementById('adminSidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');

            if (sidebar && toggle && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        }

        // Füge Event Listener hinzu wenn Sidebar geöffnet wird
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('adminSidebar');
            if (sidebar && sidebar.classList.contains('active')) {
                closeSidebarOutside(e);
            }
        });
    </script>
</body>

</html>