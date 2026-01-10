<?php
require_once 'db.php';

$current_year = date('Y');

// Fetch all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY name ASC");
$users = $stmt->fetchAll();

// Fetch all entries for current year to calculate YTD
$stmt = $pdo->prepare("SELECT user_id, month, gain_percent FROM entries WHERE year = ? AND entry_type = 'actual'");
$stmt->execute([$current_year]);
$ytd_entries = $stmt->fetchAll();

// Fetch recent entries with potential prediction pairing
$stmt = $pdo->query("
    SELECT e.*, u.name, u.color,
    (SELECT gain_percent FROM entries e2 WHERE e2.user_id = e.user_id AND e2.year = e.year AND e2.month = e.month AND e2.entry_type = 'prediction' LIMIT 1) as predicted_gain
    FROM entries e 
    JOIN users u ON e.user_id = u.id 
    ORDER BY e.year DESC, e.month DESC, e.created_at DESC 
    LIMIT 15
");
$recent_entries = $stmt->fetchAll();

// Calculate scores using new engine
$scores = calculateScores($pdo, $current_year);

// Calculate YTD and All-time for each user
$leaderboard = [];
foreach ($users as $user) {
    if ($user['name'] === MARKET_USER_NAME) continue;

    $uid = $user['id'];
    $user_score_data = $scores[$uid] ?? ['total' => 0, 'ytd' => 0];

    // YTD %
    $ytd_compound = 1.0;
    $has_ytd = false;
    foreach ($ytd_entries as $entry) {
        if ($entry['user_id'] == $user['id']) {
            $ytd_compound *= (1 + ($entry['gain_percent'] / 100));
            $has_ytd = true;
        }
    }
    
    // All-time % (Fetch only actual for this user)
    $stmt = $pdo->prepare("SELECT gain_percent FROM entries WHERE user_id = ? AND entry_type = 'actual'");
    $stmt->execute([$user['id']]);
    $all_entries = $stmt->fetchAll();
    
    $all_time_compound = 1.0;
    $has_all_time = false;
    foreach ($all_entries as $entry) {
        $all_time_compound *= (1 + ($entry['gain_percent'] / 100));
        $has_all_time = true;
    }

    $leaderboard[] = [
        'name' => $user['name'],
        'color' => $user['color'],
        'ytd' => $has_ytd ? ($ytd_compound - 1) * 100 : 0,
        'all_time' => $has_all_time ? ($all_time_compound - 1) * 100 : 0,
        'score' => $user_score_data['total'],
        'ytd_score' => $user_score_data['ytd']
    ];
}

// Sort leaderboard by Score descending
usort($leaderboard, function($a, $b) {
    if ($b['score'] != $a['score']) return $b['score'] <=> $a['score'];
    return $b['ytd'] <=> $a['ytd'];
});

$months = [
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
    7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="dark-theme">
    <div class="container">
        <header class="main-header">
            <h1><?= SITE_NAME ?></h1>
            <div class="actions">
                <a href="add-entry.php" class="btn btn-primary">Add Entry</a>
                <a href="add-user.php" class="btn btn-secondary">Add User</a>
                <a href="history.php" class="btn btn-outline">History</a>
            </div>
        </header>

        <div class="grid">
            <section class="card leaderboard">
                <h2>Leaderboard (YTD <?= $current_year ?>)</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>User</th>
                            <th>Score</th>
                            <th>YTD %</th>
                            <th>All-time %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = count($leaderboard);
                        foreach ($leaderboard as $index => $row): 
                            $icon = '';
                            if ($index === 0) {
                                $icon = '🥇 ';
                            } elseif ($count > 1 && $index === $count - 1) {
                                $icon = '💩 ';
                            } elseif ($count > 2 && $index === floor($count / 2)) {
                                $icon = '🥈 ';
                            }
                        ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td style="color: <?= $row['color'] ?>; font-weight: bold;">
                                    <?= $icon ?><?= htmlspecialchars($row['name']) ?>
                                </td>
                                <td style="font-weight: bold; color: #f1c40f;">
                                    <?= number_format($row['score'], 0) ?>
                                </td>
                                <td class="<?= $row['ytd'] >= 0 ? 'pos' : 'neg' ?>">
                                    <?= number_format($row['ytd'], 2) ?>%
                                </td>
                                <td class="<?= $row['all_time'] >= 0 ? 'pos' : 'neg' ?>">
                                    <?= number_format($row['all_time'], 2) ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>

            <section class="card chart-container">
                <h2>Performance Over Time</h2>
                <div class="chart-wrapper">
                    <canvas id="performanceChart"></canvas>
                </div>
            </section>
        </div>

        <section class="card recent-entries">
            <h2>Recent Entries</h2>
            <div class="entries-list">
                <?php foreach ($recent_entries as $entry): ?>
                    <div class="entry-item">
                        <div class="entry-meta">
                            <span class="entry-user" style="color: <?= $entry['color'] ?>"><?= htmlspecialchars($entry['name']) ?></span>
                            <span class="entry-date"><?= $months[$entry['month']] ?> <?= $entry['year'] ?></span>
                            <?php if ($entry['entry_type'] === 'prediction'): ?>
                                <span class="badge badge-prediction">Prediction</span>
                            <?php endif; ?>
                            <span class="entry-gain <?= $entry['gain_percent'] >= 0 ? 'pos' : 'neg' ?>">
                                <?= $entry['gain_percent'] >= 0 ? '+' : '' ?><?= number_format($entry['gain_percent'], 2) ?>%
                            </span>
                            
                            <?php if ($entry['entry_type'] === 'actual' && $entry['predicted_gain'] !== null): 
                                $diff = $entry['gain_percent'] - $entry['predicted_gain'];
                                $absDiff = abs($diff);
                                $accuracyClass = $absDiff <= 1 ? 'accuracy-high' : ($absDiff <= 5 ? 'accuracy-medium' : 'accuracy-low');
                            ?>
                                <span class="diff-badge <?= $accuracyClass ?>" title="Difference from prediction (<?= number_format($entry['predicted_gain'], 2) ?>%)">
                                    Diff: <?= $diff > 0 ? '+' : '' ?><?= number_format($diff, 2) ?>%
                                </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($entry['comment']): ?>
                            <p class="entry-comment">"<?= htmlspecialchars($entry['comment']) ?>"</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <script src="assets/app.js"></script>
</body>
</html>

