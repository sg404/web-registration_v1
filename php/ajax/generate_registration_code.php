<?php
// Deprecated: Registration code generation removed. This endpoint is kept for compatibility but intentionally disabled.
header('Content-Type: application/json');
http_response_code(410);
echo json_encode(['success' => false, 'message' => 'Deprecated: registration code generation has been removed.']);
return;
