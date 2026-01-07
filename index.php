<?php
require_once 'db.php';

$current_year = date('Y');

// Fetch all users
$stmt = $pdo->query("SELECT * FROM users ORDER BY name ASC");
$users = $stmt->fetchAll();

// Fetch all entries for current year to calculate YTD
$stmt = $pdo->prepare("SELECT user_id, month, gain_percent FROM entries WHERE year = ?");
$stmt->execute([$current_year]);
$ytd_entries = $stmt->fetchAll();

// Fetch recent entries
$stmt = $pdo->query("
    SELECT e.*, u.name, u.color 
    FROM entries e 
    JOIN users u ON e.user_id = u.id 
    ORDER BY e.year DESC, e.month DESC, e.created_at DESC 
    LIMIT 10
");
$recent_entries = $stmt->fetchAll();

// Calculate YTD and All-time for each user
$leaderboard = [];
foreach ($users as $user) {
    // YTD
    $ytd_compound = 1.0;
    $has_ytd = false;
    foreach ($ytd_entries as $entry) {
        if ($entry['user_id'] == $user['id']) {
            $ytd_compound *= (1 + ($entry['gain_percent'] / 100));
            $has_ytd = true;
        }
    }
    
    // All-time (Fetch all for this user)
    $stmt = $pdo->prepare("SELECT gain_percent FROM entries WHERE user_id = ?");
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
    ];
}

// Sort leaderboard by YTD descending
usort($leaderboard, function($a, $b) {
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
                            <span class="entry-gain <?= $entry['gain_percent'] >= 0 ? 'pos' : 'neg' ?>">
                                <?= $entry['gain_percent'] >= 0 ? '+' : '' ?><?= number_format($entry['gain_percent'], 2) ?>%
                            </span>
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

