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

// Build query - WICHTIG: c.address wurde hinzugefügt!
$query = "
SELECT a.*, 
       c.first_name, 
       c.last_name, 
       c.email, 
       c.phone,
       c.address,
       c.car_brand,
       c.car_model,
       c.car_year,
       c.license_plate
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

        /* Filter Bar */
        .filter-bar {
            background: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .filter-bar form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        /* Form Elements */
        .form-control {
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

        .btn-primary {
            background-color: var(--clr-primary-a0);
            color: var(--clr-dark-a0);
        }

        .filter-buttons {
            display: flex;
            gap: 8px;
        }

        /* Card */
        .card {
            background: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
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

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background-color: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }

        .status-confirmed {
            background-color: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }

        .status-completed {
            background-color: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .status-cancelled {
            background-color: rgba(239, 68, 68, 0.2);
            color: #ef4444;
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

            .filter-bar form {
                flex-direction: column;
            }

            .filter-buttons {
                width: 100%;
            }

            .btn {
                width: 100%;
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
                    <li><a href="appointments.php" class="active"><i class="fa-solid fa-calendar-check"></i>Termine</a></li>
                    <li><a href="services.php"><i class="fa-solid fa-wrench"></i>Services</a></li>
                    <li><a href="calendar.php"><i class="fa-solid fa-calendar"></i>Kalender</a></li>
                    <li><a href="settings.php"><i class="fa-solid fa-gear"></i>Einstellungen</a></li>
                    <li><a href="../index.php" style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;"><i class="fa-solid fa-arrow-left"></i>Zum Termin Planner</a></li>
                    <li><a href="logout.php" style="color: var(--clr-error);"><i class="fa-solid fa-right-from-bracket"></i>Abmelden</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-calendar-check"></i> Terminverwaltung</h1>
                    <p style="color: var(--clr-surface-a50);">Verwalte alle Termine und Buchungen</p>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Filter -->
            <div class="filter-bar">
                <form method="GET">
                    <select name="status" class="form-control">
                        <option value="">Alle Status</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Ausstehend</option>
                        <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Bestätigt</option>
                        <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Abgeschlossen</option>
                        <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Storniert</option>
                    </select>

                    <input type="date" name="date" class="form-control" value="<?php echo $filter_date; ?>">

                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">Filtern</button>
                        <a href="appointments.php" class="btn">Zurücksetzen</a>
                    </div>
                </form>
            </div>

            <!-- Appointments Table -->
            <div class="card">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kunde</th>
                                <th>Kontakt</th>
                                <th>Adresse</th>
                                <th>Fahrzeug</th>
                                <th>Datum/Zeit</th>
                                <th>Services</th>
                                <th>Betrag</th>
                                <th>Status</th>
                                <th>Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td>#<?php echo $appointment['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($appointment['email']); ?><br>
                                        <small><?php echo htmlspecialchars($appointment['phone']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($appointment['address'] ?? 'Keine Adresse angegeben'); ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($appointment['car_brand']) || !empty($appointment['car_model'])): ?>
                                            <small>
                                                <?php echo htmlspecialchars($appointment['car_brand'] ?? ''); ?>
                                                <?php echo htmlspecialchars($appointment['car_model'] ?? ''); ?><br>
                                                <?php if (!empty($appointment['car_year'])): ?>
                                                    Jahr: <?php echo htmlspecialchars($appointment['car_year']); ?><br>
                                                <?php endif; ?>
                                                <?php if (!empty($appointment['license_plate'])): ?>
                                                    <?php echo htmlspecialchars($appointment['license_plate']); ?>
                                                <?php endif; ?>
                                            </small>
                                        <?php else: ?>
                                            <small style="color: var(--clr-surface-a40);">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d.m.Y', strtotime($appointment['appointment_date'])); ?><br>
                                        <strong><?php echo $appointment['appointment_time']; ?></strong>
                                    </td>
                                    <td>
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
                                    <td>
                                        <strong><?php echo number_format($appointment['total_price'], 2, ',', '.'); ?>€</strong>
                                        <?php if ($appointment['distance_km'] > 0): ?>
                                            <br><small><?php echo number_format($appointment['distance_km'], 1, ',', '.'); ?> km</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $appointment['status']; ?>">
                                            <?php
                                            $statusLabels = [
                                                'pending' => 'Ausstehend',
                                                'confirmed' => 'Bestätigt',
                                                'completed' => 'Abgeschlossen',
                                                'cancelled' => 'Storniert'
                                            ];
                                            echo $statusLabels[$appointment['status']] ?? $appointment['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td>
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
                </div>

                <?php if (empty($appointments)): ?>
                    <p style="text-align: center; padding: 3rem; color: var(--clr-surface-a40);">
                        Keine Termine gefunden.
                    </p>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>