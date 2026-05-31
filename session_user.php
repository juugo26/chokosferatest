<?php
session_start();
header('Content-Type: application/json');

// Return JSON with current session username when available
if (!empty($_SESSION['username'])) {
    echo json_encode(['success' => true, 'username' => $_SESSION['username']]);
    exit();
}

echo json_encode(['success' => false]);
exit();

?>
