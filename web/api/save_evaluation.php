<?php
/**
 * api/save_evaluation.php
 * POST JSON:
 * {
 *   patient_id: int,
 *   doctor_id: int,
 *   text: string
 * }
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json; charset=utf-8');

/* ── autenticação ── */
$role = $_SESSION['user_role'] ?? '';
if (!in_array($role, ['doctor', 'admin'], true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
    exit;
}

/* ── JSON input ── */
$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Pedido inválido.']);
    exit;
}

$patientId = isset($body['patient_id']) ? (int)$body['patient_id'] : 0;
$doctorId  = isset($body['doctor_id']) ? (int)$body['doctor_id'] : 0;
$text      = isset($body['text']) ? trim($body['text']) : '';

/* ── validação ── */
if ($patientId <= 0 || $doctorId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos.']);
    exit;
}

if (mb_strlen($text, 'UTF-8') < 5) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Texto demasiado curto.']);
    exit;
}

if (mb_strlen($text, 'UTF-8') > 2000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Texto demasiado longo.']);
    exit;
}

try {
    $pdo = db();

    /* confirmar paciente existe */
    $stmt = $pdo->prepare("SELECT id FROM Patient WHERE id = ?");
    $stmt->execute([$patientId]);

    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Paciente não encontrado.']);
        exit;
    }

    /* confirmar doctor existe */
    $stmt = $pdo->prepare("SELECT id FROM Doctor WHERE id = ?");
    $stmt->execute([$doctorId]);

    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Médico não encontrado.']);
        exit;
    }

    /* inserir avaliação */
    $stmt = $pdo->prepare("
        INSERT INTO Evaluation (
            patient_id,
            doctor_id,
            ev_date,
            description
        )
        VALUES (?, ?, NOW(), ?)
    ");

    $stmt->execute([
        $patientId,
        $doctorId,
        $text
    ]);

    echo json_encode([
        'success' => true,
        'id' => (int)$pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}