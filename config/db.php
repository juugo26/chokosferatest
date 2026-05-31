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
?>
