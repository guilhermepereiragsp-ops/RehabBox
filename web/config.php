<?php
// config.php
// Ligaçao à base de dados TMTS + configurações do reCAPTCHA v2

declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'TMTS');
define('DB_CHARSET', 'utf8mb4');

// Google reCAPTCHA v2 - substituir pelas tuas chaves
// Obtém em: https://www.google.com/recaptcha/admin/create
// Tipo: reCAPTCHA v2 -> "Não sou um robô"
define('RECAPTCHA_SITE_KEY', '6LchB-8sAAAAADQxXKEJTQ18b1VyZ-alDL01ZEDY');
define('RECAPTCHA_SECRET_KEY', '6LchB-8sAAAAACgFXC8U3YWaM6HUziWWYOEQECaq');

function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}
