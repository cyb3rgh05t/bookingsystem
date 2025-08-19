<?php
// admin/settings.php - Einstellungen
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();

// Update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db->query("
        UPDATE settings SET 
            company_name = ?,
            address = ?,
            phone = ?,
            email = ?,
            google_maps_api_key = ?,
            paypal_client_id = ?,
            smtp_host = ?,
            smtp_port = ?,
            smtp_user = ?,
            smtp_password = ?,
            max_distance = ?,
            min_price_above_10km = ?,
            price_per_km = ?,
            free_distance_km = ?,
            working_hours_weekday_start = ?,
            working_hours_weekday_end = ?,
            working_hours_saturday_start = ?,
            working_hours_saturday_end = ?,
            time_slot_duration = ?
        WHERE id = 1
    ", [
        $_POST['company_name'],
        $_POST['address'],
        $_POST['phone'],
        $_POST['email'],
        $_POST['google_maps_api_key'] ?: null,
        $_POST['paypal_client_id'] ?: null,
        $_POST['smtp_host'] ?: null,
        $_POST['smtp_port'] ?: null,
        $_POST['smtp_user'] ?: null,
        $_POST['smtp_password'] ?: null,
        $_POST['max_distance'],
        $_POST['min_price_above_10km'],
        $_POST['price_per_km'],
        $_POST['free_distance_km'],
        $_POST['working_hours_weekday_start'],
        $_POST['working_hours_weekday_end'],
        $_POST['working_hours_saturday_start'],
        $_POST['working_hours_saturday_end'],
        $_POST['time_slot_duration']
    ]);

    $success = 'Einstellungen erfolgreich gespeichert';
}

// Get current settings
$settings = $db->fetch("SELECT * FROM settings WHERE id = 1");

// Helper function to safely handle null values for htmlspecialchars (PHP 8.1+ compatible)
function safe_html($value)
{
    // Handle null, false, and empty values
    if ($value === null || $value === false) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen - Admin</title>
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>

    <div class="admin-sidebar" id="adminSidebar">
        <h2>Admin Panel</h2>
        <nav>
            <a href="index.php" class="sidebar-link">Dashboard</a>
            <a href="appointments.php" class="sidebar-link">Termine</a>
            <a href="services.php" class="sidebar-link">Services</a>
            <a href="calendar.php" class="sidebar-link">Kalender</a>
            <a href="settings.php" class="sidebar-link active">Einstellungen</a>
            <a href="logout.php" class="sidebar-link" style="margin-top: 2rem; color: var(--clr-error);">
                Abmelden
            </a>
        </nav>
    </div>

    <div class="admin-content">
        <h1 style="margin-bottom: 2rem;">Einstellungen</h1>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST">
            <!-- Company Information -->
            <div class="card settings-section">
                <h3>Firmendaten</h3>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Firmenname *</label>
                        <input type="text" name="company_name" class="form-control"
                            value="<?php echo safe_html($settings['company_name']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Telefon *</label>
                        <input type="text" name="phone" class="form-control"
                            value="<?php echo safe_html($settings['phone']); ?>"
                            placeholder="+49 123 456789" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">E-Mail *</label>
                        <input type="email" name="email" class="form-control"
                            value="<?php echo safe_html($settings['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Adresse *</label>
                        <input type="text" name="address" class="form-control"
                            value="<?php echo safe_html($settings['address']); ?>"
                            placeholder="Straße Nr, PLZ Ort" required>
                    </div>
                </div>
            </div>

            <!-- API Settings -->
            <div class="card settings-section">
                <h3>API Einstellungen</h3>
                <p style="color: var(--clr-primary-a40); margin-bottom: 1.5rem;">
                    Optional: Für erweiterte Funktionen wie Google Maps und PayPal-Zahlungen
                </p>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Google Maps API Key</label>
                        <input type="text" name="google_maps_api_key" class="form-control"
                            value="<?php echo safe_html($settings['google_maps_api_key']); ?>"
                            placeholder="AIza...">
                        <small style="color: var(--clr-primary-a40);">
                            Für Adress-Autocomplete und Entfernungsberechnung
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">PayPal Client ID</label>
                        <input type="text" name="paypal_client_id" class="form-control"
                            value="<?php echo safe_html($settings['paypal_client_id']); ?>"
                            placeholder="AX...">
                        <small style="color: var(--clr-primary-a40);">
                            Für Online-Zahlungen via PayPal
                        </small>
                    </div>
                </div>
            </div>

            <!-- Email Settings -->
            <div class="card settings-section">
                <h3>E-Mail Einstellungen (SMTP)</h3>
                <p style="color: var(--clr-primary-a40); margin-bottom: 1.5rem;">
                    Optional: Für automatischen E-Mail-Versand. Leer lassen für PHP mail() Funktion
                </p>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control"
                            value="<?php echo safe_html($settings['smtp_host']); ?>"
                            placeholder="smtp.gmail.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label">SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-control"
                            value="<?php echo $settings['smtp_port'] ?: ''; ?>"
                            placeholder="587">
                    </div>

                    <div class="form-group">
                        <label class="form-label">SMTP Benutzer</label>
                        <input type="text" name="smtp_user" class="form-control"
                            value="<?php echo safe_html($settings['smtp_user']); ?>"
                            placeholder="ihre-email@gmail.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label">SMTP Passwort</label>
                        <input type="password" name="smtp_password" class="form-control"
                            value="<?php echo safe_html($settings['smtp_password']); ?>"
                            placeholder="••••••••">
                        <small style="color: var(--clr-primary-a40);">
                            Passwort wird verschlüsselt gespeichert
                        </small>
                    </div>
                </div>

                <div class="alert alert-info" style="margin-top: 1rem;">
                    <strong>Tipp:</strong> Für Gmail verwenden Sie: Host: smtp.gmail.com, Port: 587,
                    und ein App-spezifisches Passwort (nicht Ihr normales Gmail-Passwort)
                </div>
            </div>

            <!-- Service Area Settings -->
            <div class="card settings-section">
                <h3>Service-Gebiet & Preise</h3>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Max. Entfernung (km) *</label>
                        <input type="number" name="max_distance" class="form-control"
                            value="<?php echo $settings['max_distance'] ?: 35; ?>"
                            min="1" max="100" required>
                        <small style="color: var(--clr-primary-a40);">
                            Maximaler Radius für Ihren Service
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Kostenlose Anfahrt bis (km) *</label>
                        <input type="number" name="free_distance_km" class="form-control"
                            value="<?php echo $settings['free_distance_km'] ?: 10; ?>"
                            min="0" max="50" required>
                        <small style="color: var(--clr-primary-a40);">
                            Keine Anfahrtskosten bis zu dieser Entfernung
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Preis pro km (€) *</label>
                        <input type="number" name="price_per_km" class="form-control" step="0.1"
                            value="<?php echo $settings['price_per_km'] ?: 2.0; ?>"
                            min="0" max="10" required>
                        <small style="color: var(--clr-primary-a40);">
                            Anfahrtskosten pro Kilometer
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Mindestbestellwert ab 10km (€) *</label>
                        <input type="number" name="min_price_above_10km" class="form-control" step="0.01"
                            value="<?php echo $settings['min_price_above_10km'] ?: 59.90; ?>"
                            min="0" required>
                        <small style="color: var(--clr-primary-a40);">
                            Mindestbestellwert für Entfernungen über 10km
                        </small>
                    </div>
                </div>
            </div>

            <!-- Working Hours -->
            <div class="card settings-section">
                <h3>Arbeitszeiten</h3>

                <div class="grid grid-2">
                    <div class="form-group">
                        <label class="form-label">Wochentags Start *</label>
                        <input type="time" name="working_hours_weekday_start" class="form-control"
                            value="<?php echo $settings['working_hours_weekday_start'] ?: '16:30'; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Wochentags Ende *</label>
                        <input type="time" name="working_hours_weekday_end" class="form-control"
                            value="<?php echo $settings['working_hours_weekday_end'] ?: '21:00'; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Samstag Start *</label>
                        <input type="time" name="working_hours_saturday_start" class="form-control"
                            value="<?php echo $settings['working_hours_saturday_start'] ?: '09:00'; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Samstag Ende *</label>
                        <input type="time" name="working_hours_saturday_end" class="form-control"
                            value="<?php echo $settings['working_hours_saturday_end'] ?: '14:00'; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Zeitslot-Dauer (Minuten) *</label>
                        <select name="time_slot_duration" class="form-control" required>
                            <option value="15" <?php echo ($settings['time_slot_duration'] ?? 30) == 15 ? 'selected' : ''; ?>>15 Minuten</option>
                            <option value="30" <?php echo ($settings['time_slot_duration'] ?? 30) == 30 ? 'selected' : ''; ?>>30 Minuten</option>
                            <option value="45" <?php echo ($settings['time_slot_duration'] ?? 30) == 45 ? 'selected' : ''; ?>>45 Minuten</option>
                            <option value="60" <?php echo ($settings['time_slot_duration'] ?? 30) == 60 ? 'selected' : ''; ?>>60 Minuten</option>
                        </select>
                        <small style="color: var(--clr-primary-a40);">
                            Zeitintervalle für Terminbuchungen
                        </small>
                    </div>
                </div>

                <div class="alert alert-info" style="margin-top: 1rem;">
                    <strong>Info:</strong> Sonntags ist automatisch geschlossen
                </div>
            </div>

            <div style="display: flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary btn-large">Einstellungen speichern</button>
                <button type="button" class="btn" onclick="if(confirm('Alle Einstellungen auf Standardwerte zurücksetzen?')) resetToDefaults()">
                    Auf Standard zurücksetzen
                </button>
            </div>
        </form>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('adminSidebar');
            sidebar.classList.toggle('active');
        }

        function resetToDefaults() {
            // Set default values
            document.querySelector('[name="max_distance"]').value = '35';
            document.querySelector('[name="free_distance_km"]').value = '10';
            document.querySelector('[name="price_per_km"]').value = '2.0';
            document.querySelector('[name="min_price_above_10km"]').value = '59.90';
            document.querySelector('[name="working_hours_weekday_start"]').value = '16:30';
            document.querySelector('[name="working_hours_weekday_end"]').value = '21:00';
            document.querySelector('[name="working_hours_saturday_start"]').value = '09:00';
            document.querySelector('[name="working_hours_saturday_end"]').value = '14:00';
            document.querySelector('[name="time_slot_duration"]').value = '30';
        }
    </script>
</body>

</html>