<?php
// Backfill feature has been removed per admin request.
if (php_sapi_name() === 'cli') {
    // CLI output
    fwrite(STDOUT, "Backfill feature has been removed.\n");
    exit(0);
}
header('Content-Type: application/json', true, 410);
echo json_encode(['success' => false, 'message' => 'Backfill feature has been removed by admin.']);
exit;

