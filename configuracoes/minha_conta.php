<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'config';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$userId = (int)($_SESSION['user_id'] ?? 0);
$erro = '';
$ok   = '';

$stmt = mysqli_prepare($ligacao, "SELECT id, nome, username, perfil, senha FROM usuarios WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$me  = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

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

  if ($novoUsername === '') {
    $erro = "O username não pode estar vazio.";
  } else {
    // Verificar senha atual (suporte a hash e legado)
    $hashAtual = $me['senha'];
    if (str_starts_with($hashAtual, '$2y$')) {
      $senhaOk = password_verify($senhaAtual, $hashAtual);
    } else {
      $senhaOk = ($senhaAtual === $hashAtual);
    }

    if (!$senhaOk) {
      $erro = "Senha atual incorreta.";
    }
  }

  if ($erro === '' && ($novaSenha !== '' || $confirmar !== '')) {
    if (strlen($novaSenha) < 6) {
      $erro = "A nova senha deve ter pelo menos 6 caracteres.";
    } elseif ($novaSenha !== $confirmar) {
      $erro = "Confirmação de senha não coincide.";
    }
  }

  if ($erro === '') {
    // Verificar username duplicado
    $chk = mysqli_prepare($ligacao, "SELECT id FROM usuarios WHERE username=? AND id<>? LIMIT 1");
    mysqli_stmt_bind_param($chk, "si", $novoUsername, $userId);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);
    $dup = mysqli_stmt_num_rows($chk) > 0;
    mysqli_stmt_close($chk);

    if ($dup) {
      $erro = "Este username já está em uso.";
    } else {
      if ($novaSenha !== '') {
        $novoHash = password_hash($novaSenha, PASSWORD_BCRYPT);
        $upd = mysqli_prepare($ligacao, "UPDATE usuarios SET username=?, senha=? WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($upd, "ssi", $novoUsername, $novoHash, $userId);
      } else {
        $upd = mysqli_prepare($ligacao, "UPDATE usuarios SET username=? WHERE id=? LIMIT 1");
        mysqli_stmt_bind_param($upd, "si", $novoUsername, $userId);
      }

      if (mysqli_stmt_execute($upd)) {
        $_SESSION['user_username'] = $novoUsername;
        $me['username'] = $novoUsername;
        $ok = "Credenciais atualizadas com sucesso.";
      } else {
        $erro = "Erro ao atualizar. (" . mysqli_error($ligacao) . ")";
      }
      mysqli_stmt_close($upd);
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
        <label class="form-label">Nome de utilizador</label>
        <input class="form-control" name="username" value="<?php echo h($me['username']); ?>" required>
      </div>

      <div class="col-12">
        <label class="form-label">Senha atual (obrigatório para qualquer alteração)</label>
        <input class="form-control" type="password" name="senha_atual" required>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">Nova senha <span class="text-muted">(opcional)</span></label>
        <input class="form-control" type="password" name="nova_senha" placeholder="Mínimo 6 caracteres">
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
