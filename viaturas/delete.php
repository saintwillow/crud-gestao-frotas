<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'viaturas';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header("Location: index.php"); exit; }

$res = mysqli_query($ligacao, "SELECT id, matricula, marca_modelo FROM viaturas WHERE id=$id LIMIT 1");
$veiculo = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;

if (!$veiculo) {
  echo '<div class="page-max-4xl"><div class="glass-card p-4">Veículo não encontrado.</div></div>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $confirm = $_POST['confirm'] ?? 'no';

  if ($confirm === 'yes') {
    mysqli_query($ligacao, "DELETE FROM viaturas WHERE id=$id");
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
