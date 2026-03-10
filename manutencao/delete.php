<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'manutencao';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: index.php"); exit; }

$res = mysqli_query($ligacao, "SELECT id, descricao FROM manutencoes WHERE id=$id LIMIT 1");
$m = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;

if (!$m) {
  echo '<div class="page-max-4xl"><div class="glass-card p-4">Manutenção não encontrada.</div></div>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $confirm = $_POST['confirm'] ?? 'no';
  if ($confirm === 'yes') {
    mysqli_query($ligacao, "DELETE FROM manutencoes WHERE id=$id");
    header("Location: index.php?msg=apagada");
    exit;
  }
  header("Location: index.php");
  exit;
}
?>

<div class="page-max-4xl space-y-6">

  <a class="back-link" href="index.php">← Voltar à manutenção</a>

  <div>
    <h1 class="page-title">Apagar Manutenção</h1>
    <div class="page-subtitle">Esta ação não pode ser desfeita.</div>
  </div>

  <div class="glass-card p-4">
    <div class="alert alert-warning mb-4">
      Tem a certeza que deseja apagar: <strong><?php echo h($m['descricao'] ?? 'Manutenção'); ?></strong>?
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
