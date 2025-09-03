-- ========================================
-- Datenbank-Migrationen für Lexware & PayPal Integration
-- ========================================

-- 1. Erweitere Settings-Tabelle für Lexware & PayPal
ALTER TABLE settings ADD COLUMN lexware_api_url TEXT DEFAULT 'https://api.lexware.de/v1';
ALTER TABLE settings ADD COLUMN lexware_api_key TEXT;
ALTER TABLE settings ADD COLUMN paypal_client_secret TEXT;
ALTER TABLE settings ADD COLUMN paypal_merchant_email TEXT;
ALTER TABLE settings ADD COLUMN paypal_mode TEXT DEFAULT 'sandbox';

-- 2. Erweitere Customers-Tabelle für Lexware Kundennummer
ALTER TABLE customers ADD COLUMN lexware_customer_number TEXT;

-- 3. Erweitere Appointments-Tabelle für Zahlungen & Rechnungen
ALTER TABLE appointments ADD COLUMN payment_date DATETIME;
ALTER TABLE appointments ADD COLUMN paypal_order_id TEXT;
ALTER TABLE appointments ADD COLUMN paypal_capture_id TEXT;
ALTER TABLE appointments ADD COLUMN invoice_number TEXT;
ALTER TABLE appointments ADD COLUMN invoice_created_at DATETIME;
ALTER TABLE appointments ADD COLUMN lexware_invoice_id TEXT;

-- 4. Erstelle Tabelle für Zahlungs-Historie
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

-- 5. Erstelle Tabelle für Lexware Sync-Log
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

-- 6. Index für bessere Performance
CREATE INDEX IF NOT EXISTS idx_appointments_payment_status ON appointments(payment_status);
CREATE INDEX IF NOT EXISTS idx_appointments_paypal_order ON appointments(paypal_order_id);
CREATE INDEX IF NOT EXISTS idx_appointments_invoice ON appointments(invoice_number);
CREATE INDEX IF NOT EXISTS idx_payment_history_appointment ON payment_history(appointment_id);
CREATE INDEX IF NOT EXISTS idx_lexware_sync_appointment ON lexware_sync_log(appointment_id);

-- 7. Update bestehende Appointments (falls vorhanden)
UPDATE appointments 
SET payment_status = 'unpaid' 
WHERE payment_status IS NULL;

-- 8. Füge Standard-Werte für neue Felder hinzu
UPDATE settings 
SET 
    paypal_mode = 'sandbox',
    lexware_api_url = 'https://api.lexware.de/v1'
WHERE id = 1;