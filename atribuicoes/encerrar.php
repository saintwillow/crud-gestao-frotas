<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'atribuicoes';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header("Location: index.php");
  exit;
}

// Buscar a atribuição aberta
$stmt = mysqli_prepare($ligacao, "
  SELECT 
    a.*,
    m.nome AS motorista_nome,
    v.matricula AS viatura_matricula,
    v.marca_modelo AS viatura_marca_modelo,
    v.quilometragem AS viatura_quilometragem
  FROM atribuicoes a
  JOIN motoristas m ON m.id = a.motorista_id
  JOIN viaturas v ON v.id = a.viatura_id
  WHERE a.id = ? AND a.estado = 'aberta'
  LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$atr = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$atr) {
  header("Location: index.php");
  exit;
}

pode_ver_viatura($ligacao, (int)$atr['viatura_id']);

$erros = [];
$km_fim = $atr['viatura_quilometragem'] ?? $atr['km_inicio'];
$notas_adicionais = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $km_fim = isset($_POST['km_fim']) ? trim($_POST['km_fim']) : '';
  $notas_adicionais = trim($_POST['notas_adicionais'] ?? '');

  if ($km_fim === '' || (int)$km_fim < 0) {
    $erros[] = "Indique uma quilometragem final válida.";
  } else {
    $km_fim_int = (int)$km_fim;
    if ($km_fim_int < (int)$atr['km_inicio']) {
      $erros[] = "A quilometragem final (" . number_format($km_fim_int, 0, ',', '.') . " km) não pode ser inferior à quilometragem inicial (" . number_format($atr['km_inicio'], 0, ',', '.') . " km).";
    }
  }

  if (empty($erros)) {
    mysqli_begin_transaction($ligacao);

    try {
      $usuario_sessao = usuario_id_sessao();
      $notas_final = $atr['notas'];
      if ($notas_adicionais !== '') {
        $notas_final = trim(($notas_final ? $notas_final . " | " : "") . "[Encerrado]: " . $notas_adicionais);
      }

      // 1. Atualizar atribuição
      $stmtUpdA = mysqli_prepare($ligacao, "
        UPDATE atribuicoes 
        SET estado='encerrada', data_fim=NOW(), km_fim=?, notas=?, encerrado_por_usuario_id=? 
        WHERE id=?
      ");
      mysqli_stmt_bind_param($stmtUpdA, "isii", $km_fim_int, $notas_final, $usuario_sessao, $id);
      mysqli_stmt_execute($stmtUpdA);
      mysqli_stmt_close($stmtUpdA);

      // 2. Atualizar viatura quilometragem e recalcular o estado do veículo
      $stmtUpdV = mysqli_prepare($ligacao, "
        UPDATE viaturas 
        SET quilometragem=? 
        WHERE id=?
      ");
      mysqli_stmt_bind_param($stmtUpdV, "ii", $km_fim_int, $atr['viatura_id']);
      mysqli_stmt_execute($stmtUpdV);
      mysqli_stmt_close($stmtUpdV);

      recalcular_estado_viatura($ligacao, $atr['viatura_id']);

      mysqli_commit($ligacao);

      // Limpar id da viatura na sessão se for o motorista logado atualmente
      if (motorista_id_sessao() === (int)$atr['motorista_id']) {
        $_SESSION['user_viatura_id'] = null;
      }

      header("Location: index.php?msg=encerrada");
      exit;
    } catch (Exception $e) {
      mysqli_rollback($ligacao);
      $erros[] = "Erro ao processar encerramento: " . $e->getMessage();
    }
  }
}
?>

<div class="page-max-4xl space-y-6">
  <a class="back-link" href="index.php">← Voltar às atribuições</a>

  <div>
    <h1 class="page-title text-danger">Encerrar Atribuição</h1>
    <div class="page-subtitle">Finalize o vínculo da viatura com o motorista e devolva-a ao pátio</div>
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

  <div class="row g-3">
    <!-- Card de Informações da Atribuição -->
    <div class="col-12 col-md-5">
      <div class="glass-card p-4 h-100">
        <h3 class="section-title mb-3">Atribuição Ativa</h3>
        
        <div class="mb-3">
          <label class="form-label form-label-soft text-uppercase" style="font-size: 11px;">Motorista</label>
          <div class="fw-semibold text-light fs-5"><?php echo h($atr['motorista_nome']); ?></div>
        </div>

        <div class="mb-3">
          <label class="form-label form-label-soft text-uppercase" style="font-size: 11px;">Viatura</label>
          <div class="fw-semibold text-light"><?php echo h($atr['viatura_marca_modelo']); ?></div>
          <div class="small text-muted"><?php echo h($atr['viatura_matricula']); ?></div>
        </div>

        <div class="mb-3">
          <label class="form-label form-label-soft text-uppercase" style="font-size: 11px;">Data de Início</label>
          <div class="fw-semibold text-light"><?php echo date('d/m/Y H:i', strtotime($atr['data_inicio'])); ?></div>
        </div>

        <div class="mb-0">
          <label class="form-label form-label-soft text-uppercase" style="font-size: 11px;">Km de Início</label>
          <div class="fw-semibold text-light"><?php echo number_format($atr['km_inicio'], 0, ',', '.'); ?> km</div>
        </div>
      </div>
    </div>

    <!-- Formulário de Encerramento -->
    <div class="col-12 col-md-7">
      <div class="glass-card p-4 h-100">
        <h3 class="section-title mb-3">Dados de Devolução</h3>
        
        <form method="post" class="vstack gap-3">
          <div>
            <label class="form-label form-label-soft">Quilometragem Final (km) *</label>
            <input class="form-control" type="number" min="<?php echo (int)$atr['km_inicio']; ?>" name="km_fim" value="<?php echo h($km_fim); ?>" required>
            <div class="form-text text-muted">A quilometragem final não pode ser menor que a inicial (<?php echo number_format($atr['km_inicio'], 0, ',', '.'); ?> km).</div>
          </div>

          <div>
            <label class="form-label form-label-soft">Notas Adicionais de Encerramento</label>
            <textarea class="form-control" name="notas_adicionais" rows="4" placeholder="Descreva qualquer detalhe relevante sobre a devolução do veículo..."><?php echo h($notas_adicionais); ?></textarea>
          </div>

          <div class="d-flex gap-2 pt-2">
            <button class="btn btn-danger" type="submit">Encerrar e Devolver Viatura</button>
            <a class="btn btn-outline-secondary" href="index.php">Cancelar</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
