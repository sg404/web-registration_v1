<?php
// Process RFID and Car Pass issuance via AJAX
session_start();
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check authorization
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['SSEDMMO Admin', 'SSEDMMO Staff'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check required parameters
if (!isset($_POST['plateNum'])) {
    echo json_encode(['success' => false, 'message' => 'Missing plate number']);
    exit;
}

$plateNum = $_POST['plateNum'];
$stickerID = $_POST['stickerID'] ?? '';
$carpassId = $_POST['carpassId'] ?? '';

// Require both RFID and carpass
if (empty($stickerID) || empty($carpassId)) {
    echo json_encode(['success' => false, 'message' => 'Both RFID Tag and Car Pass ID must be selected']);
    exit;
}

// Include database connection
require_once '../dbConnection.php';

// Create database instance
$db = new Database();
$conn = $db->getConnection();

try {
    $conn->begin_transaction();

    // Fetch the latest approved application row to be issued (matching plate number)
    $appQuery = "SELECT * FROM applications WHERE plateNum = ? AND registrationStatus = 'approved' FOR UPDATE";
    $appStmt = $conn->prepare($appQuery);
    $appStmt->bind_param("s", $plateNum);
    $appStmt->execute();
    $appResult = $appStmt->get_result();

    if ($appResult->num_rows === 0) {
        throw new Exception('No matching approved application found');
    }

    $application = $appResult->fetch_assoc();

    // Use existing Owner if present; otherwise create Owner record
    if (!empty($application['OwnerID'])) {
        $ownerID = $application['OwnerID'];
    } else {
        $result2 = $conn->query("SELECT MAX(CAST(SUBSTRING(OwnerID, 2) AS UNSIGNED)) as max_id FROM vehicleowner WHERE OwnerID LIKE 'O%' FOR UPDATE");
        $row = $result2->fetch_assoc();
        $nextId = ($row['max_id'] ?? 0) + 1;
        $ownerID = 'O' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
        $approvalTime = date('Y-m-d H:i:s');

        // Insert owner (registration_code is no longer recorded from the application)
        $stmt = $conn->prepare("INSERT INTO vehicleowner (OwnerID, fName, lName, mName, role, email, contact_num, schoolID, college, course, year, section, academicYear, employment_type, registrationStatus, drivers_license, additional_driver_name, additional_driver_relationship, approvalTimestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, ?, ?)");
        $stmt->bind_param(
            "sssssssssssssssssss",
            $ownerID,
            $application['fName'],
            $application['lName'],
            $application['mName'],
            $application['role'],
            $application['email'],
            $application['contact_num'],
            $application['schoolID'],
            $application['college'],
            $application['course'],
            $application['year'],
            $application['section'],
            $application['academicYear'],
            $application['employment_type'],
            $application['drivers_license'],
            $application['additional_driver_name'],
            $application['additional_driver_relationship'],
            $approvalTime
        );
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert vehicle owner');
        }
    }

    // If a vehicle record already exists for this plate, update its OwnerID if missing; otherwise insert a vehicle record
    $checkVehicleStmt = $conn->prepare("SELECT * FROM vehicle WHERE plateNum = ? FOR UPDATE");
    $checkVehicleStmt->bind_param("s", $application['plateNum']);
    $checkVehicleStmt->execute();
    $vehicleResult = $checkVehicleStmt->get_result();

    if ($vehicleResult->num_rows > 0) {
        // Vehicle exists, ensure OwnerID is set correctly
        $updateVehicleStmt = $conn->prepare("UPDATE vehicle SET OwnerID = ? WHERE plateNum = ?");
        $updateVehicleStmt->bind_param("ss", $ownerID, $application['plateNum']);
        if (!$updateVehicleStmt->execute()) {
            throw new Exception('Failed to update existing vehicle with OwnerID');
        }
    } else {
        // Insert vehicle record
        $stmt = $conn->prepare("INSERT INTO vehicle (plateNum, OwnerID, vehicleType, model, manufacturer, color, cubicCapacity, numOfWheels, fuelType, offical_receipt, cert_of_registration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "sssssssisss",
            $application['plateNum'],
            $ownerID,
            $application['vehicleType'],
            $application['model'],
            $application['manufacturer'],
            $application['color'],
            $application['cubicCapacity'],
            $application['numOfWheels'],
            $application['fuelType'],
            $application['offical_receipt'],
            $application['cert_of_registration']
        );
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert vehicle');
        }
    }

    // Update application to reflect issuance (OwnerID may be existing or newly created)
    $updateApp = $conn->prepare("UPDATE applications SET registrationStatus = 'issued', OwnerID = ? WHERE applicationID = ?");
    $updateApp->bind_param("si", $ownerID, $application['applicationID']);
    if (!$updateApp->execute()) {
        throw new Exception('Failed to update application after issuance');
    }

    // Handle RFID tag assignment (same checks as before)
    if (!empty($stickerID)) {
        $checkRfidQuery = "SELECT status FROM rfidtag WHERE stickerID = ?";
        $checkRfidStmt = $conn->prepare($checkRfidQuery);
        $checkRfidStmt->bind_param("s", $stickerID);
        $checkRfidStmt->execute();
        $rfidResult = $checkRfidStmt->get_result();

        if ($rfidResult->num_rows === 0) {
            throw new Exception("Invalid RFID tag ID");
        }

        $rfidData = $rfidResult->fetch_assoc();
        if ($rfidData['status'] === 'unavailable') {
            throw new Exception("RFID tag $stickerID is already assigned");
        }

        // Check if sticker ID already assigned to another vehicle
        $checkQuery = "SELECT plateNum FROM vehicle WHERE stickerID = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("s", $stickerID);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            throw new Exception('This sticker ID is already assigned to another vehicle');
        }

        // Update vehicle with sticker ID
        $updateQuery = "UPDATE vehicle SET stickerID = ? WHERE plateNum = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("ss", $stickerID, $plateNum);
        $updateStmt->execute();

        // Update rfidtag status to unavailable
        $username = $_SESSION['username'] ?? 'admin';
        $updateRfidQuery = "UPDATE rfidtag SET status = 'unavailable', issuedBy = ? WHERE stickerID = ?";
        $updateRfidStmt = $conn->prepare($updateRfidQuery);
        $updateRfidStmt->bind_param("ss", $username, $stickerID);

        if (!$updateRfidStmt->execute()) {
            throw new Exception("Failed to update RFID tag status");
        }
    }

    // Handle Car Pass if provided
    if (!empty($carpassId)) {
        // Check if car pass is available in vehiclepass table
        $checkPassQuery = "SELECT status FROM vehiclepass WHERE passID = ?";
        $checkPassStmt = $conn->prepare($checkPassQuery);
        $checkPassStmt->bind_param("s", $carpassId);
        $checkPassStmt->execute();
        $passResult = $checkPassStmt->get_result();

        if ($passResult->num_rows === 0) {
            throw new Exception("Invalid car pass ID");
        }

        $passData = $passResult->fetch_assoc();
        if ($passData['status'] === 'unavailable') {
            throw new Exception("Car pass $carpassId is already assigned");
        }

        // Check if car pass ID already assigned to another vehicle
        $checkQuery = "SELECT plateNum FROM vehicle WHERE carpassid = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("s", $carpassId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            throw new Exception("Car pass ID is already assigned to another vehicle");
        }

        // Update vehicle with car pass ID
        $updateQuery = "UPDATE vehicle SET carpassid = ? WHERE plateNum = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("ss", $carpassId, $plateNum);
        $updateStmt->execute();

        // Update vehiclepass status to unavailable
        $username = $_SESSION['username'] ?? 'admin';
        $updatePassQuery = "UPDATE vehiclepass SET status = 'unavailable', issuedBy = ? WHERE passID = ?";
        $updatePassStmt = $conn->prepare($updatePassQuery);
        $updatePassStmt->bind_param("ss", $username, $carpassId);

        if (!$updatePassStmt->execute()) {
            throw new Exception("Failed to update car pass status");
        }
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Successfully issued']);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
} finally {
    $db->closeConnection();
}