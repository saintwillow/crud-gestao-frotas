<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'manutencao';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$erros = [];

// defaults
$viatura_id = '';
$descricao = '';
$tipo = 'Preventiva';
$data_inicio = date('Y-m-d');
$data_fim = '';
$custo = '';
$oficina = '';
$status = 'Agendada';

$viaturas = [];
$whereV = "1=1";
if (is_gestor_zona()) {
  $zid = (int)zona_id_sessao();
  $whereV .= " AND zona_operacional_id = $zid";
}
$resV = mysqli_query($ligacao, "SELECT id, matricula, marca_modelo FROM viaturas WHERE $whereV ORDER BY marca_modelo ASC");
if ($resV) while ($r = mysqli_fetch_assoc($resV)) $viaturas[] = $r;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $viatura_id = trim($_POST['viatura_id'] ?? '');
  $descricao = trim($_POST['descricao'] ?? '');
  $tipo = trim($_POST['tipo'] ?? '');
  $data_inicio = trim($_POST['data_inicio'] ?? '');
  $data_fim = trim($_POST['data_fim'] ?? '');
  $custo = trim($_POST['custo'] ?? '');
  $oficina = trim($_POST['oficina'] ?? '');
  $status = trim($_POST['status'] ?? '');

  if ($viatura_id === '' || !ctype_digit($viatura_id)) $erros[] = "Selecione uma viatura.";
  if ($descricao === '') $erros[] = "A descrição é obrigatória.";
  if ($data_inicio === '') $erros[] = "A data de início é obrigatória.";
  if ($data_fim !== '' && $data_fim < $data_inicio) $erros[] = "A data fim não pode ser menor que a data início.";
  if ($custo !== '' && !is_numeric($custo)) $erros[] = "O custo deve ser numérico (ou vazio).";

  // Validação extra de segurança de zona ao salvar
  if (is_gestor_zona() && $viatura_id !== '') {
    $zid = (int)zona_id_sessao();
    $chkV = mysqli_query($ligacao, "SELECT zona_operacional_id FROM viaturas WHERE id = " . (int)$viatura_id);
    if ($chkV && $rV = mysqli_fetch_assoc($chkV)) {
      if ($rV['zona_operacional_id'] !== null && (int)$rV['zona_operacional_id'] !== $zid) {
        $erros[] = "A viatura selecionada não pertence à sua zona.";
      }
    }
  }

  if (!$erros) {
    $vid        = (int)$viatura_id;
    $fim_val    = $data_fim !== '' ? $data_fim : null;
    $oficina_val= $oficina !== '' ? $oficina : null;
    $custo_val  = $custo !== '' ? (float)$custo : null;

    $stmt = mysqli_prepare($ligacao,
      "INSERT INTO manutencoes (viatura_id, tipo, descricao, data_inicio, data_fim, custo, oficina, status)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, "issssdss",
      $vid, $tipo, $descricao, $data_inicio, $fim_val, $custo_val, $oficina_val, $status
    );

    if (mysqli_stmt_execute($stmt)) {
      recalcular_estado_viatura($ligacao, $vid);
      header("Location: index.php?msg=criada");
      exit;
    } else {
      $erros[] = "Erro ao criar manutenção: " . mysqli_error($ligacao);
    }
    mysqli_stmt_close($stmt);
  }}
?>

<div class="page-max-4xl space-y-6">

  <a class="back-link" href="index.php">← Voltar à manutenção</a>

  <div>
    <h1 class="page-title">Nova Manutenção</h1>
    <div class="page-subtitle">Registar uma manutenção para uma viatura</div>
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

      <div class="col-12">
        <label class="form-label form-label-soft">Viatura *</label>
        <select name="viatura_id" class="form-select form-select-lg" required>
          <option value="">Selecione...</option>
          <?php foreach ($viaturas as $v): ?>
            <?php $id = (int)$v['id']; ?>
            <option value="<?php echo $id; ?>" <?php echo ((string)$viatura_id === (string)$id) ? 'selected' : ''; ?>>
              <?php echo h(($v['marca_modelo'] ?? '') . " • " . ($v['matricula'] ?? '')); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12">
        <label class="form-label form-label-soft">Descrição *</label>
        <input name="descricao" class="form-control form-control-lg" value="<?php echo h($descricao); ?>"
               placeholder="Troca de óleo e filtros" required>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Tipo</label>
        <select name="tipo" class="form-select form-select-lg">
          <?php foreach (['Preventiva','Corretiva','Inspeção','Pneus','Óleo','Outro'] as $t): ?>
            <option value="<?php echo h($t); ?>" <?php echo ($tipo===$t)?'selected':''; ?>><?php echo h($t); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Data Início *</label>
        <input type="date" name="data_inicio" class="form-control form-control-lg" value="<?php echo h($data_inicio); ?>" required>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Data Fim</label>
        <input type="date" name="data_fim" class="form-control form-control-lg" value="<?php echo h($data_fim); ?>">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Custo (R$)</label>
        <input type="number" step="0.01" min="0" name="custo" class="form-control form-control-lg"
               value="<?php echo h($custo); ?>" placeholder="850">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Oficina</label>
        <input name="oficina" class="form-control form-control-lg" value="<?php echo h($oficina); ?>"
               placeholder="Oficina Central">
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Status</label>
        <select name="status" class="form-select form-select-lg">
          <?php foreach (['Agendada','Pendente','Em andamento','Concluída','Cancelada'] as $es): ?>
            <option value="<?php echo h($es); ?>" <?php echo ($status===$es)?'selected':''; ?>><?php echo h($es); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 d-flex justify-content-end gap-2 pt-2">
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
        <button class="btn btn-primary" type="submit">Salvar</button>
      </div>

    </form>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
