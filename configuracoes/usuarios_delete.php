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

// não deixar apagar a si próprio
$meuId = (int)($_SESSION['user_id'] ?? 0);
if ($meuId === $id) {
  echo '<div class="alert alert-warning">Você não pode apagar o próprio utilizador.</div>';
  echo '<a class="btn btn-outline-secondary" href="usuarios.php">Voltar</a>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$res = mysqli_query($ligacao, "SELECT id, nome, username FROM usuarios WHERE id=$id LIMIT 1");
$u = $res ? mysqli_fetch_assoc($res) : null;

if (!$u) {
  echo '<div class="glass-card p-4 text-center text-muted">Utilizador não encontrado.</div>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  mysqli_query($ligacao, "DELETE FROM usuarios WHERE id=$id LIMIT 1");
  header("Location: usuarios.php?deleted=1");
  exit;
}
?>

<div class="page-max-6xl space-y-6">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h1 class="page-title mb-1">Apagar utilizador</h1>
      <div class="page-subtitle"><?php echo h($u['nome']); ?> (@<?php echo h($u['username']); ?>)</div>
    </div>
    <a class="btn btn-outline-secondary" href="usuarios.php">Voltar</a>
  </div>

  <div class="glass-card p-4">
    <div class="alert alert-danger mb-3">
      Esta ação não pode ser desfeita.
    </div>

    <form method="post" class="d-flex gap-2">
      <button class="btn btn-danger" type="submit">Confirmar apagar</button>
      <a class="btn btn-outline-secondary" href="usuarios.php">Cancelar</a>
    </form>
  </div>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
