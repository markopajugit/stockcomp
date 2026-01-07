<?php
require_once 'db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $color = $_POST['color'] ?? '#3498db';

    if (empty($name)) {
        $error = "Name is required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, color) VALUES (?, ?)");
            $stmt->execute([$name, $color]);
            $message = "User registered successfully!";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "User with this name already exists.";
            } else {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add User - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="dark-theme">
    <div class="container">
        <header>
            <h1>Add New Participant</h1>
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
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" required placeholder="e.g. Mike">
                </div>
                <div class="form-group">
                    <label for="color">Chart Color</label>
                    <input type="color" id="color" name="color" value="#3498db">
                </div>
                <button type="submit" class="btn btn-primary">Create User</button>
            </form>
        </main>
    </div>
</body>
</html>

