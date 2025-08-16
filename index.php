<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Include database connection
require_once __DIR__ . '/includes/database.php';
// Build dashboard metrics
$db = getDBConnection();

// Lightweight JSON endpoint for Sales chart
if (isset($_GET['action']) && $_GET['action'] === 'sales_chart') {
    header('Content-Type: application/json');
    $range = $_GET['range'] ?? 'week';
    $yearParam = isset($_GET['year']) ? (int)$_GET['year'] : null;
    $m1 = isset($_GET['m1']) ? (int)$_GET['m1'] : null;
    $m2 = isset($_GET['m2']) ? (int)$_GET['m2'] : null;
    $labels = [];
    $data = [];
    $txData = [];
    if ($range === 'today') {
        // today by hour (0-23)
        $stmt = $db->prepare("SELECT HOUR(transaction_date) h, SUM(total_amount) total, COUNT(*) tx
                              FROM sales_transactions
                              WHERE DATE(transaction_date) = CURDATE()
                              GROUP BY HOUR(transaction_date)
                              ORDER BY HOUR(transaction_date)");
        if ($stmt && $stmt->execute()) {
            $res = $stmt->get_result();
            $map = [];
            $mapTx = [];
            while ($row = $res->fetch_assoc()) { $map[(int)$row['h']] = (float)$row['total']; $mapTx[(int)$row['h']] = (int)$row['tx']; }
            for ($h = 0; $h < 24; $h++) {
                $labels[] = sprintf('%02d:00', $h);
                $data[] = $map[$h] ?? 0.0;
                $txData[] = $mapTx[$h] ?? 0;
            }
        }
        $sum = $db->query("SELECT 
            (SELECT COALESCE(SUM(total_amount),0) FROM sales_transactions WHERE DATE(transaction_date) = CURDATE()) AS amt,
            (SELECT COUNT(*) FROM sales_transactions WHERE DATE(transaction_date) = CURDATE()) AS tx");
        $sr = $sum ? $sum->fetch_assoc() : ['amt'=>0,'tx'=>0];
        $currAmt = (float)$sr['amt'];
        $currTx = (int)$sr['tx'];
        // previous day for change
        $prev = $db->query("SELECT 
            (SELECT COALESCE(SUM(total_amount),0) FROM sales_transactions WHERE DATE(transaction_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)) AS amt");
        $pr = $prev ? $prev->fetch_assoc() : ['amt'=>0];
        $prevAmt = (float)($pr['amt'] ?? 0);
        $change = ($prevAmt > 0) ? (($currAmt - $prevAmt) / $prevAmt) * 100.0 : null;
        // peak hour
        $peakIdx = -1; $peakVal = 0.0;
        foreach ($data as $i=>$v) { if ($v > $peakVal) { $peakVal = (float)$v; $peakIdx = $i; } }
        $summary = [
            'label' => 'Today',
            'amount' => $currAmt,
            'transactions' => $currTx,
            'avg' => $currTx > 0 ? $currAmt / max($currTx,1) : 0.0,
            'peak_label' => $labels[$peakIdx] ?? '',
            'peak_amount' => $peakVal,
            'change_pct' => $change,
        ];
    } elseif ($range === 'week') {
        // current calendar week (Mon–Sun)
        $stmt = $db->prepare("SELECT DATE(transaction_date) d, SUM(total_amount) total, COUNT(*) tx
                              FROM sales_transactions
                              WHERE YEARWEEK(transaction_date, 1) = YEARWEEK(CURDATE(), 1)
                              GROUP BY DATE(transaction_date)
                              ORDER BY DATE(transaction_date)");
        if ($stmt && $stmt->execute()) {
            $res = $stmt->get_result();
            $map = [];
            $mapTx = [];
            while ($row = $res->fetch_assoc()) { $map[$row['d']] = (float)$row['total']; $mapTx[$row['d']] = (int)$row['tx']; }
            $monday = date('Y-m-d', strtotime('monday this week'));
            for ($i = 0; $i < 7; $i++) {
                $d = date('Y-m-d', strtotime("+{$i} day", strtotime($monday)));
                $labels[] = date('D M d', strtotime($d));
                $data[] = isset($map[$d]) ? (float)$map[$d] : 0.0;
                $txData[] = isset($mapTx[$d]) ? (int)$mapTx[$d] : 0;
            }
        }
        // summary for current calendar week (Mon-Sun)
        $sum = $db->query("SELECT 
            (SELECT COALESCE(SUM(total_amount),0) FROM sales_transactions WHERE YEARWEEK(transaction_date, 1) = YEARWEEK(CURDATE(), 1)) AS amt,
            (SELECT COUNT(*) FROM sales_transactions WHERE YEARWEEK(transaction_date, 1) = YEARWEEK(CURDATE(), 1)) AS tx");
        $sr = $sum ? $sum->fetch_assoc() : ['amt'=>0,'tx'=>0];
        $currAmt = (float)$sr['amt'];
        $currTx = (int)$sr['tx'];
        // previous calendar week for change
        $sumPrev = $db->query("SELECT 
            (SELECT COALESCE(SUM(total_amount),0) FROM sales_transactions WHERE YEARWEEK(transaction_date, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)) AS amt");
        $spr = $sumPrev ? $sumPrev->fetch_assoc() : ['amt'=>0];
        $prevAmt = (float)($spr['amt'] ?? 0);
        $change = ($prevAmt > 0) ? (($currAmt - $prevAmt) / $prevAmt) * 100.0 : null;
        // peak day in week
        $peakIdx = -1; $peakVal = 0.0;
        foreach ($data as $i=>$v) { if ($v > $peakVal) { $peakVal = (float)$v; $peakIdx = $i; } }
        $summary = [
            'label' => 'This Week',
            'amount' => $currAmt,
            'transactions' => $currTx,
            'avg' => $currTx > 0 ? $currAmt / max($currTx,1) : 0.0,
            'peak_label' => $labels[$peakIdx] ?? '',
            'peak_amount' => $peakVal,
            'change_pct' => $change,
        ];
    } elseif ($range === 'month') {
        // selected year by month (defaults to current year)
        $year = $yearParam ?: (int)date('Y');
        $stmt = $db->prepare("SELECT DATE_FORMAT(transaction_date,'%Y-%m') m, SUM(total_amount) total, COUNT(*) tx
                              FROM sales_transactions
                              WHERE YEAR(transaction_date)=?
                              GROUP BY DATE_FORMAT(transaction_date,'%Y-%m')
                              ORDER BY DATE_FORMAT(transaction_date,'%Y-%m')");
        if ($stmt) {
            $stmt->bind_param('i', $year);
            $stmt->execute();
            $res = $stmt->get_result();
            $map = [];
            $mapTx = [];
            while ($row = $res->fetch_assoc()) { $map[$row['m']] = (float)$row['total']; $mapTx[$row['m']] = (int)$row['tx']; }
            for ($i = 1; $i <= 12; $i++) {
                $ym = $year . '-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                $labels[] = date('M', strtotime($ym . '-01'));
                $data[] = isset($map[$ym]) ? (float)$map[$ym] : 0.0;
                $txData[] = isset($mapTx[$ym]) ? (int)$mapTx[$ym] : 0;
            }
        }
        // Year-to-date summary
        $stmt2 = $db->prepare("SELECT 
            (SELECT COALESCE(SUM(total_amount),0) FROM sales_transactions WHERE YEAR(transaction_date)=?) AS amt,
            (SELECT COUNT(*) FROM sales_transactions WHERE YEAR(transaction_date)=?) AS tx");
        $stmt2->bind_param('ii', $year, $year);
        $stmt2->execute();
        $sum = $stmt2->get_result();
        $sr = $sum ? $sum->fetch_assoc() : ['amt'=>0,'tx'=>0];
        $currAmt = (float)$sr['amt'];
        $currTx = (int)$sr['tx'];
        // previous year YTD
        $prevYear = $year - 1;
        $stmt3 = $db->prepare("SELECT 
            (SELECT COALESCE(SUM(total_amount),0) FROM sales_transactions WHERE YEAR(transaction_date)=?) AS amt");
        $stmt3->bind_param('i', $prevYear);
        $stmt3->execute();
        $sumPrev = $stmt3->get_result();
        $spr = $sumPrev ? $sumPrev->fetch_assoc() : ['amt'=>0];
        $prevAmt = (float)($spr['amt'] ?? 0);
        $change = ($prevAmt > 0) ? (($currAmt - $prevAmt) / $prevAmt) * 100.0 : null;
        // peak month of current year
        $peakIdx = -1; $peakVal = 0.0;
        foreach ($data as $i=>$v) { if ($v > $peakVal) { $peakVal = (float)$v; $peakIdx = $i; } }
        $summary = [
            'label' => 'Monthly',
            'amount' => $currAmt,
            'transactions' => $currTx,
            'avg' => $currTx > 0 ? $currAmt / max($currTx,1) : 0.0,
            'peak_label' => $labels[$peakIdx] ?? '',
            'peak_amount' => $peakVal,
            'change_pct' => $change,
        ];
    } elseif ($range === 'year') {
        // If year provided: aggregate Jan..Dec of that year; else last 12 months
        if ($yearParam) {
            $stmt = $db->prepare("SELECT DATE_FORMAT(transaction_date,'%Y-%m') m, SUM(total_amount) total, COUNT(*) tx
                                   FROM sales_transactions
                                   WHERE YEAR(transaction_date)=?
                                   GROUP BY DATE_FORMAT(transaction_date,'%Y-%m')
                                   ORDER BY DATE_FORMAT(transaction_date,'%Y-%m')");
            if ($stmt) { $stmt->bind_param('i', $yearParam); $stmt->execute(); }
        } else {
            $stmt = $db->prepare("SELECT DATE_FORMAT(transaction_date,'%Y-%m') m, SUM(total_amount) total, COUNT(*) tx
                                   FROM sales_transactions
                                   WHERE transaction_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
                                   GROUP BY DATE_FORMAT(transaction_date,'%Y-%m')
                                   ORDER BY DATE_FORMAT(transaction_date,'%Y-%m')");
            if ($stmt) { $stmt->execute(); }
        }
        if ($stmt) {
            $res = $stmt->get_result();
            $map = [];
            $mapTx = [];
            while ($row = $res->fetch_assoc()) { $map[$row['m']] = (float)$row['total']; $mapTx[$row['m']] = (int)$row['tx']; }
            if ($yearParam) {
                for ($i = 1; $i <= 12; $i++) {
                    $m = sprintf('%04d-%02d', $yearParam, $i);
                    $labels[] = date('M Y', strtotime($m.'-01'));
                    $data[] = isset($map[$m]) ? (float)$map[$m] : 0.0;
                    $txData[] = isset($mapTx[$m]) ? (int)$mapTx[$m] : 0;
                }
            } else {
                for ($i = 11; $i >= 0; $i--) {
                    $m = date('Y-m', strtotime("first day of -{$i} month"));
                    $labels[] = date('M Y', strtotime($m . '-01'));
                    $data[] = isset($map[$m]) ? (float)$map[$m] : 0.0;
                    $txData[] = isset($mapTx[$m]) ? (int)$mapTx[$m] : 0;
                }
            }
        }
        if ($yearParam) {
            $stmt2 = $db->prepare("SELECT 
                (SELECT COALESCE(SUM(total_amount),0) FROM sales_transactions WHERE YEAR(transaction_date)=?) AS amt,
                (SELECT COUNT(*) FROM sales_transactions WHERE YEAR(transaction_date)=?) AS tx");
            $stmt2->bind_param('ii', $yearParam, $yearParam);
            $stmt2->execute();
            $sum = $stmt2->get_result();
        } else {
            $sum = $db->query("SELECT 
                (SELECT COALESCE(SUM(total_amount),0) FROM sales_transactions WHERE YEAR(transaction_date)=YEAR(CURDATE())) AS amt,
                (SELECT COUNT(*) FROM sales_transactions WHERE YEAR(transaction_date)=YEAR(CURDATE())) AS tx");
        }
        $sr = $sum ? $sum->fetch_assoc() : ['amt'=>0,'tx'=>0];
        $currAmt = (float)$sr['amt'];
        $currTx = (int)$sr['tx'];
        // previous calendar year
        $prevBaseYear = $yearParam ? $yearParam - 1 : (date('Y') - 1);
        $stmt3 = $db->prepare("SELECT 
            (SELECT COALESCE(SUM(total_amount),0) FROM sales_transactions WHERE YEAR(transaction_date)=?) AS amt");
        $stmt3->bind_param('i', $prevBaseYear);
        $stmt3->execute();
        $sumPrev = $stmt3->get_result();
        $spr = $sumPrev ? $sumPrev->fetch_assoc() : ['amt'=>0];
        $prevAmt = (float)($spr['amt'] ?? 0);
        $change = ($prevAmt > 0) ? (($currAmt - $prevAmt) / $prevAmt) * 100.0 : null;
        // peak month in last 12 months
        $peakIdx = -1; $peakVal = 0.0;
        foreach ($data as $i=>$v) { if ($v > $peakVal) { $peakVal = (float)$v; $peakIdx = $i; } }
        $summary = [
            'label' => 'Yearly',
            'amount' => $currAmt,
            'transactions' => $currTx,
            'avg' => $currTx > 0 ? $currAmt / max($currTx,1) : 0.0,
            'peak_label' => $labels[$peakIdx] ?? '',
            'peak_amount' => $peakVal,
            'change_pct' => $change,
        ];
    } elseif ($range === 'month_range') {
        // Aggregate by month over an inclusive month range in a given year
        $year = $yearParam ?: (int)date('Y');
        $m1 = $m1 ?: 1; $m2 = $m2 ?: (int)date('n');
        $m1 = max(1, min(12, (int)$m1));
        $m2 = max(1, min(12, (int)$m2));
        if ($m1 > $m2) { $tmp = $m1; $m1 = $m2; $m2 = $tmp; }
        $start = sprintf('%04d-%02d-01', $year, $m1);
        $end = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $m2)));
        $stmt = $db->prepare("SELECT DATE_FORMAT(transaction_date,'%Y-%m') m, SUM(total_amount) total, COUNT(*) tx
                               FROM sales_transactions
                               WHERE transaction_date BETWEEN ? AND ?
                               GROUP BY DATE_FORMAT(transaction_date,'%Y-%m')
                               ORDER BY DATE_FORMAT(transaction_date,'%Y-%m')");
        if ($stmt) {
            $startDt = $start . ' 00:00:00';
            $endDt = $end . ' 23:59:59';
            $stmt->bind_param('ss', $startDt, $endDt);
            $stmt->execute();
            $res = $stmt->get_result();
            $map = [];
            $mapTx = [];
            while ($row = $res->fetch_assoc()) { $map[$row['m']] = (float)$row['total']; $mapTx[$row['m']] = (int)$row['tx']; }
            for ($i = $m1; $i <= $m2; $i++) {
                $ym = sprintf('%04d-%02d', $year, $i);
                $labels[] = date('M', strtotime($ym.'-01'));
                $data[] = isset($map[$ym]) ? (float)$map[$ym] : 0.0;
                $txData[] = isset($mapTx[$ym]) ? (int)$mapTx[$ym] : 0;
            }
        }
        // Summary over the selected range (change vs prev omitted)
        $stmt2 = $db->prepare("SELECT 
            (SELECT COALESCE(SUM(total_amount),0) FROM sales_transactions WHERE transaction_date BETWEEN ? AND ?) AS amt,
            (SELECT COUNT(*) FROM sales_transactions WHERE transaction_date BETWEEN ? AND ?) AS tx");
        if ($stmt2) {
            $startDt = $start . ' 00:00:00';
            $endDt = $end . ' 23:59:59';
            $stmt2->bind_param('ssss', $startDt, $endDt, $startDt, $endDt);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            $sr = $res2 ? $res2->fetch_assoc() : ['amt'=>0,'tx'=>0];
        } else { $sr = ['amt'=>0,'tx'=>0]; }
        $currAmt = (float)($sr['amt'] ?? 0);
        $currTx = (int)($sr['tx'] ?? 0);
        $peakIdx = -1; $peakVal = 0.0;
        foreach ($data as $i=>$v) { if ($v > $peakVal) { $peakVal = (float)$v; $peakIdx = $i; } }
        $summary = [
            'label' => 'Month Range',
            'amount' => $currAmt,
            'transactions' => $currTx,
            'avg' => $currTx > 0 ? $currAmt / max($currTx,1) : 0.0,
            'peak_label' => $labels[$peakIdx] ?? '',
            'peak_amount' => $peakVal,
            'change_pct' => null,
        ];
    } else {
        // default to week if unknown
        $range = 'week';
        // current calendar week (Mon–Sun)
        $stmt = $db->prepare("SELECT DATE(transaction_date) d, SUM(total_amount) total
                              FROM sales_transactions
                              WHERE YEARWEEK(transaction_date, 1) = YEARWEEK(CURDATE(), 1)
                              GROUP BY DATE(transaction_date)
                              ORDER BY DATE(transaction_date)");
        if ($stmt && $stmt->execute()) {
            $res = $stmt->get_result();
            $map = [];
            while ($row = $res->fetch_assoc()) { $map[$row['d']] = (float)$row['total']; }
            $monday = date('Y-m-d', strtotime('monday this week'));
            for ($i = 0; $i < 7; $i++) {
                $d = date('Y-m-d', strtotime("+{$i} day", strtotime($monday)));
                $labels[] = date('D M d', strtotime($d));
                $data[] = isset($map[$d]) ? (float)$map[$d] : 0.0;
            }
        }
        $sum = $db->query("SELECT 
            (SELECT COALESCE(SUM(total_amount),0) FROM sales_transactions WHERE YEARWEEK(transaction_date, 1) = YEARWEEK(CURDATE(), 1)) AS amt,
            (SELECT COUNT(*) FROM sales_transactions WHERE YEARWEEK(transaction_date, 1) = YEARWEEK(CURDATE(), 1)) AS tx");
        $sr = $sum ? $sum->fetch_assoc() : ['amt'=>0,'tx'=>0];
        $currAmt = (float)$sr['amt'];
        $currTx = (int)$sr['tx'];
        $sumPrev = $db->query("SELECT 
            (SELECT COALESCE(SUM(total_amount),0) FROM sales_transactions WHERE YEARWEEK(transaction_date, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)) AS amt");
        $spr = $sumPrev ? $sumPrev->fetch_assoc() : ['amt'=>0];
        $prevAmt = (float)($spr['amt'] ?? 0);
        $change = ($prevAmt > 0) ? (($currAmt - $prevAmt) / $prevAmt) * 100.0 : null;
        $peakIdx = -1; $peakVal = 0.0;
        foreach ($data as $i=>$v) { if ($v > $peakVal) { $peakVal = (float)$v; $peakIdx = $i; } }
        $summary = [
            'label' => 'This Week',
            'amount' => $currAmt,
            'transactions' => $currTx,
            'avg' => $currTx > 0 ? $currAmt / max($currTx,1) : 0.0,
            'peak_label' => $labels[$peakIdx] ?? '',
            'peak_amount' => $peakVal,
            'change_pct' => $change,
        ];
    }
    echo json_encode(['labels' => $labels, 'data' => $data, 'tx' => $txData, 'summary' => ($summary ?? null)]);
    exit;
}

// Lightweight JSON endpoint for Popular Items (top by qty or revenue)
if (isset($_GET['action']) && $_GET['action'] === 'popular_items') {
    header('Content-Type: application/json');
    $range = $_GET['range'] ?? 'week';
    $metric = $_GET['metric'] ?? 'qty'; // 'qty' or 'revenue'
    $year = isset($_GET['year']) ? (int)$_GET['year'] : null;
    $m1 = isset($_GET['m1']) ? (int)$_GET['m1'] : null;
    $m2 = isset($_GET['m2']) ? (int)$_GET['m2'] : null;

    $where = '';
    $params = [];
    $types = '';
    // Build date filter
    if ($range === 'today') {
        $where = 'DATE(st.transaction_date) = CURDATE()';
    } elseif ($range === 'week') {
        $where = 'YEARWEEK(st.transaction_date, 1) = YEARWEEK(CURDATE(), 1)';
    } elseif ($range === 'month') {
        // specific year if provided, else current year
        if ($year) {
            $where = 'YEAR(st.transaction_date) = ?';
            $types .= 'i';
            $params[] = $year;
        } else {
            $where = 'YEAR(st.transaction_date) = YEAR(CURDATE())';
        }
    } elseif ($range === 'year') {
        // specific calendar year if provided; else current year
        if ($year) {
            $where = 'YEAR(st.transaction_date) = ?';
            $types .= 'i';
            $params[] = $year;
        } else {
            $where = 'YEAR(st.transaction_date) = YEAR(CURDATE())';
        }
    } elseif ($range === 'month_range') {
        // requires year, m1, m2; fallback to current year full if invalid
        if ($year && $m1 && $m2) {
            $m1 = max(1, min(12, $m1));
            $m2 = max(1, min(12, $m2));
            if ($m1 > $m2) { $tmp = $m1; $m1 = $m2; $m2 = $tmp; }
            $start = sprintf('%04d-%02d-01', $year, $m1);
            $end = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $m2)));
            $where = 'st.transaction_date BETWEEN ? AND ?';
            $types .= 'ss';
            $params[] = $start . ' 00:00:00';
            $params[] = $end . ' 23:59:59';
        } else {
            $where = 'YEAR(st.transaction_date) = YEAR(CURDATE())';
        }
    } else {
        $where = 'YEARWEEK(st.transaction_date, 1) = YEARWEEK(CURDATE(), 1)';
    }

    $order = ($metric === 'revenue') ? 'SUM(si.total_price) DESC' : 'SUM(si.quantity) DESC';
    $sql = "SELECT i.id, i.name, SUM(si.quantity) AS qty, SUM(si.total_price) AS revenue
            FROM sales_transaction_items si
            JOIN sales_transactions st ON st.id = si.sales_transaction_id
            JOIN items i ON i.id = si.item_id
            WHERE $where
            GROUP BY i.id, i.name
            ORDER BY $order
            LIMIT 10";
    $rows = [];
    if ($types) {
        $stmt = $db->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) { $rows[] = $r; }
            }
        }
    } else {
        $q = $db->query($sql);
        if ($q) { while ($r = $q->fetch_assoc()) { $rows[] = $r; } }
    }
    echo json_encode(['items' => $rows]);
    exit;
}

// Include the dashboard header AFTER the JSON endpoint so AJAX responses remain clean
require_once __DIR__ . '/templates/header.php';

// Sales: current calendar week (Mon–Sun) initial data
$salesChartLabels = [];
$salesChartData = [];
$stmt = $db->prepare("SELECT DATE(transaction_date) d, SUM(total_amount) total
                      FROM sales_transactions
                      WHERE YEARWEEK(transaction_date, 1) = YEARWEEK(CURDATE(), 1)
                      GROUP BY DATE(transaction_date)
                      ORDER BY DATE(transaction_date)");
if ($stmt && $stmt->execute()) {
    $res = $stmt->get_result();
    $map = [];
    while ($row = $res->fetch_assoc()) { $map[$row['d']] = (float)$row['total']; }
    $monday = date('Y-m-d', strtotime('monday this week'));
    for ($i = 0; $i < 7; $i++) {
        $d = date('Y-m-d', strtotime("+{$i} day", strtotime($monday)));
        $salesChartLabels[] = date('D M d', strtotime($d));
        $salesChartData[] = isset($map[$d]) ? (float)$map[$d] : 0.0;
    }
}

// Sales summary (today / this week / this month)
$one = $db->query("SELECT 
    (SELECT COALESCE(SUM(total_amount),0) FROM sales_transactions WHERE DATE(transaction_date) = CURDATE()) AS sales_today,
    (SELECT COALESCE(SUM(total_amount),0) FROM sales_transactions WHERE YEARWEEK(transaction_date, 1) = YEARWEEK(CURDATE(), 1)) AS sales_week,
    (SELECT COALESCE(SUM(total_amount),0) FROM sales_transactions WHERE YEAR(transaction_date) = YEAR(CURDATE()) AND MONTH(transaction_date) = MONTH(CURDATE())) AS sales_month,
    (SELECT COUNT(*) FROM sales_transactions WHERE DATE(transaction_date) = CURDATE()) AS tx_today,
    (SELECT COUNT(*) FROM sales_transactions WHERE YEARWEEK(transaction_date, 1) = YEARWEEK(CURDATE(), 1)) AS tx_week,
    (SELECT COUNT(*) FROM sales_transactions WHERE YEAR(transaction_date) = YEAR(CURDATE()) AND MONTH(transaction_date) = MONTH(CURDATE())) AS tx_month");
$row = $one ? $one->fetch_assoc() : null;
$salesToday = $row['sales_today'] ?? 0;
$salesWeek = $row['sales_week'] ?? 0;
$salesMonth = $row['sales_month'] ?? 0;
$txToday = (int)($row['tx_today'] ?? 0);
$txWeek = (int)($row['tx_week'] ?? 0);
$txMonth = (int)($row['tx_month'] ?? 0);

// Deliveries today and this week
$deliveriesToday = [];
$qToday = $db->query(
    "SELECT d.id, d.purchase_order_id, d.delivery_date, d.status, po.po_number
     FROM deliveries d
     LEFT JOIN purchase_orders po ON po.id = d.purchase_order_id
     WHERE d.delivery_date = CURDATE()
     ORDER BY d.id DESC"
);
if ($qToday) { while ($r = $qToday->fetch_assoc()) { $deliveriesToday[] = $r; } }

$delivCounts = [ 'today' => 0, 'week' => 0, 'pending_today' => 0 ];
$qCounts = $db->query("SELECT 
    (SELECT COUNT(*) FROM deliveries WHERE delivery_date = CURDATE()) AS c_today,
    (SELECT COUNT(*) FROM deliveries WHERE YEARWEEK(delivery_date, 1) = YEARWEEK(CURDATE(), 1)) AS c_week,
    (SELECT COUNT(*) FROM deliveries WHERE delivery_date = CURDATE() AND status = 'pending') AS c_pending_today");
if ($qCounts) {
    $rc = $qCounts->fetch_assoc();
    $delivCounts['today'] = (int)($rc['c_today'] ?? 0);
    $delivCounts['week'] = (int)($rc['c_week'] ?? 0);
    $delivCounts['pending_today'] = (int)($rc['c_pending_today'] ?? 0);
}

// Totals for header cards (fallbacks kept)
$totalProducts = $totalProducts ?? ($db->query("SELECT COUNT(*) c FROM items")->fetch_assoc()['c'] ?? 0);
$totalCustomers = $totalCustomers ?? ($db->query("SELECT COUNT(*) c FROM customers")->fetch_assoc()['c'] ?? 0);
$totalSales = $totalSales ?? ($db->query("SELECT COALESCE(SUM(total_amount),0) s FROM sales_transactions")->fetch_assoc()['s'] ?? 0);
// Total Purchase Orders
$totalPOs = (int)($db->query("SELECT COUNT(*) c FROM purchase_orders")->fetch_assoc()['c'] ?? 0);

// Additional system overview metrics
$totalSuppliers = (int)($db->query("SELECT COUNT(*) c FROM suppliers")->fetch_assoc()['c'] ?? 0);
$pendingDeliveriesAll = (int)($db->query("SELECT COUNT(*) c FROM deliveries WHERE status='pending'")->fetch_assoc()['c'] ?? 0);
$lowStockCount = (int)($db->query("SELECT COUNT(*) c FROM items WHERE reorder_level > 0 AND current_stock <= reorder_level")->fetch_assoc()['c'] ?? 0);
$outOfStockCount = (int)($db->query("SELECT COUNT(*) c FROM items WHERE current_stock <= 0")->fetch_assoc()['c'] ?? 0);

// Lists for low/out of stock items (top 10)
$lowStockItems = [];
$outOfStockItems = [];
$qLow = $db->query("SELECT id, name, current_stock, reorder_level FROM items WHERE reorder_level > 0 AND current_stock <= reorder_level ORDER BY current_stock ASC, name ASC LIMIT 10");
if ($qLow) { while ($r = $qLow->fetch_assoc()) { $lowStockItems[] = $r; } }
$qOut = $db->query("SELECT id, name, current_stock, reorder_level FROM items WHERE current_stock <= 0 ORDER BY name ASC LIMIT 10");
if ($qOut) { while ($r = $qOut->fetch_assoc()) { $outOfStockItems[] = $r; } }

// close only after queries used throughout the page if needed further
// $db->close();
?>

<div class="row">
    <!-- Welcome Card -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Welcome back, <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>!</h4>
                <p class="card-text">Here's what's happening with your store today.</p>
            </div>
        </div>
    </div>
    
    <!-- System Overview Cards (top) -->
    <div class="col-12 mb-4">
        <div class="row g-3">
            <div class="col-6 col-md-2">
                <a href="inventory/products/index.php" class="text-decoration-none text-reset">
                <div class="card stat-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Total Products</div>
                                <div class="h4 mb-0 fw-bold"><?= number_format($totalProducts) ?></div>
                            </div>
                            <span class="stat-icon bg-soft-primary text-primary"><i class="fas fa-boxes fa-lg"></i></span>
                        </div>
                    </div>
                </div>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="purchase_orders/index.php" class="text-decoration-none text-reset">
                <div class="card stat-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Total Purchase Orders</div>
                                <div class="h4 mb-0 fw-bold"><?= number_format($totalPOs) ?></div>
                            </div>
                            <span class="stat-icon bg-soft-danger text-danger"><i class="fas fa-file-invoice fa-lg"></i></span>
                        </div>
                    </div>
                </div>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="sales/index.php" class="text-decoration-none text-reset">
                <div class="card stat-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Total Sales</div>
                                <div class="h4 mb-0 fw-bold">₱<?= number_format((float)$totalSales, 2) ?></div>
                            </div>
                            <span class="stat-icon bg-soft-success text-success"><i class="fas fa-coins fa-lg"></i></span>
                        </div>
                    </div>
                </div>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="ledger/customers/index.php" class="text-decoration-none text-reset">
                <div class="card stat-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Total Customers</div>
                                <div class="h4 mb-0 fw-bold"><?= number_format($totalCustomers) ?></div>
                            </div>
                            <span class="stat-icon bg-soft-info text-info"><i class="fas fa-user-friends fa-lg"></i></span>
                        </div>
                    </div>
                </div>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="ledger/suppliers/index.php" class="text-decoration-none text-reset">
                <div class="card stat-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Total Suppliers</div>
                                <div class="h4 mb-0 fw-bold"><?= number_format($totalSuppliers) ?></div>
                            </div>
                            <span class="stat-icon bg-soft-secondary text-secondary"><i class="fas fa-truck fa-lg"></i></span>
                        </div>
                    </div>
                </div>
                </a>
            </div>
            <div class="col-6 col-md-2">
                <a href="deliveries/index.php" class="text-decoration-none text-reset">
                <div class="card stat-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Pending Deliveries</div>
                                <div class="h4 mb-0 fw-bold"><?= number_format($pendingDeliveriesAll) ?></div>
                            </div>
                            <span class="stat-icon bg-soft-warning text-warning"><i class="fas fa-shipping-fast fa-lg"></i></span>
                        </div>
                    </div>
                </div>
                </a>
            </div>
        </div>
    </div>

    <!-- Sales Row: Chart + Sales/Transactions Summary -->
    <div class="col-12 mb-4">
        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Sales</h5>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <label for="salesRange" class="me-1 small text-muted">Range</label>
                            <select id="salesRange" class="form-select form-select-sm" style="min-width: 160px;">
                                <option value="today">Today</option>
                                <option value="week" selected>This Week</option>
                                <option value="month">Monthly</option>
                                <option value="year">Yearly</option>
                                <option value="month_range">Month Range</option>
                            </select>
                            <label for="salesYear" class="ms-2 me-1 small text-muted">Year</label>
                            <select id="salesYear" class="form-select form-select-sm" style="min-width: 100px;"></select>
                            <label for="monthFrom" class="ms-2 me-1 small text-muted">From</label>
                            <select id="monthFrom" class="form-select form-select-sm" style="min-width: 100px;"></select>
                            <label for="monthTo" class="ms-2 me-1 small text-muted">To</label>
                            <select id="monthTo" class="form-select form-select-sm" style="min-width: 100px;"></select>
                            <button id="downloadCsvBtn" class="btn btn-outline-secondary btn-sm" type="button" title="Download CSV">
                                <i class="fas fa-file-csv"></i> CSV
                            </button>
                            <button id="downloadPdfBtn" class="btn btn-outline-secondary btn-sm" type="button" title="Download PDF (chart + summary)">
                                <i class="fas fa-file-pdf"></i> PDF
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="sales7Chart" height="120"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Summary: <span id="salesSummaryLabel">This Week</span></h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="text-muted small">Sales</div>
                            <div class="h5 mb-0" id="salesSummaryAmount">₱<?= number_format((float)$salesWeek, 2) ?></div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="text-muted small">Transactions</div>
                            <div class="h6 mb-0" id="salesSummaryTx"><?= number_format((int)$txWeek) ?></div>
                        </div>
                        <hr class="my-2" />
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="text-muted small">Avg Order Value</div>
                            <div class="small fw-semibold" id="salesSummaryAvg">—</div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div class="text-muted small">Peak Period</div>
                            <div class="small" id="salesSummaryPeak">—</div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted small">Change vs Prev</div>
                            <div class="small" id="salesSummaryChange">—</div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    <!-- Row 1: Popular Items (8) + Inventory Alerts (4) -->
    <div class="col-12 mb-4">
        <div class="row g-4 align-items-stretch">
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-fire text-danger me-2"></i>Popular Items</h5>
                        <div class="d-flex align-items-center gap-2">
                            <label for="popularMetric" class="me-1 small text-muted">Metric</label>
                            <select id="popularMetric" class="form-select form-select-sm" style="min-width: 140px;">
                                <option value="qty" selected>By Quantity</option>
                                <option value="revenue">By Revenue</option>
                            </select>
                            <label for="popularView" class="ms-2 me-1 small text-muted">View</label>
                            <select id="popularView" class="form-select form-select-sm" style="min-width: 120px;">
                                <option value="chart" selected>Bar Chart</option>
                                <option value="table">Table</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-3" id="popularChartWrap">
                            <canvas id="popularChart" height="220" aria-label="Popular Items Chart"></canvas>
                        </div>
                        <div class="table-responsive" id="popularTableWrap" style="max-height: 420px; overflow: auto; display: none;">
                            <table class="table table-sm table-hover mb-0 align-middle" id="popularTable">
                                <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                    <tr>
                                        <th style="width:60px;">#</th>
                                        <th>Item</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="4" class="text-center text-muted py-4">Loading…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-triangle-exclamation text-warning me-2"></i>Inventory Alerts
                            <span class="badge bg-warning text-dark ms-2"><?= number_format($lowStockCount + $outOfStockCount) ?></span>
                        </h5>
                        <a href="inventory/products/index.php" class="small text-decoration-none"><i class="fas fa-arrow-up-right-from-square me-1"></i>View all</a>
                    </div>
                    <div class="card-body p-0">
                        <?php 
                    // build combined list (max 15)
                    $combined = [];
                    foreach ($outOfStockItems as $it) { $it['__status'] = 'out'; $combined[] = $it; }
                    foreach ($lowStockItems as $it) { $it['__status'] = 'low'; $combined[] = $it; }
                    // sort: out first, then by stock asc, then name
                    usort($combined, function($a,$b){
                        $priority = ['out'=>0,'low'=>1];
                        $pa = $priority[$a['__status']] ?? 2; $pb = $priority[$b['__status']] ?? 2;
                        if ($pa !== $pb) return $pa - $pb;
                        $sa = (int)$a['current_stock']; $sb = (int)$b['current_stock'];
                        if ($sa !== $sb) return $sa - $sb;
                        return strcasecmp($a['name'], $b['name']);
                    });
                    $combined = array_slice($combined, 0, 15);
                    // Split into groups
                    $outs = [];
                    $lows = [];
                    foreach ($combined as $it) {
                        if (($it['__status'] ?? '') === 'out') { $outs[] = $it; }
                        else { $lows[] = $it; }
                    }
                    ?>
                    <?php if (empty($combined)): ?>
                        <div class="p-3 text-muted">No items require attention</div>
                    <?php else: ?>
                        <div class="px-0" style="max-height: 420px; overflow: auto;">
                            <?php if (!empty($outs)): ?>
                                <div class="px-3 py-2 bg-light border-top small fw-semibold" style="position: sticky; top: 0; z-index: 1;">Out of stock <span class="badge bg-danger ms-2"><?= count($outs) ?></span></div>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($outs as $it): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div class="me-3">
                                                <div class="fw-semibold"><?= htmlspecialchars($it['name']) ?></div>
                                                <div class="small text-muted">Stock: <?= (int)$it['current_stock'] ?> • Reorder at <?= (int)$it['reorder_level'] ?></div>
                                            </div>
                                            <span class="badge bg-danger">Out</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if (!empty($lows)): ?>
                                <div class="px-3 py-2 bg-light border-top small fw-semibold" style="position: sticky; top: <?= !empty($outs) ? '0' : '0' ?>; z-index: 1;">Low stock <span class="badge bg-warning text-dark ms-2"><?= count($lows) ?></span></div>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($lows as $it): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div class="me-3">
                                                <div class="fw-semibold"><?= htmlspecialchars($it['name']) ?></div>
                                                <div class="small text-muted">Stock: <?= (int)$it['current_stock'] ?> • Reorder at <?= (int)$it['reorder_level'] ?></div>
                                            </div>
                                            <span class="badge bg-warning text-dark">Low</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Today's Deliveries (3) + Customers with Outstanding Balance (9) -->
    <div class="col-12 mb-4">
        <div class="row g-4 align-items-stretch">
            <div class="col-12 col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Today's Deliveries</h5>
                        <span class="badge bg-secondary">Pending: <?= number_format($delivCounts['pending_today']) ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($deliveriesToday)): ?>
                            <div class="text-center text-muted py-4">No deliveries today</div>
                        <?php else: ?>
                            <div class="table-responsive" style="max-height: 420px; overflow: auto;">
                                <table class="table table-sm table-hover mb-0 align-middle">
                                    <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                        <tr>
                                            <th>ID</th>
                                            <th>PO #</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deliveriesToday as $d): ?>
                                            <tr>
                                                <td>#<?= (int)$d['id'] ?></td>
                                                <td>
                                                    <?php if (!empty($d['purchase_order_id'])): ?>
                                                        <a href="purchase_orders/view.php?id=<?= (int)$d['purchase_order_id'] ?>" class="text-decoration-none">
                                                            <?= htmlspecialchars($d['po_number'] ?? '') ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($d['po_number'] ?? '') ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($d['delivery_date']) ?></td>
                                                <td>
                                                    <?php $badge = ['pending'=>'warning','delivered'=>'success','cancelled'=>'danger'][$d['status']] ?? 'secondary'; ?>
                                                    <span class="badge bg-<?= $badge ?> text-uppercase"><?= htmlspecialchars($d['status']) ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-users text-primary me-2"></i>Customers with Outstanding Balance</h5>
                        <a href="ledger/customers/index.php" class="small text-decoration-none"><i class="fas fa-book me-1"></i>Open Ledger</a>
                    </div>
                    <div class="card-body p-0">
                        <?php
                        try {
                            $sql = "SELECT c.id, c.name, SUM(sd.balance_due) AS balance, COUNT(*) AS debts, MIN(sd.due_date) AS next_due
                                    FROM sales_debts sd
                                    JOIN customers c ON c.id = sd.customer_id
                                    WHERE sd.balance_due > 0 AND sd.status IN ('unpaid','partial')
                                    GROUP BY c.id, c.name
                                    ORDER BY balance DESC
                                    LIMIT 10";
                            $res = $db->query($sql);
                            $debtors = [];
                            if ($res) { while ($row = $res->fetch_assoc()) { $debtors[] = $row; } }
                        } catch (Exception $e) { $debtors = []; }
                        ?>
                        <?php if (empty($debtors)): ?>
                            <div class="text-center text-muted py-4">No outstanding balances</div>
                        <?php else: ?>
                            <div class="table-responsive" style="max-height: 420px; overflow: auto;">
                                <table class="table table-sm table-hover mb-0 align-middle">
                                    <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                        <tr>
                                            <th>Customer</th>
                                            <th class="text-end">Outstanding</th>
                                            <th class="text-end">Debts</th>
                                            <th class="text-nowrap">Next Due</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($debtors as $d): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($d['name'] ?? '') ?></td>
                                                <td class="text-end">₱<?= number_format((float)($d['balance'] ?? 0), 2) ?></td>
                                                <td class="text-end"><?= number_format((int)($d['debts'] ?? 0)) ?></td>
                                                <td class="text-nowrap">
                                                    <?= htmlspecialchars($d['next_due'] ?? '') ?: '—' ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  try {
    const ctx = document.getElementById('sales7Chart');
    const rangeSel = document.getElementById('salesRange');
    const yearSel = document.getElementById('salesYear');
    const mFromSel = document.getElementById('monthFrom');
    const mToSel = document.getElementById('monthTo');
    const popularMetricSel = document.getElementById('popularMetric');
    const popularViewSel = document.getElementById('popularView');
    const popularChartCanvas = document.getElementById('popularChart');
    let popularChart = null;
    if (ctx) {
      const chart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: <?= json_encode($salesChartLabels, JSON_UNESCAPED_UNICODE) ?>,
          datasets: [{
            label: 'Sales (₱)',
            data: <?= json_encode($salesChartData, JSON_NUMERIC_CHECK) ?>,
            tension: .25,
            fill: true,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13,110,253,0.15)',
            pointRadius: 3,
          }]
        },
        options: {
          plugins: { legend: { display: false } },
          scales: {
            y: { beginAtZero: true, ticks: { callback: (v)=>'₱' + Number(v).toLocaleString(undefined,{minimumFractionDigits:0,maximumFractionDigits:0}) } },
          }
        }
      });

      function monthName(n){return ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][n-1];}
      function ensureFilterControls(){
        // Populate year selector with range currentYear-4..currentYear+0
        if (yearSel && yearSel.options.length === 0) {
          const yNow = new Date().getFullYear();
          for (let y = yNow; y >= yNow - 4; y--) {
            const opt = document.createElement('option');
            opt.value = String(y); opt.textContent = String(y);
            yearSel.appendChild(opt);
          }
          yearSel.value = String(yNow);
        }
        // Populate months
        if (mFromSel && mFromSel.options.length === 0) {
          for (let m=1;m<=12;m++) {
            const o1=document.createElement('option'); o1.value=String(m); o1.textContent=monthName(m); mFromSel.appendChild(o1);
            const o2=document.createElement('option'); o2.value=String(m); o2.textContent=monthName(m); mToSel.appendChild(o2);
          }
          mFromSel.value='1';
          mToSel.value=String(new Date().getMonth()+1);
        }
        // Show/hide month range controls
        const r = rangeSel?.value || 'week';
        const showYear = r==='month' || r==='year' || r==='month_range';
        const showMonths = r==='month_range';
        const yearLbl = document.querySelector('label[for="salesYear"]');
        const mFromLbl = document.querySelector('label[for="monthFrom"]');
        const mToLbl = document.querySelector('label[for="monthTo"]');
        [yearSel, yearLbl].forEach(el=>{ if (el) el.classList.toggle('d-none', !showYear); });
        [mFromSel, mFromLbl].forEach(el=>{ if (el) el.classList.toggle('d-none', !showMonths); });
        [mToSel, mToLbl].forEach(el=>{ if (el) el.classList.toggle('d-none', !showMonths); });
      }

      async function loadSales(range) {
        try {
          ensureFilterControls();
          const y = yearSel?.value || '';
          const m1 = mFromSel?.value || '';
          const m2 = mToSel?.value || '';
          const params = new URLSearchParams({ action:'sales_chart', range, t:String(Date.now()) });
          if (y && (range==='month' || range==='year' || range==='month_range')) params.set('year', y);
          if (range==='month_range') { params.set('m1', m1); params.set('m2', m2); }
          const res = await fetch(`index.php?${params.toString()}`, { cache: 'no-store' });
          if (!res.ok) return;
          const json = await res.json();
          chart.data.labels = json.labels || [];
          chart.data.datasets[0].data = json.data || [];
          chart.update();
          // keep latest dataset for exports
          window.currentSales = json;
          // Update KPI summary
          if (json.summary) {
            const lbl = document.getElementById('salesSummaryLabel');
            const amt = document.getElementById('salesSummaryAmount');
            const tx = document.getElementById('salesSummaryTransactions');
            const avg = document.getElementById('salesSummaryAvg');
            const peak = document.getElementById('salesSummaryPeak');
            const chg = document.getElementById('salesSummaryChange');
            if (lbl) lbl.textContent = json.summary.label || '';
            if (amt) amt.textContent = '₱' + (json.summary.amount||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
            if (tx) tx.textContent = (json.summary.transactions||0).toLocaleString();
            if (avg) avg.textContent = '₱' + (json.summary.avg||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
            if (peak) peak.textContent = `${json.summary.peak_label||''} ₱${(json.summary.peak_amount||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}`;
            if (chg) {
              const pct = (json.summary.change_pct===null||json.summary.change_pct===undefined)?null:Number(json.summary.change_pct);
              if (pct===null) { chg.textContent = '—'; chg.classList.remove('text-success','text-danger'); }
              else {
                chg.textContent = `${pct.toFixed(1)}%`;
                chg.classList.remove('text-success','text-danger');
              }
            }
          }
        } catch (err) { console.error(err); }
      }

      async function loadPopular() {
        try {
          const range = rangeSel?.value || 'week';
          const metric = popularMetricSel?.value || 'qty';
          const y = yearSel?.value || '';
          const m1 = mFromSel?.value || '';
          const m2 = mToSel?.value || '';
          const params = new URLSearchParams({ action:'popular_items', range, metric, t:String(Date.now()) });
          if (y && (range==='month' || range==='year' || range==='month_range')) params.set('year', y);
          if (range==='month_range') { params.set('m1', m1); params.set('m2', m2); }
          const res = await fetch(`index.php?${params.toString()}`, { cache: 'no-store' });
          const json = await res.json();
          const tbody = document.querySelector('#popularTable tbody');
          const chartWrap = document.getElementById('popularChartWrap');
          const tableWrap = document.getElementById('popularTableWrap');
          // Toggle visibility based on selected view
          const view = popularViewSel?.value || 'chart';
          if (chartWrap && tableWrap) {
            const showChart = view === 'chart';
            chartWrap.style.display = showChart ? '' : 'none';
            tableWrap.style.display = showChart ? 'none' : '';
          }
          if (!tbody) return;
          tbody.innerHTML = '';
          const items = Array.isArray(json.items) ? json.items : [];
          if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No data</td></tr>';
            // Also clear chart if visible
            if (popularChart && popularChartCanvas) {
              popularChart.data.labels = [];
              popularChart.data.datasets[0].data = [];
              popularChart.update();
            }
            return;
          }
          items.forEach((it, idx)=>{
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${idx+1}</td>
              <td>${(it.name||'').toString().replace(/[<>&]/g, s=>({'<':'&lt;','>':'&gt;','&':'&amp;'}[s]))}</td>
              <td class="text-end">${Number(it.qty||0).toLocaleString()}</td>
              <td class="text-end">₱${Number(it.revenue||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</td>
            `;
            tbody.appendChild(tr);
          });

          // Update/Create Popular Items Bar Chart
          if (popularChartCanvas) {
            const labels = items.map(it => String(it.name||''));
            const data = items.map(it => metric === 'revenue' ? Number(it.revenue||0) : Number(it.qty||0));
            const dsLabel = metric === 'revenue' ? 'Revenue (₱)' : 'Quantity';
            if (!popularChart) {
              popularChart = new Chart(popularChartCanvas, {
                type: 'bar',
                data: { labels, datasets: [{
                  label: dsLabel,
                  data,
                  backgroundColor: metric === 'revenue' ? 'rgba(25,135,84,0.5)' : 'rgba(13,110,253,0.5)',
                  borderColor: metric === 'revenue' ? '#198754' : '#0d6efd',
                  borderWidth: 1
                }] },
                options: {
                  plugins: { legend: { display: false } },
                  responsive: true,
                  maintainAspectRatio: false,
                  scales: {
                    y: {
                      beginAtZero: true,
                      ticks: {
                        callback: (v)=> metric === 'revenue' ? '₱' + Number(v).toLocaleString() : Number(v).toLocaleString()
                      }
                    },
                    x: {
                      ticks: {
                        callback: (v, i)=> {
                          const lbl = labels[i] || '';
                          return lbl.length > 16 ? lbl.slice(0, 16) + '…' : lbl;
                        }
                      }
                    }
                  }
                }
              });
            } else {
              popularChart.data.labels = labels;
              popularChart.data.datasets[0].data = data;
              popularChart.data.datasets[0].label = dsLabel;
              popularChart.data.datasets[0].backgroundColor = metric === 'revenue' ? 'rgba(25,135,84,0.5)' : 'rgba(13,110,253,0.5)';
              popularChart.data.datasets[0].borderColor = metric === 'revenue' ? '#198754' : '#0d6efd';
              popularChart.update();
            }
          }
        } catch (e) { console.error(e); }
      }

      if (rangeSel) {
        rangeSel.addEventListener('change', (e)=>{
          ensureFilterControls();
          loadSales(e.target.value);
          loadPopular();
        });
        // initial sync (ensures KPI aligns even if server defaults differ)
        ensureFilterControls();
        loadSales(rangeSel.value);
      }

      yearSel?.addEventListener('change', ()=>{ loadSales(rangeSel.value); loadPopular(); });
      mFromSel?.addEventListener('change', ()=>{ if (rangeSel.value==='month_range') { loadSales(rangeSel.value); loadPopular(); } });
      mToSel?.addEventListener('change', ()=>{ if (rangeSel.value==='month_range') { loadSales(rangeSel.value); loadPopular(); } });
      popularMetricSel?.addEventListener('change', ()=>{ loadPopular(); });
      popularViewSel?.addEventListener('change', ()=>{ loadPopular(); });

      // Initial load of popular items (chart by default)
      loadPopular();

      // Download CSV
      const csvBtn = document.getElementById('downloadCsvBtn');
      if (csvBtn) {
        csvBtn.addEventListener('click', ()=>{
          const labels = chart.data.labels || [];
          const data = chart.data.datasets?.[0]?.data || [];
          const tx = (window.currentSales && Array.isArray(window.currentSales.tx)) ? window.currentSales.tx : [];
          const summary = window.currentSales?.summary || null;
          let csv = '';
          // Summary block
          if (summary) {
            const safe = (v)=> (v==null? '': String(v));
            const money = (n)=> Number(n||0).toFixed(2);
            const pct = (p)=> (p==null? '': `${p>0?'+':''}${Number(p).toFixed(1)}%`);
            csv += 'Summary\n';
            csv += `Label,${safe(summary.label)}\n`;
            csv += `Sales,${money(summary.amount)}\n`;
            csv += `Transactions,${Number(summary.transactions||0)}\n`;
            csv += `Avg Order Value,${money(summary.avg)}\n`;
            csv += `Peak Period,${safe(summary.peak_label)}\n`;
            csv += `Peak Amount,${money(summary.peak_amount)}\n`;
            csv += `Change vs Prev,${pct(summary.change_pct)}\n`;
            csv += '\n';
          }
          // Detail block
          csv += 'Period,Sales,Transactions\n';
          for (let i=0;i<labels.length;i++) {
            const label = String(labels[i]).replaceAll('"','""');
            const amt = Number(data[i] || 0);
            const t = Number(tx[i] || 0);
            csv += `"${label}",${amt},${t}\n`;
          }
          const csvWithBom = '\uFEFF' + csv; // add BOM for Excel
          const blob = new Blob([csvWithBom], { type: 'text/csv;charset=utf-8;' });
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          const range = document.getElementById('salesRange')?.value || 'range';
          a.download = `sales_${range}.csv`;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          URL.revokeObjectURL(url);
        });
      }

      // Download PDF (chart + summary)
      function ensureJsPDF() {
        return new Promise((resolve, reject) => {
          if (window.jspdf && window.jspdf.jsPDF) return resolve(window.jspdf.jsPDF);
          const s = document.createElement('script');
          s.src = 'https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js';
          s.onload = () => resolve(window.jspdf.jsPDF);
          s.onerror = () => reject(new Error('Failed to load jsPDF'));
          document.head.appendChild(s);
        });
      }

      const pdfBtn = document.getElementById('downloadPdfBtn');
      if (pdfBtn) {
        pdfBtn.addEventListener('click', async ()=>{
          try {
            const jsPDF = await ensureJsPDF();
            const range = document.getElementById('salesRange')?.value || 'range';
            const dataUrl = chart.toBase64Image('image/png', 1.0);
            const current = window.currentSales || {};
            const summary = current.summary || null;
            const labels = chart.data.labels || [];
            const data = chart.data.datasets?.[0]?.data || [];
            const tx = Array.isArray(current.tx) ? current.tx : [];

            // Create landscape A4 PDF in points
            const doc = new jsPDF({ orientation: 'landscape', unit: 'pt', format: 'a4' });
            const pageW = doc.internal.pageSize.getWidth();
            const pageH = doc.internal.pageSize.getHeight();
            const margin = 36; // 0.5in

            // Header
            doc.setFontSize(16);
            doc.text('Sales Report', margin, margin + 10);
            doc.setFontSize(10);
            doc.text(`Range: ${range}`, margin, margin + 28);
            const ts = new Date().toLocaleString();
            doc.text(`Generated: ${ts}`, margin, margin + 42);

            // Full-width chart
            let y = margin + 60;
            if (dataUrl) {
              const chartW = pageW - margin * 2;
              const chartH = Math.min(pageH * 0.45, chartW * 0.5); // keep room for summary
              doc.addImage(dataUrl, 'PNG', margin, y, chartW, chartH);
              y += chartH + 18;
            }

            // Summary below chart
            doc.setFontSize(12);
            if (summary) {
              // Simple formatting per request (no currency symbol)
              const money = (n)=> Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
              const pct = (p)=> {
                if (p==null) return '-';
                const sign = p>0 ? '+' : (p<0 ? '-' : '');
                return `${sign} ${Math.abs(Number(p)).toFixed(1)}%`;
              };
              const lines = [
                ['Label', summary.label || ''],
                ['Sales', money(summary.amount)],
                ['Transactions', Number(summary.transactions||0).toLocaleString()],
                ['Avg Order Value', money(summary.avg)],
                ['Peak Period', summary.peak_label || '-'],
                ['Peak Amount', money(summary.peak_amount)],
                ['Change vs Prev', pct(summary.change_pct)],
              ];
              const labelW = 160;
              lines.forEach(([k,v])=>{
                if (y > pageH - margin) {
                  doc.addPage('a4', 'landscape');
                  y = margin;
                }
                doc.text(String(k), margin, y);
                doc.text(String(v), margin + labelW, y);
                y += 18;
              });
            } else {
              doc.text('Summary unavailable (load data first).', margin, y);
              y += 18;
            }

            // Data table on a new page
            doc.addPage('a4', 'landscape');
            let ty = margin;
            doc.setFontSize(14);
            doc.text('Sales Data', margin, ty);
            ty += 18;
            doc.setFontSize(11);
            const headers = ['Period', 'Sales', 'Transactions'];
            const colW = [300, 150, 150];
            const drawHeader = () => {
              let x = margin;
              headers.forEach((h, i) => { doc.text(h, x, ty); x += colW[i]; });
              ty += 14;
              doc.setDrawColor(200);
              doc.line(margin, ty, margin + colW.reduce((a,b)=>a+b,0), ty);
              ty += 10;
            };
            drawHeader();

            const moneyPlain = (n)=> Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
            const drawRow = (period, sales, txv) => {
              if (ty > pageH - margin) {
                doc.addPage('a4', 'landscape');
                ty = margin;
                drawHeader();
              }
              let x = margin;
              doc.text(String(period), x, ty); x += colW[0];
              doc.text(moneyPlain(sales), x, ty); x += colW[1];
              doc.text(String(Number(txv||0).toLocaleString()), x, ty);
              ty += 16;
            };

            for (let i=0;i<labels.length;i++) {
              drawRow(String(labels[i]), Number(data[i]||0), Number(tx[i]||0));
            }

            doc.save(`sales_${range}.pdf`);
          } catch (err) {
            console.error(err);
            alert('Failed to generate PDF.');
          }
        });
      }

    }
  } catch (e) { console.error(e); }
});
</script>

<?php
// Include the dashboard footer
require_once __DIR__ . '/templates/footer.php';
?>