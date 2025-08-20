<?php
// ========================================
// admin/index.php - Dashboard (AKTUALISIERT)
// ========================================
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
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/admin-mobile.css">
</head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>

    <!-- Sidebar -->
    <div class="admin-sidebar" id="adminSidebar">
        <h2>Admin Panel</h2>
        <nav>
            <a href="index.php" class="sidebar-link active">Dashboard</a>
            <a href="appointments.php" class="sidebar-link">Termine</a>
            <a href="services.php" class="sidebar-link">Services</a>
            <a href="calendar.php" class="sidebar-link">Kalender</a>
            <a href="settings.php" class="sidebar-link">Einstellungen</a>
            <a href="logout.php" class="sidebar-link" style="margin-top: 2rem; color: var(--clr-error);">
                Abmelden
            </a>
            <a href="../index.php" class="sidebar-link">zum Termin Planner</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="admin-content">
        <h1 style="margin-bottom: 2rem;">Dashboard</h1>

        <!-- Statistics -->
        <div class="grid grid-4" style="margin-bottom: 3rem;">
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
                <p style="text-align: center; padding: 2rem; color: var(--clr-primary-a40);">
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
    </div>

    <script>
        f // Verbesserte Sidebar-Funktionalität
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            const isActive = sidebar.classList.contains('active');

            if (!isActive) {
                sidebar.classList.add('active');
                // Füge Event-Listener für Klick außerhalb hinzu
                document.addEventListener('click', closeSidebarOnClickOutside);
            } else {
                sidebar.classList.remove('active');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }

        function closeSidebarOnClickOutside(e) {
            const sidebar = document.getElementById('adminSidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');

            if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('active');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }

        // ESC-Taste schließt Sidebar
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const sidebar = document.getElementById('adminSidebar');
                if (sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            }
        });
    </script>
</body>

</html>