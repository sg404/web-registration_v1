<?php
// Deprecated: Registration code lookup removed. Registration codes are no longer stored on applications.
header('Content-Type: application/json');
http_response_code(410);
echo json_encode(['success' => false, 'message' => 'Deprecated endpoint: registration codes removed from applications.']);
return;
