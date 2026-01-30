<?php
header('Content-Type: application/json');
require_once '../dbConnection.php';

$db = new Database();
$conn = $db->getConnection();

if (isset($_GET['plateNum'])) {
    $plateNum = $_GET['plateNum'];

    $query = "SELECT v.*, vo.fName, vo.lName, vo.email, r.issuedBy AS rfidIssuedBy, r.status AS rfidStatus 
              FROM vehicle v 
              LEFT JOIN vehicleowner vo ON v.OwnerID = vo.OwnerID 
              LEFT JOIN rfidtag r ON v.stickerID = r.stickerID
              WHERE v.plateNum = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $plateNum);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $vehicle = $result->fetch_assoc();
        echo json_encode(['success' => true, 'vehicle' => $vehicle]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Vehicle not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Plate number required']);
}

$db->closeConnection();
?>