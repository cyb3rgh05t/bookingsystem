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
    // Bereite alle Update-Felder vor
    $updates = [];
    $params = [];

    // Basis-Einstellungen
    $updates[] = "company_name = ?";
    $params[] = $_POST['company_name'];

    $updates[] = "address = ?";
    $params[] = $_POST['address'];

    $updates[] = "phone = ?";
    $params[] = $_POST['phone'];

    $updates[] = "email = ?";
    $params[] = $_POST['email'];

    $updates[] = "google_maps_api_key = ?";
    $params[] = $_POST['google_maps_api_key'] ?: null;

    $updates[] = "paypal_client_id = ?";
    $params[] = $_POST['paypal_client_id'] ?: null;

    // NEU: PayPal erweiterte Einstellungen
    $updates[] = "paypal_client_secret = ?";
    $params[] = $_POST['paypal_client_secret'] ?: null;

    $updates[] = "paypal_merchant_email = ?";
    $params[] = $_POST['paypal_merchant_email'] ?: null;

    $updates[] = "paypal_mode = ?";
    $params[] = $_POST['paypal_mode'] ?? 'sandbox';

    // NEU: Lexware Einstellungen
    $updates[] = "lexware_api_url = ?";
    $params[] = $_POST['lexware_api_url'] ?? 'https://api.lexware.de/v1';

    $updates[] = "lexware_api_key = ?";
    $params[] = $_POST['lexware_api_key'] ?: null;

    // SMTP Einstellungen
    $updates[] = "smtp_host = ?";
    $params[] = $_POST['smtp_host'] ?: null;

    $updates[] = "smtp_port = ?";
    $params[] = $_POST['smtp_port'] ?: null;

    $updates[] = "smtp_user = ?";
    $params[] = $_POST['smtp_user'] ?: null;

    $updates[] = "smtp_password = ?";
    $params[] = $_POST['smtp_password'] ?: null;

    // Service-Gebiet Einstellungen
    $updates[] = "max_distance = ?";
    $params[] = $_POST['max_distance'];

    $updates[] = "min_price_above_10km = ?";
    $params[] = $_POST['min_price_above_10km'];

    $updates[] = "price_per_km = ?";
    $params[] = $_POST['price_per_km'];

    $updates[] = "free_distance_km = ?";
    $params[] = $_POST['free_distance_km'];

    // Arbeitszeiten
    $updates[] = "working_hours_weekday_start = ?";
    $params[] = $_POST['working_hours_weekday_start'];

    $updates[] = "working_hours_weekday_end = ?";
    $params[] = $_POST['working_hours_weekday_end'];

    $updates[] = "working_hours_saturday_start = ?";
    $params[] = $_POST['working_hours_saturday_start'];

    $updates[] = "working_hours_saturday_end = ?";
    $params[] = $_POST['working_hours_saturday_end'];

    $updates[] = "time_slot_duration = ?";
    $params[] = $_POST['time_slot_duration'];

    $updates[] = "appointment_buffer_time = ?";
    $params[] = $_POST['appointment_buffer_time'] ?? 30;

    // WHERE clause
    $params[] = 1;

    // Führe Update aus
    $query = "UPDATE settings SET " . implode(", ", $updates) . " WHERE id = ?";
    $db->query($query, $params);

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
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/admin.css">

    <style>
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

        .btn-secondary {
            background-color: var(--clr-surface-a30);
            color: var(--clr-light-a0);
        }

        .btn-secondary:hover {
            background-color: var(--clr-surface-a40);
        }

        .btn-large {
            padding: 12px 30px;
            font-size: 16px;
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
                    <li><a href="lexware-export.php"><i class="fa-solid fa-file-export"></i>Lexware Export</a></li>
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
                    <p>Optional: Für erweiterte Funktionen wie Google Maps, PayPal-Zahlungen und Lexware-Integration</p>

                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Google Maps API Key</label>
                            <input type="text" name="google_maps_api_key" class="form-control"
                                value="<?php echo safe_html($settings['google_maps_api_key'] ?? ''); ?>"
                                placeholder="AIza...">
                            <small>Für Adress-Autocomplete und Entfernungsberechnung</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">PayPal Client ID</label>
                            <input type="text" name="paypal_client_id" class="form-control"
                                value="<?php echo safe_html($settings['paypal_client_id'] ?? ''); ?>"
                                placeholder="AX...">
                            <small>Für Online-Zahlungen via PayPal</small>
                        </div>
                    </div>
                </div>

                <!-- NEU: Lexware API Settings -->
                <div class="card settings-section">
                    <h3>Lexware API Einstellungen</h3>
                    <p>Konfigurieren Sie die Lexware API für automatische Rechnungserstellung nach Zahlungseingang</p>

                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Lexware API URL</label>
                            <input type="text" name="lexware_api_url" class="form-control"
                                value="<?php echo safe_html($settings['lexware_api_url'] ?? 'https://api.lexware.de/v1'); ?>"
                                placeholder="https://api.lexware.de/v1">
                            <small>Standard: https://api.lexware.de/v1</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Lexware API Key</label>
                            <input type="password" name="lexware_api_key" class="form-control"
                                value="<?php echo safe_html($settings['lexware_api_key'] ?? ''); ?>"
                                placeholder="Ihr Lexware API Schlüssel">
                            <small>Erhalten Sie in Ihrem Lexware Account unter API-Einstellungen</small>
                        </div>
                    </div>

                    <!-- Lexware Test Button -->
                    <div class="form-group">
                        <button type="button" class="btn btn-secondary" onclick="testLexwareConnection()">
                            <i class="fas fa-plug"></i> Verbindung testen
                        </button>
                        <div id="lexware-test-result" style="margin-top: 1rem;"></div>
                    </div>
                </div>

                <!-- NEU: PayPal Erweiterte Einstellungen -->
                <div class="card settings-section">
                    <h3>PayPal Erweiterte Einstellungen</h3>
                    <p>Zusätzliche PayPal-Konfiguration für die Zahlungsabwicklung</p>

                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">PayPal Client Secret</label>
                            <input type="password" name="paypal_client_secret" class="form-control"
                                value="<?php echo safe_html($settings['paypal_client_secret'] ?? ''); ?>"
                                placeholder="Ihr PayPal Client Secret">
                            <small>Erhalten Sie im PayPal Developer Dashboard</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">PayPal Händler E-Mail</label>
                            <input type="email" name="paypal_merchant_email" class="form-control"
                                value="<?php echo safe_html($settings['paypal_merchant_email'] ?? ''); ?>"
                                placeholder="ihre-paypal@email.de">
                            <small>Ihre PayPal Business E-Mail-Adresse</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">PayPal Modus</label>
                            <select name="paypal_mode" class="form-control">
                                <option value="sandbox" <?php echo ($settings['paypal_mode'] ?? 'sandbox') === 'sandbox' ? 'selected' : ''; ?>>
                                    Sandbox (Test)
                                </option>
                                <option value="live" <?php echo ($settings['paypal_mode'] ?? '') === 'live' ? 'selected' : ''; ?>>
                                    Live (Produktion)
                                </option>
                            </select>
                            <small>Verwenden Sie Sandbox für Tests</small>
                        </div>
                    </div>

                    <!-- PayPal Test Button -->
                    <div class="form-group">
                        <button type="button" class="btn btn-secondary" onclick="testPayPalConnection()">
                            <i class="fas fa-plug"></i> PayPal Verbindung testen
                        </button>
                        <div id="paypal-test-result" style="margin-top: 1rem;"></div>
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
                            <label class="form-label">Puffer vor/nach Terminen (Minuten)</label>
                            <select name="appointment_buffer_time" class="form-control">
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
                    <button type="submit" name="save_settings" class="btn btn-primary btn-large">Speichern</button>
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
            document.querySelector('[name="appointment_buffer_time"]').value = '30';
        }

        /**
         * Test Lexware API Verbindung
         */
        function testLexwareConnection() {
            const resultDiv = document.getElementById('lexware-test-result');
            const apiUrl = document.querySelector('[name="lexware_api_url"]').value;
            const apiKey = document.querySelector('[name="lexware_api_key"]').value;

            if (!apiKey) {
                resultDiv.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> Bitte geben Sie einen API Key ein
                    </div>
                `;
                return;
            }

            resultDiv.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-spinner fa-spin"></i> Teste Verbindung...
                </div>
            `;

            // Test API Verbindung
            fetch('../api/test-lexware.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        api_url: apiUrl,
                        api_key: apiKey
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> 
                            Verbindung erfolgreich!<br>
                            <small>Firma: ${data.company_name || 'N/A'}</small>
                        </div>
                    `;
                    } else {
                        resultDiv.innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-times-circle"></i> 
                            Verbindung fehlgeschlagen: ${data.error}
                        </div>
                    `;
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-times-circle"></i> 
                        Fehler: ${error.message}
                    </div>
                `;
                });
        }

        /**
         * Test PayPal Verbindung
         */
        function testPayPalConnection() {
            const resultDiv = document.getElementById('paypal-test-result');
            const clientId = document.querySelector('[name="paypal_client_id"]').value;
            const clientSecret = document.querySelector('[name="paypal_client_secret"]').value;
            const mode = document.querySelector('[name="paypal_mode"]').value;

            if (!clientId || !clientSecret) {
                resultDiv.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Bitte geben Sie Client ID und Secret ein
                    </div>
                `;
                return;
            }

            resultDiv.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-spinner fa-spin"></i> Teste PayPal Verbindung...
                </div>
            `;

            // Test PayPal Verbindung
            fetch('../api/test-paypal.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        client_id: clientId,
                        client_secret: clientSecret,
                        mode: mode
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> 
                            PayPal Verbindung erfolgreich!<br>
                            <small>Modus: ${mode === 'sandbox' ? 'Sandbox (Test)' : 'Live'}</small>
                        </div>
                    `;
                    } else {
                        resultDiv.innerHTML = `
                        <div class="alert alert-error">
                            <i class="fas fa-times-circle"></i> 
                            Verbindung fehlgeschlagen: ${data.error}
                        </div>
                    `;
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-times-circle"></i> 
                        Fehler: ${error.message}
                    </div>
                `;
                });
        }
    </script>
</body>

</html>