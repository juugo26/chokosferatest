<?php
session_start();
require 'config/db.php';

// Allow requests from local dev servers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

$isJson = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
$input = $_POST;
if ($isJson) {
    $json = json_decode(file_get_contents('php://input'), true);
    if (is_array($json)) $input = $json;
}

$first = trim($input['firstName'] ?? $input['first_name'] ?? '');
$last = trim($input['lastName'] ?? $input['last_name'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$redeem_code = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Email is required']);
        exit();
    }

    // Check existing loyal customer record
    $stmt = $conn->prepare('SELECT id FROM loyal_customers WHERE email = ?');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Loyal customer with that email already exists']);
        exit();
    }
    $stmt->close();

    $hash = null;
    if (!empty($password)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
    }
    $redeem_code = 'LOYAL-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 4)) . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 4));

    $insert = $conn->prepare('INSERT INTO loyal_customers (first_name, last_name, email, password_hash, redeem_code) VALUES (?, ?, ?, ?, ?)');
    $insert->bind_param('sssss', $first, $last, $email, $hash, $redeem_code);
    if ($insert->execute()) {
        echo json_encode(['success' => true, 'message' => 'Loyal customer registered', 'redeem_code' => $redeem_code]);
        exit();
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'DB error: ' . $conn->error]);
        exit();
    }
}

// If not POST, show simple HTML form fallback
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Register Loyal Customer</title>
</head>
<body>
    <h2>Register Loyal Customer</h2>
    <form method="POST">
        <label>First name: <input type="text" name="firstName"></label><br>
        <label>Last name: <input type="text" name="lastName"></label><br>
        <label>Email: <input type="email" name="email" required></label><br>
        <label>Password: <input type="password" name="password" required></label><br>
        <button type="submit">Register</button>
    </form>
    <?php if (!empty($redeem_code)): ?>
        <p><strong>Your loyalty redeem code:</strong> <?php echo htmlspecialchars($redeem_code); ?></p>
        <p>Use this code during payment to receive your 10% discount.</p>
    <?php endif; ?>
</body>
</html>
