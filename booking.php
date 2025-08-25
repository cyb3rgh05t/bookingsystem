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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5">
    <title><?php echo SITE_NAME; ?> - Terminbuchung</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/mobile.css">
    <link rel="stylesheet" href="assets/css/print.css">

</head>

<body>


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
                        <div class="address-input-group">
                            <div class="address-input-wrapper">
                                <input type="text" id="address" class="form-control" placeholder="Bitte vollständige Adresse eingeben" required>
                            </div>
                            <button type="button" onclick="calculateDistanceFromInput()" class="btn btn-primary">
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
                        Sonntag: Geschlossen (Termin möglich nach Absprache)
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

                    <div class="card" style="text-align: right; background: var(--clr-warning); color: white;">
                        <h3>Gesamtbetrag :</h3>
                        <div id="summary-total" style="margin-top: 1rem; font-size: 1.5rem; font-weight: bold;">
                            <!-- Gesamtbetrag wird hier angezeigt -->
                        </div>
                    </div>

                    <div style="margin-top: 2rem; display: flex; justify-content: space-between;">
                        <button class="btn" onclick="previousStep()">← Zurück</button>
                        <button class="btn btn-large" onclick="confirmBooking()">Kostenpflichtig buchen</button>
                    </div>
                </div>

                <!-- Step 6: Bestätigung -->
                <div class="step-panel" data-step="6" style="display:none;">
                    <div id="print-area">
                        <div class="print-container">
                            <!-- Print Header (only visible in print) -->
                            <div class="print-header" style="display: none;">
                                <img src="assets/images/logo.png" alt="Logo" class="print-logo" onerror="this.style.display='none'">
                                <div class="print-company"><?php echo htmlspecialchars($settings['company_name'] ?? 'Auto Service GmbH'); ?></div>
                                <div style="font-size: 10pt; margin-top: 5mm;">
                                    <?php echo htmlspecialchars($settings['address'] ?? ''); ?><br>
                                    Tel: <?php echo htmlspecialchars($settings['phone'] ?? ''); ?> |
                                    E-Mail: <?php echo htmlspecialchars($settings['email'] ?? ''); ?>
                                </div>
                            </div>

                            <!-- Success Message -->
                            <div class="confirmation-container" style="max-width: 600px; margin: 0 auto; text-align: center;">
                                <div class="no-print" style="padding: 2rem 0;">
                                    <div style="font-size: 4rem; color: var(--clr-success); margin-bottom: 1rem;">✓</div>
                                    <h2 style="margin-bottom: 1rem; font-weight: 400; letter-spacing: 0.05em;">Vielen Dank für Ihre Buchung!</h2>
                                    <p style="margin-bottom: 2rem; color: var(--clr-primary-a40);">Eine Bestätigungs-E-Mail wurde an Ihre E-Mail-Adresse gesendet.</p>
                                </div>

                                <!-- Printable Title -->
                                <div class="print-title" style="display: none;">Terminbestätigung</div>

                                <!-- Booking Confirmation Card -->
                                <div class="confirmation-card" style="background: var(--clr-surface-a10); border: 1px solid var(--clr-surface-a30); border-radius: var(--radius-xs); padding: 2rem; margin-bottom: 2rem; text-align: left;">

                                    <!-- Booking Number -->
                                    <div class="booking-number-section" style="text-align: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--clr-surface-a30);">
                                        <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--clr-primary-a40); margin-bottom: 0.5rem;">Buchungsnummer</div>
                                        <div id="booking-number" style="font-size: 1.75rem; font-weight: 500; color: var(--clr-info); letter-spacing: 0.05em;">#2025-0001</div>
                                    </div>

                                    <!-- Customer Details -->
                                    <div class="detail-section" style="margin-bottom: 1.5rem;">
                                        <h4 style="font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 1rem; color: var(--clr-primary-a40);">Kundendaten</h4>
                                        <div id="confirmation-customer-details" style="font-size: 0.95rem; line-height: 1.8;">
                                            <!-- Will be filled by JavaScript -->
                                        </div>
                                    </div>

                                    <!-- Vehicle Details -->
                                    <div class="detail-section" style="margin-bottom: 1.5rem;">
                                        <h4 style="font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 1rem; color: var(--clr-primary-a40);">Fahrzeugdaten</h4>
                                        <div id="confirmation-vehicle-details" style="font-size: 0.95rem; line-height: 1.8;">
                                            <!-- Will be filled by JavaScript -->
                                        </div>
                                    </div>

                                    <!-- Appointment Details -->
                                    <div class="detail-section" style="margin-bottom: 1.5rem;">
                                        <h4 style="font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 1rem; color: var(--clr-primary-a40);">Termindetails</h4>
                                        <div id="confirmation-appointment-details" style="font-size: 0.95rem; line-height: 1.8;">
                                            <!-- Will be filled by JavaScript -->
                                        </div>
                                    </div>

                                    <!-- Services -->
                                    <div class="detail-section" style="margin-bottom: 1.5rem;">
                                        <h4 style="font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 1rem; color: var(--clr-primary-a40);">Gebuchte Services</h4>
                                        <div id="confirmation-services" style="font-size: 0.95rem;">
                                            <!-- Will be filled by JavaScript -->
                                        </div>
                                    </div>

                                    <!-- Total -->
                                    <div style="background: var(--clr-surface-a20); padding: 1.5rem; border-radius: var(--radius-xs); text-align: center; margin-top: 2rem;">
                                        <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--clr-primary-a40); margin-bottom: 0.5rem;">Gesamtbetrag</div>
                                        <div id="confirmation-total" style="font-size: 2rem; font-weight: 500; color: var(--clr-primary-a0);">0,00€</div>
                                        <div style="font-size: 0.75rem; color: var(--clr-primary-a40); margin-top: 0.5rem;">
                                            Zahlbar per PayPal oder Kreditkarte
                                        </div>
                                    </div>
                                </div>

                                <!-- Hidden print elements -->
                                <div style="display: none;">
                                    <div id="print-customer-details"></div>
                                    <div id="print-vehicle-details"></div>
                                    <div id="print-appointment-details"></div>
                                    <table id="print-services-table">
                                        <tbody id="print-services-body"></tbody>
                                    </table>
                                    <div id="print-total-amount"></div>
                                    <div id="print-payment-info"></div>
                                    <div id="print-date"></div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="no-print" style="margin-bottom: 2rem;">
                                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                                        <button class="btn btn-primary" onclick="printConfirmation()">
                                            📄 Drucken
                                        </button>
                                        <button class="btn btn-primary" onclick="downloadPDF()">
                                            📥 Als PDF
                                        </button>
                                        <button class="btn btn-success" onclick="processPayment()">
                                            💳 Jetzt bezahlen
                                        </button>
                                    </div>
                                </div>

                                <!-- Next Steps -->
                                <div class="no-print card" style="background: var(--clr-surface-a10); border: 1px solid var(--clr-surface-a30); padding: 1.5rem; margin-bottom: 2rem;">
                                    <h4 style="margin-bottom: 1rem; font-size: 1rem;">Nächste Schritte:</h4>
                                    <ul style="margin: 0; padding-left: 1.5rem; text-align: left; font-size: 0.9rem; line-height: 1.8;">
                                        <li>Überprüfen Sie Ihre E-Mail für die Bestätigung</li>
                                        <li>Bezahlen Sie bequem per PayPal oder Kreditkarte</li>
                                        <li>Wir kommen zum vereinbarten Termin zu Ihnen</li>
                                    </ul>
                                </div>

                                <!-- Footer -->
                                <div class="print-footer" style="display: none;">
                                    <p>Diese Terminbestätigung wurde automatisch erstellt und ist ohne Unterschrift gültig.</p>
                                    <p>Druckdatum: <span id="print-date-footer"></span></p>
                                </div>

                                <!-- New Booking Button -->
                                <div class="no-print" style="margin-top: 2rem;">
                                    <a href="index.php" class="btn" style="text-transform: uppercase; letter-spacing: 0.1em;">↻ Neue Buchung</a>
                                </div>
                            </div>
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

    <script src="assets/js/booking.min.js"></script>
</body>

</html>