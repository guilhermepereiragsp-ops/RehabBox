<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_role(['patient']);

header('Content-Type: application/json');

$userId = $_SESSION['user_id'];

$stmt = db()->prepare("UPDATE Patient SET last_eval_check = NOW() WHERE id = ?");
$stmt->execute([$userId]);

echo json_encode(['success' => true]);
exit;