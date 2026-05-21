<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'viaturas';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header("Location: index.php"); exit; }

$stmt = mysqli_prepare($ligacao, "SELECT id, matricula, marca_modelo FROM viaturas WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$veiculo = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$veiculo) {
  echo '<div class="page-max-4xl"><div class="glass-card p-4">Veículo não encontrado.</div></div>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (($_POST['confirm'] ?? '') === 'yes') {
    $del = mysqli_prepare($ligacao, "DELETE FROM viaturas WHERE id=?");
    mysqli_stmt_bind_param($del, "i", $id);
    mysqli_stmt_execute($del);
    mysqli_stmt_close($del);
    header("Location: index.php?msg=apagada");
    exit;
  }
  header("Location: show.php?id=$id");
  exit;
}
?>

<div class="page-max-4xl space-y-6">
  <a class="back-link" href="show.php?id=<?php echo (int)$id; ?>">← Voltar ao detalhe</a>

  <div>
    <h1 class="page-title">Apagar Veículo</h1>
    <div class="page-subtitle">Esta ação não pode ser desfeita.</div>
  </div>

  <div class="glass-card p-4">
    <div class="alert alert-warning mb-4">
      Tem a certeza que deseja apagar <strong><?php echo h($veiculo['marca_modelo']); ?></strong>
      (<strong><?php echo h($veiculo['matricula']); ?></strong>)?
    </div>
    <form method="post" class="d-flex justify-content-end gap-2">
      <button class="btn btn-outline-secondary" type="submit" name="confirm" value="no">Cancelar</button>
      <button class="btn btn-danger" type="submit" name="confirm" value="yes">Sim, apagar</button>
    </form>
  </div>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
