<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'viaturas';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$erros = [];
$matricula = $marca_modelo = $tipo = $combustivel = $observacoes = '';
$quilometragem = '';
$estado = 'Disponível';
$infraestrutura_id = '';

$infraestruturas = [];
$resI = mysqli_query($ligacao, "SELECT id, nome, tipo, sub_regiao FROM infraestruturas WHERE ativo=1 ORDER BY sub_regiao ASC, tipo ASC, nome ASC");
if ($resI) while ($r = mysqli_fetch_assoc($resI)) $infraestruturas[] = $r;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $matricula       = trim($_POST['matricula'] ?? '');
  $marca_modelo    = trim($_POST['marca_modelo'] ?? '');
  $tipo            = trim($_POST['tipo'] ?? '');
  $combustivel     = trim($_POST['combustivel'] ?? '');
  $quilometragem   = trim($_POST['quilometragem'] ?? '');
  $estado          = trim($_POST['estado'] ?? 'Disponível');
  $observacoes     = trim($_POST['observacoes'] ?? '');
  $infraestrutura_id = trim($_POST['infraestrutura_id'] ?? '');

  if ($matricula === '')   $erros[] = "A matrícula é obrigatória.";
  if ($marca_modelo === '') $erros[] = "A marca/modelo é obrigatória.";
  if ($quilometragem === '' || !is_numeric($quilometragem) || (int)$quilometragem < 0)
    $erros[] = "A quilometragem deve ser um número válido (>= 0).";

  if (!$erros) {
    $km             = (int)$quilometragem;
    $obs_val        = $observacoes !== '' ? $observacoes : null;
    $infra_val      = ($infraestrutura_id !== '' && (int)$infraestrutura_id > 0) ? (int)$infraestrutura_id : null;

    $stmt = mysqli_prepare($ligacao,
      "INSERT INTO viaturas (matricula, marca_modelo, tipo, combustivel, quilometragem, estado, observacoes, infraestrutura_id)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "ssssisis",
      $matricula, $marca_modelo, $tipo, $combustivel, $km, $estado, $obs_val, $infra_val
    );

    if (mysqli_stmt_execute($stmt)) {
      header("Location: index.php?msg=criada");
      exit;
    } else {
      $erros[] = "Erro ao criar veículo: " . mysqli_error($ligacao);
    }
    mysqli_stmt_close($stmt);
  }
}
?>

<div class="page-max-4xl space-y-6">

  <a class="back-link" href="index.php">← Voltar aos veículos</a>

  <div>
    <h1 class="page-title">Novo Veículo</h1>
    <div class="page-subtitle">Cadastrar um novo veículo na frota</div>
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
               value="<?php echo h($matricula); ?>" placeholder="ABC-1234" required>
      </div>

      <div class="col-12 col-md-8">
        <label class="form-label form-label-soft">Marca/Modelo *</label>
        <input type="text" name="marca_modelo" class="form-control form-control-lg"
               value="<?php echo h($marca_modelo); ?>" placeholder="Mercedes-Benz Sprinter 515" required>
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
               value="<?php echo h($quilometragem); ?>" min="0" step="1" placeholder="45230" required>
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
        <textarea name="observacoes" class="form-control" rows="5"
                  placeholder="Notas, avarias, detalhes..."><?php echo h($observacoes); ?></textarea>
        <div class="form-text">Opcional. Pode atualizar depois.</div>
      </div>

      <div class="col-12 d-flex justify-content-end gap-2 pt-2">
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>

    </form>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
