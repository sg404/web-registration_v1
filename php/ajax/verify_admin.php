<?php
// Include database connection
require_once '../dbConnection.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['username']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$username = trim($data['username']);
$password = $data['password'];

// Validate input
if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

// Check admin credentials
$query = "SELECT userID, username, role FROM user WHERE username = ? AND password = ? AND role = 'admin'";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $username, $password);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'admin' => [
            'userID' => $admin['userID'],
            'username' => $admin['username']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid admin credentials']);
}

$stmt->close();
$conn->close();
?>
