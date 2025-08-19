<?php

header('Content-Type: application/json');
require_once '../includes/config.php';
require_once '../includes/db.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $date = $data['date'] ?? '';
    $requestedDuration = intval($data['duration'] ?? 0); // Duration in minutes

    if (empty($date)) {
        throw new Exception('Kein Datum angegeben');
    }

    $db = Database::getInstance();

    // Get settings for working hours
    $settings = $db->fetch("SELECT * FROM settings WHERE id = 1");
    $slotDuration = intval($settings['time_slot_duration'] ?? 30);

    // Get day of week
    $dayOfWeek = date('w', strtotime($date));

    // Check if Sunday (closed)
    if ($dayOfWeek == 0) {
        echo json_encode(['blocked' => 'all', 'message' => 'Sonntags geschlossen']);
        exit;
    }

    // Determine working hours based on day
    if ($dayOfWeek == 6) {
        // Saturday
        $workStart = $settings['working_hours_saturday_start'] ?? '09:00';
        $workEnd = $settings['working_hours_saturday_end'] ?? '14:00';
    } else {
        // Weekday
        $workStart = $settings['working_hours_weekday_start'] ?? '16:30';
        $workEnd = $settings['working_hours_weekday_end'] ?? '21:00';
    }

    // Convert times to minutes for easier calculation
    function timeToMinutes($time)
    {
        list($hours, $minutes) = explode(':', $time);
        return $hours * 60 + $minutes;
    }

    function minutesToTime($minutes)
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }

    $workStartMin = timeToMinutes($workStart);
    $workEndMin = timeToMinutes($workEnd);

    // Get all ACTIVE appointments for this date (excluding cancelled)
    $appointments = $db->fetchAll("
        SELECT appointment_time, total_duration 
        FROM appointments 
        WHERE appointment_date = ? 
        AND status NOT IN ('cancelled', 'storniert')
        ORDER BY appointment_time ASC
    ", [$date]);

    // Get blocked times for this date
    $blocked = $db->fetchAll("
        SELECT start_time, end_time, is_full_day 
        FROM blocked_times 
        WHERE date = ?
    ", [$date]);

    // Check if entire day is blocked
    foreach ($blocked as $block) {
        if ($block['is_full_day']) {
            echo json_encode(['blocked' => 'all', 'message' => 'Tag ist blockiert']);
            exit;
        }
    }

    // Build array of busy time ranges
    $busyRanges = [];

    // Add appointments to busy ranges
    foreach ($appointments as $apt) {
        $startMin = timeToMinutes($apt['appointment_time']);
        $endMin = $startMin + intval($apt['total_duration']);
        $busyRanges[] = ['start' => $startMin, 'end' => $endMin];
    }

    // Add manual blocks to busy ranges
    foreach ($blocked as $block) {
        if (!$block['is_full_day']) {
            $startMin = timeToMinutes($block['start_time']);
            $endMin = timeToMinutes($block['end_time']);
            $busyRanges[] = ['start' => $startMin, 'end' => $endMin];
        }
    }

    // Sort busy ranges by start time
    usort($busyRanges, function ($a, $b) {
        return $a['start'] - $b['start'];
    });

    // Merge overlapping ranges
    $mergedRanges = [];
    foreach ($busyRanges as $range) {
        if (empty($mergedRanges)) {
            $mergedRanges[] = $range;
        } else {
            $lastIndex = count($mergedRanges) - 1;
            if ($range['start'] <= $mergedRanges[$lastIndex]['end']) {
                // Overlapping, merge them
                $mergedRanges[$lastIndex]['end'] = max($mergedRanges[$lastIndex]['end'], $range['end']);
            } else {
                // Not overlapping, add as new range
                $mergedRanges[] = $range;
            }
        }
    }

    // Find available time slots
    $availableSlots = [];
    $currentTime = $workStartMin;

    // Check if there's enough time at the beginning
    if (!empty($mergedRanges)) {
        $firstBusyStart = $mergedRanges[0]['start'];
        if ($firstBusyStart > $workStartMin) {
            // There's free time at the beginning
            $freeTime = $firstBusyStart - $workStartMin;
            if ($freeTime >= $requestedDuration) {
                // Generate slots for this free period
                $slotTime = $workStartMin;
                while ($slotTime + $requestedDuration <= $firstBusyStart) {
                    $availableSlots[] = minutesToTime($slotTime);
                    $slotTime += $slotDuration;
                }
            }
        }

        // Check gaps between appointments
        for ($i = 0; $i < count($mergedRanges) - 1; $i++) {
            $gapStart = $mergedRanges[$i]['end'];
            $gapEnd = $mergedRanges[$i + 1]['start'];
            $gapDuration = $gapEnd - $gapStart;

            if ($gapDuration >= $requestedDuration) {
                // This gap is big enough
                $slotTime = $gapStart;
                while ($slotTime + $requestedDuration <= $gapEnd) {
                    $availableSlots[] = minutesToTime($slotTime);
                    $slotTime += $slotDuration;
                }
            }
        }

        // Check if there's enough time at the end
        $lastBusyEnd = $mergedRanges[count($mergedRanges) - 1]['end'];
        if ($lastBusyEnd < $workEndMin) {
            $freeTime = $workEndMin - $lastBusyEnd;
            if ($freeTime >= $requestedDuration) {
                // Generate slots for this free period
                $slotTime = $lastBusyEnd;
                while ($slotTime + $requestedDuration <= $workEndMin) {
                    $availableSlots[] = minutesToTime($slotTime);
                    $slotTime += $slotDuration;
                }
            }
        }
    } else {
        // No appointments at all, entire day is free
        $slotTime = $workStartMin;
        while ($slotTime + $requestedDuration <= $workEndMin) {
            $availableSlots[] = minutesToTime($slotTime);
            $slotTime += $slotDuration;
        }
    }

    // Return result
    if (empty($availableSlots)) {
        // No available slots for the requested duration
        echo json_encode([
            'available' => false,
            'slots' => [],
            'message' => 'An diesem Tag ist nicht genügend Zeit für die gewählten Services verfügbar. Bitte wählen Sie einen anderen Tag.',
            'busyRanges' => array_map(function ($r) {
                return minutesToTime($r['start']) . '-' . minutesToTime($r['end']);
            }, $mergedRanges)
        ]);
    } else {
        // Return all time slots, marking which ones are NOT available
        $allSlots = [];
        $slotTime = $workStartMin;
        while ($slotTime < $workEndMin) {
            $timeStr = minutesToTime($slotTime);
            $allSlots[] = [
                'time' => $timeStr,
                'available' => in_array($timeStr, $availableSlots)
            ];
            $slotTime += $slotDuration;
        }

        echo json_encode([
            'available' => true,
            'slots' => $allSlots,
            'message' => count($availableSlots) . ' Termine verfügbar'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'available' => false
    ]);
}
