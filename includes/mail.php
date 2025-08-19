<?php

/**
 * Send confirmation email to customer
 */
function sendConfirmationEmail($to, $bookingNumber, $bookingData)
{
    try {
        // Get SMTP settings from database
        $db = Database::getInstance();
        $settings = $db->fetch("SELECT * FROM settings WHERE id = 1");

        $subject = "Terminbestätigung - Buchungsnummer: $bookingNumber";

        // Build services list
        $servicesList = '';
        foreach ($bookingData['services'] as $service) {
            $servicesList .= "<li>{$service['name']} - " . number_format($service['price'], 2) . "€ ({$service['duration_minutes']} Min.)</li>";
        }

        // Format date
        $appointmentDate = date('d.m.Y', strtotime($bookingData['date']));
        $dayName = getDayNameGerman($bookingData['date']);

        $message = "
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
                <strong>Gesamtdauer:</strong> " . array_sum(array_column($bookingData['services'], 'duration_minutes')) . " Minuten
            </div>";

        if ($bookingData['travelCost'] > 0) {
            $message .= "
            <div class='info-box'>
                <strong>📍 Anfahrt:</strong><br>
                Entfernung: " . number_format($bookingData['distance'], 1) . " km<br>
                Anfahrtskosten: " . number_format($bookingData['travelCost'], 2) . "€
            </div>";
        }

        $message .= "
            <div class='total-box'>
                <div>Gesamtbetrag</div>
                <div class='amount'>" . number_format($bookingData['total'], 2) . "€</div>
            </div>

            <div style='text-align: center; margin: 30px 0;'>
                <a href='#' class='button'>Jetzt online bezahlen</a>
                <p style='font-size: 14px; color: #666;'>
                    Wir akzeptieren PayPal und alle gängigen Kreditkarten.<br>
                    <strong>Bitte beachten:</strong> Wir akzeptieren keine Barzahlung.
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

        // Set headers for HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: {$settings['company_name']} <{$settings['email']}>" . "\r\n";
        $headers .= "Reply-To: {$settings['email']}" . "\r\n";

        // Send email
        // In production, use PHPMailer or SwiftMailer for SMTP
        if (!empty($settings['smtp_host'])) {
            // SMTP configuration would go here
            // For now, use PHP's mail() function
            return @mail($to, $subject, $message, $headers);
        } else {
            // Fallback to PHP mail()
            return @mail($to, $subject, $message, $headers);
        }
    } catch (Exception $e) {
        error_log('Email sending failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send appointment reminder
 */
function sendReminderEmail($appointmentId)
{
    // Implementation for reminder emails
    // This could be called by a cron job
}

/**
 * Send cancellation email
 */
function sendCancellationEmail($appointmentId)
{
    // Implementation for cancellation emails
}
