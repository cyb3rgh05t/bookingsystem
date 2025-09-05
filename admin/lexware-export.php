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
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
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