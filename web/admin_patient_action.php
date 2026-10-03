<?php
require_once __DIR__ . '/auth.php';
require_role(['admin']);

header('Content-Type: application/json; charset=utf-8');

$action = $_POST['action'] ?? '';

try {
    $pdo = db();

    if ($action === 'create_patient') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $diseaseStatus = trim($_POST['disease_status'] ?? 'Parkinson estadio inicial');
        $doctorIdRaw = $_POST['doctor_id'] ?? '';
        $doctorId = $doctorIdRaw !== '' ? (int)$doctorIdRaw : null;

        if ($name === '' || $email === '') {
            throw new Exception('Nome e email são obrigatórios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email inválido.');
        }

        if ($diseaseStatus === '') {
            $diseaseStatus = 'Parkinson estadio inicial';
        }

        if ($doctorId === null || $doctorId <= 0) {
            $doctorId = (int)$pdo->query("SELECT id FROM Doctor ORDER BY id ASC LIMIT 1")->fetchColumn();

            if ($doctorId <= 0) {
                throw new Exception('Não existe nenhum médico registado.');
            }
        }

        $tempPassword = bin2hex(random_bytes(4));
        $hash = password_hash($tempPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE email = ?");
$stmt->execute([$email]);

if ((int)$stmt->fetchColumn() > 0) {
    throw new Exception('Este email já está registado.');
}

        $pdo->beginTransaction();

        $status = $_POST['status'] ?? 'active';
$isActive = $status === 'inactive' ? 0 : 1;

$stmt = $pdo->prepare("
    INSERT INTO Users (name, email, password, role, is_active)
    VALUES (?, ?, ?, 'patient', ?)
");
$stmt->execute([$name, $email, $hash, $isActive]);

        $patientId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO Patient (id, disease_status, difficulty, doctor_id)
            VALUES (?, ?, 1, ?)
        ");
        $stmt->execute([$patientId, $diseaseStatus, $doctorId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Paciente criado com sucesso.',
            'tempPassword' => $tempPassword
        ]);
        exit;
    }

    if ($action === 'update_patient') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $diseaseStatus = trim($_POST['disease_status'] ?? 'Parkinson estadio inicial');
        $doctorIdRaw = $_POST['doctor_id'] ?? '';
        $doctorId = $doctorIdRaw !== '' ? (int)$doctorIdRaw : null;

        if ($id <= 0) {
            throw new Exception('Paciente inválido.');
        }

        if ($name === '' || $email === '') {
            throw new Exception('Nome e email são obrigatórios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email inválido.');
        }

        if ($diseaseStatus === '') {
            $diseaseStatus = 'Parkinson estadio inicial';
        }

        if ($doctorId === null || $doctorId <= 0) {
            $doctorId = (int)$pdo->query("SELECT id FROM Doctor ORDER BY id ASC LIMIT 1")->fetchColumn();

            if ($doctorId <= 0) {
                throw new Exception('Não existe nenhum médico registado.');
            }
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Users WHERE email = ? AND id <> ?");
$stmt->execute([$email, $id]);

if ((int)$stmt->fetchColumn() > 0) {
    throw new Exception('Este email já pertence a outro utilizador.');
}

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE Users
            SET name = ?, email = ?
            WHERE id = ? AND role = 'patient'
        ");
        $stmt->execute([$name, $email, $id]);

        $stmt = $pdo->prepare("
            UPDATE Patient
            SET disease_status = ?, doctor_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$diseaseStatus, $doctorId, $id]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Paciente atualizado com sucesso.'
        ]);
        exit;
    }

    if ($action === 'delete_patient_data') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            throw new Exception('Paciente inválido.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM GameSession WHERE patient_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM Evaluation WHERE patient_id = ?");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("
            UPDATE Patient
            SET difficulty = 1
            WHERE id = ?
        ");
        $stmt->execute([$id]);

        $stmt = $pdo->prepare("
            UPDATE ConsentRevocationRequest
            SET status = 'approved',
                handled_by = ?,
                handled_at = NOW()
            WHERE patient_id = ?
              AND status = 'pending'
        ");
        $stmt->execute([$_SESSION['user_id'] ?? null, $id]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Dados do paciente apagados com sucesso.'
        ]);
        exit;
    }

    if ($action === 'delete_patient') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            throw new Exception('Paciente inválido.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE ConsentRevocationRequest
            SET status = 'approved',
                handled_by = ?,
                handled_at = NOW()
            WHERE patient_id = ?
              AND status = 'pending'
        ");
        $stmt->execute([$_SESSION['user_id'] ?? null, $id]);

        $stmt = $pdo->prepare("
            DELETE FROM Users
            WHERE id = ? AND role = 'patient'
        ");
        $stmt->execute([$id]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Conta do paciente eliminada com sucesso.'
        ]);
        exit;
    }

    throw new Exception('Ação inválida.');

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}