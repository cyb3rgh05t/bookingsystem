<?php
// index.php - Hauptseite mit Buchungsbutton
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
    <title><?php echo SITE_NAME; ?> - Willkommen</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
        }

        .hero-content {
            z-index: 1;
            max-width: 800px;
            padding: 2rem;
        }

        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--clr-primary-a0), var(--clr-info));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            color: var(--clr-primary-a20);
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin: 3rem 0;
        }

        .feature {
            padding: 2rem;
            background: var(--clr-surface-tonal-a10);
            border-radius: var(--radius-md);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature h3 {
            margin-bottom: 0.5rem;
            color: var(--clr-primary-a0);
        }

        .feature p {
            color: var(--clr-primary-a30);
            font-size: 0.95rem;
        }

        .cta-section {
            background: linear-gradient(135deg, var(--clr-surface-a10), var(--clr-surface-a20));
            padding: 4rem 0;
            margin-top: 4rem;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.1rem;
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

            <div class="features">
                <div class="feature">
                    <div class="feature-icon">🚗</div>
                    <h3>Mobiler Service</h3>
                    <p>Wir kommen direkt zu Ihnen nach Hause oder an Ihren Arbeitsplatz</p>
                </div>

                <div class="feature">
                    <div class="feature-icon">⏰</div>
                    <h3>Flexible Zeiten</h3>
                    <p>Mo-Fr: 16:30-21:00 Uhr<br>Sa: 09:00-14:00 Uhr</p>
                </div>

                <div class="feature">
                    <div class="feature-icon">✨</div>
                    <h3>Top Qualität</h3>
                    <p>Professionelle Durchführung aller Services mit hochwertigen Materialien</p>
                </div>

                <div class="feature">
                    <div class="feature-icon">💳</div>
                    <h3>Bequeme Zahlung</h3>
                    <p>Zahlen Sie einfach per PayPal oder Kreditkarte - kein Bargeld nötig</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Preview -->
    <section class="container" style="padding: 4rem 20px;">
        <h2 style="text-align: center; margin-bottom: 3rem;">Unsere Services</h2>

        <div class="grid grid-3" id="services-preview">
            <?php
            $services = $db->fetchAll("SELECT * FROM services WHERE is_active = 1 LIMIT 6");
            foreach ($services as $service):
            ?>
                <div class="service-card">
                    <div class="service-card-bg" style="background-image: url('assets/images/services/<?php echo $service['background_image']; ?>')"></div>
                    <div class="service-card-content">
                        <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                        <p style="font-size: 0.9rem; margin: 0.5rem 0;">
                            <?php echo htmlspecialchars($service['description']); ?>
                        </p>
                        <div class="service-card-meta">
                            <span class="service-price"><?php echo number_format($service['price'], 2); ?>€</span>
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
            <div class="card" style="max-width: 800px; margin: 0 auto; text-align: center;">
                <h2 style="margin-bottom: 2rem;">So funktioniert's</h2>

                <div class="grid grid-3" style="text-align: left; margin-top: 2rem;">
                    <div>
                        <h3 style="color: var(--clr-info);">1. Service wählen</h3>
                        <p>Wählen Sie aus unserem Angebot die gewünschten Services aus</p>
                    </div>
                    <div>
                        <h3 style="color: var(--clr-info);">2. Termin buchen</h3>
                        <p>Wählen Sie Datum und Uhrzeit, die Ihnen am besten passen</p>
                    </div>
                    <div>
                        <h3 style="color: var(--clr-info);">3. Wir kommen zu Ihnen</h3>
                        <p>Unser Team kommt pünktlich zu Ihrem Wunschtermin</p>
                    </div>
                </div>

                <div class="alert alert-info" style="margin-top: 2rem;">
                    <strong>Service-Gebiet:</strong> Bis 35km Umkreis |
                    <strong>Kostenlose Anfahrt:</strong> Bis 10km |
                    <strong>Ab 10km:</strong> 2€/km Anfahrtskosten
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
                        Montag - Freitag: 16:30 - 21:00 Uhr<br>
                        Samstag: 09:00 - 14:00 Uhr<br>
                        Sonntag: Geschlossen
                    </p>
                </div>

                <div>
                    <h3 style="margin-bottom: 1rem;">Service</h3>
                    <p style="color: var(--clr-primary-a40);">
                        ✓ Mobiler Service bis 35km<br>
                        ✓ Online-Terminbuchung<br>
                        ✓ Bargeldlose Zahlung<br>
                        ✓ Professionelle Beratung
                    </p>
                </div>
            </div>

            <hr style="margin: 2rem 0; border-color: var(--clr-surface-a30);">

            <p style="text-align: center; color: var(--clr-primary-a50);">
                © <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['company_name'] ?? 'Auto Service GmbH'); ?>.
                Alle Rechte vorbehalten.
            </p>
        </div>
    </footer>
</body>

</html>