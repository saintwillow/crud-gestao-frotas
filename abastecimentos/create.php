<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'abastecimento';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$erros = [];

$viatura_id = '';
$colaborador_id = '';
$posto = '';
$combustivel = 'Diesel';
$litros = '';
$preco_litro = '';
$data_abastecimento = date('Y-m-d');
$observacoes = '';

$viaturas = [];
$resV = mysqli_query($ligacao, "SELECT id, matricula, marca_modelo FROM viaturas ORDER BY marca_modelo ASC");
if ($resV) while ($r = mysqli_fetch_assoc($resV)) $viaturas[] = $r;

$colaboradores = [];
$resC = mysqli_query($ligacao, "SELECT id, nome FROM colaboradores ORDER BY nome ASC");
if ($resC) while ($r = mysqli_fetch_assoc($resC)) $colaboradores[] = $r;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $viatura_id = trim($_POST['viatura_id'] ?? '');
  $colaborador_id = trim($_POST['colaborador_id'] ?? '');
  $posto = trim($_POST['posto'] ?? '');
  $combustivel = trim($_POST['combustivel'] ?? '');
  $litros = trim($_POST['litros'] ?? '');
  $preco_litro = trim($_POST['preco_litro'] ?? '');
  $data_abastecimento = trim($_POST['data_abastecimento'] ?? '');
  $observacoes = trim($_POST['observacoes'] ?? '');

  if ($viatura_id === '' || !ctype_digit($viatura_id)) $erros[] = "Selecione uma viatura.";
  if ($colaborador_id !== '' && !ctype_digit($colaborador_id)) $erros[] = "Motorista inválido.";
  if ($posto === '') $erros[] = "O posto é obrigatório.";
  if ($litros === '' || !is_numeric($litros) || (float)$litros <= 0) $erros[] = "Informe os litros (maior que 0).";
  if ($preco_litro === '' || !is_numeric($preco_litro) || (float)$preco_litro <= 0) $erros[] = "Informe o preço por litro (maior que 0).";
  if ($data_abastecimento === '') $erros[] = "A data é obrigatória.";

  if (!$erros) {
    $vid = (int)$viatura_id;
    $cid = ($colaborador_id === '') ? "NULL" : (string)((int)$colaborador_id);

    $posto_s = mysqli_real_escape_string($ligacao, $posto);
    $comb_s = mysqli_real_escape_string($ligacao, $combustivel);

    $lit = (float)$litros;
    $pl = (float)$preco_litro;
    $total = $lit * $pl;

    $data_s = mysqli_real_escape_string($ligacao, $data_abastecimento);
    $obs_sql = ($observacoes === '') ? "NULL" : ("'".mysqli_real_escape_string($ligacao, $observacoes)."'");

    $sql = "INSERT INTO abastecimentos
              (viatura_id, colaborador_id, posto, combustivel, litros, preco_litro, total, data_abastecimento, observacoes)
            VALUES
              ($vid, $cid, '$posto_s', '$comb_s', $lit, $pl, $total, '$data_s', $obs_sql)";

    if (mysqli_query($ligacao, $sql)) {
      header("Location: index.php?msg=criado");
      exit;
    } else {
      $erros[] = "Erro ao salvar: " . mysqli_error($ligacao);
    }
  }
}
?>

<div class="page-max-4xl space-y-6">

  <a class="back-link" href="index.php">← Voltar ao abastecimento</a>

  <div>
    <h1 class="page-title">Novo Abastecimento</h1>
    <div class="page-subtitle">Registar abastecimento para uma viatura</div>
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
        <label class="form-label form-label-soft">Motorista (opcional)</label>
        <select name="colaborador_id" class="form-select form-select-lg">
          <option value="">Sem motorista</option>
          <?php foreach ($colaboradores as $c): ?>
            <?php $cid = (int)$c['id']; ?>
            <option value="<?php echo $cid; ?>" <?php echo ((string)$colaborador_id === (string)$cid) ? 'selected' : ''; ?>>
              <?php echo h($c['nome'] ?? ''); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Posto *</label>
        <input name="posto" class="form-control form-control-lg" value="<?php echo h($posto); ?>" placeholder="Posto Shell - Av. Central" required>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Combustível</label>
        <select name="combustivel" class="form-select form-select-lg">
          <?php foreach (['Diesel','Gasolina','Etanol','Elétrico','Híbrido','Outro'] as $c): ?>
            <option value="<?php echo h($c); ?>" <?php echo ($combustivel===$c)?'selected':''; ?>><?php echo h($c); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Litros *</label>
        <input type="number" step="0.01" min="0" name="litros" class="form-control form-control-lg" value="<?php echo h($litros); ?>" required>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Preço/Litro *</label>
        <input type="number" step="0.001" min="0" name="preco_litro" class="form-control form-control-lg" value="<?php echo h($preco_litro); ?>" required>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Data *</label>
        <input type="date" name="data_abastecimento" class="form-control form-control-lg" value="<?php echo h($data_abastecimento); ?>" required>
      </div>

      <div class="col-12">
        <label class="form-label form-label-soft">Observações</label>
        <textarea name="observacoes" class="form-control" rows="4" placeholder="Opcional"><?php echo h($observacoes); ?></textarea>
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
