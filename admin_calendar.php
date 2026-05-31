<?php
session_start();
require 'config/db.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'user') !== 'superadmin') {
    http_response_code(403);
    echo "<h1>Access denied</h1><p>This page is available only to the superadmin.</p>";
    exit();
}

$action = $_GET['action'] ?? '';
if ($action === 'details') {
    header('Content-Type: application/json');
    $date = $_GET['date'] ?? null;
    if (!$date) {
        echo json_encode(['error' => 'Date is required']);
        exit();
    }

    $stmt = $conn->prepare("SELECT o.id, o.items, o.total, o.final_price, o.status, o.user_id, u.email, u.username, o.created_at
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        WHERE DATE(o.created_at) = ?
        ORDER BY o.created_at ASC");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = [];
    while ($row = $result->fetch_assoc()) {
        $row['items'] = json_decode($row['items'], true);
        $orders[] = $row;
    }
    echo json_encode(['date' => $date, 'orders' => $orders]);
    exit();
}

$today = new DateTime();
$days = [];
for ($i = 0; $i < 30; $i++) {
    $day = clone $today;
    $day->modify("+$i day");
    $days[] = $day->format('Y-m-d');
}
$startDate = $days[0];
$endDate = end($days);

$stmt = $conn->prepare("SELECT DATE(created_at) AS order_date, COUNT(*) AS total_orders
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ? AND status <> 'Cancelled'
    GROUP BY DATE(created_at)");
$stmt->bind_param('ss', $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
$countByDate = [];
while ($row = $result->fetch_assoc()) {
    $countByDate[$row['order_date']] = (int)$row['total_orders'];
}
$stmt->close();

function statusForCount($count) {
    if ($count >= 20) {
        return ['label' => 'Closed', 'class' => 'closed', 'remaining' => 0];
    }
    return ['label' => 'Open', 'class' => 'open', 'remaining' => 20 - $count];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Superadmin Order Calendar</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 24px; background: #f7f7fb; color: #222; }
        h1 { margin-bottom: 12px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
        .day-card { padding: 14px; border-radius: 16px; background: white; box-shadow: 0 1px 8px rgba(0,0,0,0.07); cursor: pointer; transition: transform .18s ease; }
        .day-card:hover { transform: translateY(-2px); }
        .day-card.closed { background: #ffe4e5; }
        .day-card.open { background: #e8f7ff; }
        .day-date { font-weight: bold; margin-bottom: 8px; }
        .status { font-size: 0.95rem; margin-bottom: 6px; }
        .count { font-size: 1.6rem; font-weight: bold; }
        .details { margin-top: 24px; }
        .details table { width: 100%; border-collapse: collapse; }
        .details th, .details td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        .details th { background: #f1f3f5; }
        .badge { display: inline-flex; padding: 4px 10px; border-radius: 999px; font-size: .85rem; }
        .badge.open { background: #d1edf9; color: #0c6581; }
        .badge.closed { background: #ffd7dd; color: #8b1d1f; }
    </style>
</head>
<body>
    <h1>Superadmin Order Calendar</h1>
    <p>Daily order limit: <strong>20 orders</strong>. If a day reaches 20 orders, it becomes closed.</p>
    <div class="grid">
        <?php foreach ($days as $date):
            $count = $countByDate[$date] ?? 0;
            $status = statusForCount($count);
        ?>
        <div class="day-card <?php echo $status['class']; ?>" data-date="<?php echo $date; ?>">
            <div class="day-date"><?php echo date('D, M j', strtotime($date)); ?></div>
            <div class="status"><span class="badge <?php echo $status['class']; ?>"><?php echo $status['label']; ?></span></div>
            <div class="count"><?php echo $count; ?></div>
            <div><?php echo $status['remaining']; ?> spots left</div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="details">
        <h2 id="detailsTitle">Select a date to view order details</h2>
        <div id="detailsContent"></div>
    </div>

    <script>
        const cards = document.querySelectorAll('.day-card');
        const detailsTitle = document.getElementById('detailsTitle');
        const detailsContent = document.getElementById('detailsContent');

        cards.forEach(card => {
            card.addEventListener('click', () => {
                const date = card.dataset.date;
                detailsTitle.innerText = 'Orders for ' + date;
                detailsContent.innerHTML = '<p>Loading...</p>';
                fetch('admin_calendar.php?action=details&date=' + encodeURIComponent(date))
                    .then(resp => resp.json())
                    .then(data => {
                        if (data.error) {
                            detailsContent.innerHTML = '<p>' + data.error + '</p>';
                            return;
                        }
                        if (!data.orders || !data.orders.length) {
                            detailsContent.innerHTML = '<p>No orders for this date.</p>';
                            return;
                        }
                        let html = '<table><thead><tr><th>ID</th><th>User</th><th>Email</th><th>Total</th><th>Final Price</th><th>Status</th><th>Placed At</th></tr></thead><tbody>';
                        data.orders.forEach(order => {
                            const userLabel = order.username ? order.username : ('User ' + order.user_id);
                            html += '<tr>' +
                                '<td>' + order.id + '</td>' +
                                '<td>' + userLabel + '</td>' +
                                '<td>' + (order.email || '-') + '</td>' +
                                '<td>' + Number(order.total).toFixed(2) + ' KM</td>' +
                                '<td>' + Number(order.final_price).toFixed(2) + ' KM</td>' +
                                '<td>' + order.status + '</td>' +
                                '<td>' + order.created_at + '</td>' +
                                '</tr>';
                        });
                        html += '</tbody></table>';
                        detailsContent.innerHTML = html;
                    })
                    .catch(() => {
                        detailsContent.innerHTML = '<p>Failed to load orders for this date.</p>';
                    });
            });
        });
    </script>
</body>
</html>
