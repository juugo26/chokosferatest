<?php
session_start();
require 'config/db.php';

$isJson = isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = $_POST;
    if ($isJson) {
        $json = json_decode(file_get_contents('php://input'), true);
        if (is_array($json)) {
            $input = $json;
        }
    }

    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "All fields are required";
    } else {
        // Check for admin credentials
        $isAdmin = ($email === 'sarah.karacic@gmail.com' && $password === 'donutsarajevo');
        
        if ($isAdmin) {
            // Admin login successful
            $_SESSION['user_id'] = 'admin';
            $_SESSION['username'] = 'Admin';
            $_SESSION['email'] = 'sarah.karacic@gmail.com';
            $_SESSION['isAdmin'] = true;
            if ($isJson) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Login successful!', 'username' => 'Admin', 'isAdmin' => true]);
                exit();
            }
            header("Location: admin.html");
            exit();
        }
        
        $sql = "SELECT id, username, email, password FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                if ($isJson) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Login successful!', 'username' => $user['username'], 'isAdmin' => false]);
                    exit();
                }
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid password";
            }
        } else {
            $error = "User not found";
        }
        $stmt->close();
    }

    if ($isJson) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $error]);
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <style>
        body { font-family: Arial; max-width: 400px; margin: 50px auto; }
        .form-group { margin: 15px 0; }
        input { width: 100%; padding: 10px; box-sizing: border-box; }
        button { padding: 10px 20px; background: #2196F3; color: white; border: none; cursor: pointer; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>Login</h2>
    
    <?php if ($error): ?>
        <div class="error"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Email:</label>
            <input type="email" name="email" required>
        </div>

        <div class="form-group">
            <label>Password:</label>
            <input type="password" name="password" required>
        </div>

        <button type="submit">Login</button>
        <p>Don't have an account? <a href="register.php">Register here</a></p>
    </form>
</body>
</html>