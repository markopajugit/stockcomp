<?php
require_once 'db.php';

$message = '';
$error = '';

// Fetch users for dropdown
$stmt = $pdo->query("SELECT id, name FROM users ORDER BY name ASC");
$users = $stmt->fetchAll();

$user_id = $_POST['user_id'] ?? $_GET['user_id'] ?? null;
$year = $_POST['year'] ?? $_GET['year'] ?? date('Y');
$month = $_POST['month'] ?? $_GET['month'] ?? date('n');

// If editing, fetch existing entry
$existing_gain = '';
$existing_comment = '';
if ($user_id && $year && $month) {
    $stmt = $pdo->prepare("SELECT gain_percent, comment FROM entries WHERE user_id = ? AND year = ? AND month = ?");
    $stmt->execute([$user_id, $year, $month]);
    $entry = $stmt->fetch();
    if ($entry) {
        $existing_gain = $entry['gain_percent'];
        $existing_comment = $entry['comment'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = $_POST['pin'] ?? '';
    $gain = $_POST['gain_percent'] ?? 0;
    $comment = trim($_POST['comment'] ?? '');

    if (!$user_id || empty($pin)) {
        $error = "User and PIN are required.";
    } else {
        // Verify PIN
        $stmt = $pdo->prepare("SELECT pin_hash FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user && password_verify($pin, $user['pin_hash'])) {
            try {
                // Upsert entry
                $stmt = $pdo->prepare("
                    INSERT INTO entries (user_id, year, month, gain_percent, comment) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    gain_percent = VALUES(gain_percent), 
                    comment = VALUES(comment)
                ");
                $stmt->execute([$user_id, $year, $month, $gain, $comment]);
                $message = "Entry saved successfully!";
                $existing_gain = $gain;
                $existing_comment = $comment;
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        } else {
            $error = "Incorrect PIN.";
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
            <h1>Add Monthly Entry</h1>
            <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        </header>

        <main>
            <?php if ($message): ?>
                <div class="alert alert-success"><?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" class="card">
                <div class="form-group">
                    <label for="user_id">Your Name</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">Select your name...</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $u['id'] == $user_id ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="pin">Your PIN</label>
                    <input type="password" id="pin" name="pin" required placeholder="Enter PIN to verify">
                </div>
                <div class="row">
                    <div class="form-group col">
                        <label for="month">Month</label>
                        <select id="month" name="month">
                            <?php foreach ($months as $num => $name): ?>
                                <option value="<?= $num ?>" <?= $num == $month ? 'selected' : '' ?>><?= $name ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col">
                        <label for="year">Year</label>
                        <input type="number" id="year" name="year" value="<?= $year ?>" min="2020" max="2100">
                    </div>
                </div>
                <div class="form-group">
                    <label for="gain_percent">Monthly Gain (%)</label>
                    <input type="number" step="0.01" id="gain_percent" name="gain_percent" value="<?= $existing_gain ?>" required placeholder="e.g. 5.25 or -2.1">
                </div>
                <div class="form-group">
                    <label for="comment">Comments</label>
                    <textarea id="comment" name="comment" rows="3" placeholder="e.g. GOOGL +10%, BTC crash..."><?= htmlspecialchars($existing_comment) ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Save Entry</button>
            </form>
        </main>
    </div>
</body>
</html>

