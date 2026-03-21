<?php
// Compatibility wrapper: many admin pages link to attendance_logs.php at project root.
// Include the real attendance log located under admin/attendance/.
$target = __DIR__ . '/admin/attendance/attendance_log.php';
if (file_exists($target)) {
    include $target;
    exit;
}

// Fallback — show a 404 if the target doesn't exist.
http_response_code(404);
echo "Not Found";
?>
