<?php
session_start();
require 'config/db.php';

$isJson = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = $_POST;
    if ($isJson) {
        $json = json_decode(file_get_contents('php://input'), true);
        if (is_array($json)) {
            $input = $json;
        }
    }

    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? $input['confirmPassword'] ?? '';

    if (empty($username) && !empty($email)) {
        $username = strstr($email, '@', true) ?: $email;
    }

    // Validation
    if (empty($email) || empty($password)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        $check_sql = "SELECT id FROM users WHERE email = ? OR username = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Username or email already exists";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $insert_sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sss", $username, $email, $hashed_password);

            if ($stmt->execute()) {
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $success = "Registration successful! You are now logged in.";
            } else {
                $error = "Registration failed: " . $conn->error;
            }
        }
        $stmt->close();
    }

    if ($isJson) {
        header('Content-Type: application/json');
        if ($error) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $error]);
        } else {
            echo json_encode(['success' => true, 'message' => $success]);
        }
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <style>
        body { font-family: Arial; max-width: 400px; margin: 50px auto; }
        .form-group { margin: 15px 0; }
        input { width: 100%; padding: 10px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #4CAF50; color: white; border: none; cursor: pointer; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <h2>Register</h2>
    
    <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Username:</label>
            <input type="text" name="username" required>
        </div>

        <div class="form-group">
            <label>Email:</label>
            <input type="email" name="email" required>
        </div>

        <div class="form-group">
            <label>Password:</label>
            <input type="password" name="password" required>
        </div>

        <div class="form-group">
            <label>Confirm Password:</label>
            <input type="password" name="confirm_password" required>
        </div>

        <button type="submit">Register</button>
        <p>Already have an account? <a href="login.php">Login here</a></p>
    </form>
</body>
</html>