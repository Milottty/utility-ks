<?php
declare(strict_types=1);
define('IN_ADMIN', true);
require dirname(__DIR__) . '/includes/bootstrap.php';
require dirname(__DIR__) . '/includes/stats.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (!admin_logged()) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
$range = in_array($_GET['range'] ?? '', ['today', '7d', '30d', '12m'], true) ? $_GET['range'] : '7d';
[$labels, $values, $orders] = sales_series($range);
echo json_encode(['labels' => $labels, 'values' => $values, 'orders' => $orders, 'total' => array_sum($values)]);
