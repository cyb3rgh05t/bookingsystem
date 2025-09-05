<?php

require_once '../includes/config.php';

try {
    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Tabellen erstellen mit allen Feldern für Lexware & PayPal Integration
    $sql = "
    -- ========================================
    -- Firmen-Einstellungen (erweitert um Lexware & PayPal)
    -- ========================================
    CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY,
        company_name TEXT,
        address TEXT,
        phone TEXT,
        email TEXT,
        google_maps_api_key TEXT,
        paypal_client_id TEXT,
        paypal_client_secret TEXT,
        paypal_merchant_email TEXT,
        paypal_mode TEXT DEFAULT 'sandbox',
        lexware_api_url TEXT DEFAULT 'https://api.lexware.de/v1',
        lexware_api_key TEXT,
        smtp_host TEXT,
        smtp_port INTEGER,
        smtp_user TEXT,
        smtp_password TEXT,
        max_distance INTEGER DEFAULT 35,
        min_price_above_10km REAL DEFAULT 59.90,
        price_per_km REAL DEFAULT 2.0,
        free_distance_km INTEGER DEFAULT 10,
        working_hours_weekday_start TEXT DEFAULT '16:30',
        working_hours_weekday_end TEXT DEFAULT '21:00',
        working_hours_saturday_start TEXT DEFAULT '09:00',
        working_hours_saturday_end TEXT DEFAULT '14:00',
        time_slot_duration INTEGER DEFAULT 30,
        appointment_buffer_time INTEGER DEFAULT 30
    );

    -- ========================================
    -- Services-Tabelle
    -- ========================================
    CREATE TABLE IF NOT EXISTS services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT,
        price REAL NOT NULL,
        duration_minutes INTEGER NOT NULL,
        background_image TEXT,
        is_active BOOLEAN DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    -- ========================================
    -- Kunden-Tabelle (erweitert um Lexware Kundennummer)
    -- ========================================
    CREATE TABLE IF NOT EXISTS customers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        first_name TEXT NOT NULL,
        last_name TEXT NOT NULL,
        email TEXT NOT NULL,
        phone TEXT NOT NULL,
        address TEXT NOT NULL,
        car_brand TEXT,
        car_model TEXT,
        car_year INTEGER,
        license_plate TEXT,
        lexware_customer_number TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    -- ========================================
    -- Termine-Tabelle (erweitert um Zahlungen & Rechnungen)
    -- ========================================
    CREATE TABLE IF NOT EXISTS appointments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_id INTEGER,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        total_duration INTEGER NOT NULL,
        distance_km REAL,
        travel_cost REAL DEFAULT 0,
        subtotal REAL NOT NULL,
        total_price REAL NOT NULL,
        status TEXT DEFAULT 'pending',
        payment_status TEXT DEFAULT 'unpaid',
        payment_method TEXT,
        payment_date DATETIME,
        paypal_order_id TEXT,
        paypal_capture_id TEXT,
        invoice_number TEXT,
        invoice_created_at DATETIME,
        lexware_invoice_id TEXT,
        notes TEXT,
        lexware_export_date DATETIME
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id)
    );

    -- ========================================
    -- Termin-Services Verknüpfung
    -- ========================================
    CREATE TABLE IF NOT EXISTS appointment_services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        appointment_id INTEGER,
        service_id INTEGER,
        price REAL NOT NULL,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id),
        FOREIGN KEY (service_id) REFERENCES services(id)
    );

    -- ========================================
    -- Blockierte Zeiten
    -- ========================================
    CREATE TABLE IF NOT EXISTS blocked_times (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        date DATE NOT NULL,
        start_time TIME,
        end_time TIME,
        reason TEXT,
        is_full_day BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    -- ========================================
    -- Admin-Benutzer
    -- ========================================
    CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        email TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    -- ========================================
    -- Zahlungs-Historie (NEU)
    -- ========================================
    CREATE TABLE IF NOT EXISTS payment_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        appointment_id INTEGER NOT NULL,
        payment_method TEXT NOT NULL,
        amount REAL NOT NULL,
        currency TEXT DEFAULT 'EUR',
        transaction_id TEXT,
        status TEXT NOT NULL,
        gateway_response TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id)
    );

    -- ========================================
    -- Lexware Sync-Log (NEU)
    -- ========================================
    CREATE TABLE IF NOT EXISTS lexware_sync_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        appointment_id INTEGER,
        action TEXT NOT NULL,
        request_data TEXT,
        response_data TEXT,
        status TEXT NOT NULL,
        error_message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (appointment_id) REFERENCES appointments(id)
    );
    ";

    $db->exec($sql);

    // ========================================
    // Indizes für bessere Performance erstellen
    // ========================================
    $indices = [
        "CREATE INDEX IF NOT EXISTS idx_appointments_payment_status ON appointments(payment_status)",
        "CREATE INDEX IF NOT EXISTS idx_appointments_paypal_order ON appointments(paypal_order_id)",
        "CREATE INDEX IF NOT EXISTS idx_appointments_invoice ON appointments(invoice_number)",
        "CREATE INDEX IF NOT EXISTS idx_payment_history_appointment ON payment_history(appointment_id)",
        "CREATE INDEX IF NOT EXISTS idx_lexware_sync_appointment ON lexware_sync_log(appointment_id)",
        "CREATE INDEX IF NOT EXISTS idx_customers_lexware ON customers(lexware_customer_number)",
        "CREATE INDEX IF NOT EXISTS idx_appointments_date ON appointments(appointment_date)",
        "CREATE INDEX IF NOT EXISTS idx_appointments_status ON appointments(status)"
    ];

    foreach ($indices as $index) {
        $db->exec($index);
    }

    // ========================================
    // Standard-Einstellungen einfügen
    // ========================================
    $checkSettings = $db->query("SELECT COUNT(*) as count FROM settings")->fetch();
    if ($checkSettings['count'] == 0) {
        $db->exec("INSERT INTO settings (
                        id, 
                        company_name, 
                        address, 
                        email, 
                        phone,
                        paypal_mode,
                        lexware_api_url,
                        max_distance,
                        min_price_above_10km,
                        price_per_km,
                        free_distance_km,
                        working_hours_weekday_start,
                        working_hours_weekday_end,
                        working_hours_saturday_start,
                        working_hours_saturday_end,
                        time_slot_duration,
                        appointment_buffer_time
                   ) VALUES (
                        1, 
                        'Meine Firma', 
                        'Musterstrasse 123', 
                        'meinefirma@meinefirme.dev', 
                        '0123456789',
                        'sandbox',
                        'https://api.lexware.de/v1',
                        35,
                        59.90,
                        2.0,
                        10,
                        '16:30',
                        '21:00',
                        '09:00',
                        '14:00',
                        30,
                        30
                   )");
    }

    // ========================================
    // Standard-Admin erstellen (Passwort: admin123)
    // ========================================
    $checkAdmin = $db->query("SELECT COUNT(*) as count FROM admin_users")->fetch();
    if ($checkAdmin['count'] == 0) {
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO admin_users (username, password_hash, email) 
                   VALUES ('admin', '$passwordHash', 'admin@autoservice.de')");
    }

    // ========================================
    // Beispiel-Services einfügen
    // ========================================
    $checkServices = $db->query("SELECT COUNT(*) as count FROM services")->fetch();
    if ($checkServices['count'] == 0) {
        $services = [
            ['Sorglos-Paket', 'Check + Pflege in einem Termin: Dein Auto wird technisch geprüft und außen frisch gewaschen – perfekt, wenn du es einfach sorglos willst.', 119.00, 45, 'sorglos.jpg'],
            ['Verkaufsklar-Paket', 'Auto verkaufen? Ich mache dein Fahrzeug sauber, fotografiere es und erstelle ein Profi-Inserat – so verkaufst du schneller und besser', 159.90, 90, 'verkaufsklar.jpg'],
            ['Check & Wechsel-Paket', 'Der Saisonklassiker: Räderwechsel vor Ort plus ein Sicherheits-Check – alles in einem Termin.', 89.90, 60, 'check.jpg'],
            ['Diagnose Paket', 'Dein Auto zeigt eine Warnlampe oder macht Probleme? Ich lese den Fehlerspeicher aus, prüfe die wichtigsten Punkte und erkläre dir klar, was wirklich los ist – damit du weißt, woran du bist.', 39.90, 30, 'verkaufsklar.jpg'],
            ['Basis Check', 'Dein Auto zeigt eine Warnlampe oder macht Probleme? Ich lese den Fehlerspeicher aus, prüfe die wichtigsten Punkte und erkläre dir klar, was wirklich los ist – damit du weißt, woran du bist.', 59.90, 30, 'sorglos.jpg'],
            ['Komfort Check', 'Der umfassende Check: Zusätzlich zur Basis-Prüfung lese ich Fehler aus, kontrolliere Bremsen, Batterie und Unterboden – perfekt, wenn du sicher unterwegs sein willst.', 79.90, 120, 'rader.jpg'],
            ['Räderwechsel mobil', 'Ich komme zu dir, wechsel deine Räder direkt vor Ort und checke Bremsen & Luftdruck gleich mit – kein Werkstatttermin, kein Schleppen.', 39.90, 60, 'hilfe.jpg'],
            ['Hilfe beim Fahrzeugkauf', 'Du willst ein Auto kaufen, bist dir aber unsicher? Ich prüfe das Auto gründlich und sage dir ehrlich, ob es den Preis wert ist.', 79.90, 60, 'wash.jpg'],
            ['Hilfe beim Fahrzeugverkauf', 'Ich mache dein Auto verkaufsfertig: Check, Profi Fotos, Preis-Analyse und ein ansprechender Inserat Text.', 59.90, 60, 'check.jpg'],
            ['Wash & Care Pakete Außenpflege Basic', 'Frischer Glanz: Handwäsche, Felgenreinigung, Trocknen.', 89.90, 60, 'washcare.jpg'],
            ['Außen- & Innenpflege Plus', 'Innen & außen sauber: Handwäsche, Innenraumreinigung, Oberflächenpflege.', 149.90, 60, 'wash2.jpg'],
            ['Außen- & Innenpflege Premium', 'Das volle Programm: intensive Pflege inkl. Versiegelung, Polster- & Kunststoffpflege.', 89.90, 60, 'wash2.jpg'],
            ['Batterie-Service', '(zzgl. Batteriepreis) Batterie schwach? Ich teste, wechsle und programmiere sie – damit dein Auto sofort wieder startet.', 69.90, 60, 'batterie.jpg'],
            ['Scheinwerferaufbereitung', 'Matte Scheinwerfer? Ich schleife, poliere und versiegel sie – für klare Sicht und frische Optik.', 69.90, 60, 'scheinwerfer.jpg'],
            ['Ersatzteilbeschaffung', '+ Marge - Kein Lust auf Teile suchen? Ich besorge die passenden Ersatzteile und bringe sie dir – ohne Stress.', 14.90, 60, 'ersatz.jpg']
        ];

        $stmt = $db->prepare("INSERT INTO services (name, description, price, duration_minutes, background_image) 
                             VALUES (?, ?, ?, ?, ?)");
        foreach ($services as $service) {
            $stmt->execute($service);
        }
    }

    // ========================================
    // Erfolgsmeldung mit Details
    // ========================================
    echo "<h2>✓ Datenbank erfolgreich eingerichtet!</h2>";
    echo "<h3>Folgende Komponenten wurden installiert:</h3>";
    echo "<ul>";
    echo "<li>✓ Settings-Tabelle mit Lexware & PayPal Feldern</li>";
    echo "<li>✓ Services-Tabelle mit " . count($services) . " Beispiel-Services</li>";
    echo "<li>✓ Customers-Tabelle mit Lexware-Integration</li>";
    echo "<li>✓ Appointments-Tabelle mit erweiterter Zahlungsabwicklung</li>";
    echo "<li>✓ Payment History für Zahlungsverfolgung</li>";
    echo "<li>✓ Lexware Sync-Log für API-Kommunikation</li>";
    echo "<li>✓ Performance-Indizes angelegt</li>";
    echo "<li>✓ Standard-Admin (Username: admin, Passwort: admin123)</li>";
    echo "</ul>";
    echo "<p><strong>Nächste Schritte:</strong></p>";
    echo "<ol>";
    echo "<li>Melde dich im Admin-Bereich an</li>";
    echo "<li>Konfiguriere die PayPal API-Zugangsdaten</li>";
    echo "<li>Konfiguriere die Lexware API-Zugangsdaten</li>";
    echo "<li>Passe die Firmeneinstellungen an</li>";
    echo "</ol>";
} catch (PDOException $e) {
    echo "<h2>❌ Fehler beim Einrichten der Datenbank</h2>";
    echo "<p><strong>Fehlermeldung:</strong> " . $e->getMessage() . "</p>";
    echo "<p>Bitte überprüfe die Datenbankverbindung und Berechtigungen.</p>";
}
