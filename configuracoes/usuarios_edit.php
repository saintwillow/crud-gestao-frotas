<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_perfil(['admin']);

$active = 'config';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  echo '<div class="glass-card p-4 text-center text-muted">ID inválido.</div>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$erro = '';

$res = mysqli_query($ligacao, "SELECT id, nome, username, perfil, ativo FROM usuarios WHERE id=$id LIMIT 1");
$u = $res ? mysqli_fetch_assoc($res) : null;

if (!$u) {
  echo '<div class="glass-card p-4 text-center text-muted">Utilizador não encontrado.</div>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$nome = $u['nome'];
$username = $u['username'];
$perfil = strtolower(trim($u['perfil']));
$ativo = (int)$u['ativo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nome = trim($_POST['nome'] ?? '');
  $username = trim($_POST['username'] ?? '');
  $perfil = strtolower(trim($_POST['perfil'] ?? 'operario'));
  $ativo = isset($_POST['ativo']) ? 1 : 0;

  $novaSenha = (string)($_POST['senha'] ?? ''); // opcional

  if ($nome === '' || $username === '') {
    $erro = 'Preencha nome e username.';
  } elseif (!in_array($perfil, ['admin','gestor','operario'], true)) {
    $erro = 'Perfil inválido.';
  } else {
    $nomeEsc = mysqli_real_escape_string($ligacao, $nome);
    $userEsc = mysqli_real_escape_string($ligacao, $username);
    $perfilEsc = mysqli_real_escape_string($ligacao, $perfil);

    $chk = mysqli_query($ligacao, "SELECT id FROM usuarios WHERE username='$userEsc' AND id<>$id LIMIT 1");
    if ($chk && mysqli_num_rows($chk) > 0) {
      $erro = 'Já existe outro utilizador com esse username.';
    } else {
      $setSenha = '';
      if ($novaSenha !== '') {
        $senhaEsc = mysqli_real_escape_string($ligacao, $novaSenha);
        $setSenha = ", senha='$senhaEsc'";
      }

      $sql = "UPDATE usuarios
              SET nome='$nomeEsc', username='$userEsc', perfil='$perfilEsc', ativo=$ativo $setSenha
              WHERE id=$id
              LIMIT 1";

      if (mysqli_query($ligacao, $sql)) {
        header("Location: usuarios.php?updated=1");
        exit;
      } else {
        $erro = 'Erro ao atualizar: ' . mysqli_error($ligacao);
      }
    }
  }
}
?>

<div class="page-max-6xl space-y-6">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h1 class="page-title mb-1">Editar utilizador</h1>
      <div class="page-subtitle">@<?php echo h($u['username']); ?></div>
    </div>
    <a class="btn btn-outline-secondary" href="usuarios.php">Voltar</a>
  </div>

  <?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo h($erro); ?></div>
  <?php endif; ?>

  <div class="glass-card p-4">
    <form method="post" class="row g-3">
      <div class="col-12 col-md-6">
        <label class="form-label">Nome</label>
        <input class="form-control" name="nome" value="<?php echo h($nome); ?>" required>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">Username</label>
        <input class="form-control" name="username" value="<?php echo h($username); ?>" required>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label">Nova senha (opcional)</label>
        <input type="password" class="form-control" name="senha" placeholder="Deixa vazio para não alterar">
      </div>

      <div class="col-12 col-md-3">
        <label class="form-label">Perfil</label>
        <select class="form-select" name="perfil">
          <option value="operario" <?php if($perfil==='operario') echo 'selected'; ?>>Operário</option>
          <option value="gestor" <?php if($perfil==='gestor') echo 'selected'; ?>>Gestor</option>
          <option value="admin" <?php if($perfil==='admin') echo 'selected'; ?>>Admin</option>
        </select>
      </div>

      <div class="col-12 col-md-3 d-flex align-items-end">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="ativo" id="ativo" <?php echo $ativo ? 'checked' : ''; ?>>
          <label class="form-check-label" for="ativo">Ativo</label>
        </div>
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Salvar</button>
        <a class="btn btn-outline-secondary" href="usuarios.php">Cancelar</a>
      </div>
    </form>
  </div>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
