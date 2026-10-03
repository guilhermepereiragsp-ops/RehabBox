<?php
header("Content-Type: application/json; charset=utf-8");

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "TMTS";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Falha na conexão"]);
    exit;
}

$conn->set_charset("utf8mb4");

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? [];

/* OBTER DIFICULDADE DO PACIENTE */
if ($action === 'get_patient_difficulty') {
    $patient_id = (int)($_GET['patient_id'] ?? 0);

    $stmt = $conn->prepare("SELECT difficulty FROM Patient WHERE id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        echo json_encode([
            "status" => "success",
            "difficulty" => (int)$row['difficulty']
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Paciente não encontrado"
        ]);
    }

    exit;
}

/* CRIAR GAME SESSION */
if ($action === 'create_game_session') {
    $patient_id = (int)($data['patient_id'] ?? 0);

    if ($patient_id <= 0) {
        echo json_encode(["status" => "error", "message" => "patient_id inválido"]);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO GameSession (patient_id) VALUES (?)");
    $stmt->bind_param("i", $patient_id);

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success",
            "game_group_id" => $conn->insert_id
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }

    exit;
}

/* CRIAR GAME */
if ($action === 'create_game') {
    $name = $data['name'] ?? '';
    $date = $data['date'] ?? date('Y-m-d');
    $difficulty = (int)($data['difficulty'] ?? 1);
    $final_score = (int)($data['final_score'] ?? 0);
    $game_group_id = (int)($data['game_group_id'] ?? 0);
    $patient_id = (int)($data['patient_id'] ?? 0);
    $duration_minutes = (int)($data['duration_minutes'] ?? 0);

    $stmt = $conn->prepare("
        INSERT INTO Game 
        (name, date, difficulty, final_score, game_group_id, patient_id, duration_minutes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssiiiii",
        $name,
        $date,
        $difficulty,
        $final_score,
        $game_group_id,
        $patient_id,
        $duration_minutes
    );

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "id" => $conn->insert_id]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }

    exit;
}

/* REGISTAR ERRO */
if ($action === 'log_error') {
    $description = $data['description'] ?? '';
    $game_id = (int)($data['game_id'] ?? 0);

    $stmt = $conn->prepare("
        INSERT INTO Mistakes (description, game_id)
        VALUES (?, ?)
    ");
    $stmt->bind_param("si", $description, $game_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Erro registado"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }

    exit;
}

/* ATUALIZAR SCORE */
if ($action === 'update_game_score') {
    $game_id = (int)($data['game_id'] ?? 0);
    $final_score = (int)($data['final_score'] ?? 0);

    $stmt = $conn->prepare("UPDATE Game SET final_score = ? WHERE id = ?");
    $stmt->bind_param("ii", $final_score, $game_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Score atualizado"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }

    exit;
}

/* ATUALIZAR DURAÇÃO */
if ($action === 'update_game_duration') {
    $game_id = (int)($data['game_id'] ?? 0);
    $duration_minutes = (int)($data['duration_minutes'] ?? 0);

    $stmt = $conn->prepare("UPDATE Game SET duration_minutes = ? WHERE id = ?");
    $stmt->bind_param("ii", $duration_minutes, $game_id);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Duração atualizada"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }

    exit;
}
/* REGISTAR RONDA COLOR GAME */
if ($action === 'log_color_round') {
    $game_id      = (int)($data['game_id'] ?? 0);
    $ronda        = (int)($data['ronda'] ?? 0);
    $cor_acendeu  = $data['cor_acendeu'] ?? '';
    $cor_clicada  = $data['cor_clicada'] ?? '';
    $acertou      = (int)($data['acertou'] ?? 0);

    $stmt = $conn->prepare("
        INSERT INTO ColorGame (game_id, ronda, cor_acendeu, cor_clicada, acertou)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iissi", $game_id, $ronda, $cor_acendeu, $cor_clicada, $acertou);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "id" => $conn->insert_id]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }

    exit;
}
echo json_encode([
    "status" => "error",
    "message" => "Ação desconhecida: " . $action
]);

$conn->close();
?>