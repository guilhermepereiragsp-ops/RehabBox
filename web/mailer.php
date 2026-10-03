<?php
/**
 * mailer.php
 * Notificações fictícias — não envia emails reais.
 * Em produção substituir send_email() por PHPMailer ou serviço SMTP.
 */

define('MAIL_ADMIN', 'admin@rehabbox.pt');
define('APP_URL',    'http://localhost/projeto/frontend');

// Não envia nada — apenas regista no log do PHP/Laragon
function send_email(string $to, string $toName, string $subject, string $htmlBody): bool
{
    error_log("[MAIL FICTÍCIO] Para: $to ($toName) | Assunto: $subject");
    return true;
}

function notify_admin_new_registration(string $name, string $email, string $role): void
{
    $roleLabel = $role === 'doctor' ? 'Médico / Terapeuta' : 'Paciente';
    send_email(
        MAIL_ADMIN, 'Admin RehabBox',
        'Novo pedido de registo — RehabBox',
        "Novo pedido de conta $roleLabel: $name — $email"
    );
}

function notify_user_approved(string $name, string $email, string $role): void
{
    $roleLabel = $role === 'doctor' ? 'Médico / Terapeuta' : 'Paciente';
    send_email(
        $email, $name,
        'Conta RehabBox aprovada',
        "Olá $name, a tua conta foi aprovada como $roleLabel."
    );
}

function notify_user_rejected(string $name, string $email): void
{
    send_email(
        $email, $name,
        'Pedido de acesso RehabBox',
        "Olá $name, o teu pedido de acesso não foi aprovado."
    );
}