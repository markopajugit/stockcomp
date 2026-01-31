<?php
header('Content-Type: application/json');
require_once '../db.php';

// Fetch all users
$stmt = $pdo->query("SELECT id, name, color FROM users ORDER BY id ASC");
$users = $stmt->fetchAll();

// Fetch all entries ordered by time
$stmt = $pdo->query("SELECT * FROM entries ORDER BY year ASC, month ASC");
$entries = $stmt->fetchAll();

// Group entries by user and type
$user_entries = [];
foreach ($entries as $e) {
    $user_entries[$e['user_id']][$e['entry_type']][] = $e;
}

// Find min and max year/month to create a complete timeline
if (empty($entries)) {
    echo json_encode(['labels' => [], 'datasets' => []]);
    exit;
}

$first = $entries[0];
$last = end($entries);

$start_date = new DateTime($first['year'] . '-' . $first['month'] . '-01');
$end_date = new DateTime($last['year'] . '-' . $last['month'] . '-01');

$labels = [];
$timeline = [];
$interval = new DateInterval('P1M');
$period = new DatePeriod($start_date, $interval, $end_date->modify('+1 month'));

foreach ($period as $dt) {
    $labels[] = $dt->format('M Y');
    $timeline[] = [
        'year' => (int)$dt->format('Y'),
        'month' => (int)$dt->format('n')
    ];
}

$datasets = [];
foreach ($users as $user) {
    // First, pre-calculate actual cumulative performance for this user
    $actualCumulative = [];
    $currentActual = 100.0;
    foreach ($timeline as $t) {
        $found_gain = 0.0;
        if (isset($user_entries[$user['id']]['actual'])) {
            foreach ($user_entries[$user['id']]['actual'] as $e) {
                if ($e['year'] == $t['year'] && $e['month'] == $t['month']) {
                    $found_gain = (float)$e['gain_percent'];
                    break;
                }
            }
        }
        $currentActual *= (1 + ($found_gain / 100));
        $actualCumulative[] = $currentActual;
    }

    foreach (['actual', 'prediction'] as $type) {
        $data = [];
        $monthlyGains = [];
        $cumulative = 100.0; // Starting at 100 (index)
        $has_data = false;
        
        foreach ($timeline as $idx => $t) {
            $found_gain = null;
            if (isset($user_entries[$user['id']][$type])) {
                foreach ($user_entries[$user['id']][$type] as $e) {
                    if ($e['year'] == $t['year'] && $e['month'] == $t['month']) {
                        $found_gain = (float)$e['gain_percent'];
                        $has_data = true;
                        break;
                    }
                }
            }
            
            if ($type === 'prediction') {
                // For predictions, we start from the previous month's ACTUAL cumulative value
                $base = ($idx === 0) ? 100.0 : $actualCumulative[$idx - 1];
                
                if ($found_gain !== null) {
                    $cumulative = $base * (1 + ($found_gain / 100));
                } else {
                    // If no prediction, it stays at the base level (previous actual)
                    $cumulative = $base;
                }
            } else {
                // For actuals, we continue compounding
                if ($found_gain === null) {
                    $found_gain = 0.0;
                } else {
                    $cumulative *= (1 + ($found_gain / 100));
                }
            }
            
            $data[] = round($cumulative - 100, 2); // % return from start
            $monthlyGains[] = ($found_gain === null) ? 0.0 : $found_gain;
        }

        if ($has_data) {
            $datasets[] = [
                'label' => $user['name'] . ($type === 'prediction' ? ' (Prediction)' : ''),
                'borderColor' => $user['color'],
                'backgroundColor' => $user['color'] . '33', // 20% opacity
                'data' => $data,
                'monthlyGains' => $monthlyGains,
                'fill' => false,
                'tension' => 0.1,
                'borderDash' => $type === 'prediction' ? [5, 5] : [],
                'isPrediction' => $type === 'prediction'
            ];
        }
    }
}

echo json_encode([
    'labels' => $labels,
    'datasets' => $datasets
]);

