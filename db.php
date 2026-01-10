<?php
require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

/**
 * Calculates comprehensive scores for all users based on rules:
 * 1. Monthly Winner: +10 pts
 * 2. Gain Reward: +10 pts per 1%
 * 3. Prediction Penalty: -1 pt per 1% miss
 * 4. Beat Market: +10 pts
 * 5. Underdog Bonus: +5 pts (Monthly winner who was in last place)
 */
function calculateScores($pdo, $target_year = null) {
    // Fetch all users
    $stmt = $pdo->query("SELECT id, name, color FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_UNIQUE);
    
    // Fetch all entries, sorted by year/month
    $query = "SELECT * FROM entries ORDER BY year ASC, month ASC";
    $entries = $pdo->query($query)->fetchAll();
    
    $market_user_id = null;
    foreach ($users as $id => $user) {
        if ($user['name'] === MARKET_USER_NAME) {
            $market_user_id = $id;
            break;
        }
    }
    
    $monthly_data = [];
    foreach ($entries as $e) {
        $key = $e['year'] . '-' . $e['month'];
        if (!isset($monthly_data[$key])) {
            $monthly_data[$key] = ['users' => [], 'market_actual' => null];
        }
        
        $uid = $e['user_id'];
        if (!isset($monthly_data[$key]['users'][$uid])) {
            $monthly_data[$key]['users'][$uid] = ['actual' => null, 'prediction' => null];
        }
        
        $monthly_data[$key]['users'][$uid][$e['entry_type']] = $e['gain_percent'];
        
        if ($uid == $market_user_id && $e['entry_type'] === 'actual') {
            $monthly_data[$key]['market_actual'] = $e['gain_percent'];
        }
    }
    
    $user_scores = [];
    foreach ($users as $id => $user) {
        if ($id == $market_user_id) continue;
        $user_scores[$id] = [
            'total' => 0,
            'ytd' => 0,
            'details' => [], // [year-month => [points_breakdown]]
            'name' => $user['name'],
            'color' => $user['color']
        ];
    }
    
    $current_year = date('Y');
    $all_time_leaderboard_before = []; // user_id => total_points
    foreach ($user_scores as $uid => $data) $all_time_leaderboard_before[$uid] = 0;

    foreach ($monthly_data as $month_key => $data) {
        list($year, $month) = explode('-', $month_key);
        $month_winner_id = null;
        $max_gain = -999999;
        
        // First pass: Basic points (Gains, Accuracy, Market Beat) and find winner
        $month_results = [];
        
        // Find the "last place" user BEFORE this month's points
        asort($all_time_leaderboard_before);
        $last_place_user_id = key($all_time_leaderboard_before);

        foreach ($data['users'] as $uid => $entries) {
            if ($uid == $market_user_id) continue;
            if (!isset($user_scores[$uid])) continue; // Should not happen

            $actual = $entries['actual'];
            $pred = $entries['prediction'];
            
            $pts_gain = 0;
            $pts_pred = 0;
            $pts_market = 0;
            $pts_winner = 0;
            $pts_underdog = 0;
            
            if ($actual !== null) {
                // Rule 2: Gain Reward
                $pts_gain = $actual * SCORE_GAIN_MULTIPLIER;
                
                // Rule 3: Prediction Penalty
                if ($pred !== null) {
                    $diff = abs($actual - $pred);
                    $pts_pred = -($diff * SCORE_PREDICTION_PENALTY);
                }
                
                // Rule 4: Beat Market
                if ($data['market_actual'] !== null && $actual > $data['market_actual']) {
                    $pts_market = SCORE_BEAT_MARKET;
                }
                
                // Track for Rule 1 (Monthly Winner)
                if ($actual > $max_gain) {
                    $max_gain = $actual;
                    $month_winner_id = $uid;
                }
            }
            
            $month_results[$uid] = [
                'gain' => $pts_gain,
                'pred' => $pts_pred,
                'market' => $pts_market,
                'actual_gain' => $actual
            ];
        }
        
        // Second pass: Apply winner and underdog bonuses
        if ($month_winner_id !== null) {
            $month_results[$month_winner_id]['winner'] = SCORE_MONTHLY_WINNER;
            // Rule 5: Underdog Bonus
            if ($month_winner_id == $last_place_user_id && count($all_time_leaderboard_before) > 1) {
                $month_results[$month_winner_id]['underdog'] = SCORE_UNDERDOG_BONUS;
            }
        }
        
        // Aggregate to user_scores
        foreach ($month_results as $uid => $pts) {
            $total_month = $pts['gain'] + $pts['pred'] + $pts['market'] + ($pts['winner'] ?? 0) + ($pts['underdog'] ?? 0);
            
            $user_scores[$uid]['total'] += $total_month;
            if ($year == ($target_year ?? $current_year)) {
                $user_scores[$uid]['ytd'] += $total_month;
            }
            
            $user_scores[$uid]['details'][$month_key] = array_merge($pts, [
                'total' => $total_month,
                'winner' => $pts['winner'] ?? 0,
                'underdog' => $pts['underdog'] ?? 0
            ]);
            
            // Update the "before" leaderboard for the NEXT month calculation
            $all_time_leaderboard_before[$uid] += $total_month;
        }
    }
    
    return $user_scores;
}

