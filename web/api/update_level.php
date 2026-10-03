<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json; charset=utf-8');

/* Apenas médicos e administradores */
$role = $_SESSION['user_role'] ?? '';

if (!in_array($role, ['doctor', 'admin'], true)) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'error'   => 'Não autorizado.'
    ]);
    exit;
}

/* Ler JSON */
$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error'   => 'Pedido inválido.'
    ]);
    exit;
}

$patientId = (int)($data['patient_id'] ?? 0);
$level     = (int)($data['level'] ?? 0);

/* Validar */
if ($patientId <= 0) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error'   => 'Paciente inválido.'
    ]);
    exit;
}

if (!in_array($level, [1, 2, 3], true)) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error'   => 'Nível inválido.'
    ]);
    exit;
}

try {

    $pdo = db();

    /* Confirmar existência do paciente */
    $check = $pdo->prepare("
        SELECT id
        FROM Patient
        WHERE id = ?
    ");

    $check->execute([$patientId]);

    if (!$check->fetch()) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'error'   => 'Paciente não encontrado.'
        ]);
        exit;
    }

    /* Atualizar dificuldade */
    $stmt = $pdo->prepare("
        UPDATE Patient
        SET difficulty = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $level,
        $patientId
    ]);

    echo json_encode([
        'success' => true,
        'level'   => $level
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}