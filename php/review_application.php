<?php
// Check authentication
require_once 'auth_check.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Set page title and current page for navigation highlighting
$pageTitle = "Review Application";
$currentPage = "registration_applications";
$cssFiles = ["registration_applications.css", "review_application.css", "loading-animation.css"];
$jsFiles = ["review_application.js", "loading-animation.js"];

// Include database connection
require_once 'dbConnection.php';

// Create database instance
$db = new Database();
$conn = $db->getConnection();

// Check if owner ID is provided
if (!isset($_GET['id'])) {
    header("Location: registration_applications.php");
    exit();
}

$ownerId = $_GET['id'];

// Get application details by applicationID first, then get all applications for the same user
$query = "SELECT * FROM applications WHERE applicationID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $ownerId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: registration_applications.php");
    exit();
}

$firstApp = $result->fetch_assoc();

// Now get all applications for this user
$query = "SELECT * FROM applications WHERE fName = ? AND lName = ? AND email = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $firstApp['fName'], $firstApp['lName'], $firstApp['email']);
$stmt->execute();
$result = $stmt->get_result();

// Get the first row for applicant details
$applicant = $result->fetch_assoc();

// Reset result pointer
$result->data_seek(0);

// Get all vehicles
$vehicles = [];
while ($row = $result->fetch_assoc()) {
    $vehicles[] = $row;
}

function send_application_email($recipient, $subject, $body)
{
    $descriptorspec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];

    $process = proc_open("python ../python/send_email.py " . escapeshellarg($recipient) . " " . escapeshellarg($subject), $descriptorspec, $pipes);

    if (is_resource($process)) {
        fwrite($pipes[0], $body);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_close($process);
        return trim($output) === "SUCCESS";
    }
    return false;
}

// Process form submission for approval/rejection
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $reviewedBy = $_SESSION['username'] ?? 'Admin'; // Capture approver

        if ($action === 'approve') {
            $conn->begin_transaction();

            try {
                // Generate unique code in format A001, A002, etc.
                $result = $conn->query("SELECT MAX(CAST(SUBSTRING(OwnerID, 2) AS UNSIGNED)) as max_num FROM vehicleowner WHERE OwnerID LIKE 'A%'");
                $maxNum = $result ? $result->fetch_assoc()['max_num'] : 0;
                $uniqueCode = 'A' . str_pad($maxNum + 1, 3, '0', STR_PAD_LEFT);

                $approvalTime = date('Y-m-d H:i:s');
                // Include schoolID, employment_type, and additional driver info to keep vehicleowner in sync with application
                $stmt = $conn->prepare("INSERT INTO vehicleowner (OwnerID, schoolID, fName, lName, mName, role, employment_type, email, contact_num, college, course, year, section, academicYear, registrationStatus, approvalTimestamp, drivers_license, additional_driver_name, additional_driver_relationship) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, ?, ?)");
                $stmt->bind_param("sssssssssssssssssss", $uniqueCode, $applicant['schoolID'], $applicant['fName'], $applicant['lName'], $applicant['mName'], $applicant['role'], $applicant['employment_type'], $applicant['email'], $applicant['contact_num'], $applicant['college'], $applicant['course'], $applicant['year'], $applicant['section'], $applicant['academicYear'], $approvalTime, $applicant['drivers_license'], $applicant['additional_driver_name'], $applicant['additional_driver_relationship']);
                $stmt->execute();

                foreach ($vehicles as $vehicle) {
                    $stmt = $conn->prepare("INSERT INTO vehicle (plateNum, OwnerID, vehicleType, model, manufacturer, color, cubicCapacity, numOfWheels, fuelType, offical_receipt, cert_of_registration) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssssisss", $vehicle['plateNum'], $uniqueCode, $vehicle['vehicleType'], $vehicle['model'], $vehicle['manufacturer'], $vehicle['color'], $vehicle['cubicCapacity'], $vehicle['numOfWheels'], $vehicle['fuelType'], $vehicle['offical_receipt'], $vehicle['cert_of_registration']);
                    $stmt->execute();
                }

                // Update applications table with status AND reviewed_by
                $updateQuery = "UPDATE applications SET registrationStatus = ?, OwnerID = ?, reviewed_by = ? WHERE fName = ? AND lName = ? AND email = ?";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bind_param("ssssss", $status, $uniqueCode, $reviewedBy, $applicant['fName'], $applicant['lName'], $applicant['email']);
                $updateStmt->execute();

                $conn->commit();

                $subject = "Vehicle Registration Application Approved";
                $body = "Dear " . $applicant['fName'] . " " . $applicant['lName'] . ",\n\nCongratulations! Your vehicle registration application has been approved.\n\nYour unique registration code is: " . $uniqueCode . "\n\nYou have 48 hours to process your documents to the SSEDMMO office. Please bring a physical of your OR(Official Receipt), CR(Certificate of Registration), and Drivers License to fully verify your registration. \n\nPlease keep this code for your records.\n\nBest regards,\nISATU Vehicle Registration System";

                send_application_email($applicant['email'], $subject, $body);
                $applicant['registrationStatus'] = $status;
                $message = "Application approved and data transferred successfully. Email sent to applicant.";

            } catch (Exception $e) {
                $conn->rollback();
                $message = "Error approving application: " . $e->getMessage();
            }
        } else {
            $rejectionReason = $_POST['rejection_reason'] ?? 'No reason provided';

            // Update applications table with status AND reviewed_by for rejection too
            $updateQuery = "UPDATE applications SET registrationStatus = ?, reviewed_by = ? WHERE fName = ? AND lName = ? AND email = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("sssss", $status, $reviewedBy, $applicant['fName'], $applicant['lName'], $applicant['email']);

            if ($updateStmt->execute()) {
                $subject = "Vehicle Registration Application Declined";
                $body = "Dear " . $applicant['fName'] . " " . $applicant['lName'] . ",\n\nWe regret to inform you that your vehicle registration application has been declined.\n\nReason: " . $rejectionReason . "\n\nIf you have any questions, please contact the administration.\n\nBest regards,\nISATU Vehicle Registration System";

                send_application_email($applicant['email'], $subject, $body);
                $applicant['registrationStatus'] = $status;
                $message = "Application rejected successfully. Email sent to applicant.";
            } else {
                $message = "Error updating application status.";
            }
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<main class="main">
    <div class="review-header">
        <h2>Review Application</h2>
        <a href="registration_applications.php" class="back-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5M12 19l-7-7 7-7" />
            </svg>
            Back to Applications
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert <?php echo strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="review-container">
        <div class="review-section application-header">
            <div class="application-status">
                <h3>Application #<?php echo htmlspecialchars($firstApp['applicationID']); ?></h3>
                <span class="status-badge large <?php echo strtolower($applicant['registrationStatus']); ?>">
                    <?php if ($applicant['registrationStatus'] == 'pending'): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 6v6l4 2" />
                            <circle cx="12" cy="12" r="10" />
                        </svg>
                    <?php elseif ($applicant['registrationStatus'] == 'approved'): ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.801 10A10 10 0 1 1 17 3.335" />
                            <path d="m9 11 3 3L22 4" />
                        </svg>
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10" />
                            <path d="m15 9-6 6" />
                            <path d="m9 9 6 6" />
                        </svg>
                    <?php endif; ?>
                    <?php echo ucfirst($applicant['registrationStatus']); ?>
                </span>
            </div>
            <div class="application-date">
                Submitted:
                <?php echo isset($applicant['applicationDate']) && !empty($applicant['applicationDate']) ? date('F j, Y \a\t g:i A', strtotime($applicant['applicationDate'])) : 'Date not available'; ?>
            </div>
        </div>

        <<div class="review-section">
            <h3>Applicant Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Full Name:</span>
                    <span class="info-value">
                        <?php echo htmlspecialchars(($applicant['fName'] ?? '') . ' ' . ($applicant['mName'] ?? '') . ' ' . ($applicant['lName'] ?? '')); ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Role:</span>
                    <span class="info-value"><?php echo htmlspecialchars(ucfirst($applicant['role'] ?? '')); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($applicant['email'] ?? ''); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Contact Number:</span>
                    <span class="info-value"><?php echo htmlspecialchars($applicant['contact_num'] ?? ''); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">College:</span>
                    <span class="info-value"><?php echo htmlspecialchars($applicant['college'] ?? ''); ?></span>
                </div>

                <?php if ($applicant['role'] === 'student' && !empty($applicant['course'])): ?>
                    <div class="info-item">
                        <span class="info-label">Course:</span>
                        <span class="info-value"><?php echo htmlspecialchars($applicant['course']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($applicant['year'])): ?>
                    <div class="info-item">
                        <span class="info-label">Year Level:</span>
                        <span class="info-value"><?php echo htmlspecialchars($applicant['year']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($applicant['section'])): ?>
                    <div class="info-item">
                        <span class="info-label">Section:</span>
                        <span class="info-value"><?php echo htmlspecialchars($applicant['section']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($applicant['role'] === 'student' && !empty($applicant['academicYear'])): ?>
                    <div class="info-item">
                        <span class="info-label">Academic Year:</span>
                        <span class="info-value"><?php echo htmlspecialchars($applicant['academicYear']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (($applicant['role'] === 'faculty' || $applicant['role'] === 'non-teaching') && !empty($applicant['employment_type'])): ?>
                    <div class="info-item">
                        <span class="info-label">Employment Status:</span>
                        <span class="info-value">
                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $applicant['employment_type']))); ?>
                        </span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($applicant['additional_driver_name']) || !empty($applicant['additional_driver_relationship'])): ?>
                    <div class="info-item">
                        <span class="info-label">Additional Driver:</span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($applicant['additional_driver_name'] ?? ''); ?>
                            <?php if (!empty($applicant['additional_driver_relationship'])): ?>
                                <span class="muted">(<?php echo htmlspecialchars($applicant['additional_driver_relationship']); ?>)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

            <div class="document-section">
                <h4>Driver's License</h4>
                <div class="document-preview">
                    <?php if (!empty($applicant['drivers_license'])): ?>
                        <?php
                        $ext = pathinfo($applicant['drivers_license'], PATHINFO_EXTENSION);
                        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])):
                            ?>
                            <img src="<?php echo htmlspecialchars($applicant['drivers_license']); ?>" alt="Driver's License">
                        <?php else: ?>
                            <div class="document-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                                    <polyline points="14 2 14 8 20 8" />
                                </svg>
                            </div>
                        <?php endif; ?>
                        <div class="document-actions">
                            <a href="<?php echo htmlspecialchars($applicant['drivers_license']); ?>" class="btn-view"
                                target="_blank">View Full Document</a>
                        </div>
                    <?php else: ?>
                        <div class="document-missing">
                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 17.5a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11Zm0 0V22m0-22v4.5" />
                            </svg>
                            <p>No document provided</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="review-section">
            <h3>Vehicle Information</h3>

            <?php foreach ($vehicles as $index => $vehicle): ?>
                <div class="vehicle-card">
                    <div class="vehicle-header">
                        <h4>Vehicle #<?php echo $index + 1; ?>: <?php echo htmlspecialchars($vehicle['vehicleType']); ?>
                        </h4>
                        <div class="plate-number">Plate Number:
                            <strong><?php echo htmlspecialchars($vehicle['plateNum']); ?></strong></div>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Manufacturer:</span>
                            <span class="info-value"><?php echo htmlspecialchars($vehicle['manufacturer']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Model:</span>
                            <span class="info-value"><?php echo htmlspecialchars($vehicle['model']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Color:</span>
                            <span class="info-value"><?php echo htmlspecialchars($vehicle['color']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Number of Wheels:</span>
                            <span class="info-value"><?php echo htmlspecialchars($vehicle['numOfWheels']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Fuel Type:</span>
                            <span class="info-value"><?php echo htmlspecialchars($vehicle['fuelType']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Cubic Capacity:</span>
                            <span class="info-value"><?php echo htmlspecialchars($vehicle['cubicCapacity']); ?> cc</span>
                        </div>
                    </div>

                    <div class="document-section">
                        <div class="document-row">
                            <div class="document-col">
                                <h4>Official Receipt (OR)</h4>
                                <div class="document-preview">
                                    <?php if (!empty($vehicle['offical_receipt'])): ?>
                                        <?php
                                        $ext = pathinfo($vehicle['offical_receipt'], PATHINFO_EXTENSION);
                                        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])):
                                            ?>
                                            <img src="<?php echo htmlspecialchars($vehicle['offical_receipt']); ?>"
                                                alt="Official Receipt">
                                        <?php else: ?>
                                            <div class="document-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <path
                                                        d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                                                    <polyline points="14 2 14 8 20 8" />
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        <div class="document-actions">
                                            <a href="<?php echo htmlspecialchars($vehicle['offical_receipt']); ?>"
                                                class="btn-view" target="_blank">View Full Document</a>
                                        </div>
                                    <?php else: ?>
                                        <div class="document-missing">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path d="M12 17.5a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11Zm0 0V22m0-22v4.5" />
                                            </svg>
                                            <p>No document provided</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="document-col">
                                <h4>Certificate of Registration (CR)</h4>
                                <div class="document-preview">
                                    <?php if (!empty($vehicle['cert_of_registration'])): ?>
                                        <?php
                                        $ext = pathinfo($vehicle['cert_of_registration'], PATHINFO_EXTENSION);
                                        if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])):
                                            ?>
                                            <img src="<?php echo htmlspecialchars($vehicle['cert_of_registration']); ?>"
                                                alt="Certificate of Registration">
                                        <?php else: ?>
                                            <div class="document-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
                                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <path
                                                        d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                                                    <polyline points="14 2 14 8 20 8" />
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                        <div class="document-actions">
                                            <a href="<?php echo htmlspecialchars($vehicle['cert_of_registration']); ?>"
                                                class="btn-view" target="_blank">View Full Document</a>
                                        </div>
                                    <?php else: ?>
                                        <div class="document-missing">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
                                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path d="M12 17.5a5.5 5.5 0 1 1 0-11 5.5 5.5 0 0 1 0 11Zm0 0V22m0-22v4.5" />
                                            </svg>
                                            <p>No document provided</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($applicant['registrationStatus'] === 'pending'): ?>
            <div class="review-section action-section">
                <h3>Review Decision</h3>
                <form method="POST" action="" id="reviewForm">
                    <input type="hidden" name="rejection_reason" id="rejectionReason" value="">
                    <div class="action-buttons">
                        <button type="button" class="btn-decline" id="declineBtn">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <path d="m15 9-6 6" />
                                <path d="m9 9 6 6" />
                            </svg>
                            Decline Application
                        </button>
                        <button type="submit" name="action" value="approve" class="btn-approve">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21.801 10A10 10 0 1 1 17 3.335" />
                                <path d="m9 11 3 3L22 4" />
                            </svg>
                            Approve Application
                        </button>
                    </div>
                </form>
            </div>

            <div id="rejectModal" class="modal hidden">
                <div class="modal-content">
                    <h3>Decline Application</h3>
                    <p>Please provide a reason for declining this application:</p>
                    <textarea id="rejectReasonText" placeholder="Enter reason for rejection..." rows="4"
                        class="reject-reason-textarea"></textarea>
                    <div class="modal-buttons">
                        <button type="button" onclick="closeRejectModal()" class="btn-cancel">Cancel</button>
                        <button type="button" onclick="submitRejection()" class="btn-confirm-reject"
                            id="confirmRejectBtn">Decline Application</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>


<?php include_once '../includes/footer.php'; ?>