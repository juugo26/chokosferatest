<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Leave empty if no password
define('DB_NAME', 'chokosfera');

// Create connection to MySQL server (database may not exist yet)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it does not exist
$dbCreated = $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
if ($dbCreated === false) {
    die("Database creation failed: " . $conn->error);
}

// Select the database
$conn->select_db(DB_NAME);

// Create users table if it does not exist
$tableSql = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($tableSql) === false) {
    die("Table creation failed: " . $conn->error);
}

// Ensure the users password column is large enough for full bcrypt hashes
if ($conn->query("ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NOT NULL") === false) {
    die("Users table alter failed: " . $conn->error);
}

// Create orders table if it does not exist
$ordersSql = "CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    items JSON NOT NULL,
    total DECIMAL(10,2) NOT NULL DEFAULT 0,
    final_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'Pending',
    user_id VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($ordersSql) === false) {
    die("Orders table creation failed: " . $conn->error);
}

// Ensure final_price column exists for older installations
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS final_price DECIMAL(10,2) NOT NULL DEFAULT 0");

// Create loyal_customers table if not exists
$loyalSql = "CREATE TABLE IF NOT EXISTS loyal_customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(150) DEFAULT NULL,
    last_name VARCHAR(150) DEFAULT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($loyalSql) === false) {
    die("Loyal customers table creation failed: " . $conn->error);
}

// Set charset
$conn->set_charset("utf8mb4");

// Start session
session_start();

// Enable error logging for debugging
error_log("Login page accessed - Method: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

// CORS headers - simplified for local development
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(2100);
    exit();
}

// Simplified JSON detection - always treat as AJAX if using fetch
$isJson = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Alternative: Force JSON mode for API-style requests
if (isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $isJson = true;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = $_POST;
    
    // Handle JSON input
    if ($isJson) {
        $json = json_decode(file_get_contents('php://input'), true);
        if (is_array($json)) {
            $input = $json;
        }
    }

    $loginIdentifier = trim($input['email'] ?? $input['username'] ?? '');
    $password = trim($input['password'] ?? '');
    error_log("LOGIN - Primljena lozinka ima ovoliko znakova: " . strlen($password));

    error_log("Login attempt - Identifier: " . $loginIdentifier . ", Password length: " . strlen($password));

    if (empty($loginIdentifier) || empty($password)) {
        $error = "All fields are required";
    } else {
        $sql = "SELECT id, username, email, password FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $loginIdentifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // Fall back to username lookup
            $stmt->close();
            $sql = "SELECT id, username, email, password FROM users WHERE username = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $loginIdentifier);
            $stmt->execute();
            $result = $stmt->get_result();
        }

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $storedPassword = trim($user['password']);
            $isPasswordMatch = false;

            // Multiple password verification methods
            if ($storedPassword !== '' && password_verify($password, $storedPassword)) {
                $isPasswordMatch = true;
            } elseif ($password === $storedPassword) {
                $isPasswordMatch = true;
            } elseif ($storedPassword !== '' && hash_equals($storedPassword, md5($password))) {
                $isPasswordMatch = true;
            } elseif ($storedPassword !== '' && hash_equals($storedPassword, sha1($password))) {
                $isPasswordMatch = true;
            } elseif (!empty($storedPassword) && crypt($password, $storedPassword) === $storedPassword) {
                $isPasswordMatch = true;
            }

            if ($isPasswordMatch && ($storedPassword === '' || !password_verify($password, $storedPassword))) {
                // Upgrade legacy passwords to bcrypt
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                $updateStmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                $updateStmt->bind_param('si', $newHash, $user['id']);
                $updateStmt->execute();
                $updateStmt->close();
            }

            if ($isPasswordMatch) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                
                error_log("Login successful for user: " . $user['email']);
                
                if ($isJson) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true, 
                        'message' => 'Login successful!', 
                        'user' => [
                            'id' => $user['id'], 
                            'username' => $user['username'], 
                            'email' => $user['email']
                        ]
                    ]);
                    exit();
                }
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid password";
                error_log("Login failed - Invalid password for: " . $loginIdentifier);
            }
        } else {
            $error = "User not found";
            error_log("Login failed - User not found: " . $loginIdentifier);
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
        body { 
            font-family: Arial; 
            max-width: 400px; 
            margin: 50px auto; 
            background: #f5f5f5;
        }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group { 
            margin: 15px 0; 
        }
        input { 
            width: 100%; 
            padding: 12px; 
            box-sizing: border-box; 
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        input:focus {
            outline: none;
            border-color: #ff69b4;
        }
        button { 
            padding: 12px 20px; 
            background: #ff69b4; 
            color: white; 
            border: none; 
            cursor: pointer;
            border-radius: 5px;
            font-size: 16px;
            width: 100%;
        }
        button:hover {
            background: #ff1493;
        }
        .error { 
            color: #d32f2f; 
            margin-bottom: 15px;
            padding: 10px;
            background: #ffebee;
            border-radius: 5px;
        }
        .success {
            color: #388e3c;
            margin-bottom: 15px;
            padding: 10px;
            background: #e8f5e8;
            border-radius: 5px;
        }
        .links {
            text-align: center;
            margin-top: 20px;
        }
        .links a {
            color: #ff69b4;
            text-decoration: none;
            margin: 0 10px;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2 style="text-align: center; color: #ff69b4; margin-bottom: 30px;">Login</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
            <div class="success">Registration successful! Please login.</div>
        <?php endif; ?>

        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit">Login</button>
        </form>
        
        <div class="links">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
            <p><a href="index.html">Back to Home</a></p>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!email || !password) {
                alert('Please enter both email and password.');
                return;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Logging in...';
            submitBtn.disabled = true;
            
            fetch('login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ email: email, password: password })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(data => {
                        throw new Error(data.error || 'Login failed');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.href = 'dashboard.php';
                } else {
                    throw new Error(data.error || 'Login failed');
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
                console.error('Login error:', error);
            })
            .finally(() => {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    </script>
</body>
</html>
