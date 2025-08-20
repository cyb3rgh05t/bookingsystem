<?php

session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $db->query("UPDATE appointments SET status = ? WHERE id = ?", [
        $_POST['status'],
        $_POST['appointment_id']
    ]);
    $success = 'Status erfolgreich aktualisiert';
}

// Get filter parameters
$filter_status = $_GET['status'] ?? '';
$filter_date = $_GET['date'] ?? '';

// Build query
$query = "
SELECT a.*, c.first_name, c.last_name, c.email, c.phone
FROM appointments a
JOIN customers c ON a.customer_id = c.id
WHERE 1=1
";
$params = [];

if ($filter_status) {
    $query .= " AND a.status = ?";
    $params[] = $filter_status;
}

if ($filter_date) {
    $query .= " AND a.appointment_date = ?";
    $params[] = $filter_date;
}

$query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$appointments = $db->fetchAll($query, $params);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5">
    <title>Terminverwaltung - Admin</title>
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

        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: var(--clr-surface-a10);
            border-radius: var(--radius-md);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-pending {
            background: var(--clr-warning);
            color: var(--clr-dark-a0);
        }

        .status-confirmed {
            background: var(--clr-info);
            color: var(--clr-light-a0);
        }

        .status-completed {
            background: var(--clr-success);
            color: var(--clr-light-a0);
        }

        .status-cancelled {
            background: var(--clr-error);
            color: var(--clr-light-a0);
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
            <a href="appointments.php" class="sidebar-link active">Termine</a>
            <a href="services.php" class="sidebar-link">Services</a>
            <a href="calendar.php" class="sidebar-link">Kalender</a>
            <a href="settings.php" class="sidebar-link">Einstellungen</a>
            <a href="logout.php" class="sidebar-link" style="margin-top: 2rem; color: var(--clr-error);">
                Abmelden
            </a>
        </nav>
    </div>

    <div class="admin-content">
        <h1 style="margin-bottom: 2rem;">Terminverwaltung</h1>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 1rem; width: 100%;">
                <select name="status" class="form-control" style="width: 200px;">
                    <option value="">Alle Status</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Ausstehend</option>
                    <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Bestätigt</option>
                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Abgeschlossen</option>
                    <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Storniert</option>
                </select>

                <input type="date" name="date" class="form-control" value="<?php echo $filter_date; ?>" style="width: 200px;">

                <button type="submit" class="btn btn-primary">Filtern</button>
                <a href="appointments.php" class="btn">Zurücksetzen</a>
            </form>
        </div>

        <!-- Appointments Table -->
        <div class="card">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--clr-surface-a30);">
                        <th style="padding: 1rem; text-align: left;">ID</th>
                        <th style="padding: 1rem; text-align: left;">Kunde</th>
                        <th style="padding: 1rem; text-align: left;">Kontakt</th>
                        <th style="padding: 1rem; text-align: left;">Datum/Zeit</th>
                        <th style="padding: 1rem; text-align: left;">Services</th>
                        <th style="padding: 1rem; text-align: left;">Betrag</th>
                        <th style="padding: 1rem; text-align: left;">Status</th>
                        <th style="padding: 1rem; text-align: left;">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr style="border-bottom: 1px solid var(--clr-surface-a20);">
                            <td style="padding: 1rem;">#<?php echo $appointment['id']; ?></td>
                            <td style="padding: 1rem;">
                                <strong><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></strong>
                            </td>
                            <td style="padding: 1rem;">
                                <?php echo htmlspecialchars($appointment['email']); ?><br>
                                <small><?php echo htmlspecialchars($appointment['phone']); ?></small>
                            </td>
                            <td style="padding: 1rem;">
                                <?php echo date('d.m.Y', strtotime($appointment['appointment_date'])); ?><br>
                                <strong><?php echo $appointment['appointment_time']; ?></strong>
                            </td>
                            <td style="padding: 1rem;">
                                <?php
                                $services = $db->fetchAll("
                                SELECT s.name 
                                FROM appointment_services aps
                                JOIN services s ON aps.service_id = s.id
                                WHERE aps.appointment_id = ?
                            ", [$appointment['id']]);
                                foreach ($services as $service) {
                                    echo htmlspecialchars($service['name']) . '<br>';
                                }
                                ?>
                            </td>
                            <td style="padding: 1rem;">
                                <strong><?php echo number_format($appointment['total_price'], 2); ?>€</strong>
                            </td>
                            <td style="padding: 1rem;">
                                <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                    <?php
                                    $statusLabels = [
                                        'pending' => 'Ausstehend',
                                        'confirmed' => 'Bestätigt',
                                        'completed' => 'Abgeschlossen',
                                        'cancelled' => 'Storniert'
                                    ];
                                    echo $statusLabels[$appointment['status']];
                                    ?>
                                </span>
                            </td>
                            <td style="padding: 1rem;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <select name="status" onchange="this.form.submit()" class="form-control" style="width: 120px; font-size: 0.875rem;">
                                        <option value="pending" <?php echo $appointment['status'] === 'pending' ? 'selected' : ''; ?>>Ausstehend</option>
                                        <option value="confirmed" <?php echo $appointment['status'] === 'confirmed' ? 'selected' : ''; ?>>Bestätigt</option>
                                        <option value="completed" <?php echo $appointment['status'] === 'completed' ? 'selected' : ''; ?>>Abgeschlossen</option>
                                        <option value="cancelled" <?php echo $appointment['status'] === 'cancelled' ? 'selected' : ''; ?>>Storniert</option>
                                    </select>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($appointments)): ?>
                <p style="text-align: center; padding: 3rem; color: var(--clr-primary-a40);">
                    Keine Termine gefunden.
                </p>
            <?php endif; ?>
        </div>
    </div>
    <script>
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