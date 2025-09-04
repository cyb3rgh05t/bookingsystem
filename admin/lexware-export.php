<?php

/**
 * Admin Panel - Lexware Export
 * Datei: admin/lexware-export.php
 */

session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

// Check admin login
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$db = Database::getInstance();
$message = '';
$messageType = '';
$exportFile = '';

// Handle Export-Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'export_payments') {
        $startDate = $_POST['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $_POST['end_date'] ?? date('Y-m-d');

        // Hole alle bezahlten Rechnungen im Zeitraum
        $payments = $db->fetchAll("
            SELECT 
                a.id,
                a.lexware_invoice_id,
                a.invoice_number,
                a.total_price,
                a.payment_date,
                a.payment_method,
                a.paypal_capture_id,
                c.first_name,
                c.last_name,
                c.email,
                c.company,
                c.lexware_customer_number
            FROM appointments a
            JOIN customers c ON a.customer_id = c.id
            WHERE DATE(a.payment_date) BETWEEN ? AND ?
            AND a.payment_status = 'paid'
            AND a.lexware_invoice_id IS NOT NULL
            ORDER BY a.payment_date DESC
        ", [$startDate, $endDate]);

        if (count($payments) > 0) {
            // Erstelle CSV
            $csvData = [];

            // Header für Lexware Buchhaltung
            $csvData[] = [
                'Rechnungsnummer',
                'Kundennummer',
                'Kundenname',
                'Betrag (EUR)',
                'Zahlungsdatum',
                'Zahlungsart',
                'Verwendungszweck',
                'Lexware Invoice ID'
            ];

            foreach ($payments as $payment) {
                $csvData[] = [
                    $payment['invoice_number'] ?: 'RE-' . date('Y-m', strtotime($payment['payment_date'])) . '-' . str_pad($payment['id'], 4, '0', STR_PAD_LEFT),
                    $payment['lexware_customer_number'] ?: 'KD-' . str_pad($payment['id'], 5, '0', STR_PAD_LEFT),
                    $payment['first_name'] . ' ' . $payment['last_name'] . ($payment['company'] ? ' (' . $payment['company'] . ')' : ''),
                    number_format($payment['total_price'], 2, '.', ''),
                    date('d.m.Y', strtotime($payment['payment_date'])),
                    $payment['payment_method'] === 'paypal' ? 'PayPal' : 'Überweisung',
                    'Buchung #' . str_pad($payment['id'], 4, '0', STR_PAD_LEFT) . ($payment['paypal_capture_id'] ? ' / PayPal: ' . $payment['paypal_capture_id'] : ''),
                    $payment['lexware_invoice_id']
                ];
            }

            // Speichere CSV
            $exportDir = '../exports/';
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }

            $filename = 'lexware_zahlungen_' . date('Y-m-d_His') . '.csv';
            $filepath = $exportDir . $filename;

            $fp = fopen($filepath, 'w');
            fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM für Excel/Lexware
            foreach ($csvData as $row) {
                fputcsv($fp, $row, ';'); // Semikolon für deutsche Excel/Lexware
            }
            fclose($fp);

            $exportFile = $filename;
            $message = 'Export erfolgreich! ' . count($payments) . ' Zahlungen exportiert.';
            $messageType = 'success';
        } else {
            $message = 'Keine bezahlten Rechnungen im gewählten Zeitraum gefunden.';
            $messageType = 'warning';
        }
    }
}

// Statistiken laden
$stats = $db->fetch("
    SELECT 
        COUNT(CASE WHEN payment_status = 'paid' AND lexware_invoice_id IS NOT NULL THEN 1 END) as paid_with_invoice,
        COUNT(CASE WHEN payment_status = 'paid' AND lexware_invoice_id IS NULL THEN 1 END) as paid_without_invoice,
        COUNT(CASE WHEN payment_status = 'paid' AND lexware_export_date IS NULL AND lexware_invoice_id IS NOT NULL THEN 1 END) as pending_export,
        COUNT(CASE WHEN payment_status = 'paid' AND DATE(payment_date) = DATE('now') THEN 1 END) as paid_today,
        SUM(CASE WHEN payment_status = 'paid' AND lexware_export_date IS NULL THEN total_price ELSE 0 END) as pending_amount
    FROM appointments
");

// Letzte bezahlte Rechnungen für Vorschau
$pendingPayments = $db->fetchAll("
    SELECT 
        a.id,
        a.invoice_number,
        a.total_price,
        a.payment_date,
        c.first_name,
        c.last_name
    FROM appointments a
    JOIN customers c ON a.customer_id = c.id
    WHERE a.payment_status = 'paid'
    AND a.lexware_invoice_id IS NOT NULL
    AND (a.lexware_export_date IS NULL OR DATE(a.payment_date) = DATE('now'))
    ORDER BY a.payment_date DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5">
    <title>Lexware Export - Admin</title>
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--clr-surface-a10) 0%, var(--clr-surface-tonal-a10) 100%);
            border: 1px solid var(--clr-surface-a20);
            border-radius: 8px;
            padding: 20px;
        }

        .stat-card h4 {
            color: var(--clr-surface-a50);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: var(--clr-primary-a20);
            margin-bottom: 5px;
        }

        .stat-card p {
            color: var(--clr-surface-a50);
            font-size: 12px;
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

        .alert-warning {
            background-color: rgba(251, 191, 36, 0.1);
            border: 1px solid #fbbf24;
            color: #fde68a;
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

        .card h2 {
            color: var(--clr-primary-a20);
            margin: 0;
            margin-bottom: 20px;
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

        .date-range {
            display: flex;
            gap: 20px;
            align-items: end;
            margin-bottom: 20px;
        }

        .date-range .form-group {
            flex: 1;
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
            gap: 8px;
        }

        .btn:hover {
            background-color: var(--clr-primary-a10);
            transform: translateY(-1px);
        }

        .btn-success {
            background-color: var(--clr-success);
            color: var(--clr-light-a0);
        }

        .btn-success:hover {
            background-color: #16a34a;
        }

        /* Table */
        .table-responsive {
            overflow-x: auto;
        }

        .admin-table {
            width: 100%;
            background-color: var(--clr-surface-a05);
            border-radius: 8px;
            overflow: hidden;
        }

        .admin-table th,
        .admin-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--clr-surface-a20);
        }

        .admin-table th {
            background-color: var(--clr-surface-tonal-a10);
            color: var(--clr-primary-a20);
            font-weight: 600;
            font-size: 14px;
        }

        .admin-table tr:last-child td {
            border-bottom: none;
        }

        .admin-table tr:hover {
            background-color: var(--clr-surface-a10);
        }

        .download-link {
            display: inline-block;
            margin-left: 20px;
            padding: 8px 16px;
            background: var(--clr-primary-a0);
            color: var(--clr-dark-a0);
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
        }

        .download-link:hover {
            background: var(--clr-primary-a10);
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .date-range {
                flex-direction: column;
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
                    <li><a href="settings.php"><i class="fa-solid fa-gear"></i>Einstellungen</a></li>
                    <li><a href="lexware-export.php" class="active"><i class="fa-solid fa-file-export"></i>Lexware Export</a></li>
                    <li><a href="../index.php" style="margin-top: 20px; border-top: 1px solid var(--clr-surface-a20); padding-top: 20px;"><i class="fa-solid fa-arrow-left"></i>Zum Termin Planner</a></li>
                    <li><a href="logout.php" style="color: var(--clr-error);"><i class="fa-solid fa-right-from-bracket"></i>Abmelden</a></li>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fa-solid fa-file-export"></i> Lexware Export</h1>
                    <p style="color: var(--clr-surface-a50);">Zahlungen für Lexware Buchhaltung exportieren</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>

                    <?php if ($exportFile): ?>
                        <a href="../exports/<?php echo $exportFile; ?>"
                            download
                            class="download-link">
                            <i class="fas fa-download"></i> CSV herunterladen
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Statistiken -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Nicht exportiert</h4>
                    <div class="stat-value"><?php echo $stats['pending_export'] ?? 0; ?></div>
                    <p>Ausstehende Zahlungen</p>
                </div>
                <div class="stat-card">
                    <h4>Ausstehender Betrag</h4>
                    <div class="stat-value"><?php echo number_format($stats['pending_amount'] ?? 0, 2, ',', '.'); ?> €</div>
                    <p>Zu exportieren</p>
                </div>
                <div class="stat-card">
                    <h4>Heute bezahlt</h4>
                    <div class="stat-value"><?php echo $stats['paid_today'] ?? 0; ?></div>
                    <p>Zahlungen</p>
                </div>
                <div class="stat-card">
                    <h4>Mit Rechnung</h4>
                    <div class="stat-value"><?php echo $stats['paid_with_invoice'] ?? 0; ?></div>
                    <p>Gesamt</p>
                </div>
            </div>

            <!-- Export Form -->
            <div class="card">
                <h2><i class="fas fa-calendar-alt"></i> Zahlungen exportieren</h2>

                <form method="POST">
                    <input type="hidden" name="action" value="export_payments">

                    <div class="date-range">
                        <div class="form-group">
                            <label class="form-label" for="start_date">Von:</label>
                            <input type="date"
                                id="start_date"
                                name="start_date"
                                value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>"
                                class="form-control">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="end_date">Bis:</label>
                            <input type="date"
                                id="end_date"
                                name="end_date"
                                value="<?php echo date('Y-m-d'); ?>"
                                class="form-control">
                        </div>

                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-file-csv"></i>
                            CSV Export erstellen
                        </button>
                    </div>

                    <div style="margin-top: 10px; color: var(--clr-surface-a50);">
                        <small>
                            <i class="fas fa-info-circle"></i>
                            Exportiert alle bezahlten Rechnungen im gewählten Zeitraum als CSV für Lexware.
                        </small>
                    </div>
                </form>
            </div>

            <!-- Vorschau der ausstehenden Zahlungen -->
            <div class="card">
                <h2><i class="fas fa-clock"></i> Ausstehende Zahlungen (Vorschau)</h2>

                <?php if (count($pendingPayments) > 0): ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Rechnungsnr.</th>
                                    <th>Kunde</th>
                                    <th>Betrag</th>
                                    <th>Zahlungsdatum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingPayments as $payment): ?>
                                    <tr>
                                        <td><?php echo $payment['invoice_number'] ?: 'RE-' . str_pad($payment['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></td>
                                        <td><?php echo number_format($payment['total_price'], 2, ',', '.'); ?> €</td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($payment['payment_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: var(--clr-surface-a40);">
                        Keine ausstehenden Zahlungen zum Export.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Anleitung -->
            <div class="card">
                <h2><i class="fas fa-question-circle"></i> Anleitung für Lexware Import</h2>
                <ol style="line-height: 1.8; color: var(--clr-surface-a60);">
                    <li>Wählen Sie den gewünschten Zeitraum und klicken Sie auf "CSV Export erstellen"</li>
                    <li>Laden Sie die CSV-Datei herunter</li>
                    <li>Öffnen Sie Lexware Buchhaltung</li>
                    <li>Gehen Sie zu <strong style="color: var(--clr-primary-a30);">Datei → Import → Zahlungen</strong></li>
                    <li>Wählen Sie die heruntergeladene CSV-Datei</li>
                    <li>Folgen Sie dem Import-Assistenten</li>
                    <li>Die Zahlungen werden automatisch den Rechnungen zugeordnet</li>
                </ol>

                <div class="alert alert-info" style="margin-top: 20px;">
                    <strong>💡 Tipp:</strong> Exportieren Sie täglich oder wöchentlich, um die Buchhaltung aktuell zu halten.
                </div>
            </div>
        </main>
    </div>

    <script>
        // Auto-Download wenn Export erfolgreich
        document.addEventListener('DOMContentLoaded', function() {
            const downloadLink = document.querySelector('.download-link');
            if (downloadLink) {
                // Optional: Auto-Download nach 2 Sekunden
                setTimeout(() => {
                    downloadLink.click();
                }, 2000);
            }
        });
    </script>
</body>

</html>