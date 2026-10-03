<?php
/**
 * api/delete_evaluation.php
 * POST  { id: int }
 * →     { success: true }  |  { success: false, error: string }
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json; charset=utf-8');

$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['doctor', 'admin'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$evalId = isset($body['id']) ? (int)$body['id'] : 0;

if ($evalId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID inválido.']);
    exit;
}

try {
    $pdo = db();
    $chk = $pdo->prepare("SELECT id FROM Evaluation WHERE id = ?");
    $chk->execute([$evalId]);
    if (!$chk->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Avaliação não encontrada.']);
        exit;
    }
    $pdo->prepare("DELETE FROM Evaluation WHERE id = ?")->execute([$evalId]);
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro na base de dados.']);
}
