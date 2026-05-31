<?php
// Development helper: show stored password info for a user
require 'config/db.php';

$info = null;
if (!empty($_GET['email'])) {
    $email = $_GET['email'];
    $stmt = $conn->prepare('SELECT id, username, email, password FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $pw = $row['password'];
        $info = [
            'id' => $row['id'],
            'username' => $row['username'],
            'email' => $row['email'],
            'password_length' => strlen($pw),
            'password_preview' => substr($pw,0,60),
            'password_hex_preview' => bin2hex(substr($pw,0,40))
        ];
    }
    $stmt->close();
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Debug User</title></head>
<body>
  <h2>Debug User</h2>
  <form method="get">
    <label>Email: <input name="email" type="email" required></label>
    <button type="submit">Lookup</button>
  </form>
  <?php if ($info): ?>
    <pre><?php echo htmlspecialchars(json_encode($info, JSON_PRETTY_PRINT)); ?></pre>
  <?php elseif (isset($_GET['email'])): ?>
    <p>No user found.</p>
  <?php endif; ?>
</body>
</html>
