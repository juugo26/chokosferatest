<?php
require 'config/db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $message = 'Email is required.';
    } else {
        $stmt = $conn->prepare('UPDATE users SET role = ? WHERE email = ?');
        $role = 'superadmin';
        $stmt->bind_param('ss', $role, $email);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $message = 'User promoted to superadmin: ' . htmlspecialchars($email);
            } else {
                $message = 'No user found with that email.';
            }
        } else {
            $message = 'DB error: ' . $conn->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Promote to Superadmin</title>
</head>
<body style="font-family:Arial;margin:20px;">
    <h2>Promote User to Superadmin</h2>
    <?php if ($message): ?><p><strong><?php echo $message; ?></strong></p><?php endif; ?>
    <form method="post">
        <label>Email: <input type="email" name="email" required></label><br><br>
        <button type="submit">Promote</button>
    </form>
    <p>Use this locally to grant superadmin access to an existing user.</p>
</body>
</html>
