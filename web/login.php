<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

start_secure_session();

if (!empty($_SESSION['user_id'])) {
    redirect_by_role();
}

$error   = '';
$success = '';
$email   = '';
$activeTab = 'login'; // controla qual tab mostrar após POST

/* ═══════════════════════════════════════
   LOGIN
═══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'login') {
    $activeTab = 'login';
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Preenche o email e a palavra-passe.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido.';
    } elseif (!verify_recaptcha($recaptchaToken)) {
        $error = 'Confirma o reCAPTCHA antes de entrar.';
    } else {
        $user         = find_user_by_email($email);
        $validPassword = $user && password_verify($password, $user['password']);

        if (!$user || !$validPassword) {
            log_login_attempt(
                userId:  $user['id'] ?? null,
                email:   $email,
                success: false,
                reason:  !$user ? 'Email não encontrado' : 'Password incorrecta'
            );
            $error = 'Email ou palavra-passe incorretos.';
        } elseif ((int)$user['is_active'] === 0) {
            // conta pendente ou desativada
            if ($user['role'] === 'pending') {
                $error = 'A tua conta está a aguardar aprovação. Receberás um email quando for ativada.';
            } else {
                $error = 'A tua conta está desativada. Contacta o administrador.';
            }
        } else {
            log_login_attempt(userId: (int)$user['id'], email: $email, success: true);
            login_user($user);
            redirect_by_role();
        }
    }
}

/* ═══════════════════════════════════════
   REGISTO
═══════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_action']) && $_POST['_action'] === 'register') {
    $activeTab     = 'register';
    $regName       = trim($_POST['name'] ?? '');
    $regEmail      = trim($_POST['email'] ?? '');
    $regPassword   = $_POST['password'] ?? '';
    $regConfirm    = $_POST['password_confirm'] ?? '';
    $regRole       = $_POST['role'] ?? '';
    $regJustify    = trim($_POST['justification'] ?? '');
    $recaptchaToken = $_POST['g-recaptcha-response'] ?? '';
    $privacyOk     = !empty($_POST['privacy']);
    $clinicalOk    = !empty($_POST['clinical_consent']);

    $allowedRoles  = ['patient', 'doctor'];

    if ($regName === '' || $regEmail === '' || $regPassword === '') {
        $error = 'Preenche todos os campos obrigatórios.';
    } elseif (!filter_var($regEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email inválido.';
    } elseif (!in_array($regRole, $allowedRoles, true)) {
        $error = 'Tipo de conta inválido.';
    } elseif (strlen($regPassword) < 8) {
        $error = 'A palavra-passe deve ter pelo menos 8 caracteres.';
    } elseif ($regPassword !== $regConfirm) {
        $error = 'As palavras-passe não coincidem.';
    } elseif (!$privacyOk) {
        $error = 'Tens de aceitar a Política de Privacidade.';
    } elseif (!verify_recaptcha($recaptchaToken)) {
        $error = 'Confirma o reCAPTCHA antes de continuar.';
    } else {
        // verificar email duplicado
        $chk = db()->prepare("SELECT id FROM Users WHERE email = ?");
        $chk->execute([$regEmail]);
        if ($chk->fetch()) {
            $error = 'Este email já está registado.';
        } else {
            $hash = password_hash($regPassword, PASSWORD_BCRYPT);

            db()->beginTransaction();
            try {
                $stmt = db()->prepare("
                    INSERT INTO Users (name, email, password, role, is_active)
                    VALUES (?, ?, ?, 'pending', 0)
                ");
                $stmt->execute([$regName, $regEmail, $hash]);
                $userId = (int) db()->lastInsertId();

                $stmt2 = db()->prepare("
                    INSERT INTO PendingRegistrations (user_id, requested_role, justification)
                    VALUES (?, ?, ?)
                ");
                $stmt2->execute([$userId, $regRole, $regJustify]);

                db()->commit();

                // notificar admin
                notify_admin_new_registration($regName, $regEmail, $regRole);

                $activeTab = 'login'; // voltar para login após sucesso
                $success   = 'Pedido enviado com sucesso! Receberás um email quando a tua conta for aprovada.';

            } catch (Exception $e) {
                db()->rollBack();
                $error = 'Erro ao criar conta. Por favor tenta novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>RehabBox — Entrar / Registar</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300;12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/style.css">
  <link rel="icon" type="image/png" href="./assets/images/logo1.png">

  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>

<body class="auth-page">

<div class="cursor-dot"   id="cursorDot"></div>
<div class="cursor-cross" id="cursorCross"></div>

<nav id="mainNav">
  <a href="./index.html" class="nav-logo">
    <img src="./assets/images/logo1.png" alt="RehabBox Logo" class="logo-img">
    <span class="logo-wordmark">Rehab<span>Box</span></span>
  </a>
  <ul class="nav-links">
    <li><a href="./index.html#sobre">Sobre</a></li>
    <li><a href="./index.html#funcionalidades">Funcionalidades</a></li>
    <li><a href="./index.html#jogos">Jogos</a></li>
    <li><a href="./index.html#contacto">Contacto</a></li>
  </ul>
  <div class="nav-actions">
    <a href="./index.html" class="btn-ghost">Voltar ao site</a>
  </div>
</nav>

<main class="auth-wrapper">
  <section class="auth-panel reveal">

    <div class="auth-brand">
      <img src="./assets/images/logo1.png" alt="RehabBox Logo" class="auth-logo">
      <div>
        <span class="auth-kicker">Área segura</span>
        <h1>Entrar na plataforma</h1>
      </div>
    </div>

    <p class="auth-intro">
      Aceda ao acompanhamento das sessões, progresso terapêutico e gestão de perfis RehabBox.
    </p>

    <?php if ($error): ?>
      <div class="auth-alert auth-alert--error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div class="auth-alert auth-alert--success"><?= e($success) ?></div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="auth-tabs">
      <button class="auth-tab <?= $activeTab === 'login'    ? 'active' : '' ?>"
              type="button" data-auth-tab="login">Entrar</button>
      <button class="auth-tab <?= $activeTab === 'register' ? 'active' : '' ?>"
              type="button" data-auth-tab="register">Criar conta</button>
    </div>

    <!-- ══ LOGIN FORM ══ -->
    <form class="auth-form <?= $activeTab === 'login' ? 'active' : '' ?>"
          id="loginForm" action="login.php" method="POST">

      <input type="hidden" name="_action" value="login">

      <div class="form-group">
        <label for="loginEmail">Email</label>
        <input type="email" id="loginEmail" name="email"
               placeholder="exemplo@email.com"
               autocomplete="email"
               value="<?= e($email) ?>" required>
      </div>

      <div class="form-group">
        <label for="loginPassword">Palavra-passe</label>
        <input type="password" id="loginPassword" name="password"
               placeholder="Introduza a palavra-passe"
               autocomplete="current-password" required>
      </div>

      <div class="form-row">
        <label class="check-line">
          <input type="checkbox" name="remember">
          <span>Manter sessão iniciada</span>
        </label>
      </div>

      <div class="g-recaptcha" data-sitekey="<?= e(RECAPTCHA_SITE_KEY) ?>"></div>

      <button type="submit" class="btn-primary auth-submit">Entrar</button>
    </form>

    <!-- ══ REGISTER FORM ══ -->
    <form class="auth-form <?= $activeTab === 'register' ? 'active' : '' ?>"
          id="registerForm" action="login.php" method="POST">

      <input type="hidden" name="_action" value="register">

      <div class="form-group">
        <label for="registerName">Nome completo</label>
        <input type="text" id="registerName" name="name"
               placeholder="O seu nome completo"
               autocomplete="name" required>
      </div>

      <div class="form-group">
        <label for="registerEmail">Email</label>
        <input type="email" id="registerEmail" name="email"
               placeholder="exemplo@email.com"
               autocomplete="email" required>
      </div>

      <div class="form-group">
        <label for="registerRole">Tipo de conta</label>
        <select id="registerRole" name="role" required>
          <option value="">Selecionar opção</option>
          <option value="patient">Paciente</option>
          <option value="doctor">Médico / Terapeuta</option>
        </select>
      </div>

      <!-- Campo de justificação — aparece apenas quando escolhe Médico -->
      <div class="form-group" id="justificationGroup" style="display:none;">
        <label for="registerJustification">Instituição / justificação <span style="opacity:.6">(opcional)</span></label>
        <input type="text" id="registerJustification" name="justification"
               placeholder="Ex: Fisioterapeuta no Centro de Saúde X">
      </div>

      <div class="form-group">
        <label for="registerPassword">Palavra-passe</label>
        <input type="password" id="registerPassword" name="password"
               placeholder="Mínimo 8 caracteres"
               autocomplete="new-password" minlength="8" required>
      </div>

      <div class="form-group">
        <label for="registerPasswordConfirm">Confirmar palavra-passe</label>
        <input type="password" id="registerPasswordConfirm" name="password_confirm"
               placeholder="Repita a palavra-passe"
               autocomplete="new-password" minlength="8" required>
      </div>

      <label class="check-line rgpd-line">
        <input type="checkbox" name="privacy" required>
        <span>
          Li e aceito a <a href="https://projeto.test/frontend/legal.html?tab=privacidade" class="auth-link">Política de Privacidade</a>
          e autorizo o tratamento dos meus dados para gestão da conta RehabBox.
        </span>
      </label>

      <label class="check-line rgpd-line">
        <input type="checkbox" name="clinical_consent">
        <span>
          Autorizo o tratamento de dados de progresso terapêutico para acompanhamento clínico.
        </span>
      </label>

      <div class="g-recaptcha" data-sitekey="<?= e(RECAPTCHA_SITE_KEY) ?>"></div>

      <button type="submit" class="btn-primary auth-submit">Pedir acesso</button>

      <p class="auth-pending-note">
        O pedido será analisado pelo administrador. Receberás um email de confirmação.
      </p>
    </form>

    <div class="auth-security-note">
      <strong>RGPD:</strong> os dados são usados apenas para autenticação e acompanhamento terapêutico, de acordo com as finalidades consentidas.
    </div>

  </section>

  <aside class="auth-side reveal reveal-delay-2">
    <div class="auth-side-card">
      <span class="auth-side-label">RehabBox</span>
      <h2>Reabilitação segura, acompanhada e personalizada.</h2>
      <p>
        Cada utilizador tem um perfil próprio com histórico de sessões, evolução e permissões adequadas ao seu papel clínico.
      </p>
      <div class="auth-benefits">
        <div><span>✓</span> Acesso protegido</div>
        <div><span>✓</span> Consentimento RGPD</div>
        <div><span>✓</span> Perfis por utilizador</div>
        <div><span>✓</span> Aprovação manual de contas</div>
        <div><span>✓</span> Preparado para acompanhamento clínico</div>
      </div>
    </div>
  </aside>
</main>

<script src="./assets/js/main.js"></script>
<script>
  // mostrar campo de justificação apenas para médicos
  document.getElementById('registerRole').addEventListener('change', function () {
    document.getElementById('justificationGroup').style.display =
      this.value === 'doctor' ? 'block' : 'none';
  });
</script>
</body>
</html>