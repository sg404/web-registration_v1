<?php
// Verify admin password for sensitive actions
session_start();
require_once '../dbConnection.php';
header('Content-Type: application/json');

// Accept JSON body or form-encoded POST
$inputJson = json_decode(file_get_contents('php://input'), true);
$password = $inputJson['password'] ?? $_POST['password'] ?? null;

if (!$password) {
    echo json_encode(['success' => false, 'message' => 'Please provide admin password']);
    exit;
}

// Require an authenticated admin session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Fetch password hash for current user
$stmt = $conn->prepare("SELECT userID, username, password, role FROM user WHERE userID = ? LIMIT 1");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    $stmt->close();
    $conn->close();
    exit;
}

$user = $result->fetch_assoc();

// Ensure user has admin role
if (($user['role'] ?? '') !== 'SSEDMMO Admin') {
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    $stmt->close();
    $conn->close();
    exit;
}

// Verify password using password_verify (supports hashed passwords)
if (password_verify($password, $user['password'])) {
    echo json_encode(['success' => true, 'message' => 'Admin verified']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid admin password']);
}

$stmt->close();
$conn->close();
?>
