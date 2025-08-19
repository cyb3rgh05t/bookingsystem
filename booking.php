<?php
// booking.php - Buchungsprozess
require_once 'includes/config.php';
require_once 'includes/db.php';

$db = Database::getInstance();
$settings = $db->fetch("SELECT * FROM settings WHERE id = 1");
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Terminbuchung</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <img src="assets/images/logo.png" alt="Logo" onerror="this.style.display='none'">
                    <h1><?php echo htmlspecialchars($settings['company_name'] ?? 'Auto Service'); ?></h1>
                </a>
                <nav>
                    <a href="admin/login.php" class="btn">Admin</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container" style="padding: 3rem 20px;">
        <div class="card" style="max-width: 1000px; margin: 0 auto;">
            <!-- Booking Steps -->
            <div class="booking-steps">
                <div class="step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">Kundendaten</div>
                </div>
                <div class="step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Services</div>
                </div>
                <div class="step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-label">Datum</div>
                </div>
                <div class="step" data-step="4">
                    <div class="step-number">4</div>
                    <div class="step-label">Zeit</div>
                </div>
                <div class="step" data-step="5">
                    <div class="step-number">5</div>
                    <div class="step-label">Zusammenfassung</div>
                </div>
                <div class="step" data-step="6">
                    <div class="step-number">6</div>
                    <div class="step-label">Bestätigung</div>
                </div>
            </div>

            <!-- Step Content -->
            <div id="step-content">
                <!-- Step 1: Kundendaten -->
                <div class="step-panel active" data-step="1">
                    <h2 style="margin-bottom: 2rem;">Ihre Kontaktdaten</h2>

                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Vorname *</label>
                            <input type="text" class="form-control" id="first_name" required>
                            <span class="form-error" style="display:none;">Bitte Vorname eingeben</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Nachname *</label>
                            <input type="text" class="form-control" id="last_name" required>
                            <span class="form-error" style="display:none;">Bitte Nachname eingeben</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">E-Mail *</label>
                            <input type="email" class="form-control" id="email" required>
                            <span class="form-error" style="display:none;">Bitte gültige E-Mail eingeben</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Telefon *</label>
                            <input type="tel" class="form-control" id="phone" required>
                            <span class="form-error" style="display:none;">Bitte Telefonnummer eingeben</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Adresse *</label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" class="form-control" id="address" placeholder="Straße Nr, PLZ Ort" required style="flex: 1;">
                            <button type="button" class="btn btn-primary" onclick="calculateDistanceFromInput()" title="Entfernung berechnen">
                                📍 Berechnen
                            </button>
                        </div>
                        <span class="form-error" style="display:none;">Bitte vollständige Adresse eingeben</span>
                    </div>

                    <div id="distance-info" class="alert alert-info" style="display:none;">
                        <span id="distance-text"></span>
                    </div>

                    <h3 style="margin: 2rem 0 1rem;">Fahrzeugdaten</h3>

                    <div class="grid grid-2">
                        <div class="form-group">
                            <label class="form-label">Marke *</label>
                            <input type="text" class="form-control" id="car_brand" placeholder="z.B. Volkswagen" required>
                            <span class="form-error" style="display:none;">Bitte Fahrzeugmarke eingeben</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Modell *</label>
                            <input type="text" class="form-control" id="car_model" placeholder="z.B. Golf" required>
                            <span class="form-error" style="display:none;">Bitte Fahrzeugmodell eingeben</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Baujahr *</label>
                            <input type="number" class="form-control" id="car_year" min="1950" max="2026" placeholder="z.B. 2020" required>
                            <span class="form-error" style="display:none;">Bitte Baujahr eingeben</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Kennzeichen *</label>
                            <input type="text" class="form-control" id="license_plate" placeholder="z.B. B-XX 1234" required>
                            <span class="form-error" style="display:none;">Bitte Kennzeichen eingeben</span>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 2rem;">
                        <button class="btn btn-primary btn-large" onclick="nextStep()">
                            Weiter zu Services →
                        </button>
                    </div>
                </div>

                <!-- Step 2: Services -->
                <div class="step-panel" data-step="2" style="display:none;">
                    <h2 style="margin-bottom: 2rem;">Wählen Sie Ihre Services</h2>

                    <div class="alert alert-info" style="margin-bottom: 1.5rem;">
                        <strong>Tipp:</strong> Die Gesamtdauer Ihrer gewählten Services bestimmt, welche Termine verfügbar sind.
                        Je länger die Gesamtdauer, desto weniger Zeitfenster stehen zur Verfügung.
                    </div>

                    <div class="grid grid-3" id="services-grid">
                        <!-- Services werden hier dynamisch geladen -->
                    </div>

                    <div class="alert alert-warning" id="min-price-warning" style="display:none; margin-top: 2rem;">
                        <strong>Hinweis:</strong> Bei einer Entfernung über 10km beträgt der Mindestbestellwert 59,90€
                    </div>

                    <div style="margin-top: 2rem; display: flex; justify-content: space-between; align-items: center;">
                        <button class="btn" onclick="previousStep()">← Zurück</button>
                        <div>
                            <span style="margin-right: 1rem;">Gesamtdauer: <strong id="total-duration">0 Min.</strong></span>
                            <span>Zwischensumme: <strong id="subtotal">0,00€</strong></span>
                        </div>
                        <button class="btn btn-primary" onclick="nextStep()">Weiter zu Datum →</button>
                    </div>
                </div>

                <!-- Step 3: Datum -->
                <div class="step-panel" data-step="3" style="display:none;">
                    <h2 style="margin-bottom: 2rem;">Wählen Sie ein Datum</h2>

                    <?php if (true): // Always show this info 
                    ?>
                        <div class="alert alert-info" style="margin-bottom: 1.5rem;">
                            <strong>Hinweis:</strong> Die Verfügbarkeit wird basierend auf der Gesamtdauer Ihrer gewählten Services geprüft.
                            Wir suchen nach zusammenhängenden Zeitfenstern, die groß genug für alle Ihre Services sind.
                        </div>
                    <?php endif; ?>

                    <div class="calendar">
                        <div class="calendar-header">
                            <button class="btn" onclick="changeMonth(-1)">←</button>
                            <h3 id="calendar-month">Januar 2025</h3>
                            <button class="btn" onclick="changeMonth(1)">→</button>
                        </div>
                        <div class="calendar-grid" id="calendar-grid">
                            <!-- Kalender wird hier dynamisch generiert -->
                        </div>
                    </div>

                    <div style="margin-top: 2rem; display: flex; justify-content: space-between;">
                        <button class="btn" onclick="previousStep()">← Zurück</button>
                        <button class="btn btn-primary" onclick="nextStep()" id="date-continue" disabled>Weiter zu Uhrzeit →</button>
                    </div>
                </div>

                <!-- Step 4: Zeit -->
                <div class="step-panel" data-step="4" style="display:none;">
                    <h2 style="margin-bottom: 2rem;">Wählen Sie eine Uhrzeit</h2>

                    <div class="alert alert-info">
                        <strong>Arbeitszeiten:</strong><br>
                        <?php
                        $weekdayStart = substr($settings['working_hours_weekday_start'] ?? '16:30', 0, 5);
                        $weekdayEnd = substr($settings['working_hours_weekday_end'] ?? '21:00', 0, 5);
                        $saturdayStart = substr($settings['working_hours_saturday_start'] ?? '09:00', 0, 5);
                        $saturdayEnd = substr($settings['working_hours_saturday_end'] ?? '14:00', 0, 5);
                        ?>
                        Montag - Freitag: <?php echo $weekdayStart; ?> - <?php echo $weekdayEnd; ?> Uhr<br>
                        Samstag: <?php echo $saturdayStart; ?> - <?php echo $saturdayEnd; ?> Uhr<br>
                        Sonntag: Geschlossen
                    </div>

                    <div class="time-slots" id="time-slots"
                        data-weekday-start="<?php echo $weekdayStart; ?>"
                        data-weekday-end="<?php echo $weekdayEnd; ?>"
                        data-saturday-start="<?php echo $saturdayStart; ?>"
                        data-saturday-end="<?php echo $saturdayEnd; ?>"
                        data-slot-duration="<?php echo $settings['time_slot_duration'] ?? 30; ?>">
                        <!-- Zeitslots werden hier dynamisch generiert -->
                    </div>

                    <div style="margin-top: 2rem; display: flex; justify-content: space-between;">
                        <button class="btn" onclick="previousStep()">← Zurück</button>
                        <button class="btn btn-primary" onclick="nextStep()" id="time-continue" disabled>Weiter zur Zusammenfassung →</button>
                    </div>
                </div>

                <!-- Step 5: Zusammenfassung -->
                <div class="step-panel" data-step="5" style="display:none;">
                    <h2 style="margin-bottom: 2rem;">Zusammenfassung Ihrer Buchung</h2>

                    <div class="card" style="background: var(--clr-surface-a20); margin-bottom: 1.5rem;">
                        <h3>Kundendaten</h3>
                        <div id="summary-customer" style="margin-top: 1rem;">
                            <!-- Kundendaten werden hier angezeigt -->
                        </div>
                    </div>

                    <div class="card" style="background: var(--clr-surface-a20); margin-bottom: 1.5rem;">
                        <h3>Gewählte Services</h3>
                        <div id="summary-services" style="margin-top: 1rem;">
                            <!-- Services werden hier angezeigt -->
                        </div>
                    </div>

                    <div class="card" style="background: var(--clr-surface-a20); margin-bottom: 1.5rem;">
                        <h3>Termin</h3>
                        <div id="summary-appointment" style="margin-top: 1rem;">
                            <!-- Termin wird hier angezeigt -->
                        </div>
                    </div>

                    <div class="card" style="background: var(--clr-info); color: white;">
                        <h3>Gesamtbetrag</h3>
                        <div id="summary-total" style="margin-top: 1rem; font-size: 1.5rem; font-weight: bold;">
                            <!-- Gesamtbetrag wird hier angezeigt -->
                        </div>
                    </div>

                    <div style="margin-top: 2rem; display: flex; justify-content: space-between;">
                        <button class="btn" onclick="previousStep()">← Zurück</button>
                        <button class="btn btn-success btn-large" onclick="confirmBooking()">Kostenpflichtig buchen</button>
                    </div>
                </div>

                <!-- Step 6: Bestätigung -->
                <div class="step-panel" data-step="6" style="display:none;">
                    <div style="text-align: center; padding: 3rem 0;">
                        <div style="font-size: 4rem; color: var(--clr-success); margin-bottom: 1rem;">✓</div>
                        <h2 style="margin-bottom: 1rem;">Vielen Dank für Ihre Buchung!</h2>
                        <p style="margin-bottom: 2rem;">Eine Bestätigungs-E-Mail wurde an Ihre E-Mail-Adresse gesendet.</p>

                        <div class="card" style="max-width: 500px; margin: 0 auto; text-align: left;">
                            <h3>Ihre Buchungsnummer:</h3>
                            <p style="font-size: 1.5rem; font-weight: bold; color: var(--clr-info);" id="booking-number">#2025-0001</p>

                            <div style="margin-top: 2rem;">
                                <h4>Nächste Schritte:</h4>
                                <ul style="margin-top: 1rem; padding-left: 1.5rem;">
                                    <li>Überprüfen Sie Ihre E-Mail für die Bestätigung</li>
                                    <li>Bezahlen Sie bequem per PayPal oder Kreditkarte</li>
                                    <li>Wir kommen zum vereinbarten Termin zu Ihnen</li>
                                </ul>
                            </div>

                            <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                                <button class="btn btn-primary" onclick="window.print()">
                                    Drucken
                                </button>
                                <button class="btn btn-success" onclick="processPayment()">
                                    Jetzt bezahlen
                                </button>
                            </div>
                        </div>

                        <div style="margin-top: 3rem;">
                            <a href="index.php" class="btn">Neue Buchung</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Google Maps API - WICHTIG: API Key einfügen! -->
    <?php if (!empty($settings['google_maps_api_key'])): ?>
        <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $settings['google_maps_api_key']; ?>&libraries=places"></script>
    <?php else: ?>
        <!-- Google Maps API Key fehlt - Bitte im Admin-Panel eintragen -->
        <script>
            console.warn('Google Maps API Key fehlt. Bitte im Admin-Panel unter Einstellungen eintragen.');
            // Dummy Google object für Entwicklung
            window.google = {
                maps: {
                    places: {
                        Autocomplete: function() {
                            return {
                                addListener: function() {}
                            };
                        }
                    }
                }
            };
        </script>
    <?php endif; ?>

    <script src="assets/js/booking.js"></script>
</body>

</html>