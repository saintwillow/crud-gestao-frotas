<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'abastecimento';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: index.php"); exit; }

$res = mysqli_query($ligacao, "SELECT * FROM abastecimentos WHERE id=$id LIMIT 1");
$a = ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;

if (!$a) {
  echo '<div class="page-max-4xl"><div class="glass-card p-4">Abastecimento não encontrado.</div></div>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$erros = [];

$viatura_id = (string)($a['viatura_id'] ?? '');
$colaborador_id = (string)($a['colaborador_id'] ?? '');
$posto = (string)($a['posto'] ?? '');
$combustivel = (string)($a['combustivel'] ?? 'Diesel');
$litros = (string)($a['litros'] ?? '');
$preco_litro = (string)($a['preco_litro'] ?? '');
$data_abastecimento = (string)($a['data_abastecimento'] ?? date('Y-m-d'));
$observacoes = (string)($a['observacoes'] ?? '');

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

    $sql = "UPDATE abastecimentos SET
              viatura_id=$vid,
              colaborador_id=$cid,
              posto='$posto_s',
              combustivel='$comb_s',
              litros=$lit,
              preco_litro=$pl,
              total=$total,
              data_abastecimento='$data_s',
              observacoes=$obs_sql
            WHERE id=$id";

    if (mysqli_query($ligacao, $sql)) {
      header("Location: index.php?msg=editado");
      exit;
    } else {
      $erros[] = "Erro ao atualizar: " . mysqli_error($ligacao);
    }
  }
}
?>

<div class="page-max-4xl space-y-6">

  <a class="back-link" href="index.php">← Voltar ao abastecimento</a>

  <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-2">
    <div>
      <h1 class="page-title">Editar Abastecimento</h1>
      <div class="page-subtitle"><?php echo h($posto); ?></div>
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

      <div class="col-12">
        <label class="form-label form-label-soft">Viatura *</label>
        <select name="viatura_id" class="form-select form-select-lg" required>
          <option value="">Selecione...</option>
          <?php foreach ($viaturas as $v): ?>
            <?php $vidOpt = (int)$v['id']; ?>
            <option value="<?php echo $vidOpt; ?>" <?php echo ((string)$viatura_id === (string)$vidOpt) ? 'selected' : ''; ?>>
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
            <?php $cidOpt = (int)$c['id']; ?>
            <option value="<?php echo $cidOpt; ?>" <?php echo ((string)$colaborador_id === (string)$cidOpt) ? 'selected' : ''; ?>>
              <?php echo h($c['nome'] ?? ''); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Posto *</label>
        <input name="posto" class="form-control form-control-lg" value="<?php echo h($posto); ?>" required>
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
        <textarea name="observacoes" class="form-control" rows="4"><?php echo h($observacoes); ?></textarea>
      </div>

      <div class="col-12 d-flex justify-content-end gap-2 pt-2">
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
        <button class="btn btn-primary" type="submit">Salvar alterações</button>
      </div>

    </form>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
