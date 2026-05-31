<?php
// Small local admin helper to reset a user's password (development only)
require 'config/db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $newpw = $_POST['password'] ?? '';
    if ($email === '' || $newpw === '') {
        $message = 'Email and new password are required.';
    } else {
        $hash = password_hash($newpw, PASSWORD_BCRYPT);
        $stmt = $conn->prepare('UPDATE users SET password = ? WHERE email = ?');
        $stmt->bind_param('ss', $hash, $email);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $message = 'Password updated successfully for ' . htmlspecialchars($email);
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
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Reset User Password</title>
  <style>body{font-family:Arial;margin:20px;}label{display:block;margin-top:8px;}input{padding:8px;width:320px}</style>
</head>
<body>
  <h2>Reset User Password (local dev)</h2>
  <?php if ($message): ?><p><strong><?php echo htmlspecialchars($message); ?></strong></p><?php endif; ?>
  <form method="post">
    <label>Email
      <input name="email" type="email" required>
    </label>
    <label>New password
      <input name="password" type="password" required>
    </label>
    <button type="submit">Reset Password</button>
  </form>
  <p>Note: This updates the database with a bcrypt hash. Use only on your local dev environment.</p>
</body>
</html>
