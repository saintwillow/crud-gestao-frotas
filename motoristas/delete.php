<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'motoristas';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header("Location: index.php"); exit; }

$stmt = mysqli_prepare($ligacao, "SELECT id, nome, nif FROM motoristas WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$m = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$m) { header("Location: index.php"); exit; }

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $stmt = mysqli_prepare($ligacao, "DELETE FROM motoristas WHERE id=? LIMIT 1");
  mysqli_stmt_bind_param($stmt, "i", $id);
  $ok = mysqli_stmt_execute($stmt);
  if ($ok) {
    header("Location: index.php?msg=apagado");
    exit;
  } else {
    $erro = "Erro ao apagar: " . mysqli_error($ligacao);
  }
  mysqli_stmt_close($stmt);
}
?>

<div class="page-max-6xl space-y-6">

  <a class="back-link" href="index.php">← Voltar aos motoristas</a>

  <div class="glass-card p-4">
    <h1 class="page-title">Apagar motorista</h1>
    <div class="page-subtitle">
      Tem certeza que deseja apagar <strong><?php echo h($m['nome']); ?></strong>
      <?php if (!empty($m['nif'])): ?> (NIF: <?php echo h($m['nif']); ?>)<?php endif; ?>?
    </div>

    <?php if ($erro): ?>
      <div class="alert alert-danger mt-3"><?php echo h($erro); ?></div>
    <?php endif; ?>

    <form method="post" class="mt-4 d-flex gap-2">
      <button class="btn btn-danger">Sim, apagar</button>
      <a class="btn btn-outline-secondary" href="edit.php?id=<?php echo (int)$id; ?>">Cancelar</a>
    </form>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
