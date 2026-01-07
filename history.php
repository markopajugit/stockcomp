<?php
require_once 'db.php';

// Fetch all entries grouped by year/month
$stmt = $pdo->query("
    SELECT e.*, u.name, u.color 
    FROM entries e 
    JOIN users u ON e.user_id = u.id 
    ORDER BY e.year DESC, e.month DESC, u.name ASC
");
$entries = $stmt->fetchAll();

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

// Group by month and user for pairing predictions with actuals
$grouped_entries = [];
foreach ($entries as $e) {
    $key = $e['year'] . '-' . $e['month'];
    if (!isset($grouped_entries[$key])) {
        $grouped_entries[$key] = [
            'label' => $months[$e['month']] . ' ' . $e['year'],
            'users' => []
        ];
    }
    
    $userId = $e['user_id'];
    if (!isset($grouped_entries[$key]['users'][$userId])) {
        $grouped_entries[$key]['users'][$userId] = [
            'name' => $e['name'],
            'color' => $e['color'],
            'actual' => null,
            'prediction' => null
        ];
    }
    
    $grouped_entries[$key]['users'][$userId][$e['entry_type']] = $e;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="dark-theme">
    <div class="container">
        <header>
            <h1>Entry History</h1>
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        </header>

        <main>
            <?php if (empty($grouped_entries)): ?>
                <div class="card">
                    <p>No entries found yet.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($grouped_entries as $month_data): ?>
                <section class="history-month">
                    <h2 class="month-title"><?= $month_data['label'] ?></h2>
                    <div class="card">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Actual %</th>
                                    <th>Predicted %</th>
                                    <th>Accuracy / Diff</th>
                                    <th>Comment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($month_data['users'] as $userId => $data): 
                                    $actual = $data['actual'];
                                    $pred = $data['prediction'];
                                    $diff = null;
                                    if ($actual && $pred) {
                                        $diff = $actual['gain_percent'] - $pred['gain_percent'];
                                    }
                                ?>
                                    <tr>
                                        <td style="color: <?= $data['color'] ?>; font-weight: bold;">
                                            <?= htmlspecialchars($data['name']) ?>
                                        </td>
                                        <td>
                                            <?php if ($actual): ?>
                                                <span class="<?= $actual['gain_percent'] >= 0 ? 'pos' : 'neg' ?>">
                                                    <?= $actual['gain_percent'] >= 0 ? '+' : '' ?><?= number_format($actual['gain_percent'], 2) ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="muted-text">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($pred): ?>
                                                <span class="<?= $pred['gain_percent'] >= 0 ? 'pos' : 'neg' ?>">
                                                    <?= $pred['gain_percent'] >= 0 ? '+' : '' ?><?= number_format($pred['gain_percent'], 2) ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="muted-text">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($diff !== null): 
                                                $absDiff = abs($diff);
                                                $accuracyClass = $absDiff <= 1 ? 'accuracy-high' : ($absDiff <= 5 ? 'accuracy-medium' : 'accuracy-low');
                                            ?>
                                                <span class="diff-badge <?= $accuracyClass ?>">
                                                    <?= $diff > 0 ? '+' : '' ?><?= number_format($diff, 2) ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="muted-text">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="comment-cell">
                                            <?php 
                                                $comments = [];
                                                if ($actual && $actual['comment']) $comments[] = "Actual: " . htmlspecialchars($actual['comment']);
                                                if ($pred && $pred['comment']) $comments[] = "Pred: " . htmlspecialchars($pred['comment']);
                                                echo implode('<br>', $comments);
                                            ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <?php if ($actual): ?>
                                                    <a href="add-entry.php?user_id=<?= $userId ?>&year=<?= $actual['year'] ?>&month=<?= $actual['month'] ?>&entry_type=actual" class="btn btn-small">Edit Actual</a>
                                                <?php endif; ?>
                                                <?php if ($pred): ?>
                                                    <a href="add-entry.php?user_id=<?= $userId ?>&year=<?= $pred['year'] ?>&month=<?= $pred['month'] ?>&entry_type=prediction" class="btn btn-small btn-outline">Edit Pred</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endforeach; ?>
        </main>
    </div>
</body>
</html>

