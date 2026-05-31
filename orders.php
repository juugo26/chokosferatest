<?php
require 'config/db.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// Read JSON input if present
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

try {
    if ($method === 'GET') {
        // optional userId filter
        $userId = $_GET['userId'] ?? null;
        if ($userId) {
            $stmt = $conn->prepare('SELECT id, items, total, status, user_id, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC');
            $stmt->bind_param('s', $userId);
        } else {
            $stmt = $conn->prepare('SELECT id, items, total, status, user_id, created_at FROM orders ORDER BY created_at DESC');
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $orders = [];
        while ($row = $res->fetch_assoc()) {
            $row['items'] = json_decode($row['items'], true);
            $orders[] = $row;
        }
        echo json_encode($orders);
        exit();
    }

    if ($method === 'POST') {
        // support cancel via ?action=cancel or body action
        $action = $_GET['action'] ?? ($input['action'] ?? null);
        if ($action === 'cancel') {
            $orderId = $input['id'] ?? $_GET['id'] ?? null;
            if (!$orderId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing order id']);
                exit();
            }
            $stmt = $conn->prepare('SELECT status FROM orders WHERE id = ?');
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Order not found']);
                exit();
            }
            $row = $res->fetch_assoc();
            if ($row['status'] === 'Cancelled') {
                echo json_encode(['success' => false, 'error' => 'Already cancelled']);
                exit();
            }
            $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $status = 'Cancelled';
            $stmt->bind_param('si', $status, $orderId);
            $stmt->execute();
            echo json_encode(['success' => true, 'order' => ['id' => (int)$orderId, 'status' => $status]]);
            exit();
        }

        // Otherwise treat as create order
        // Support both JSON body and form-encoded POST
        $items = $input['items'] ?? ($_POST['items'] ?? null);
        $total = $input['total'] ?? ($_POST['total'] ?? 0);
        $userId = $input['userId'] ?? ($_POST['userId'] ?? null);
        $redeemCode = trim($input['redeem_code'] ?? ($_POST['redeem_code'] ?? ''));
        $userEmail = $input['userEmail'] ?? ($_POST['userEmail'] ?? null);

        // Parse items if it's a JSON string
        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        if (!$items || !is_array($items)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing items']);
            exit();
        }

        $total = (float)$total;
        $itemsJson = json_encode($items);
        $status = 'Pending';

        // Enforce daily order cap of 20 orders
        $orderDate = date('Y-m-d');
        $countStmt = $conn->prepare("SELECT COUNT(*) AS total_orders FROM orders WHERE DATE(created_at) = ? AND status <> 'Cancelled'");
        $countStmt->bind_param('s', $orderDate);
        $countStmt->execute();
        $countResult = $countStmt->get_result()->fetch_assoc();
        $countStmt->close();
        if ((int)$countResult['total_orders'] >= 20) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Daily order limit reached for ' . $orderDate]);
            exit();
        }

        // Check loyalty redeem code if provided and compute final price
        $finalPrice = $total;
        $appliedDiscount = 0;
        if ($redeemCode) {
            if (!$userEmail && $userId) {
                $uStmt = $conn->prepare('SELECT email FROM users WHERE id = ?');
                $uStmt->bind_param('s', $userId);
                $uStmt->execute();
                $uRes = $uStmt->get_result();
                if ($uRow = $uRes->fetch_assoc()) {
                    $userEmail = $uRow['email'];
                }
                $uStmt->close();
            }

            $lStmt = $conn->prepare('SELECT email FROM loyal_customers WHERE redeem_code = ?');
            $lStmt->bind_param('s', $redeemCode);
            $lStmt->execute();
            $lRes = $lStmt->get_result();
            if ($lRow = $lRes->fetch_assoc()) {
                if (!$userEmail || strcasecmp($lRow['email'], $userEmail) === 0) {
                    $appliedDiscount = 10; // percent
                    $finalPrice = round($total * 0.9, 2);
                } else {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Redeem code does not match this user email']);
                    exit();
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid loyalty redeem code']);
                exit();
            }
            $lStmt->close();
        }

        // Insert order including final_price
        $stmt = $conn->prepare('INSERT INTO orders (items, total, final_price, status, user_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('sddss', $itemsJson, $total, $finalPrice, $status, $userId);
        if ($stmt->execute()) {
            $orderId = $stmt->insert_id;
            $order = ['id' => $orderId, 'items' => $items, 'total' => $total, 'final_price' => $finalPrice, 'status' => $status, 'user_id' => $userId, 'created_at' => date('c'), 'discount_applied' => $appliedDiscount];
            echo json_encode(['success' => true, 'order' => $order]);
            exit();
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'DB error: ' . $conn->error]);
            exit();
        }
    }

    if ($method === 'PATCH' || $method === 'DELETE' || $method === 'PUT') {
        // parse incoming raw JSON
        if ($method === 'PATCH') {
            $input = json_decode(file_get_contents('php://input'), true);
            $orderId = $input['id'] ?? null;
            if (!$orderId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing order id']);
                exit();
            }
            $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $status = 'Cancelled';
            $stmt->bind_param('si', $status, $orderId);
            $stmt->execute();
            echo json_encode(['success' => true, 'order' => ['id' => (int)$orderId, 'status' => $status]]);
            exit();
        }
    }

    // method not implemented
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit();
} catch (Exception $ex) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
    exit();
}

?>