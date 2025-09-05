<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

// Check admin login
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();

// Get statistics
$stats = [
    'today_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = DATE('now')")['count'] ?? 0,
    'week_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE appointment_date >= DATE('now', '-7 days')")['count'] ?? 0,
    'total_revenue' => $db->fetch("SELECT SUM(total_price) as sum FROM appointments WHERE status = 'completed'")['sum'] ?? 0,
    'pending_appointments' => $db->fetch("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'")['count'] ?? 0
];

// Get recent appointments
$recent = $db->fetchAll("
    SELECT a.*, c.first_name, c.last_name 
    FROM appointments a 
    JOIN customers c ON a.customer_id = c.id 
    ORDER BY a.created_at DESC 
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5">
    <title>Admin Dashboard</title>
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
                    <li><a href="index.php" class="active"><i class="fa-solid fa-house"></i>Dashboard</a></li>
                    <li><a href="appointments.php"><i class="fa-solid fa-calendar-check"></i>Termine</a></li>
                    <li><a href="services.php"><i class="fa-solid fa-wrench"></i>Services</a></li>
                    <li><a href="calendar.php"><i class="fa-solid fa-calendar"></i>Kalender</a></li>
                    <li><a href="settings.php"><i class="fa-solid fa-gear"></i>Einstellungen</a></li>
                    <li><a href="lexware-export.php"><i class="fa-solid fa-file-export"></i>Lexware Export</a></li>
                    <li><a href="../index.php" style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;"><i class="fa-solid fa-arrow-left"></i>Zum Termin Planner</a></li>
                    <li><a href="logout.php" style="color: var(--clr-error);"><i class="fa-solid fa-right-from-bracket"></i>Abmelden</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="dashboard-header">
                <div class="welcome-text">
                    <h1><i class="fa-solid fa-house"></i> Dashboard</h1>
                    <p>Übersicht über alle Aktivitäten und Statistiken</p>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Heute</h4>
                    <div class="stat-value"><?php echo $stats['today_appointments']; ?></div>
                    <p>Termine</p>
                </div>

                <div class="stat-card">
                    <h4>Diese Woche</h4>
                    <div class="stat-value"><?php echo $stats['week_appointments']; ?></div>
                    <p>Termine</p>
                </div>

                <div class="stat-card">
                    <h4>Umsatz</h4>
                    <div class="stat-value"><?php echo number_format($stats['total_revenue'], 2, ',', '.'); ?>€</div>
                    <p>Gesamt</p>
                </div>

                <div class="stat-card">
                    <h4>Ausstehend</h4>
                    <div class="stat-value"><?php echo $stats['pending_appointments']; ?></div>
                    <p>Termine</p>
                </div>
            </div>

            <!-- Recent Appointments -->
            <div class="card">
                <h2 style="margin-bottom: 1.5rem;">Neueste Termine</h2>

                <?php if (empty($recent)): ?>
                    <p style="text-align: center; padding: 2rem; color: var(--clr-surface-a40);">
                        Noch keine Termine vorhanden.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Kunde</th>
                                    <th>Datum</th>
                                    <th>Zeit</th>
                                    <th>Betrag</th>
                                    <th>Status</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent as $appointment): ?>
                                    <tr>
                                        <td>#<?php echo $appointment['id']; ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                                        </td>
                                        <td><?php echo date('d.m.Y', strtotime($appointment['appointment_date'])); ?></td>
                                        <td><?php echo $appointment['appointment_time']; ?></td>
                                        <td><?php echo number_format($appointment['total_price'], 2, ',', '.'); ?>€</td>
                                        <td>
                                            <?php
                                            $statusColors = [
                                                'pending' => 'var(--clr-warning)',
                                                'confirmed' => 'var(--clr-info)',
                                                'completed' => 'var(--clr-success)',
                                                'cancelled' => 'var(--clr-error)'
                                            ];
                                            $statusLabels = [
                                                'pending' => 'Ausstehend',
                                                'confirmed' => 'Bestätigt',
                                                'completed' => 'Abgeschlossen',
                                                'cancelled' => 'Storniert'
                                            ];
                                            ?>
                                            <span style="color: <?php echo $statusColors[$appointment['status']]; ?>">
                                                <?php echo $statusLabels[$appointment['status']]; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="appointments.php?id=<?php echo $appointment['id']; ?>"
                                                class="btn btn-primary btn-sm">
                                                Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>