<?php
// index.php - Hauptseite mit Buchungsbutton
require_once 'includes/config.php';
require_once 'includes/db.php';

$db = Database::getInstance();
$settings = $db->fetch("SELECT * FROM settings WHERE id = 1");

// Arbeitszeiten formatieren
$weekdayStart = substr($settings['working_hours_weekday_start'] ?? '16:30', 0, 5);
$weekdayEnd = substr($settings['working_hours_weekday_end'] ?? '21:00', 0, 5);
$saturdayStart = substr($settings['working_hours_saturday_start'] ?? '09:00', 0, 5);
$saturdayEnd = substr($settings['working_hours_saturday_end'] ?? '14:00', 0, 5);

// Service-Gebiet Werte
$maxDistance = $settings['max_distance'] ?? 35;
$freeDistanceKm = $settings['free_distance_km'] ?? 10;
$pricePerKm = number_format($settings['price_per_km'] ?? 2.0, 2, ',', '.');
$minPriceAbove10km = number_format($settings['min_price_above_10km'] ?? 59.90, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['company_name'] ?? 'Auto Service'); ?> - Willkommen</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome für Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hero {
            min-height: auto;
            padding: 4rem 0 2rem 0;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
        }

        .hero-content {
            z-index: 1;
            max-width: 1200px;
            width: 100%;
            padding: 2rem;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 0.5rem;
            background: var(--clr-primary-a0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            color: var(--clr-primary-a20);
        }

        /* Features in einer Reihe */
        .features {
            display: flex;
            justify-content: center;
            align-items: stretch;
            gap: 1.5rem;
            margin: 2rem 0 1rem 0;
            flex-wrap: wrap;
        }

        .feature {
            flex: 1 1 0;
            min-width: 200px;
            width: 25%;
            min-height: 200px;
            padding: 1.5rem;
            background: var(--clr-surface-tonal-a10);
            border-radius: var(--radius-md);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            transition: transform 0.3s ease, background 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .feature:hover {
            transform: translateY(-5px);
            background: var(--clr-surface-tonal-a20);
        }

        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--clr-info);
        }

        .feature h3 {
            margin-bottom: 0.5rem;
            color: var(--clr-primary-a0);
            font-size: 1rem;
            font-weight: 500;
        }

        .feature p {
            color: var(--clr-primary-a30);
            font-size: 0.85rem;
            line-height: 1.4;
        }

        .cta-section {
            background: linear-gradient(135deg, var(--clr-surface-a10), var(--clr-surface-a20));
            padding: 4rem 0;
            margin-top: 4rem;
        }

        /* Process Steps - Horizontal */
        .process-steps {
            display: flex;
            justify-content: space-around;
            align-items: flex-start;
            gap: 2rem;
            margin-bottom: 4rem;
            position: relative;
            flex-wrap: wrap;
        }

        .process-step {
            flex: 1;
            min-width: 200px;
            text-align: center;
            position: relative;
            transition: transform 0.3s ease;
        }

        .process-step:hover {
            transform: translateY(-5px);
        }

        /* Connecting lines between steps */
        .process-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 30px;
            left: calc(50% + 40px);
            width: calc(100% - 80px);
            height: 2px;
            background: linear-gradient(90deg, var(--clr-info), transparent);
            z-index: -1;
        }

        .step-number {
            width: 60px;
            height: 60px;
            background: var(--clr-info);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            font-weight: bold;
            color: white;
        }

        /* Service Info Cards */
        .service-info-grid {
            display: flex;
            justify-content: space-between;
            gap: 1.5rem;
            text-align: center;
            flex-wrap: wrap;
        }

        .service-info-card {
            flex: 1;
            min-width: 150px;
            padding: 1.5rem 1rem;
            transition: all 0.3s ease;
            border-radius: var(--radius-md);
        }

        .service-info-card:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: scale(1.05);
        }

        .service-info-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .features {
                flex-direction: column;
                align-items: center;
            }

            .feature {
                max-width: 100%;
            }

            .process-step::after {
                display: none;
            }

            .process-steps {
                flex-direction: column;
                align-items: center;
            }

            .service-info-grid {
                flex-direction: column;
            }
        }
    </style>
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
                    <a href="booking.php" class="btn btn-primary">Termin buchen</a>
                    <a href="admin/login.php" class="btn">Admin</a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Ihr mobiler Auto-Service</h1>
            <p>Wir kommen zu Ihnen - professionell, zuverlässig und bequem</p>

            <a href="booking.php" class="btn btn-primary btn-large" style="font-size: 1.3rem; padding: 1.2rem 3rem;">
                Jetzt Termin buchen →
            </a>

            <!-- Features in einer Reihe mit FontAwesome Icons -->
            <div class="features">
                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <h3>Mobiler Service</h3>
                    <p>Wir kommen direkt zu Ihnen nach Hause oder an Ihren Arbeitsplatz</p>
                </div>

                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Flexible Zeiten</h3>
                    <p>
                        Mo-Fr: <?php echo $weekdayStart; ?>-<?php echo $weekdayEnd; ?> Uhr<br>
                        Sa: <?php echo $saturdayStart; ?>-<?php echo $saturdayEnd; ?> Uhr<br>
                        So: Geschlossen (Termin möglich nach Absprache)
                    </p>
                </div>

                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3>Top Qualität</h3>
                    <p>Professionelle Durchführung aller Services mit hochwertigen Materialien</p>
                </div>

                <div class="feature">
                    <div class="feature-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h3>Bequeme Zahlung</h3>
                    <p>Zahlen Sie einfach per PayPal oder Kreditkarte - kein Bargeld nötig</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Preview -->
    <section class="container" style="padding: 2rem 20px;">
        <h2 style="text-align: center; margin-bottom: 2rem;">Unsere Services</h2>

        <div class="grid grid-3" id="services-preview">
            <?php
            $services = $db->fetchAll("SELECT * FROM services WHERE is_active = 1");
            foreach ($services as $service):
            ?>
                <div class="service-card">
                    <div class="service-card-bg" style="background-image: url('assets/images/services/<?php echo $service['background_image']; ?>')"></div>
                    <div class="service-card-content">
                        <div class="service-card-top">
                            <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                            <p class="service-card-description">
                                <?php echo htmlspecialchars($service['description']); ?>
                            </p>
                        </div>
                        <div class="service-card-meta">
                            <span class="service-price"><?php echo number_format($service['price'], 2, ',', '.'); ?>€</span>
                            <span class="service-duration"><?php echo $service['duration_minutes']; ?> Min.</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="text-align: center; margin-top: 3rem;">
            <a href="booking.php" class="btn btn-primary btn-large">
                Alle Services ansehen & Termin buchen
            </a>
        </div>
    </section>

    <!-- Info Section -->
    <section class="cta-section">
        <div class="container">
            <!-- So funktioniert's -->
            <div class="card" style="max-width: 1000px; margin: 0 auto; padding: 3rem; background: rgba(0,0,0,0.5); backdrop-filter: blur(10px);">
                <h2 style="text-align: center; margin-bottom: 3rem; font-size: 2rem; font-weight: 300; letter-spacing: 0.1em; text-transform: uppercase;">
                    So funktioniert's
                </h2>

                <!-- 3 Schritte in einer Reihe -->
                <div class="process-steps">
                    <div class="process-step">
                        <div class="step-number">1</div>
                        <h3 style="color: var(--clr-info); margin-bottom: 0.5rem; font-size: 1.1rem;">Service wählen</h3>
                        <p style="color: var(--clr-primary-a30); font-size: 0.9rem; line-height: 1.4;">Wählen Sie aus unserem Angebot die gewünschten Services aus</p>
                    </div>

                    <div class="process-step">
                        <div class="step-number">2</div>
                        <h3 style="color: var(--clr-info); margin-bottom: 0.5rem; font-size: 1.1rem;">Termin buchen</h3>
                        <p style="color: var(--clr-primary-a30); font-size: 0.9rem; line-height: 1.4;">Wählen Sie Datum und Uhrzeit, die Ihnen am besten passen</p>
                    </div>

                    <div class="process-step">
                        <div class="step-number">3</div>
                        <h3 style="color: var(--clr-info); margin-bottom: 0.5rem; font-size: 1.1rem;">Wir kommen zu Ihnen</h3>
                        <p style="color: var(--clr-primary-a30); font-size: 0.9rem; line-height: 1.4;">Unser Team kommt pünktlich zu Ihrem Wunschtermin</p>
                    </div>
                </div>

                <!-- Service-Gebiet Info in einer Reihe -->
                <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: var(--radius-md); padding: 2rem; margin-top: 2rem;">
                    <h3 style="text-align: center; margin-bottom: 2rem; color: var(--clr-primary-a0); font-size: 1.2rem; font-weight: 400; letter-spacing: 0.05em;">
                        <i class="fas fa-map-marker-alt"></i> Service-Gebiet & Preise
                    </h3>

                    <div class="service-info-grid">
                        <!-- Service-Gebiet -->
                        <div class="service-info-card">
                            <div class="service-info-icon">
                                <i class="fas fa-car" style="color: var(--clr-info);"></i>
                            </div>
                            <div style="color: var(--clr-primary-a20); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Service-Gebiet</div>
                            <div style="color: var(--clr-primary-a0); font-size: 1.5rem; font-weight: 500;">Bis <?php echo $maxDistance; ?>km</div>
                            <div style="color: var(--clr-primary-a40); font-size: 0.85rem; margin-top: 0.25rem;">Umkreis</div>
                        </div>

                        <!-- Kostenlose Anfahrt -->
                        <div class="service-info-card">
                            <div class="service-info-icon">
                                <i class="fas fa-check-circle" style="color: var(--clr-success);"></i>
                            </div>
                            <div style="color: var(--clr-primary-a20); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Kostenlose Anfahrt</div>
                            <div style="color: var(--clr-success); font-size: 1.5rem; font-weight: 500;">Bis <?php echo $freeDistanceKm; ?>km</div>
                            <div style="color: var(--clr-primary-a40); font-size: 0.85rem; margin-top: 0.25rem;">inklusive</div>
                        </div>

                        <!-- Anfahrtskosten -->
                        <div class="service-info-card">
                            <div class="service-info-icon">
                                <i class="fas fa-euro-sign" style="color: var(--clr-info);"></i>
                            </div>
                            <div style="color: var(--clr-primary-a20); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Ab <?php echo $freeDistanceKm; ?>km</div>
                            <div style="color: var(--clr-info); font-size: 1.5rem; font-weight: 500;"><?php echo $pricePerKm; ?>€/km</div>
                            <div style="color: var(--clr-primary-a40); font-size: 0.85rem; margin-top: 0.25rem;">Anfahrtskosten</div>
                        </div>

                        <?php if ($minPriceAbove10km > 0): ?>
                            <!-- Mindestbestellwert -->
                            <div class="service-info-card">
                                <div class="service-info-icon">
                                    <i class="fas fa-file-invoice-dollar" style="color: var(--clr-warning);"></i>
                                </div>
                                <div style="color: var(--clr-primary-a20); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Mindestbestellwert</div>
                                <div style="color: var(--clr-warning); font-size: 1.5rem; font-weight: 500;"><?php echo $minPriceAbove10km; ?>€</div>
                                <div style="color: var(--clr-primary-a40); font-size: 0.85rem; margin-top: 0.25rem;">ab 10km Entfernung</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Call to Action -->
                <div style="text-align: center; margin-top: 3rem;">
                    <a href="booking.php" class="btn btn-primary btn-large" style="font-size: 1.1rem; padding: 1rem 2.5rem; letter-spacing: 0.05em;">
                        Jetzt Termin vereinbaren →
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer style="background: var(--clr-surface-a0); padding: 3rem 0; margin-top: 4rem;">
        <div class="container">
            <div class="grid grid-3" style="gap: 3rem;">
                <div>
                    <h3 style="margin-bottom: 1rem;">Kontakt</h3>
                    <p style="color: var(--clr-primary-a40);">
                        <?php echo htmlspecialchars($settings['company_name'] ?? 'Auto Service GmbH'); ?><br>
                        <?php echo htmlspecialchars($settings['address'] ?? 'Musterstraße 1, 12345 Musterstadt'); ?><br>
                        Tel: <?php echo htmlspecialchars($settings['phone'] ?? '0123-456789'); ?><br>
                        E-Mail: <?php echo htmlspecialchars($settings['email'] ?? 'info@autoservice.de'); ?>
                    </p>
                </div>

                <div>
                    <h3 style="margin-bottom: 1rem;">Öffnungszeiten</h3>
                    <p style="color: var(--clr-primary-a40);">
                        Montag - Freitag: <?php echo $weekdayStart; ?> - <?php echo $weekdayEnd; ?> Uhr<br>
                        Samstag: <?php echo $saturdayStart; ?> - <?php echo $saturdayEnd; ?> Uhr<br>
                        Sonntag: Geschlossen (Termin möglich nach Absprache)
                    </p>
                </div>

                <div>
                    <h3 style="margin-bottom: 1rem;">Service</h3>
                    <p style="color: var(--clr-primary-a40);">
                        ✓ Mobiler Service bis <?php echo $maxDistance; ?>km<br>
                        ✓ Online-Terminbuchung<br>
                        ✓ Bargeldlose Zahlung<br>
                        ✓ Professionelle Beratung
                    </p>
                </div>
            </div>

            <hr style="margin: 2rem 0; border-color: var(--clr-surface-a30);">

            <div style="text-align: center;">
                <p style="color: var(--clr-primary-a50); margin-bottom: 1rem;">
                    © <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['company_name'] ?? 'Auto Service GmbH'); ?>.
                    Alle Rechte vorbehalten.
                </p>

                <?php if (!empty($settings['google_maps_api_key'])): ?>
                    <p style="color: var(--clr-success); font-size: 0.875rem;">
                        <i class="fas fa-check-circle"></i> Google Maps Integration aktiv
                    </p>
                <?php else: ?>
                    <p style="color: var(--clr-warning); font-size: 0.875rem;">
                        <i class="fas fa-exclamation-triangle"></i> Google Maps API Key fehlt - Demo-Modus aktiv
                    </p>
                <?php endif; ?>

                <?php if (!empty($settings['smtp_host']) && !empty($settings['smtp_user'])): ?>
                    <p style="color: var(--clr-success); font-size: 0.875rem;">
                        <i class="fas fa-check-circle"></i> E-Mail-Versand konfiguriert
                    </p>
                <?php else: ?>
                    <p style="color: var(--clr-warning); font-size: 0.875rem;">
                        <i class="fas fa-exclamation-triangle"></i> E-Mail-Versand nicht konfiguriert
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </footer>
</body>

</html>