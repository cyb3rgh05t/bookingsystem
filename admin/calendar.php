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
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/admin-mobile.css">

<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>

    <!-- Sidebar - WICHTIG: id="adminSidebar" hinzugefügt! -->
    <div class="admin-sidebar" id="adminSidebar">
        <h2>Admin Panel</h2>
        <nav>
            <a href="index.php" class="sidebar-link">Dashboard</a>
            <a href="appointments.php" class="sidebar-link">Termine</a>
            <a href="services.php" class="sidebar-link">Services</a>
            <a href="calendar.php" class="sidebar-link active">Kalender</a>
            <a href="settings.php" class="sidebar-link">Einstellungen</a>
            <a href="logout.php" class="sidebar-link" style="margin-top: 2rem; color: var(--clr-error);">
                Abmelden
            </a>
            <a href="../index.php" class="sidebar-link">zum Termin Planner</a>
        </nav>
    </div>

    <div class="admin-content">
        <h1 style="margin-bottom: 2rem;">Kalender-Verwaltung</h1>

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
            <div class="calendar-cell-header" data-short="So">So</div>
            <div class="calendar-cell-header" data-short="Mo">Mo</div>
            <div class="calendar-cell-header" data-short="Di">Di</div>
            <div class="calendar-cell-header" data-short="Mi">Mi</div>
            <div class="calendar-cell-header" data-short="Do">Do</div>
            <div class="calendar-cell-header" data-short="Fr">Fr</div>
            <div class="calendar-cell-header" data-short="Sa">Sa</div>

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

        // Verbesserte Sidebar-Funktionalität
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const isActive = sidebar.classList.contains('active');

            if (!isActive) {
                sidebar.classList.add('active');
                // Füge Event-Listener für Klick außerhalb hinzu
                setTimeout(() => {
                    document.addEventListener('click', closeSidebarOnClickOutside);
                }, 100);
            } else {
                sidebar.classList.remove('active');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }

        function closeSidebarOnClickOutside(e) {
            const sidebar = document.getElementById('adminSidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');

            if (sidebar && toggle && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('active');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }

        // ESC-Taste schließt Sidebar
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const sidebar = document.getElementById('adminSidebar');
                if (sidebar && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                    document.removeEventListener('click', closeSidebarOnClickOutside);
                }
            }
        });
    </script>
</body>

</html>