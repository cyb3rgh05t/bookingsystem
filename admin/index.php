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

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--clr-surface-a20);
            flex-wrap: wrap;
            gap: 20px;
        }

        .welcome-text h1 {
            color: var(--clr-primary-a20);
            margin-bottom: 5px;
        }

        .welcome-text p {
            color: var(--clr-surface-a50);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--clr-surface-a10) 0%, var(--clr-surface-tonal-a10) 100%);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 8px;
            padding: 20px;
        }

        .stat-card h4 {
            color: var(--clr-surface-a50);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: var(--clr-primary-a20);
            margin-bottom: 5px;
        }

        .stat-card p {
            color: var(--clr-surface-a50);
            font-size: 12px;
        }

        /* Card */
        .card {
            background: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .card h2 {
            color: var(--clr-primary-a20);
            margin: 0;
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

        .btn-primary {
            background-color: var(--clr-primary-a0);
            color: var(--clr-dark-a0);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
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

            .stats-grid {
                grid-template-columns: 1fr;
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
                    <li><a href="index.php" class="active"><i class="fa-solid fa-house"></i>Dashboard</a></li>
                    <li><a href="appointments.php"><i class="fa-solid fa-calendar-check"></i>Termine</a></li>
                    <li><a href="services.php"><i class="fa-solid fa-wrench"></i>Services</a></li>
                    <li><a href="calendar.php"><i class="fa-solid fa-calendar"></i>Kalender</a></li>
                    <li><a href="settings.php"><i class="fa-solid fa-gear"></i>Einstellungen</a></li>
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