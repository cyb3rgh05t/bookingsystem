<?php
// admin/services.php - Service-Verwaltung
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $db->query("INSERT INTO services (name, description, price, duration_minutes, background_image, is_active) 
                       VALUES (?, ?, ?, ?, ?, ?)", [
                $_POST['name'],
                $_POST['description'],
                $_POST['price'],
                $_POST['duration_minutes'],
                $_POST['background_image'],
                $_POST['is_active'] ?? 1
            ]);
            $success = 'Service erfolgreich hinzugefügt';
        } elseif ($_POST['action'] === 'toggle') {
            $db->query("UPDATE services SET is_active = NOT is_active WHERE id = ?", [$_POST['service_id']]);
            $success = 'Service-Status geändert';
        } elseif ($_POST['action'] === 'delete') {
            $db->query("DELETE FROM services WHERE id = ?", [$_POST['service_id']]);
            $success = 'Service gelöscht';
        } elseif ($_POST['action'] === 'edit') {
            $db->query("UPDATE services SET name = ?, description = ?, price = ?, duration_minutes = ?, background_image = ? WHERE id = ?", [
                $_POST['name'],
                $_POST['description'],
                $_POST['price'],
                $_POST['duration_minutes'],
                $_POST['background_image'],
                $_POST['service_id']
            ]);
            $success = 'Service erfolgreich bearbeitet';
        }
    }
}

// Get all services
$services = $db->fetchAll("SELECT * FROM services ORDER BY name");
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5">
    <title>Service-Verwaltung - Admin</title>
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

        .btn-primary {
            background-color: var(--clr-primary-a0);
            color: var(--clr-dark-a0);
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

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background-color: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .modal-header h3 {
            color: var(--clr-primary-a20);
            margin: 0;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--clr-surface-a20);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
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
                    <li><a href="services.php" class="active"><i class="fa-solid fa-wrench"></i>Services</a></li>
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
                    <h1><i class="fa-solid fa-wrench"></i> Service-Verwaltung</h1>
                    <p style="color: var(--clr-surface-a50);">Verwalte deine angebotenen Dienstleistungen</p>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Add Service Form -->
            <div class="card">
                <h3 style="margin-bottom: 1.5rem;">Neuen Service hinzufügen</h3>

                <form method="POST">
                    <input type="hidden" name="action" value="add">

                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Name *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Preis (€) *</label>
                            <input type="number" name="price" class="form-control" step="0.01" min="0" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Dauer (Minuten) *</label>
                            <input type="number" name="duration_minutes" class="form-control" min="15" step="15" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Hintergrundbild</label>
                            <input type="text" name="background_image" class="form-control" placeholder="service-image.jpg">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Beschreibung</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Optionale Beschreibung des Services..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Service hinzufügen</button>
                </form>
            </div>

            <!-- Services List -->
            <div class="card">
                <h3 style="margin-bottom: 1.5rem;">Vorhandene Services</h3>

                <?php if (empty($services)): ?>
                    <p style="text-align: center; padding: 2rem; color: var(--clr-surface-a40);">
                        Noch keine Services vorhanden. Fügen Sie oben einen neuen Service hinzu.
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Preis</th>
                                    <th>Dauer</th>
                                    <th>Status</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($services as $service): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($service['name']); ?></strong>
                                            <?php if (!empty($service['description'])): ?>
                                                <br>
                                                <small style="color: var(--clr-surface-a40);">
                                                    <?php echo htmlspecialchars($service['description']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo number_format($service['price'], 2, ',', '.'); ?> €</td>
                                        <td><?php echo $service['duration_minutes']; ?> Min.</td>
                                        <td>
                                            <?php if ($service['is_active']): ?>
                                                <span style="color: var(--clr-success);">✔ Aktiv</span>
                                            <?php else: ?>
                                                <span style="color: var(--clr-error);">✗ Inaktiv</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                                    <button type="submit" class="btn btn-warning btn-sm">
                                                        <?php echo $service['is_active'] ? 'Deaktivieren' : 'Aktivieren'; ?>
                                                    </button>
                                                </form>

                                                <button onclick="editService(<?php echo htmlspecialchars(json_encode($service)); ?>)"
                                                    class="btn btn-primary btn-sm">
                                                    Bearbeiten
                                                </button>

                                                <?php if ($service['id'] > 0): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Service wirklich löschen?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            Löschen
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
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

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Service bearbeiten</h3>
            </div>
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="service_id" id="edit_service_id">

                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>

                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Preis (€)</label>
                            <input type="number" name="price" id="edit_price" class="form-control" step="0.01" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Dauer (Minuten)</label>
                            <input type="number" name="duration_minutes" id="edit_duration" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Hintergrundbild</label>
                        <input type="text" name="background_image" id="edit_background_image" class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Beschreibung</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeEditModal()">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">Speichern</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editService(service) {
            document.getElementById('edit_service_id').value = service.id;
            document.getElementById('edit_name').value = service.name;
            document.getElementById('edit_price').value = service.price;
            document.getElementById('edit_duration').value = service.duration_minutes;
            document.getElementById('edit_background_image').value = service.background_image || '';
            document.getElementById('edit_description').value = service.description || '';

            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>
</body>

</html>