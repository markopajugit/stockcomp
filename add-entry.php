<?php
require_once 'db.php';

$message = '';
$error = '';

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_entry'])) {
    $user_id = $_POST['user_id'] ?? null;
    $year = $_POST['year'] ?? null;
    $month = $_POST['month'] ?? null;
    $entry_type = $_POST['entry_type'] ?? null;
    
    if ($user_id && $year && $month && $entry_type) {
        try {
            $stmt = $pdo->prepare("DELETE FROM entries WHERE user_id = ? AND year = ? AND month = ? AND entry_type = ?");
            $stmt->execute([$user_id, $year, $month, $entry_type]);
            if ($stmt->rowCount() > 0) {
                $message = "Entry deleted successfully!";
            } else {
                $error = "No entry found to delete.";
            }
        } catch (PDOException $e) {
            $error = "Error deleting entry: " . $e->getMessage();
        }
    }
}

// Fetch users for dropdown
$stmt = $pdo->query("SELECT id, name FROM users ORDER BY name ASC");
$users = $stmt->fetchAll();

$user_id = $_POST['user_id'] ?? $_GET['user_id'] ?? null;
$year = $_POST['year'] ?? $_GET['year'] ?? date('Y');
$month = $_POST['month'] ?? $_GET['month'] ?? date('n');
$entry_type = $_POST['entry_type'] ?? $_GET['entry_type'] ?? 'actual';

// If editing, fetch existing entry
$existing_gain = '';
$existing_comment = '';
if ($user_id && $year && $month) {
    $stmt = $pdo->prepare("SELECT gain_percent, comment FROM entries WHERE user_id = ? AND year = ? AND month = ? AND entry_type = ?");
    $stmt->execute([$user_id, $year, $month, $entry_type]);
    $entry = $stmt->fetch();
    if ($entry) {
        $existing_gain = $entry['gain_percent'];
        $existing_comment = $entry['comment'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_entry'])) {
    $input_mode = $_POST['input_mode'] ?? '1m';
    $gain = $_POST['gain_percent'] ?? 0;
    $ytd_gain = $_POST['ytd_gain'] ?? null;
    $comment = trim($_POST['comment'] ?? '');
    $entry_type = $_POST['entry_type'] ?? 'actual';

    // If YTD mode, calculate the monthly gain from YTD
    if ($input_mode === 'ytd' && $ytd_gain !== null && $ytd_gain !== '') {
        $ytd_gain = floatval($ytd_gain);
        
        // Fetch all previous months' gains for this user/year/type
        $stmt = $pdo->prepare("SELECT month, gain_percent FROM entries WHERE user_id = ? AND year = ? AND month < ? AND entry_type = ? ORDER BY month ASC");
        $stmt->execute([$user_id, $year, $month, $entry_type]);
        $previous_entries = $stmt->fetchAll();
        
        // Calculate cumulative multiplier from previous months
        $cumulative_multiplier = 1.0;
        foreach ($previous_entries as $entry) {
            $cumulative_multiplier *= (1 + (floatval($entry['gain_percent']) / 100));
        }
        
        // Calculate what this month's gain needs to be to achieve the target YTD
        // Formula: monthly = ((1 + YTD/100) / cumulative_previous - 1) * 100
        $gain = (((1 + ($ytd_gain / 100)) / $cumulative_multiplier) - 1) * 100;
        $gain = round($gain, 2);
    }

    if (!$user_id) {
        $error = "User is required.";
    } else {
        try {
            // Upsert entry
            $stmt = $pdo->prepare("
                INSERT INTO entries (user_id, year, month, entry_type, gain_percent, comment) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                gain_percent = VALUES(gain_percent), 
                comment = VALUES(comment)
            ");
            $stmt->execute([$user_id, $year, $month, $entry_type, $gain, $comment]);
            $message = "Entry saved successfully!";
            $existing_gain = $gain;
            $existing_comment = $comment;
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Entry - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="dark-theme">
    <div class="container">
        <header>
            <h1>📊 Add Entry</h1>
            <a href="index.php" class="btn btn-secondary btn-small">← Back</a>
        </header>

        <main>
            <?php if ($message): ?>
                <div class="alert alert-success"><?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" class="entry-form">
                <div class="form-group">
                    <label for="user_id">Your Name</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">Select your name...</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $u['id'] == $user_id ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="period-selector">
                    <div class="form-group">
                        <label for="month">Month</label>
                        <select id="month" name="month">
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?= $num ?>" <?= $num == $month ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="year">Year</label>
                        <input type="number" id="year" name="year" value="<?= $year ?>" min="2020" max="2100">
                    </div>
                </div>

                <div class="form-group">
                    <label for="entry_type">Entry Type</label>
                    <select id="entry_type" name="entry_type">
                        <option value="actual" <?= $entry_type == 'actual' ? 'selected' : '' ?>>Actual Results</option>
                        <option value="prediction" <?= $entry_type == 'prediction' ? 'selected' : '' ?>>Prediction</option>
                    </select>
                </div>

                <div class="gain-input-section">
                    <div class="mode-toggle">
                        <button type="button" class="mode-btn active" data-mode="1m">Monthly</button>
                        <button type="button" class="mode-btn" data-mode="ytd">YTD</button>
                    </div>
                    <input type="hidden" name="input_mode" id="input_mode" value="1m">
                    
                    <div class="gain-input-wrapper">
                        <label id="gain_label" for="gain_input">Monthly Gain</label>
                        <div class="input-with-unit">
                            <input type="number" step="0.01" id="gain_input" name="gain_percent" value="<?= $existing_gain ?>" required placeholder="0.00">
                            <span class="unit">%</span>
                        </div>
                        <input type="hidden" id="ytd_gain" name="ytd_gain" value="">
                    </div>

                    <div id="ytd-calc-result" class="calc-result" style="display: none;">
                        <div class="calc-row">
                            <span class="calc-label">Previous YTD</span>
                            <span id="prev-ytd-value" class="calc-value">—</span>
                        </div>
                        <div class="calc-row highlight">
                            <span class="calc-label">Calculated Monthly</span>
                            <span id="calc-monthly-value" class="calc-value">—</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="comment">Comments <span class="optional">(optional)</span></label>
                    <textarea id="comment" name="comment" rows="2" placeholder="e.g. GOOGL +10%, BTC crash..."><?= htmlspecialchars($existing_comment) ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <span class="btn-icon">💾</span> Save Entry
                    </button>
                    <?php if ($existing_gain !== ''): ?>
                        <button type="submit" name="delete_entry" value="1" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this entry?');">
                            <span class="btn-icon">🗑️</span> Delete
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </main>
    </div>
    <script src="assets/app.js"></script>
</body>
</html>

