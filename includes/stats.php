<?php
declare(strict_types=1);

/** Shuma e shitjeve (pa porositë e anuluara) nga një datë e caktuar. */
function sales_since(?string $from): float
{
    if ($from === null) {
        return (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> 'cancelled'");
    }
    return (float)val("SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> 'cancelled' AND created_at >= ?", [$from]);
}

/** Të dhënat e grafikut: [labels[], values[], orders[]] */
function sales_series(string $range): array
{
    $labels = [];
    $buckets = [];
    $today = new DateTimeImmutable('today');

    switch ($range) {
        case 'today':
            $from = $today->format('Y-m-d 00:00:00');
            for ($h = 0; $h < 24; $h++) {
                $k = sprintf('%02d', $h);
                $buckets[$k] = [0.0, 0];
                $labels[$k] = $k . ':00';
            }
            $keyFn = fn($ts) => substr($ts, 11, 2);
            break;
        case '30d':
            $from = $today->modify('-29 days')->format('Y-m-d 00:00:00');
            for ($i = 29; $i >= 0; $i--) {
                $d = $today->modify("-$i days");
                $buckets[$d->format('Y-m-d')] = [0.0, 0];
                $labels[$d->format('Y-m-d')] = $d->format('d M');
            }
            $keyFn = fn($ts) => substr($ts, 0, 10);
            break;
        case '12m':
            $first = $today->modify('first day of this month')->modify('-11 months');
            $from = $first->format('Y-m-d 00:00:00');
            for ($i = 0; $i < 12; $i++) {
                $d = $first->modify("+$i months");
                $buckets[$d->format('Y-m')] = [0.0, 0];
                $labels[$d->format('Y-m')] = $d->format('M Y');
            }
            $keyFn = fn($ts) => substr($ts, 0, 7);
            break;
        default: // 7d
            $from = $today->modify('-6 days')->format('Y-m-d 00:00:00');
            for ($i = 6; $i >= 0; $i--) {
                $d = $today->modify("-$i days");
                $buckets[$d->format('Y-m-d')] = [0.0, 0];
                $labels[$d->format('Y-m-d')] = $d->format('D d M');
            }
            $keyFn = fn($ts) => substr($ts, 0, 10);
    }

    foreach (rows("SELECT total, created_at FROM orders WHERE status <> 'cancelled' AND created_at >= ?", [$from]) as $o) {
        $k = $keyFn((string)$o['created_at']);
        if (isset($buckets[$k])) {
            $buckets[$k][0] += (float)$o['total'];
            $buckets[$k][1]++;
        }
    }

    return [
        array_values($labels),
        array_map(fn($b) => round($b[0], 2), array_values($buckets)),
        array_map(fn($b) => $b[1], array_values($buckets)),
    ];
}

function dashboard_stats(): array
{
    $today = date('Y-m-d 00:00:00');
    $weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
    $monthStart = date('Y-m-01 00:00:00');

    return [
        'total_sales' => sales_since(null),
        'today' => sales_since($today),
        'week' => sales_since($weekStart),
        'month' => sales_since($monthStart),
        'orders' => (int)val('SELECT COUNT(*) FROM orders'),
        'products' => (int)val('SELECT COUNT(*) FROM products'),
        'customers' => (int)val('SELECT COUNT(*) FROM customers'),
        'pending' => (int)val("SELECT COUNT(*) FROM orders WHERE status NOT IN ('delivered','cancelled')"),
        'low' => (int)val("SELECT COUNT(*) FROM products WHERE status <> 'draft' AND stock < low_stock_threshold"),
        'units_sold' => (int)val("SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.status <> 'cancelled'"),
    ];
}
