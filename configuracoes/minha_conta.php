<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'config';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_SESSION['user_id'] ?? 0);
$perfil = perfil_atual();

$erro = '';
$ok = '';

/* Buscar dados atuais */
$sqlMe = "SELECT id, nome, username, perfil FROM usuarios WHERE id=$userId LIMIT 1";
$resMe = mysqli_query($ligacao, $sqlMe);
$me = $resMe ? mysqli_fetch_assoc($resMe) : null;

if (!$me) {
  echo '<div class="glass-card p-4 text-center text-muted">Utilizador não encontrado.</div>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $novoUsername = trim($_POST['username'] ?? '');
  $senhaAtual   = trim($_POST['senha_atual'] ?? '');
  $novaSenha    = trim($_POST['nova_senha'] ?? '');
  $confirmar    = trim($_POST['confirmar_senha'] ?? '');

  // Valida username
  if ($novoUsername === '') {
    $erro = "O username não pode estar vazio.";
  } else {
    // Verificar senha atual (obrigatório para alterar qualquer coisa)
    $sa = mysqli_real_escape_string($ligacao, $senhaAtual);
    $check = mysqli_query($ligacao, "SELECT id FROM usuarios WHERE id=$userId AND senha='$sa' LIMIT 1");
    if (!$check || mysqli_num_rows($check) !== 1) {
      $erro = "Senha atual incorreta.";
    }
  }

  // Se o user também quer trocar a senha
  if ($erro === '' && ($novaSenha !== '' || $confirmar !== '')) {
    if (strlen($novaSenha) < 4) {
      $erro = "A nova senha deve ter pelo menos 4 caracteres.";
    } elseif ($novaSenha !== $confirmar) {
      $erro = "Confirmação de senha não coincide.";
    }
  }

  if ($erro === '') {
    $u = mysqli_real_escape_string($ligacao, $novoUsername);

    // username já existe?
    $dup = mysqli_query($ligacao, "SELECT id FROM usuarios WHERE username='$u' AND id<>$userId LIMIT 1");
    if ($dup && mysqli_num_rows($dup) > 0) {
      $erro = "Este username já está em uso.";
    } else {

      if ($novaSenha !== '') {
        $ns = mysqli_real_escape_string($ligacao, $novaSenha);
        $sqlUp = "UPDATE usuarios SET username='$u', senha='$ns' WHERE id=$userId LIMIT 1";
      } else {
        $sqlUp = "UPDATE usuarios SET username='$u' WHERE id=$userId LIMIT 1";
      }

      if (mysqli_query($ligacao, $sqlUp)) {
        $_SESSION['user_username'] = $novoUsername;
        $ok = "Credenciais atualizadas com sucesso.";

        // atualizar $me
        $me['username'] = $novoUsername;
      } else {
        $erro = "Erro ao atualizar. (" . mysqli_error($ligacao) . ")";
      }
    }
  }
}
?>

<div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <h1 class="page-title">Minha Conta</h1>
    <div class="page-subtitle">Atualize as suas credenciais</div>
  </div>
  <a class="btn btn-outline-secondary" href="index.php">Voltar</a>
</div>

<?php if ($erro !== ''): ?>
  <div class="alert alert-danger"><?php echo h($erro); ?></div>
<?php endif; ?>

<?php if ($ok !== ''): ?>
  <div class="alert alert-success"><?php echo h($ok); ?></div>
<?php endif; ?>

<div class="glass-card p-4" style="max-width:720px;">
  <div class="mb-3">
    <div class="small text-muted">Nome</div>
    <div class="fw-semibold"><?php echo h($me['nome']); ?></div>
  </div>

  <div class="mb-3">
    <div class="small text-muted">Perfil</div>
    <span class="badge-pill badge-info-soft"><?php echo h($me['perfil']); ?></span>
  </div>

  <form method="post" class="mt-3">
    <div class="row g-3">
      <div class="col-12">
        <label class="form-label">Nome de usuario</label>
        <input class="form-control" name="username" value="<?php echo h($me['username']); ?>" required>
      </div>

      <div class="col-12">
        <label class="form-label">Senha atual (obrigatório)</label>
        <input class="form-control" type="password" name="senha_atual" required>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">Nova senha </label>
        <input class="form-control" type="password" name="nova_senha">
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">Confirmar nova senha</label>
        <input class="form-control" type="password" name="confirmar_senha">
      </div>
    </div>

    <div class="mt-4 d-flex gap-2">
      <button class="btn btn-primary" type="submit">Guardar alterações</button>
      <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
    </div>
  </form>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
