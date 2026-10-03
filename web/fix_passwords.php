<?php
// fix_passwords.php — APAGA DEPOIS DE CORRER
require_once 'config.php';

$users = [
    ['admin@tmts.pt',         'admin123'],
    ['joao.silva@tmts.pt',    'med123'],
    ['maria.costa@gmail.com', 'pac123'],
    ['rui.pinto@gmail.com',   'pac123'],
];

foreach ($users as [$email, $pass]) {
    $hash = password_hash($pass, PASSWORD_BCRYPT);
    db()->prepare("UPDATE Users SET password = ? WHERE email = ?")->execute([$hash, $email]);
    echo "✓ $email actualizado<br>";
}
echo "<br><strong>Apaga este ficheiro agora!</strong>";