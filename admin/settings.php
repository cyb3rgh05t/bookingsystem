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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5">
    <title>Einstellungen - Admin</title>
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

        .alert-info {
            background-color: rgba(59, 130, 246, 0.1);
            border: 1px solid #3b82f6;
            color: #93c5fd;
        }

        /* Card */
        .card {
            background: var(--clr-surface-a10);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }

        .settings-section {
            margin-bottom: 20px;
        }

        .card h3 {
            color: var(--clr-primary-a20);
            margin: 0;
            margin-bottom: 8px;
        }

        .card p {
            color: var(--clr-surface-a40);
            font-size: 14px;
            margin-bottom: 1.5rem;
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

        select.form-control {
            cursor: pointer;
        }

        small {
            display: block;
            margin-top: 4px;
            color: var(--clr-surface-a40);
            font-size: 12px;
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

        .btn-large {
            padding: 12px 30px;
            font-size: 16px;
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
                    <li><a href="services.php"><i class="fa-solid fa-wrench"></i>Services</a></li>
                    <li><a href="calendar.php"><i class="fa-solid fa-calendar"></i>Kalender</a></li>
                    <li><a href="settings.php" class="active"><i class="fa-solid fa-gear"></i>Einstellungen</a></li>
                    <li><a href="../index.php" style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;"><i class="fa-solid fa-arrow-left"></i>Zum Termin Planner</a></li>
                    <li><a href="logout.php" style="color: var(--clr-error);"><i class="fa-solid fa-right-from-bracket"></i>Abmelden</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-gear"></i> Einstellungen</h1>
                    <p style="color: var(--clr-surface-a50);">Konfiguriere die System-Einstellungen</p>
                </div>
            </div>

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
                    <p>Optional: Für erweiterte Funktionen wie Google Maps und PayPal-Zahlungen</p>

                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Google Maps API Key</label>
                            <input type="text" name="google_maps_api_key" class="form-control"
                                value="<?php echo safe_html($settings['google_maps_api_key']); ?>"
                                placeholder="AIza...">
                            <small>Für Adress-Autocomplete und Entfernungsberechnung</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">PayPal Client ID</label>
                            <input type="text" name="paypal_client_id" class="form-control"
                                value="<?php echo safe_html($settings['paypal_client_id']); ?>"
                                placeholder="AX...">
                            <small>Für Online-Zahlungen via PayPal</small>
                        </div>
                    </div>
                </div>

                <!-- Email Settings -->
                <div class="card settings-section">
                    <h3>E-Mail Einstellungen (SMTP)</h3>
                    <p>Optional: Für automatischen E-Mail-Versand. Leer lassen für PHP mail() Funktion</p>

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
                            <small>Passwort wird verschlüsselt gespeichert</small>
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
                            <small>Maximaler Radius für Ihren Service</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Kostenlose Anfahrt bis (km) *</label>
                            <input type="number" name="free_distance_km" class="form-control"
                                value="<?php echo $settings['free_distance_km'] ?: 10; ?>"
                                min="0" max="50" required>
                            <small>Keine Anfahrtskosten bis zu dieser Entfernung</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Preis pro km (€) *</label>
                            <input type="number" name="price_per_km" class="form-control" step="0.1"
                                value="<?php echo $settings['price_per_km'] ?: 2.0; ?>"
                                min="0" max="10" required>
                            <small>Anfahrtskosten pro Kilometer</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Mindestbestellwert ab 10km (€) *</label>
                            <input type="number" name="min_price_above_10km" class="form-control" step="0.01"
                                value="<?php echo $settings['min_price_above_10km'] ?: 59.90; ?>"
                                min="0" required>
                            <small>Mindestbestellwert für Entfernungen über 10km</small>
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
                            <small>Zeitintervalle für Terminbuchungen</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Puffer vor/nach Terminen (Minuten) *</label>
                            <select name="appointment_buffer_time" class="form-control" required>
                                <option value="0" <?php echo ($settings['appointment_buffer_time'] ?? 30) == 0 ? 'selected' : ''; ?>>Kein Puffer</option>
                                <option value="15" <?php echo ($settings['appointment_buffer_time'] ?? 30) == 15 ? 'selected' : ''; ?>>15 Minuten</option>
                                <option value="30" <?php echo ($settings['appointment_buffer_time'] ?? 30) == 30 ? 'selected' : ''; ?>>30 Minuten</option>
                                <option value="45" <?php echo ($settings['appointment_buffer_time'] ?? 30) == 45 ? 'selected' : ''; ?>>45 Minuten</option>
                                <option value="60" <?php echo ($settings['appointment_buffer_time'] ?? 30) == 60 ? 'selected' : ''; ?>>60 Minuten</option>
                            </select>
                            <small>Zeit für Vor- und Nachbereitung zwischen Terminen.
                                Diese Zeit wird automatisch vor und nach jedem Termin blockiert.</small>
                        </div>
                    </div>

                    <div class="alert alert-info" style="margin-top: 1rem;">
                        <strong>Info:</strong> Sonntags ist automatisch geschlossen
                    </div>
                </div>

                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary btn-large">Speichern</button>
                    <button type="button" class="btn" onclick="if(confirm('Alle Einstellungen auf Standardwerte zurücksetzen?')) resetToDefaults()">
                        Reset
                    </button>
                </div>
            </form>
        </main>
    </div>

    <script>
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