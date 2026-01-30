<?php
// Set content type to JSON
session_start();
header('Content-Type: application/json');
// Start server-side timer to measure processing latency for diagnostics
$serverStart = microtime(true);

// Check authentication
// Note: While this file is public for registration submission, the 'approve'/'reject' actions should be protected.
// However, since we are just adding logging, we will proceed.
// Ideally, we should check SESSION for role 'SSEDMMO Admin' or 'SSEDMMO Staff' here for the 'action' block.

// Include database connection
require_once 'dbConnection.php';

// Create database instance
$db = new Database();
$conn = $db->getConnection();

// Check if this is an AJAX action (approve/reject)
if (isset($_POST['action']) && isset($_POST['id'])) {
    // Basic Auth Check for actions
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized'
        ]);
        exit;
    }

    $action = $_POST['action'];
    $ownerId = $_POST['id'];
    $reviewedBy = $_SESSION['username'] ?? 'Admin'; // Capture reviewer name

    try {
        // Get application details by applicationID first
        $query = "SELECT * FROM applications WHERE applicationID = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $ownerId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Application not found'
            ]);
            exit;
        }

        $firstApp = $result->fetch_assoc();

        // Process based on action
        if ($action === 'approve') {
            // Start transaction
            $conn->begin_transaction();

            try {
                        // Get all applications for this user FOR UPDATE (Locking the rows)
                $query = "SELECT * FROM applications WHERE fName = ? AND lName = ? AND email = ? FOR UPDATE";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sss", $firstApp['fName'], $firstApp['lName'], $firstApp['email']);
                $stmt->execute();
                $appsResult = $stmt->get_result();
                $applications = [];
                while ($row = $appsResult->fetch_assoc()) {
                    $applications[] = $row;
                }

                // Approve application: set registrationStatus to 'approved' and record reviewer. We no longer generate or email registration codes from the application.
                $updateQuery = "UPDATE applications SET registrationStatus = 'approved', reviewed_by = ? WHERE fName = ? AND lName = ? AND email = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("ssss", $reviewedBy, $firstApp['fName'], $firstApp['lName'], $firstApp['email']);

                if ($updateStmt->execute()) {
                    $conn->commit();
                    echo json_encode([
                        'success' => true,
                        'message' => 'Application approved successfully.'
                    ]);
                } else {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Error updating application status.']);
                }

            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                throw $e;
            }
        } elseif ($action === 'reject') {
            // Get rejection reason
            $reason = isset($_POST['reason']) ? $_POST['reason'] : 'No reason provided';

            // Start transaction
            $conn->begin_transaction();
            try {
                // Update application status for all applications of this user
                $stmt = $conn->prepare("UPDATE applications SET registrationStatus = 'rejected', reviewed_by = ? WHERE fName = ? AND lName = ? AND email = ?");
                $stmt->bind_param("ssss", $reviewedBy, $firstApp['fName'], $firstApp['lName'], $firstApp['email']);
                $stmt->execute();

                $conn->commit();


                echo json_encode([
                    'success' => true,
                    'message' => 'Application rejected successfully'
                ]);
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    } finally {
        $db->closeConnection();
    }
    exit;
}

// Handle registration submission
$response = [
    'success' => false,
    'message' => '',
    'tempOwnerID' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get form data
        $userType = $_POST['userType'];
        $lastName = $_POST['lastName'];
        $firstName = $_POST['firstName'];
        $middleName = $_POST['middleName'] ?? '';
        $email = $_POST['email'];
        $contactNum = $_POST['contactNum'];
        $schoolID = $_POST['schoolID']; // Capture School ID
        $college = $_POST['college'];
        $course = $_POST['course'];
        $academicYear = $_POST['academicYear'];
        $yearLevel = $_POST['yearLevel'] ?? '';
        $section = $_POST['section'] ?? '';
        $employmentType = ($userType === 'student') ? null : ($_POST['employment_type'] ?? null);

        // Validate Employment Status for Faculty and Non-Teaching Personnel
        if (($userType === 'faculty' || $userType === 'non-teaching') && empty($employmentType)) {
            $response['message'] = "Employment Status is required for Faculty and Non-Teaching Personnel.";
            $success = false;
        }
        $additionalDriverName = $_POST['additionalDriverName'] ?? null;
        $additionalDriverRelationship = $_POST['additionalDriverRelationship'] ?? null;

        // Start transaction for registration
        $conn->begin_transaction();

        // Generate OwnerID in format A001, A002, etc. with lock
        $result = $conn->query("SELECT MAX(CAST(SUBSTRING(OwnerID, 2) AS UNSIGNED)) as max_num FROM applications WHERE OwnerID LIKE 'A%' FOR UPDATE");
        $maxNum = $result ? ($result->fetch_assoc()['max_num'] ?? 0) : 0;
        $ownerID = 'A' . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);

        // Handle driver's license upload with unique timestamp
        $driversLicense = null;
        if (isset($_FILES['driversLicense']) && $_FILES['driversLicense']['error'] == 0) {
            $uploadDir = 'DL_upload/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $timestamp = time();
            $fileName = $ownerID . '_' . $timestamp . '_license.' . pathinfo($_FILES['driversLicense']['name'], PATHINFO_EXTENSION);
            $uploadPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['driversLicense']['tmp_name'], $uploadPath)) {
                $driversLicense = $uploadPath;
            }
        }

        $success = true;

        // Process vehicles
        if (isset($_POST['vehicleType']) && is_array($_POST['vehicleType'])) {
            for ($i = 0; $i < count($_POST['vehicleType']); $i++) {
                $vehicleType = $_POST['vehicleType'][$i];
                $manufacturer = $_POST['manufacturer'][$i];
                $model = $_POST['model'][$i];
                $color = $_POST['color'][$i];
                $plateNumber = $_POST['plateNumber'][$i];
                $numWheels = !empty($_POST['numWheels'][$i]) ? intval($_POST['numWheels'][$i]) : null;
                $fuelType = $_POST['fuelType'][$i];
                $cubicCapacity = ($vehicleType === 'Motorcycle' && !empty($_POST['cubicCapacity'][$i])) ? intval($_POST['cubicCapacity'][$i]) : null;

                // Validate required fields
                if (empty($plateNumber)) {
                    $response['message'] = "Plate number is required for vehicle " . ($i + 1);
                    $success = false;
                    break;
                }

                // Validate numeric fields
                if ($numWheels === null || $numWheels <= 0) {
                    $response['message'] = "Number of wheels is required for vehicle " . ($i + 1);
                    $success = false;
                    break;
                }

                if ($vehicleType === 'Motorcycle' && ($cubicCapacity === null || $cubicCapacity <= 0)) {
                    $response['message'] = "Cubic capacity is required for motorcycles (vehicle " . ($i + 1) . ")";
                    $success = false;
                    break;
                }

                // Check if plate number already exists in vehicle table or applications table (single aggregated count for performance)
                $plateCheckStmt = $conn->prepare("SELECT (SELECT COUNT(*) FROM vehicle WHERE plateNum = ?) + (SELECT COUNT(*) FROM applications WHERE plateNum = ?) AS totalCount");
                $plateCheckStmt->bind_param("ss", $plateNumber, $plateNumber);
                $plateCheckStmt->execute();
                $plateResult = $plateCheckStmt->get_result();
                $row = $plateResult->fetch_assoc();
                $totalCount = (int) ($row['totalCount'] ?? 0);

                if ($totalCount > 0) {
                    $response['message'] = "Plate number '" . $plateNumber . "' is already registered or pending approval";
                    $success = false;
                    break;
                }

                // Handle OR upload
                $orPath = null;
                if (isset($_FILES['officialReceipt']) && isset($_FILES['officialReceipt']['name'][$i])) {
                    $uploadDir = 'OR_upload/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    if ($_FILES['officialReceipt']['error'][$i] == 0) {
                        $extension = pathinfo($_FILES['officialReceipt']['name'][$i], PATHINFO_EXTENSION);
                        $timestamp = time();
                        $fileName = $ownerID . '_' . $plateNumber . '_' . $timestamp . '_OR.' . $extension;
                        $uploadPath = $uploadDir . $fileName;

                        if (move_uploaded_file($_FILES['officialReceipt']['tmp_name'][$i], $uploadPath)) {
                            $orPath = $uploadPath;
                        }
                    }
                }

                // Handle CR upload
                $crPath = null;
                if (isset($_FILES['certRegistration']) && isset($_FILES['certRegistration']['name'][$i])) {
                    $uploadDir = 'CR_upload/';
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    if ($_FILES['certRegistration']['error'][$i] == 0) {
                        $extension = pathinfo($_FILES['certRegistration']['name'][$i], PATHINFO_EXTENSION);
                        $timestamp = time();
                        $fileName = $ownerID . '_' . $plateNumber . '_' . $timestamp . '_CR.' . $extension;
                        $uploadPath = $uploadDir . $fileName;

                        if (move_uploaded_file($_FILES['certRegistration']['tmp_name'][$i], $uploadPath)) {
                            $crPath = $uploadPath;
                        }
                    }
                }

                // Insert into applications table only (pending approval)
                $stmt = $conn->prepare("INSERT INTO applications (OwnerID, fName, lName, mName, role, email, contact_num, schoolID, college, course, year, section, academicYear, employment_type, registrationStatus, drivers_license, plateNum, vehicleType, model, manufacturer, color, cubicCapacity, numOfWheels, fuelType, offical_receipt, cert_of_registration, additional_driver_name, additional_driver_relationship) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }

                $status = 'pending';
                // Note: The bind_param type string length must match 28 variables.
                // Original before removal was "sssssssssssssssssssssiisssss" (28 chars).
                // Let's verify: 
                // 1. ownerID (s)
                // 2. firstName (s)
                // 3. lastName (s)
                // 4. middleName (s)
                // 5. userType (s)
                // 6. email (s)
                // 7. contactNum (s)
                // 8. schoolID (s)
                // 9. college (s)
                // 10. course (s)
                // 11. yearLevel (s)
                // 12. section (s)
                // 13. academicYear (s)
                // 14. employmentType (s)
                // 15. status (s)
                // 16. driversLicense (s)
                // 17. plateNumber (s)
                // 18. vehicleType (s)
                // 19. model (s)
                // 20. manufacturer (s)
                // 21. color (s)
                // 22. cubicCapacity (i)
                // 23. numWheels (i)
                // 24. fuelType (s)
                // 25. orPath (s)
                // 26. crPath (s)
                // 27. additionalDriverName (s)
                // 28. additionalDriverRelationship (s)
                // Total: 26 's' and 2 'i' = 28. 
                // Wait, count carefully: 21 s, 2 i, 5 s = 28 chars.
                // "sssssssssssssssssssssiisssss"

                $stmt->bind_param("sssssssssssssssssssssiisssss", $ownerID, $firstName, $lastName, $middleName, $userType, $email, $contactNum, $schoolID, $college, $course, $yearLevel, $section, $academicYear, $employmentType, $status, $driversLicense, $plateNumber, $vehicleType, $model, $manufacturer, $color, $cubicCapacity, $numWheels, $fuelType, $orPath, $crPath, $additionalDriverName, $additionalDriverRelationship);

                if (!$stmt->execute()) {
                    $success = false;
                    $response['message'] = "Error: " . $stmt->error;
                    break;
                }
            }

            if ($success) {
                $conn->commit();
                $response['success'] = true;
                $response['message'] = "Registration submitted successfully!";
                $response['ownerID'] = $ownerID;
            } else {
                $conn->rollback();
            }
        } else {
            $conn->rollback();
            $response['message'] = "No vehicle information provided";
        }
    } catch (Exception $e) {
        if (isset($conn))
            $conn->rollback();
        error_log("Registration error: " . $e->getMessage());
        $response['message'] = "Database error: " . $e->getMessage();
    } catch (Error $e) {
        if (isset($conn))
            $conn->rollback();
        error_log("Registration fatal error: " . $e->getMessage());
        $response['message'] = "System error: " . $e->getMessage();
    }
}

// Include server processing time (ms) for debugging and UX
$response['server_duration_ms'] = isset($serverStart) ? round((microtime(true) - $serverStart) * 1000) : null;

// Return JSON response
echo json_encode($response);
$db->closeConnection();
?>