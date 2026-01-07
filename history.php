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

// Group by month for easier browsing
$grouped_entries = [];
foreach ($entries as $e) {
    $key = $e['year'] . '-' . $e['month'];
    $grouped_entries[$key]['label'] = $months[$e['month']] . ' ' . $e['year'];
    $grouped_entries[$key]['items'][] = $e;
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
                                    <th>Type</th>
                                    <th>Gain %</th>
                                    <th>Comment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($month_data['items'] as $item): ?>
                                    <tr>
                                        <td style="color: <?= $item['color'] ?>; font-weight: bold;">
                                            <?= htmlspecialchars($item['name']) ?>
                                        </td>
                                        <td>
                                            <?php if ($item['entry_type'] === 'prediction'): ?>
                                                <span class="badge badge-prediction">Prediction</span>
                                            <?php else: ?>
                                                <span style="font-size: 0.8rem; opacity: 0.7;">Actual</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="<?= $item['gain_percent'] >= 0 ? 'pos' : 'neg' ?>">
                                            <?= $item['gain_percent'] >= 0 ? '+' : '' ?><?= number_format($item['gain_percent'], 2) ?>%
                                        </td>
                                        <td class="comment-cell">
                                            <?= htmlspecialchars($item['comment']) ?>
                                        </td>
                                        <td>
                                            <a href="add-entry.php?user_id=<?= $item['user_id'] ?>&year=<?= $item['year'] ?>&month=<?= $item['month'] ?>&entry_type=<?= $item['entry_type'] ?>" class="btn btn-small">Edit</a>
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

