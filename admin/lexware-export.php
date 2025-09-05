<?php

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

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Einzelne Zahlung als "in Lexware erledigt" markieren
    if (isset($_POST['mark_done'])) {
        $appointmentId = $_POST['appointment_id'];

        // Setze lexware_export_date = jetzt (bedeutet: "Ich habe das in Lexware gemacht")
        $db->query("
            UPDATE appointments 
            SET lexware_export_date = CURRENT_TIMESTAMP
            WHERE id = ? 
            AND payment_status = 'paid'
        ", [$appointmentId]);

        $message = '✅ Als in Lexware erledigt markiert';
        $messageType = 'success';
    }

    // Mehrere als erledigt markieren
    if (isset($_POST['mark_selected'])) {
        $selected = $_POST['selected_appointments'] ?? [];

        if (count($selected) > 0) {
            $placeholders = str_repeat('?,', count($selected) - 1) . '?';
            $db->query("
                UPDATE appointments 
                SET lexware_export_date = CURRENT_TIMESTAMP
                WHERE id IN ($placeholders)
                AND payment_status = 'paid'
            ", $selected);

            $message = '✅ ' . count($selected) . ' Zahlungen als erledigt markiert';
            $messageType = 'success';
        } else {
            $message = '⚠️ Keine Zahlungen ausgewählt';
            $messageType = 'warning';
        }
    }
}

// Statistiken laden
$stats = $db->fetch("
    SELECT 
        COUNT(CASE WHEN payment_status = 'paid' AND lexware_invoice_id IS NOT NULL AND lexware_export_date IS NULL THEN 1 END) as pending_export,
        COUNT(CASE WHEN payment_status = 'paid' AND lexware_invoice_id IS NOT NULL AND lexware_export_date IS NOT NULL THEN 1 END) as done_export,
        COUNT(CASE WHEN payment_status = 'paid' AND DATE(payment_date) = DATE('now') THEN 1 END) as paid_today,
        SUM(CASE WHEN payment_status = 'paid' AND lexware_export_date IS NULL THEN total_price ELSE 0 END) as pending_amount
    FROM appointments
");

// Lade "To-Do" Zahlungen (bezahlt, aber noch nicht in Lexware erledigt)
$pendingPayments = $db->fetchAll("
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
        c.phone
    FROM appointments a
    JOIN customers c ON a.customer_id = c.id
    WHERE a.payment_status = 'paid'
    AND a.lexware_invoice_id IS NOT NULL
    AND a.lexware_export_date IS NULL
    ORDER BY a.payment_date DESC
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

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
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

        /* Zusätzliche Styles für Workflow */
        .workflow-banner {
            background: linear-gradient(135deg, var(--clr-surface-a10) 0%, var(--clr-surface-tonal-a10) 100%);
            border: 1px solid var(--clr-surface-a20);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
        }

        .workflow-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .workflow-step {
            background: var(--clr-surface-a05);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid var(--clr-surface-a20);
        }

        .workflow-step.active {
            background: var(--clr-surface-tonal-a10);
            border: 2px solid var(--clr-primary-a0);
        }

        .workflow-step i {
            font-size: 1.5rem;
            color: var(--clr-primary-a20);
            margin-bottom: 10px;
            display: block;
        }

        .bulk-actions {
            background: var(--clr-surface-a05);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid var(--clr-surface-a20);
        }

        .payment-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .payment-paypal {
            background: rgba(0, 48, 135, 0.1);
            color: #003087;
            border: 1px solid rgba(0, 48, 135, 0.2);
        }

        .payment-bank {
            background: rgba(74, 222, 128, 0.1);
            color: var(--clr-success);
            border: 1px solid rgba(74, 222, 128, 0.2);
        }

        .lexware-id {
            font-family: monospace;
            font-size: 0.875rem;
            background: var(--clr-surface-a05);
            padding: 4px 8px;
            border-radius: 4px;
            color: var(--clr-primary-a20);
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

            .workflow-steps {
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
                    <p style="color: var(--clr-surface-a50);">Manueller Workflow für Lexware Zahlungen</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Workflow Erklärung -->
            <div class="workflow-banner">
                <h2 style="color: var(--clr-primary-a20); margin-bottom: 20px;"><i class="fas fa-route"></i> Dein Workflow</h2>
                <div class="workflow-steps">
                    <div class="workflow-step">
                        <i class="fas fa-calendar-check"></i>
                        <div><strong>1. Kunde bucht</strong><br>Termin wird erstellt</div>
                    </div>
                    <div class="workflow-step">
                        <i class="fas fa-file-invoice"></i>
                        <div><strong>2. Rechnung erstellt</strong><br>Automatisch in Lexware</div>
                    </div>
                    <div class="workflow-step">
                        <i class="fas fa-credit-card"></i>
                        <div><strong>3. Kunde zahlt</strong><br>PayPal/Überweisung</div>
                    </div>
                    <div class="workflow-step active">
                        <i class="fas fa-hand-pointer"></i>
                        <div><strong>4. Du markierst</strong><br>Manuell in Lexware</div>
                    </div>
                    <div class="workflow-step">
                        <i class="fas fa-check-double"></i>
                        <div><strong>5. Bestätigen</strong><br>Hier als erledigt</div>
                    </div>
                </div>
            </div>

            <!-- Statistiken -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h4>To-Do in Lexware</h4>
                    <div class="stat-value"><?php echo $stats['pending_export'] ?? 0; ?></div>
                    <p>Manuell zu markieren</p>
                </div>
                <div class="stat-card">
                    <h4>Erledigt</h4>
                    <div class="stat-value"><?php echo $stats['done_export'] ?? 0; ?></div>
                    <p>Bereits abgeschlossen</p>
                </div>
                <div class="stat-card">
                    <h4>Offener Betrag</h4>
                    <div class="stat-value"><?php echo number_format($stats['pending_amount'] ?? 0, 2, ',', '.'); ?> €</div>
                    <p>Zu bearbeiten</p>
                </div>
                <div class="stat-card">
                    <h4>Heute bezahlt</h4>
                    <div class="stat-value"><?php echo $stats['paid_today'] ?? 0; ?></div>
                    <p>Neue Zahlungen</p>
                </div>
            </div>

            <!-- To-Do Liste -->
            <div class="card">
                <h2><i class="fas fa-clipboard-list"></i> Zahlungen zum Bearbeiten in Lexware</h2>
                <p style="color: var(--clr-surface-a50); margin-bottom: 20px;">
                    Diese Zahlungen sind eingegangen und müssen manuell in Lexware als bezahlt markiert werden.
                </p>

                <?php if (count($pendingPayments) > 0): ?>
                    <form method="POST">
                        <div class="bulk-actions">
                            <input type="checkbox" id="select-all">
                            <label for="select-all"><strong>Alle auswählen</strong></label>
                            <button type="submit" name="mark_selected" class="btn btn-success">
                                <i class="fas fa-check-double"></i> Ausgewählte als erledigt markieren
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <i class="fas fa-check-square"></i>
                                        </th>
                                        <th>Datum</th>
                                        <th>Kunde</th>
                                        <th>Betrag</th>
                                        <th>Zahlungsart</th>
                                        <th>Lexware ID</th>
                                        <th>Aktion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingPayments as $payment): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox"
                                                    name="selected_appointments[]"
                                                    value="<?php echo $payment['id']; ?>"
                                                    class="payment-checkbox">
                                            </td>
                                            <td>
                                                <strong><?php echo date('d.m.Y', strtotime($payment['payment_date'])); ?></strong><br>
                                                <small style="color: var(--clr-surface-a50);">
                                                    <?php echo date('H:i', strtotime($payment['payment_date'])); ?> Uhr
                                                </small>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></div>
                                                <div style="font-size: 0.875rem; color: var(--clr-surface-a50);"><?php echo htmlspecialchars($payment['email']); ?></div>
                                            </td>
                                            <td>
                                                <strong style="color: var(--clr-success);">
                                                    <?php echo number_format($payment['total_price'], 2, ',', '.'); ?> €
                                                </strong>
                                            </td>
                                            <td>
                                                <?php if ($payment['payment_method'] === 'paypal'): ?>
                                                    <span class="payment-badge payment-paypal">
                                                        <i class="fab fa-paypal"></i> PayPal
                                                    </span>
                                                    <?php if ($payment['paypal_capture_id']): ?>
                                                        <br><small style="color: var(--clr-surface-a50); font-size: 0.75rem;">
                                                            <?php echo substr($payment['paypal_capture_id'], 0, 8); ?>...
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="payment-badge payment-bank">
                                                        <i class="fas fa-university"></i> Überweisung
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code class="lexware-id"><?php echo htmlspecialchars($payment['lexware_invoice_id']); ?></code>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $payment['id']; ?>">
                                                    <button type="submit" name="mark_done" class="btn btn-success btn-sm"
                                                        title="Als in Lexware erledigt markieren">
                                                        <i class="fas fa-check"></i> Erledigt
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                <?php else: ?>
                    <p style="text-align: center; padding: 2rem; color: var(--clr-surface-a40);">
                        <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--clr-success); display: block; margin-bottom: 10px;"></i>
                        Alles erledigt! Keine offenen Zahlungen zum Bearbeiten in Lexware.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Anleitung -->
            <div class="card">
                <h2><i class="fas fa-question-circle"></i> So funktioniert's:</h2>
                <ol style="line-height: 1.8; color: var(--clr-surface-a60); padding-left: 20px;">
                    <li><strong style="color: var(--clr-primary-a30);">Öffne Lexware</strong> und suche die Rechnung mit der angezeigten Lexware ID</li>
                    <li><strong style="color: var(--clr-primary-a30);">Markiere die Rechnung als bezahlt</strong> in Lexware (manuell)</li>
                    <li><strong style="color: var(--clr-primary-a30);">Klicke hier auf "Erledigt"</strong> um die Zahlung aus dieser Liste zu entfernen</li>
                    <li>Optional: Wähle mehrere Zahlungen aus und markiere sie gleichzeitig als erledigt</li>
                </ol>

                <div class="alert alert-info" style="margin-top: 20px;">
                    <strong>💡 Tipp:</strong> Die Lexware API unterstützt leider keine automatische Status-Änderung.
                    Dieser manuelle Workflow stellt sicher, dass deine Buchhaltung synchron bleibt.
                </div>
            </div>
        </main>
    </div>

    <script>
        // Select All Checkbox
        document.getElementById('select-all')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.payment-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });

        // Update select-all wenn einzelne Checkboxen geändert werden
        document.querySelectorAll('.payment-checkbox').forEach(cb => {
            cb.addEventListener('change', function() {
                const total = document.querySelectorAll('.payment-checkbox').length;
                const checked = document.querySelectorAll('.payment-checkbox:checked').length;
                document.getElementById('select-all').checked = (total === checked);
            });
        });
    </script>
</body>

</html>