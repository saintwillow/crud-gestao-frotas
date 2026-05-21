<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'viaturas';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header("Location: index.php"); exit; }

// Buscar viatura com prepared statement
$stmt = mysqli_prepare($ligacao, "SELECT * FROM viaturas WHERE id=? LIMIT 1");
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

$infraestruturas = [];
$resI = mysqli_query($ligacao, "SELECT id, nome, tipo, sub_regiao FROM infraestruturas WHERE ativo=1 ORDER BY sub_regiao ASC, tipo ASC, nome ASC");
if ($resI) while ($r = mysqli_fetch_assoc($resI)) $infraestruturas[] = $r;

$erros = [];
$matricula       = $veiculo['matricula'] ?? '';
$marca_modelo    = $veiculo['marca_modelo'] ?? '';
$tipo            = $veiculo['tipo'] ?? '';
$combustivel     = $veiculo['combustivel'] ?? '';
$quilometragem   = (string)($veiculo['quilometragem'] ?? '');
$estado          = $veiculo['estado'] ?? 'Disponível';
$observacoes     = $veiculo['observacoes'] ?? '';
$infraestrutura_id = (string)($veiculo['infraestrutura_id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $matricula       = trim($_POST['matricula'] ?? '');
  $marca_modelo    = trim($_POST['marca_modelo'] ?? '');
  $tipo            = trim($_POST['tipo'] ?? '');
  $combustivel     = trim($_POST['combustivel'] ?? '');
  $quilometragem   = trim($_POST['quilometragem'] ?? '');
  $estado          = trim($_POST['estado'] ?? 'Disponível');
  $observacoes     = trim($_POST['observacoes'] ?? '');
  $infraestrutura_id = trim($_POST['infraestrutura_id'] ?? '');

  if ($matricula === '')    $erros[] = "A matrícula é obrigatória.";
  if ($marca_modelo === '') $erros[] = "A marca/modelo é obrigatória.";
  if ($quilometragem === '' || !is_numeric($quilometragem) || (int)$quilometragem < 0)
    $erros[] = "A quilometragem deve ser um número válido (>= 0).";

  if (!$erros) {
    $km        = (int)$quilometragem;
    $obs_val   = $observacoes !== '' ? $observacoes : null;
    $infra_val = ($infraestrutura_id !== '' && (int)$infraestrutura_id > 0) ? (int)$infraestrutura_id : null;

    $stmt = mysqli_prepare($ligacao,
      "UPDATE viaturas SET matricula=?, marca_modelo=?, tipo=?, combustivel=?, quilometragem=?,
       estado=?, observacoes=?, infraestrutura_id=? WHERE id=?"
    );
    mysqli_stmt_bind_param($stmt, "ssssisisi",
      $matricula, $marca_modelo, $tipo, $combustivel, $km, $estado, $obs_val, $infra_val, $id
    );

    if (mysqli_stmt_execute($stmt)) {
      header("Location: show.php?id=$id");
      exit;
    } else {
      $erros[] = "Erro ao atualizar veículo: " . mysqli_error($ligacao);
    }
    mysqli_stmt_close($stmt);
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
        <?php foreach ($erros as $e): ?><li><?php echo h($e); ?></li><?php endforeach; ?>
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
            <option value="<?php echo h($t); ?>" <?php echo ($tipo===$t)?'selected':''; ?>><?php echo h($t); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Combustível</label>
        <select name="combustivel" class="form-select form-select-lg">
          <option value="">Selecione...</option>
          <?php foreach (['Diesel','Gasolina','Elétrico','Híbrido','Outro'] as $c): ?>
            <option value="<?php echo h($c); ?>" <?php echo ($combustivel===$c)?'selected':''; ?>><?php echo h($c); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Quilometragem *</label>
        <input type="number" name="quilometragem" class="form-control form-control-lg"
               value="<?php echo h($quilometragem); ?>" min="0" step="1" required>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Estado</label>
        <select name="estado" class="form-select form-select-lg">
          <?php foreach (['Disponível','Atribuída','Em Manutenção','Inativo'] as $es): ?>
            <option value="<?php echo h($es); ?>" <?php echo ($estado===$es)?'selected':''; ?>><?php echo h($es); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Infraestrutura / Base operacional</label>
        <select name="infraestrutura_id" class="form-select form-select-lg">
          <option value="">Sem atribuição</option>
          <?php foreach ($infraestruturas as $i): ?>
            <option value="<?php echo (int)$i['id']; ?>" <?php echo ((string)$infraestrutura_id===(string)$i['id'])?'selected':''; ?>>
              <?php echo h($i['tipo'].' • '.$i['nome'].' • '.$i['sub_regiao']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12">
        <label class="form-label form-label-soft">Observações</label>
        <textarea name="observacoes" class="form-control" rows="5"><?php echo h($observacoes); ?></textarea>
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
