<?php
require_once __DIR__ . "/inc/database.php";
require_once __DIR__ . "/inc/auth.php";

$erro = '';
$base = base_url();

if (esta_logado()) {
  $dest = perfil_atual() === 'operario' ? "/operario/index.php" : "/index.php";
  header("Location: " . $base . $dest);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'] ?? '';
  $senha = $_POST['senha'] ?? '';

  if (login($ligacao, $username, $senha)) {
    mysqli_close($ligacao);
    $dest = perfil_atual() === 'operario' ? "/operario/index.php" : "/index.php";
    header("Location: " . $base . $dest);
    exit;
  } else {
    $erro = "Utilizador ou senha inválidos.";
  }
}
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="<?php echo $base; ?>/css/bootstrap_css/bootstrap.min.css">
  <!-- ✅ CSS certo do teu projeto -->
  <link rel="stylesheet" href="<?php echo $base; ?>/css/style_css/style.css">

  <title>Login - Gestão de Frotas</title>
</head>

<body class="login-page">

  <div class="login-bg"></div>
  <div class="login-overlay"></div>

  <div class="container login-wrap">
    <div class="row justify-content-center">
      <div class="col-md-5 col-lg-4">

        <div class="card panel login-card">
          <div class="card-body p-4">
            <h4 class="mb-1">Entrar</h4>
            <p class="text-muted mb-3">Gestão de Frotas – Águas do Algarve</p>

            <?php if ($erro !== ''): ?>
              <div class="alert alert-danger"><?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
              <div class="mb-3">
                <label class="form-label">Utilizador</label>
                <input type="text" name="username" class="form-control" required>
              </div>

              <div class="mb-3">
                <label class="form-label">Senha</label>
                <input type="password" name="senha" class="form-control" required>
              </div>

              <button class="btn btn-primary w-100" type="submit">Entrar</button>
            </form>

          </div>
        </div>

      </div>
    </div>
  </div>

  <script src="<?php echo $base; ?>/js/bootstrap_js/bootstrap.bundle.min.js"></script>
</body>
</html>
