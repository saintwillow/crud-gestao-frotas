<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'viaturas';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header("Location: index.php"); exit; }

$res = mysqli_query($ligacao, "SELECT * FROM viaturas WHERE id=$id LIMIT 1");
$veiculo = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;

if (!$veiculo) {
  echo '<div class="page-max-4xl"><div class="glass-card p-4">Veículo não encontrado.</div></div>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$erros = [];

$matricula = $veiculo['matricula'] ?? '';
$marca_modelo = $veiculo['marca_modelo'] ?? '';
$tipo = $veiculo['tipo'] ?? '';
$combustivel = $veiculo['combustivel'] ?? '';
$quilometragem = (string)($veiculo['quilometragem'] ?? '');
$estado = $veiculo['estado'] ?? 'Disponível';
$observacoes = $veiculo['observacoes'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $matricula = trim($_POST['matricula'] ?? '');
  $marca_modelo = trim($_POST['marca_modelo'] ?? '');
  $tipo = trim($_POST['tipo'] ?? '');
  $combustivel = trim($_POST['combustivel'] ?? '');
  $quilometragem = trim($_POST['quilometragem'] ?? '');
  $estado = trim($_POST['estado'] ?? 'Disponível');
  $observacoes = trim($_POST['observacoes'] ?? '');

  if ($matricula === '') $erros[] = "A matrícula é obrigatória.";
  if ($marca_modelo === '') $erros[] = "A marca/modelo é obrigatória.";
  if ($quilometragem === '' || !is_numeric($quilometragem) || (int)$quilometragem < 0) {
    $erros[] = "A quilometragem deve ser um número válido (>= 0).";
  }

  if (!$erros) {
    $matricula_s = mysqli_real_escape_string($ligacao, $matricula);
    $marca_modelo_s = mysqli_real_escape_string($ligacao, $marca_modelo);
    $tipo_s = mysqli_real_escape_string($ligacao, $tipo);
    $comb_s = mysqli_real_escape_string($ligacao, $combustivel);
    $estado_s = mysqli_real_escape_string($ligacao, $estado);
    $km = (int)$quilometragem;
    $obs_sql = ($observacoes === '') ? "NULL" : ("'" . mysqli_real_escape_string($ligacao, $observacoes) . "'");

    $sqlU = "UPDATE viaturas SET
              matricula='$matricula_s',
              marca_modelo='$marca_modelo_s',
              tipo='$tipo_s',
              combustivel='$comb_s',
              quilometragem=$km,
              estado='$estado_s',
              observacoes=$obs_sql
            WHERE id=$id";

    if (mysqli_query($ligacao, $sqlU)) {
      header("Location: show.php?id=$id");
      exit;
    } else {
      $erros[] = "Erro ao atualizar veículo: " . mysqli_error($ligacao);
    }
  }
}
?>

<div class="page-max-4xl space-y-6">

  <a class="back-link" href="show.php?id=<?php echo (int)$id; ?>">← Voltar ao detalhe</a>

  <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-2">
    <div>
      <h1 class="page-title">Editar Veículo</h1>
      <div class="page-subtitle"><?php echo h($marca_modelo); ?> • <?php echo h($matricula); ?></div>
    </div>

    <a class="btn btn-outline-danger" href="delete.php?id=<?php echo (int)$id; ?>">Apagar</a>
  </div>

  <?php if ($erros): ?>
    <div class="alert alert-danger">
      <strong>Verifique os campos:</strong>
      <ul class="mb-0">
        <?php foreach ($erros as $e): ?>
          <li><?php echo h($e); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="glass-card p-4">
    <form method="post" class="row g-3">

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Matrícula *</label>
        <input type="text" name="matricula" class="form-control form-control-lg"
               value="<?php echo h($matricula); ?>" required>
      </div>

      <div class="col-12 col-md-8">
        <label class="form-label form-label-soft">Marca/Modelo *</label>
        <input type="text" name="marca_modelo" class="form-control form-control-lg"
               value="<?php echo h($marca_modelo); ?>" required>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Tipo</label>
        <select name="tipo" class="form-select form-select-lg">
          <option value="">Selecione...</option>
          <?php foreach (['Ligeiro','Pick-up','Carrinha','Camião','Elétrico','Outro'] as $t): ?>
            <option value="<?php echo h($t); ?>" <?php echo ($tipo===$t)?'selected':''; ?>>
              <?php echo h($t); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Combustível</label>
        <select name="combustivel" class="form-select form-select-lg">
          <option value="">Selecione...</option>
          <?php foreach (['Diesel','Gasolina','Elétrico','Híbrido','Outro'] as $c): ?>
            <option value="<?php echo h($c); ?>" <?php echo ($combustivel===$c)?'selected':''; ?>>
              <?php echo h($c); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Quilometragem *</label>
        <input type="number" name="quilometragem" class="form-control form-control-lg"
               value="<?php echo h($quilometragem); ?>" min="0" step="1" required>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Estado</label>
        <select name="estado" class="form-select form-select-lg">
          <?php foreach (['Disponível','Atribuída','Em Manutenção','Inativo'] as $es): ?>
            <option value="<?php echo h($es); ?>" <?php echo ($estado===$es)?'selected':''; ?>>
              <?php echo h($es); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12">
        <label class="form-label form-label-soft">Observações</label>
        <textarea name="observacoes" class="form-control" rows="5"><?php echo h($observacoes); ?></textarea>
        <div class="form-text">Opcional. Use para registar notas do veículo.</div>
      </div>

      <div class="col-12 d-flex justify-content-end gap-2 pt-2">
        <a href="show.php?id=<?php echo (int)$id; ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">Salvar alterações</button>
      </div>

    </form>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
