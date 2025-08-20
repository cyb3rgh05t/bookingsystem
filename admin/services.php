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
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/mobile.css">
</head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>

    <!-- Sidebar -->
    <div class="admin-sidebar" id="adminSidebar">
        <h2>Admin Panel</h2>
        <nav>
            <a href="index.php" class="sidebar-link">Dashboard</a>
            <a href="appointments.php" class="sidebar-link">Termine</a>
            <a href="services.php" class="sidebar-link active">Services</a>
            <a href="calendar.php" class="sidebar-link">Kalender</a>
            <a href="settings.php" class="sidebar-link">Einstellungen</a>
            <a href="logout.php" class="sidebar-link" style="margin-top: 2rem; color: var(--clr-error);">
                Abmelden
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="admin-content">
        <h1 style="margin-bottom: 2rem;">Service-Verwaltung</h1>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Add Service Form -->
        <div class="card" style="margin-bottom: 2rem;">
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
                <p style="text-align: center; padding: 2rem; color: var(--clr-primary-a40);">
                    Noch keine Services vorhanden. Fügen Sie oben einen neuen Service hinzu.
                </p>
            <?php else: ?>
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
                                        <small style="color: var(--clr-primary-a40);">
                                            <?php echo htmlspecialchars($service['description']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($service['price'], 2, ',', '.'); ?> €</td>
                                <td><?php echo $service['duration_minutes']; ?> Min.</td>
                                <td>
                                    <?php if ($service['is_active']): ?>
                                        <span style="color: var(--clr-success);">✓ Aktiv</span>
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

                                        <?php if ($service['id'] > 0): // Nur selbst erstellte Services löschen 
                                        ?>
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
            <?php endif; ?>
        </div>
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