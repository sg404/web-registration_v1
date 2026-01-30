<?php
header('Content-Type: application/json');

require_once '../dbConnection.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Get all available car passes from vehiclepass table and ensure not currently assigned to any vehicle
    $query = "SELECT vp.passID FROM vehiclepass vp WHERE vp.status = 'available' AND NOT EXISTS (SELECT 1 FROM vehicle v WHERE v.carpassid = vp.passID AND v.carpassid <> '') ORDER BY vp.passID";
    $result = $conn->query($query);

    $availableCarpass = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $availableCarpass[] = $row['passID'];
        }
    }

    echo json_encode(['success' => true, 'data' => $availableCarpass]);
    $db->closeConnection();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>