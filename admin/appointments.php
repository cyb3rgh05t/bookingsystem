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
                    <li><a href="appointments.php" class="active"><i class="fa-solid fa-calendar-check"></i>Termine</a></li>
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