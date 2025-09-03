<?php

// PHPMailer Dateien manuell einbinden (ohne Composer)
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

// PHPMailer Namespaces
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send confirmation email via SMTP using PHPMailer
 * 
 * @param string $to Email address of recipient
 * @param string $bookingNumber Booking reference number
 * @param array $bookingData All booking information
 * @return bool Success status
 */
function sendConfirmationEmailSMTP($to, $bookingNumber, $bookingData)
{
    try {
        // Get settings from database
        $db = Database::getInstance();
        $settings = $db->fetch("SELECT * FROM settings WHERE id = 1");

        // Check if SMTP is configured
        if (empty($settings['smtp_host']) || empty($settings['smtp_user'])) {
            error_log("SMTP not configured - falling back to PHP mail()");
            return sendConfirmationEmailFallback($to, $bookingNumber, $bookingData);
        }

        // Create PHPMailer instance
        $mail = new PHPMailer(true);

        // ===== SMTP CONFIGURATION =====

        // Enable verbose debug output (nur für Tests - später auskommentieren)
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;

        // Server settings
        $mail->isSMTP();                                   // Use SMTP
        $mail->Host       = $settings['smtp_host'];        // SMTP server
        $mail->SMTPAuth   = true;                          // Enable SMTP authentication
        $mail->Username   = $settings['smtp_user'];        // SMTP username
        $mail->Password   = $settings['smtp_password'];    // SMTP password

        // Encryption settings (automatic detection based on port)
        $port = intval($settings['smtp_port'] ?: 587);
        if ($port == 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;  // SSL
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // TLS
        }
        $mail->Port = $port;

        // Set charset for German umlauts
        $mail->CharSet = 'UTF-8';

        // ===== EMAIL CONTENT =====

        // Sender
        $mail->setFrom($settings['email'], $settings['company_name']);

        // Recipient
        $customerName = $bookingData['customer']['first_name'] . ' ' . $bookingData['customer']['last_name'];
        $mail->addAddress($to, $customerName);

        // Reply-To
        $mail->addReplyTo($settings['email'], $settings['company_name']);

        // Subject
        $mail->Subject = "Terminbestätigung - Buchungsnummer: $bookingNumber";

        // HTML Content
        $mail->isHTML(true);
        $mail->Body = generateEmailHTML($bookingNumber, $bookingData, $settings);

        // Plain text alternative for non-HTML mail clients
        $mail->AltBody = generateEmailPlainText($bookingNumber, $bookingData, $settings);

        // Send the email
        $mail->send();

        error_log("Email successfully sent to: $to");
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        error_log("Falling back to PHP mail() function");

        // Fallback to PHP mail() if SMTP fails
        return sendConfirmationEmailFallback($to, $bookingNumber, $bookingData);
    }
}

/**
 * Generate HTML email content
 */
function generateEmailHTML($bookingNumber, $bookingData, $settings)
{

    // Build services list
    $servicesList = '';
    $totalDuration = 0;
    foreach ($bookingData['services'] as $service) {
        $servicesList .= "<li>{$service['name']} - " . number_format($service['price'], 2, ',', '.') . "€ ({$service['duration_minutes']} Min.)</li>";
        $totalDuration += $service['duration_minutes'];
    }

    // Format date
    $dateParts = explode('-', $bookingData['date']);
    $dateObj = new DateTime($bookingData['date']);
    $dayName = [
        'Monday' => 'Montag',
        'Tuesday' => 'Dienstag',
        'Wednesday' => 'Mittwoch',
        'Thursday' => 'Donnerstag',
        'Friday' => 'Freitag',
        'Saturday' => 'Samstag',
        'Sunday' => 'Sonntag'
    ][$dateObj->format('l')];
    $appointmentDate = $dateObj->format('d.m.Y');

    // Travel cost section
    $travelCostSection = '';
    if ($bookingData['travelCost'] > 0) {
        $travelCostSection = "
        <div style='background: #f8f9fa; padding: 15px; border-left: 4px solid #667eea; margin: 20px 0;'>
            <strong>📍 Anfahrt:</strong><br>
            Entfernung: " . number_format($bookingData['distance'], 1, ',', '.') . " km<br>
            Anfahrtskosten: " . number_format($bookingData['travelCost'], 2, ',', '.') . "€
        </div>";
    }

    $html = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .content {
            padding: 30px;
            background: #ffffff;
        }
        .info-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
        }
        .services-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .services-list ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        .total-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        .total-box .amount {
            font-size: 32px;
            font-weight: bold;
        }
        .footer {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>✓ Terminbestätigung</h1>
            <p style='margin: 10px 0 0 0; font-size: 18px;'>Ihre Buchung war erfolgreich!</p>
        </div>
        
        <div class='content'>
            <p>Guten Tag {$bookingData['customer']['first_name']} {$bookingData['customer']['last_name']},</p>
            
            <p>vielen Dank für Ihre Buchung! Wir haben Ihren Termin erfolgreich registriert.</p>
            
            <div class='info-box'>
                <h3 style='margin-top: 0;'>📋 Buchungsdetails</h3>
                <strong>Buchungsnummer:</strong> {$bookingNumber}<br>
                <strong>Datum:</strong> {$dayName}, {$appointmentDate}<br>
                <strong>Uhrzeit:</strong> {$bookingData['time']} Uhr<br>
                <strong>Adresse:</strong> {$bookingData['customer']['address']}
            </div>
            
            <div class='services-list'>
                <h3 style='margin-top: 0;'>🔧 Gebuchte Services</h3>
                <ul>
                    {$servicesList}
                </ul>
                <hr style='border: none; border-top: 1px solid #ddd; margin: 15px 0;'>
                <strong>Gesamtdauer:</strong> {$totalDuration} Minuten
            </div>
            
            {$travelCostSection}
            
            <div class='total-box'>
                <div>Gesamtbetrag</div>
                <div class='amount'>" . number_format($bookingData['total'], 2, ',', '.') . "€</div>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <p style='font-size: 14px; color: #666;'>
                    <strong>Zahlungsinformationen:</strong><br>
                    Bitte bezahlen Sie den Betrag per PayPal oder Kreditkarte.<br>
                    Wir akzeptieren keine Barzahlung.
                </p>
            </div>
            
            <hr style='border: none; border-top: 1px solid #ddd; margin: 30px 0;'>
            
            <h3>📞 Bei Fragen:</h3>
            <p>
                Telefon: {$settings['phone']}<br>
                E-Mail: {$settings['email']}
            </p>
            
            <p style='margin-top: 30px;'>
                Mit freundlichen Grüßen<br>
                <strong>{$settings['company_name']}</strong>
            </p>
        </div>
        
        <div class='footer'>
            <p style='margin: 0;'>
                {$settings['company_name']}<br>
                {$settings['address']}<br>
                Tel: {$settings['phone']} | E-Mail: {$settings['email']}
            </p>
            <p style='margin: 10px 0 0 0; font-size: 12px;'>
                Diese E-Mail wurde automatisch generiert. Bitte antworten Sie nicht auf diese E-Mail.
            </p>
        </div>
    </div>
</body>
</html>";

    return $html;
}

/**
 * Generate plain text email content
 */
function generateEmailPlainText($bookingNumber, $bookingData, $settings)
{
    $text = "TERMINBESTÄTIGUNG\n";
    $text .= "================\n\n";

    $text .= "Buchungsnummer: $bookingNumber\n\n";

    $text .= "KUNDENDATEN:\n";
    $text .= "Name: {$bookingData['customer']['first_name']} {$bookingData['customer']['last_name']}\n";
    $text .= "E-Mail: {$bookingData['customer']['email']}\n";
    $text .= "Telefon: {$bookingData['customer']['phone']}\n";
    $text .= "Adresse: {$bookingData['customer']['address']}\n\n";

    $text .= "TERMIN:\n";
    $text .= "Datum: {$bookingData['date']}\n";
    $text .= "Uhrzeit: {$bookingData['time']} Uhr\n\n";

    $text .= "GEBUCHTE SERVICES:\n";
    foreach ($bookingData['services'] as $service) {
        $text .= "- {$service['name']}: " . number_format($service['price'], 2, ',', '.') . "€\n";
    }

    $text .= "\nGESAMTBETRAG: " . number_format($bookingData['total'], 2, ',', '.') . "€\n\n";

    $text .= "Mit freundlichen Grüßen\n";
    $text .= "{$settings['company_name']}\n";
    $text .= "{$settings['phone']}\n";
    $text .= "{$settings['email']}\n";

    return $text;
}

/**
 * Fallback function using PHP mail()
 */
function sendConfirmationEmailFallback($to, $bookingNumber, $bookingData)
{
    try {
        $db = Database::getInstance();
        $settings = $db->fetch("SELECT * FROM settings WHERE id = 1");

        $subject = "Terminbestätigung - Buchungsnummer: $bookingNumber";
        $message = generateEmailHTML($bookingNumber, $bookingData, $settings);

        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: {$settings['company_name']} <{$settings['email']}>\r\n";
        $headers .= "Reply-To: {$settings['email']}\r\n";

        return mail($to, $subject, $message, $headers);
    } catch (Exception $e) {
        error_log("Fallback mail error: " . $e->getMessage());
        return false;
    }
}
