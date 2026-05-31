<?php
require_once 'config/db.php';

// Create calendar table if not exists
$calendarSql = "CREATE TABLE IF NOT EXISTS calendar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    time_slots JSON NOT NULL,
    is_available BOOLEAN NOT NULL DEFAULT TRUE,
    special_hours VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($calendarSql) === false) {
    die(json_encode(['error' => 'Calendar table creation failed: ' . $conn->error]));
}

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// GET available slots
function getAvailableSlots($conn) {
    $date = $_GET['date'] ?? null;
    if (!$date) {
        echo json_encode(['error' => 'Date is required']);
        return;
    }
    $stmt = $conn->prepare("SELECT * FROM calendar WHERE date = ? AND is_available = TRUE");
    $stmt->bind_param("s", $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $slots = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['slots' => $slots]);
}

// POST book a slot
function bookSlot($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $date = $data['date'] ?? null;
    $time_slots = $data['time_slots'] ?? null;
    $special_hours = $data['special_hours'] ?? null;

    if (!$date || !$time_slots) {
        echo json_encode(['error' => 'Date and time_slots are required']);
        return;
    }

    $time_slots_json = json_encode($time_slots);
    $stmt = $conn->prepare("INSERT INTO calendar (date, time_slots, is_available, special_hours) VALUES (?, ?, TRUE, ?)");
    $stmt->bind_param("sss", $date, $time_slots_json, $special_hours);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['error' => $conn->error]);
    }
}

// PATCH cancel a slot
function cancelSlot($conn) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;

    if (!$id) {
        echo json_encode(['error' => 'ID is required']);
        return;
    }

    $stmt = $conn->prepare("UPDATE calendar SET is_available = FALSE WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => $conn->error]);
    }
}

// Router
if ($method === 'GET' && $action === 'slots') {
    getAvailableSlots($conn);
} elseif ($method === 'POST' && $action === 'book') {
    bookSlot($conn);
} elseif ($method === 'PATCH' && $action === 'cancel') {
    cancelSlot($conn);
} else {
    echo json_encode(['error' => 'Invalid action']);
}

$conn->close();
?>