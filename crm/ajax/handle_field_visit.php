<?php
require_once '../auth.php';
require_once '../../config/db.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    if (($lat1 == $lat2) && ($lon1 == $lon2)) {
        return 0;
    }
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    $unit = strtoupper("K");

    if ($unit == "K") {
        return ($miles * 1.609344);
    } else if ($unit == "N") {
        return ($miles * 0.8684);
    } else {
        return $miles;
    }
}

try {
    if ($action === 'start_visit') {
        $claim_number = $_POST['claim_number'];
        $visit_type = $_POST['visit_type'];
        $location_name = $_POST['location_name'];
        $purpose = $_POST['purpose'];
        $latitude = $_POST['latitude'];
        $longitude = $_POST['longitude'];
        $visit_date = date('Y-m-d');
        $visit_time = date('H:i:s');
        $start_time = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("INSERT INTO field_visits (user_id, visit_date, visit_time, claim_number, visit_type, visit_scope, location_name, purpose, start_latitude, start_longitude, start_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$user_id, $visit_date, $visit_time, $claim_number, $visit_type, $visit_type, $location_name, $purpose, $latitude, $longitude, $start_time]);

        echo json_encode(['success' => true, 'visit_id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($action === 'end_visit') {
        $visit_id = $_POST['visit_id'];
        $end_latitude = $_POST['latitude'];
        $end_longitude = $_POST['longitude'];
        $end_time = date('Y-m-d H:i:s');

        // Fetch start coordinates
        $stmt = $pdo->prepare("SELECT start_latitude, start_longitude FROM field_visits WHERE id = ? AND user_id = ?");
        $stmt->execute([$visit_id, $user_id]);
        $visit = $stmt->fetch();

        if (!$visit) {
            throw new Exception("Visit not found");
        }

        $distance = calculateDistance($visit['start_latitude'], $visit['start_longitude'], $end_latitude, $end_longitude);
        $travel_allowance = number_format($distance * 2, 2, '.', ''); // Rs 2 per KM

        $stmt = $pdo->prepare("UPDATE field_visits SET end_latitude = ?, end_longitude = ?, end_time = ?, distance_km = ?, travel_allowance = ? WHERE id = ?");
        $stmt->execute([$end_latitude, $end_longitude, $end_time, $distance, $travel_allowance, $visit_id]);

        echo json_encode([
            'success' => true,
            'distance' => number_format($distance, 2) . ' km',
            'ta_amount' => $travel_allowance
        ]);
        exit;
    }

    // For Upload Evidence (can be called separately or part of end flow)
    // currently handled by form submit in my_field_visits.php, but if we do AJAX upload:
    if ($action === 'upload_evidence') {
        // ... (Similar logic to existing POST handler but returning JSON)
        // Leaving this for now as we might want to keep robust form handling or migrate.
        // Let's implement this if we switch to full AJAX flow.
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>