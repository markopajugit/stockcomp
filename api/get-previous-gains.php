<?php
header('Content-Type: application/json');
require_once '../db.php';

$user_id = $_GET['user_id'] ?? null;
$year = $_GET['year'] ?? null;
$month = $_GET['month'] ?? null;
$entry_type = $_GET['entry_type'] ?? 'actual';

if (!$user_id || !$year || !$month) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

// Fetch all entries for this user, year, and type before the current month
$stmt = $pdo->prepare("SELECT month, gain_percent FROM entries WHERE user_id = ? AND year = ? AND month < ? AND entry_type = ? ORDER BY month ASC");
$stmt->execute([$user_id, $year, $month, $entry_type]);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'entries' => $entries
]);

