<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_role(['patient']);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$patientId = $_SESSION['user_id'];

$check = db()->prepare("
    SELECT COUNT(*) FROM ConsentRevocationRequest
    WHERE patient_id = ? AND status = 'pending'
");
$check->execute([$patientId]);
if ($check->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'message' => 'Já existe um pedido pendente.']);
    exit;
}

$stmt = db()->prepare("
    INSERT INTO ConsentRevocationRequest (patient_id)
    VALUES (?)
");
$stmt->execute([$patientId]);

echo json_encode(['success' => true, 'message' => 'Pedido registado com sucesso.']);