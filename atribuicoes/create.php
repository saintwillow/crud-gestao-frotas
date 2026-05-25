<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'atribuicoes';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$erros = [];
$motorista_id = '';
$viatura_id = '';
$km_inicio = '';
$notas = '';

// Carregar motoristas ativos sem atribuição ativa
$motoristas = [];
$resM = mysqli_query($ligacao, "
  SELECT id, nome, nif FROM motoristas 
  WHERE status='Ativo' 
    AND id NOT IN (SELECT motorista_id FROM atribuicoes WHERE estado='aberta' AND motorista_id IS NOT NULL)
  ORDER BY nome ASC
");
if ($resM) {
  while ($row = mysqli_fetch_assoc($resM)) {
    $motoristas[] = $row;
  }
}

// Carregar viaturas disponíveis
$viaturas = [];
$resV = mysqli_query($ligacao, "
  SELECT id, matricula, marca_modelo, quilometragem FROM viaturas 
  WHERE estado='Disponível'
  ORDER BY matricula ASC
");
if ($resV) {
  while ($row = mysqli_fetch_assoc($resV)) {
    $viaturas[] = $row;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $motorista_id = isset($_POST['motorista_id']) ? (int)$_POST['motorista_id'] : 0;
  $viatura_id   = isset($_POST['viatura_id']) ? (int)$_POST['viatura_id'] : 0;
  $km_inicio    = isset($_POST['km_inicio']) ? trim($_POST['km_inicio']) : '';
  $notas        = trim($_POST['notas'] ?? '');

  if ($motorista_id <= 0) $erros[] = "Selecione um motorista válido.";
  if ($viatura_id <= 0) $erros[] = "Selecione uma viatura válida.";
  if ($km_inicio === '' || (int)$km_inicio < 0) $erros[] = "Indique uma quilometragem inicial válida.";

  if (empty($erros)) {
    mysqli_begin_transaction($ligacao);

    try {
      // 1. Validar se o motorista tem atribuição ativa (dupla verificação)
      $stmtCheckM = mysqli_prepare($ligacao, "SELECT id FROM atribuicoes WHERE motorista_id=? AND estado='aberta' LIMIT 1");
      mysqli_stmt_bind_param($stmtCheckM, "i", $motorista_id);
      mysqli_stmt_execute($stmtCheckM);
      mysqli_stmt_store_result($stmtCheckM);
      if (mysqli_stmt_num_rows($stmtCheckM) > 0) {
        throw new Exception("Este motorista já tem uma viatura atribuída.");
      }
      mysqli_stmt_close($stmtCheckM);

      // 2. Validar se a viatura tem atribuição ativa (dupla verificação)
      $stmtCheckV = mysqli_prepare($ligacao, "SELECT id FROM atribuicoes WHERE viatura_id=? AND estado='aberta' LIMIT 1");
      mysqli_stmt_bind_param($stmtCheckV, "i", $viatura_id);
      mysqli_stmt_execute($stmtCheckV);
      mysqli_stmt_store_result($stmtCheckV);
      if (mysqli_stmt_num_rows($stmtCheckV) > 0) {
        throw new Exception("Esta viatura já está atribuída a outro motorista.");
      }
      mysqli_stmt_close($stmtCheckV);

      // 3. Obter quilometragem atual do veículo para garantir que km_inicio não seja inferior
      $stmtVData = mysqli_prepare($ligacao, "SELECT quilometragem, estado FROM viaturas WHERE id=? LIMIT 1");
      mysqli_stmt_bind_param($stmtVData, "i", $viatura_id);
      mysqli_stmt_execute($stmtVData);
      $resVData = mysqli_stmt_get_result($stmtVData);
      $vData = mysqli_fetch_assoc($resVData);
      mysqli_stmt_close($stmtVData);

      if (!$vData) {
        throw new Exception("Viatura não encontrada.");
      }
      if ($vData['estado'] !== 'Disponível') {
        throw new Exception("A viatura selecionada não está disponível.");
      }
      if ((int)$km_inicio < (int)$vData['quilometragem']) {
        throw new Exception("A quilometragem inicial (" . h($km_inicio) . " km) não pode ser inferior à quilometragem atual do veículo (" . number_format($vData['quilometragem'], 0, ',', '.') . " km).");
      }

      // 4. Buscar o colaborador_id associado ao motorista
      $stmtM = mysqli_prepare($ligacao, "SELECT colaborador_id FROM motoristas WHERE id=? LIMIT 1");
      mysqli_stmt_bind_param($stmtM, "i", $motorista_id);
      mysqli_stmt_execute($stmtM);
      $resMData = mysqli_stmt_get_result($stmtM);
      $mData = mysqli_fetch_assoc($resMData);
      mysqli_stmt_close($stmtM);

      if (!$mData || !$mData['colaborador_id']) {
        throw new Exception("O motorista selecionado não possui um Colaborador associado.");
      }
      $colaborador_id = (int)$mData['colaborador_id'];
      $usuario_sessao = usuario_id_sessao();

      // 5. Inserir atribuição
      $stmtIns = mysqli_prepare($ligacao, "
        INSERT INTO atribuicoes (viatura_id, colaborador_id, motorista_id, km_inicio, estado, notas, criado_por_usuario_id)
        VALUES (?, ?, ?, ?, 'aberta', ?, ?)
      ");
      $km_inicio_int = (int)$km_inicio;
      mysqli_stmt_bind_param($stmtIns, "iiiisi", $viatura_id, $colaborador_id, $motorista_id, $km_inicio_int, $notas, $usuario_sessao);
      mysqli_stmt_execute($stmtIns);
      mysqli_stmt_close($stmtIns);

      // 6. Atualizar estado do veículo para 'Atribuída' e atualizar a quilometragem se for maior
      $stmtUpdV = mysqli_prepare($ligacao, "UPDATE viaturas SET estado='Atribuída', quilometragem=? WHERE id=?");
      mysqli_stmt_bind_param($stmtUpdV, "ii", $km_inicio_int, $viatura_id);
      mysqli_stmt_execute($stmtUpdV);
      mysqli_stmt_close($stmtUpdV);

      mysqli_commit($ligacao);

      header("Location: index.php?msg=criada");
      exit;
    } catch (Exception $e) {
      mysqli_rollback($ligacao);
      $erros[] = $e->getMessage();
    }
  }
}

// Se uma viatura específica foi passada por GET, pré-seleciona
$get_viatura_id = isset($_GET['viatura_id']) ? (int)$_GET['viatura_id'] : 0;
if ($get_viatura_id > 0 && $viatura_id === '') {
  $viatura_id = $get_viatura_id;
}
?>

<div class="page-max-6xl space-y-6">
  <a class="back-link" href="index.php">← Voltar às atribuições</a>

  <div>
    <h1 class="page-title">Nova Atribuição de Viatura</h1>
    <div class="page-subtitle">Vincule uma viatura disponível a um motorista ativo</div>
  </div>

  <?php if (count($erros) > 0): ?>
    <div class="alert alert-danger">
      <strong>Erro ao processar:</strong>
      <ul class="mb-0 mt-1">
        <?php foreach ($erros as $e): ?>
          <li><?php echo h($e); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="glass-card p-4">
    <form method="post" class="row g-3" id="formAtribuicao">

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Motorista *</label>
        <select class="form-select" name="motorista_id" required>
          <option value="">Selecione um motorista livre</option>
          <?php foreach ($motoristas as $m): ?>
            <option value="<?php echo (int)$m['id']; ?>" <?php echo ((int)$motorista_id === (int)$m['id'])?'selected':''; ?>>
              <?php echo h($m['nome'] . " (NIF: " . $m['nif'] . ")"); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($motoristas)): ?>
          <div class="form-text text-warning mt-1"><i class="bi bi-exclamation-triangle"></i> Não há motoristas ativos livres disponíveis no momento.</div>
        <?php endif; ?>
      </div>

      <div class="col-12 col-md-6">
        <label class="form-label form-label-soft">Viatura *</label>
        <select class="form-select" name="viatura_id" id="selectViatura" required>
          <option value="" data-km="0">Selecione uma viatura disponível</option>
          <?php foreach ($viaturas as $v): ?>
            <option value="<?php echo (int)$v['id']; ?>" data-km="<?php echo (int)$v['quilometragem']; ?>" <?php echo ((int)$viatura_id === (int)$v['id'])?'selected':''; ?>>
              <?php echo h($v['matricula'] . " — " . $v['marca_modelo'] . " (" . number_format($v['quilometragem'], 0, ',', '.') . " km)"); ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?php if (empty($viaturas)): ?>
          <div class="form-text text-warning mt-1"><i class="bi bi-exclamation-triangle"></i> Não há viaturas com estado 'Disponível'.</div>
        <?php endif; ?>
      </div>

      <div class="col-12 col-md-4">
        <label class="form-label form-label-soft">Quilometragem Inicial (km) *</label>
        <input class="form-control" type="number" min="0" name="km_inicio" id="inputKmInicio" value="<?php echo h($km_inicio); ?>" required>
        <div class="form-text text-muted" id="kmAtualHint">Selecione um veículo para carregar a quilometragem atual.</div>
      </div>

      <div class="col-12">
        <label class="form-label form-label-soft">Notas / Observações</label>
        <textarea class="form-control" name="notas" rows="3" placeholder="Ex: Equipamentos extras presentes, observações sobre o estado estético do veículo..."><?php echo h($notas); ?></textarea>
      </div>

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit" <?php echo (empty($motoristas) || empty($viaturas)) ? 'disabled' : ''; ?>>Criar Atribuição</button>
        <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
      </div>

    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const selectViatura = document.getElementById('selectViatura');
  const inputKmInicio = document.getElementById('inputKmInicio');
  const kmHint = document.getElementById('kmAtualHint');

  function updateKm() {
    const selectedOption = selectViatura.options[selectViatura.selectedIndex];
    if (selectedOption) {
      const km = selectedOption.getAttribute('data-km');
      if (km && parseInt(km) > 0) {
        inputKmInicio.value = km;
        inputKmInicio.min = km;
        kmHint.textContent = `A quilometragem mínima permitida é ${parseInt(km).toLocaleString('pt-PT')} km (quilometragem atual do veículo).`;
      } else {
        inputKmInicio.value = '';
        kmHint.textContent = 'Selecione um veículo para carregar a quilometragem atual.';
      }
    }
  }

  selectViatura.addEventListener('change', updateKm);

  // Executar ao carregar (em caso de preenchimento anterior pós-erro ou parâmetro GET)
  if (selectViatura.value !== '') {
    updateKm();
  }
});
</script>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
