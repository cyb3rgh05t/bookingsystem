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
                    <li><a href="services.php" class="active"><i class="fa-solid fa-wrench"></i>Services</a></li>
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
                                                    <button type="submit" class="btn btn-info btn-sm">
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