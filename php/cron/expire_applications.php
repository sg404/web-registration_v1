<?php
// Registration code expiry job disabled
// The system no longer generates registration codes on approval. This cron is intentionally a no-op to avoid accidental changes.
require_once '../dbConnection.php';
$db = new Database();
$conn = $db->getConnection();

// No operation required since registration codes are no longer used on applications.
echo "Registration code expiry feature disabled.";

$db->closeConnection();
