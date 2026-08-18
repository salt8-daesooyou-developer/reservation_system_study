<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$isStaff = !empty($_SESSION['staff_id']);
$isCustomer = !empty($_SESSION['customer_id']);
if (!$isStaff && !$isCustomer) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

if ($isStaff) {
    $customerId = (int) ($_GET['customer_id'] ?? 0);
    if (!$customerId) {
        http_response_code(422);
        echo json_encode(['error' => 'customer_id_required']);
        exit;
    }
} else {
    $customerId = (int) $_SESSION['customer_id'];
}

$cStmt = $pdo->prepare('SELECT c.*, b.name AS branch_name FROM customers c LEFT JOIN branches b ON b.id = c.branch_id WHERE c.id = ?');
$cStmt->execute([$customerId]);
$customer = $cStmt->fetch();
if (!$customer) {
    http_response_code(404);
    echo json_encode(['error' => 'not_found']);
    exit;
}

$section = $_GET['section'] ?? 'dashboard';

if ($section === 'products') {
    $stmt = $pdo->prepare('
        SELECT cm.*, mp.name AS product_name, mp.type AS product_type, mp.session_count AS product_session_count, mp.valid_days AS product_valid_days
        FROM customer_memberships cm
        JOIN membership_products mp ON mp.id = cm.product_id
        WHERE cm.customer_id = ?
        ORDER BY cm.start_date DESC
    ');
    $stmt->execute([$customerId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($section === 'payments') {
    $stmt = $pdo->prepare('
        SELECT p.*, mp.name AS product_name, s.name AS staff_name
        FROM payments p
        LEFT JOIN membership_products mp ON mp.id = p.product_id
        LEFT JOIN staff s ON s.id = p.created_by
        WHERE p.customer_id = ?
        ORDER BY p.created_at DESC
    ');
    $stmt->execute([$customerId]);
    $rows = $stmt->fetchAll();
    $saleTotal = 0;
    $refundTotal = 0;
    foreach ($rows as $r) {
        if ($r['type'] === 'sale') $saleTotal += (float) $r['amount'];
        else $refundTotal += (float) $r['amount'];
    }
    echo json_encode(['rows' => $rows, 'sale_total' => $saleTotal, 'refund_total' => $refundTotal]);
    exit;
}

if ($section === 'attendance') {
    $stmt = $pdo->prepare('
        SELECT r.id, r.status, s.schedule_date, s.start_time, s.end_time, c.name AS class_name, s.instructor_name, b.name AS branch_name
        FROM reservations r
        JOIN schedules s ON s.id = r.schedule_id
        JOIN classes c ON c.id = s.class_id
        JOIN branches b ON b.id = s.branch_id
        WHERE r.customer_id = ? AND r.status IN ("show","noshow")
        ORDER BY s.schedule_date DESC, s.start_time DESC
    ');
    $stmt->execute([$customerId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($section === 'reservations') {
    $stmt = $pdo->prepare('
        SELECT r.id, r.status, r.reserved_at, s.schedule_date, s.start_time, s.end_time, c.name AS class_name, s.instructor_name, b.name AS branch_name
        FROM reservations r
        JOIN schedules s ON s.id = r.schedule_id
        JOIN classes c ON c.id = s.class_id
        JOIN branches b ON b.id = s.branch_id
        WHERE r.customer_id = ?
        ORDER BY s.schedule_date DESC, s.start_time DESC
    ');
    $stmt->execute([$customerId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($section === 'events') {
    $stmt = $pdo->prepare('
        SELECT me.*, mp.name AS product_name, fc.name AS from_customer_name, tc.name AS to_customer_name, s.name AS staff_name
        FROM membership_events me
        JOIN customer_memberships cm ON cm.id = me.customer_membership_id
        JOIN membership_products mp ON mp.id = cm.product_id
        LEFT JOIN customers fc ON fc.id = me.from_customer_id
        LEFT JOIN customers tc ON tc.id = me.to_customer_id
        LEFT JOIN staff s ON s.id = me.created_by
        WHERE cm.customer_id = ? OR me.from_customer_id = ? OR me.to_customer_id = ?
        ORDER BY me.created_at DESC
    ');
    $stmt->execute([$customerId, $customerId, $customerId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($section === 'contracts') {
    $stmt = $pdo->prepare('
        SELECT ct.*, s.name AS staff_name
        FROM contracts ct
        LEFT JOIN staff s ON s.id = ct.created_by
        WHERE ct.customer_id = ?
        ORDER BY ct.contract_date DESC, ct.id DESC
    ');
    $stmt->execute([$customerId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($section === 'coupons') {
    $stmt = $pdo->prepare('SELECT * FROM coupons WHERE customer_id = ? ORDER BY created_at DESC');
    $stmt->execute([$customerId]);
    $coupons = $stmt->fetchAll();

    $mStmt = $pdo->prepare('SELECT * FROM mileage_logs WHERE customer_id = ? ORDER BY created_at DESC LIMIT 20');
    $mStmt->execute([$customerId]);
    $mileageLogs = $mStmt->fetchAll();

    echo json_encode(['coupons' => $coupons, 'mileage_points' => (int) $customer['mileage_points'], 'mileage_logs' => $mileageLogs]);
    exit;
}

if ($section === 'notes') {
    if (!$isStaff) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }
    $stmt = $pdo->prepare('
        SELECT cn.*, s.name AS staff_name
        FROM customer_notes cn
        LEFT JOIN staff s ON s.id = cn.created_by
        WHERE cn.customer_id = ?
        ORDER BY cn.created_at DESC
    ');
    $stmt->execute([$customerId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($section === 'logs') {
    if (!$isStaff) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }
    $stmt = $pdo->prepare('
        SELECT al.*, s.name AS staff_name
        FROM activity_logs al
        LEFT JOIN staff s ON s.id = al.created_by
        WHERE al.customer_id = ?
        ORDER BY al.created_at DESC
        LIMIT 200
    ');
    $stmt->execute([$customerId]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// section === 'dashboard' (default)

$pStmt = $pdo->prepare('
    SELECT cm.*, mp.name AS product_name, mp.type AS product_type, mp.session_count AS product_session_count, mp.valid_days AS product_valid_days
    FROM customer_memberships cm
    JOIN membership_products mp ON mp.id = cm.product_id
    WHERE cm.customer_id = ? AND cm.status = "active"
    ORDER BY cm.start_date DESC
    LIMIT 1
');
$pStmt->execute([$customerId]);
$pass = $pStmt->fetch();
if ($pass) {
    if ($pass['product_type'] === 'count') {
        $total = (int) $pass['product_session_count'];
        $remaining = (int) $pass['remaining_count'];
        $pass['progress_pct'] = $total > 0 ? round((($total - $remaining) / $total) * 100) : 0;
        $pass['progress_label'] = "{$remaining}回 残り";
    } else {
        $start = new DateTime($pass['start_date']);
        $end = $pass['end_date'] ? new DateTime($pass['end_date']) : null;
        $today = new DateTime('today');
        if ($end) {
            $totalDays = max(1, $start->diff($end)->days);
            $elapsedDays = min($totalDays, max(0, $start->diff($today)->days));
            $daysLeft = max(0, (int) $today->diff($end)->days);
            if ($today > $end) $daysLeft = 0;
            $pass['progress_pct'] = round(($elapsedDays / $totalDays) * 100);
            $pass['progress_label'] = "{$daysLeft}日 残り";
        } else {
            $pass['progress_pct'] = 0;
            $pass['progress_label'] = '期限なし';
        }
    }
}

$weekStart = new DateTime('today');
$weekStart->modify('-' . $weekStart->format('w') . ' days');
$weekEnd = (clone $weekStart)->modify('+7 days');
$wStmt = $pdo->prepare('
    SELECT r.id, r.status, s.schedule_date, s.start_time, s.end_time, c.name AS class_name, s.instructor_name, b.name AS branch_name
    FROM reservations r
    JOIN schedules s ON s.id = r.schedule_id
    JOIN classes c ON c.id = s.class_id
    JOIN branches b ON b.id = s.branch_id
    WHERE r.customer_id = ? AND r.status IN ("reserved","show") AND s.schedule_date >= ? AND s.schedule_date < ?
    ORDER BY s.schedule_date, s.start_time
');
$wStmt->execute([$customerId, $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);
$weekReservations = $wStmt->fetchAll();

$monthStart = (new DateTime('first day of this month'))->format('Y-m-d');
$monthEnd = (new DateTime('first day of next month'))->format('Y-m-d');
$calStmt = $pdo->prepare('
    SELECT DISTINCT s.schedule_date
    FROM reservations r
    JOIN schedules s ON s.id = r.schedule_id
    WHERE r.customer_id = ? AND r.status IN ("reserved","show") AND s.schedule_date >= ? AND s.schedule_date < ?
');
$calStmt->execute([$customerId, $monthStart, $monthEnd]);
$monthDates = array_column($calStmt->fetchAll(), 'schedule_date');

$payStmt = $pdo->prepare('
    SELECT p.*, mp.name AS product_name
    FROM payments p
    LEFT JOIN membership_products mp ON mp.id = p.product_id
    WHERE p.customer_id = ?
    ORDER BY p.created_at DESC
    LIMIT 3
');
$payStmt->execute([$customerId]);
$recentPayments = $payStmt->fetchAll();

$ctCountStmt = $pdo->prepare('SELECT COUNT(*) FROM contracts WHERE customer_id = ?');
$ctCountStmt->execute([$customerId]);
$contractsCount = (int) $ctCountStmt->fetchColumn();

$couponCountStmt = $pdo->prepare('SELECT COUNT(*) FROM coupons WHERE customer_id = ? AND used_at IS NULL AND (valid_until IS NULL OR valid_until >= CURDATE())');
$couponCountStmt->execute([$customerId]);
$activeCouponsCount = (int) $couponCountStmt->fetchColumn();

$response = [
    'customer' => [
        'id' => $customer['id'],
        'name' => $customer['name'],
        'phone' => $customer['phone'],
        'status' => $customer['status'],
        'branch_name' => $customer['branch_name'],
        'mileage_points' => (int) $customer['mileage_points'],
    ],
    'pass' => $pass ?: null,
    'week_reservations' => $weekReservations,
    'month_reservation_dates' => $monthDates,
    'recent_payments' => $recentPayments,
    'contracts_count' => $contractsCount,
    'active_coupons_count' => $activeCouponsCount,
];

if ($isStaff) {
    $noteStmt = $pdo->prepare('
        SELECT cn.*, s.name AS staff_name
        FROM customer_notes cn
        LEFT JOIN staff s ON s.id = cn.created_by
        WHERE cn.customer_id = ?
        ORDER BY cn.created_at DESC
        LIMIT 5
    ');
    $noteStmt->execute([$customerId]);
    $response['recent_notes'] = $noteStmt->fetchAll();

    $logStmt = $pdo->prepare('
        SELECT al.*, s.name AS staff_name
        FROM activity_logs al
        LEFT JOIN staff s ON s.id = al.created_by
        WHERE al.customer_id = ?
        ORDER BY al.created_at DESC
        LIMIT 5
    ');
    $logStmt->execute([$customerId]);
    $response['recent_logs'] = $logStmt->fetchAll();
}

echo json_encode($response);
