<?php

/**
 * Sanitize input data
 */
function sanitize($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Validate email
 */
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone number (German format)
 */
function validatePhone($phone)
{
    // Remove spaces and dashes
    $phone = str_replace([' ', '-', '/', '(', ')'], '', $phone);
    // Check if it's a valid German phone number
    return preg_match('/^(\+49|0)[1-9][0-9]{1,14}$/', $phone);
}

/**
 * Format price
 */
function formatPrice($price)
{
    return number_format($price, 2, ',', '.') . ' €';
}

/**
 * Format date to German format
 */
function formatDate($date)
{
    return date('d.m.Y', strtotime($date));
}

/**
 * Format time
 */
function formatTime($time)
{
    return date('H:i', strtotime($time)) . ' Uhr';
}

/**
 * Get day name in German
 */
function getDayNameGerman($date)
{
    $days = [
        'Sunday' => 'Sonntag',
        'Monday' => 'Montag',
        'Tuesday' => 'Dienstag',
        'Wednesday' => 'Mittwoch',
        'Thursday' => 'Donnerstag',
        'Friday' => 'Freitag',
        'Saturday' => 'Samstag'
    ];

    $dayName = date('l', strtotime($date));
    return $days[$dayName];
}

/**
 * Check if date is working day
 */
function isWorkingDay($date)
{
    $dayOfWeek = date('w', strtotime($date));
    // Sunday = 0, Saturday = 6
    return $dayOfWeek != 0; // All days except Sunday
}

/**
 * Calculate end time based on start time and duration
 */
function calculateEndTime($startTime, $durationMinutes)
{
    $start = strtotime($startTime);
    $end = $start + ($durationMinutes * 60);
    return date('H:i', $end);
}

/**
 * Check if time slot is available
 */
function isTimeSlotAvailable($db, $date, $time, $duration)
{
    // Check appointments
    $appointments = $db->fetchAll("
        SELECT appointment_time, total_duration 
        FROM appointments 
        WHERE appointment_date = ? 
        AND status != 'cancelled'
    ", [$date]);

    $requestedStart = strtotime($time);
    $requestedEnd = $requestedStart + ($duration * 60);

    foreach ($appointments as $apt) {
        $aptStart = strtotime($apt['appointment_time']);
        $aptEnd = $aptStart + ($apt['total_duration'] * 60);

        // Check for overlap
        if (($requestedStart >= $aptStart && $requestedStart < $aptEnd) ||
            ($requestedEnd > $aptStart && $requestedEnd <= $aptEnd) ||
            ($requestedStart <= $aptStart && $requestedEnd >= $aptEnd)
        ) {
            return false;
        }
    }

    // Check blocked times
    $blocked = $db->fetchAll("
        SELECT start_time, end_time, is_full_day 
        FROM blocked_times 
        WHERE date = ?
    ", [$date]);

    foreach ($blocked as $block) {
        if ($block['is_full_day']) {
            return false;
        }

        $blockStart = strtotime($block['start_time']);
        $blockEnd = strtotime($block['end_time']);

        // Check for overlap
        if (($requestedStart >= $blockStart && $requestedStart < $blockEnd) ||
            ($requestedEnd > $blockStart && $requestedEnd <= $blockEnd) ||
            ($requestedStart <= $blockStart && $requestedEnd >= $blockEnd)
        ) {
            return false;
        }
    }

    return true;
}

/**
 * Generate random booking reference
 */
function generateBookingReference($year, $id)
{
    return $year . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
}

/**
 * Calculate distance using Google Maps API
 */
function calculateDistanceGoogleMaps($origin, $destination, $apiKey)
{
    $origin = urlencode($origin);
    $destination = urlencode($destination);

    $url = "https://maps.googleapis.com/maps/api/distancematrix/json?"
        . "origins=$origin"
        . "&destinations=$destination"
        . "&key=$apiKey"
        . "&units=metric"
        . "&language=de";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (
        $data['status'] == 'OK' &&
        isset($data['rows'][0]['elements'][0]['status']) &&
        $data['rows'][0]['elements'][0]['status'] == 'OK'
    ) {

        $distance = $data['rows'][0]['elements'][0]['distance']['value'] / 1000; // Convert to km
        $duration = $data['rows'][0]['elements'][0]['duration']['value'] / 60; // Convert to minutes

        return [
            'distance' => round($distance, 1),
            'duration' => round($duration)
        ];
    }

    return false;
}
