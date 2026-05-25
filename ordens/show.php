<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'ordens_servico';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function pillClassOS($estado) {
  switch ($estado) {
    case 'rascunho': return 'pill-secondary';
    case 'atribuida': return 'pill-info';
    case 'aceite': return 'pill-primary';
    case 'em_deslocacao':
    case 'em_execucao': return 'pill-warning';
    case 'concluida': return 'pill-success';
    case 'impedida':
    case 'cancelada': return 'pill-danger';
    default: return 'pill-info';
  }
}

function badgePrioridadeOS($prioridade) {
  switch ($prioridade) {
    case 'baixa': return 'badge-info-soft';
    case 'media': return 'badge-primary-soft';
    case 'alta': return 'badge-warning-soft';
    case 'critica': return 'badge-danger-soft';
    default: return 'badge-secondary-soft';
  }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header("Location: index.php");
  exit;
}

$erros = [];
$sucesso = false;

// Processamento de Ações Rápidas (Cancelar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
  $acao = $_POST['acao'];
  if ($acao === 'cancelar') {
    mysqli_begin_transaction($ligacao);
    try {
      // Validar estado atual
      $stmtCheck = mysqli_prepare($ligacao, "SELECT estado FROM ordens_servico WHERE id=? LIMIT 1");
      mysqli_stmt_bind_param($stmtCheck, "i", $id);
      mysqli_stmt_execute($stmtCheck);
      $resCheck = mysqli_stmt_get_result($stmtCheck);
      $osCheck = mysqli_fetch_assoc($resCheck);
      mysqli_stmt_close($stmtCheck);

      if (!$osCheck) {
        throw new Exception("Ordem de Serviço não encontrada.");
      }
      if (in_array($osCheck['estado'], ['concluida', 'cancelada'], true)) {
        throw new Exception("Esta Ordem de Serviço já está finalizada ou cancelada.");
      }

      $stmtUpd = mysqli_prepare($ligacao, "UPDATE ordens_servico SET estado='cancelada', fim_real=NOW() WHERE id=?");
      mysqli_stmt_bind_param($stmtUpd, "i", $id);
      mysqli_stmt_execute($stmtUpd);
      mysqli_stmt_close($stmtUpd);

      mysqli_commit($ligacao);
      $sucesso = "Ordem de Serviço cancelada com sucesso.";
    } catch (Exception $e) {
      mysqli_rollback($ligacao);
      $erros[] = $e->getMessage();
    }
  }
}

// Buscar a Ordem de Serviço
$stmt = mysqli_prepare($ligacao, "
  SELECT 
    os.*,
    m.nome AS motorista_nome, m.telefone AS motorista_telefone,
    v.matricula AS viatura_matricula, v.marca_modelo AS viatura_marca_modelo,
    inf.nome AS infraestrutura_nome, inf.tipo AS infraestrutura_tipo, inf.localidade AS infraestrutura_localidade,
    u.nome AS gestor_atribuicao_nome
  FROM ordens_servico os
  LEFT JOIN motoristas m ON m.id = os.motorista_id
  LEFT JOIN viaturas v ON v.id = os.viatura_id
  LEFT JOIN infraestruturas inf ON inf.id = os.infraestrutura_id
  LEFT JOIN usuarios u ON u.id = os.atribuido_por_usuario_id
  WHERE os.id = ?
  LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$os = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$os) {
  echo '<div class="page-max-4xl"><div class="glass-card p-4">Ordem de Serviço não encontrada.</div></div>';
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

$codigo = (string)$os['codigo'];
$titulo = (string)$os['titulo'];
$descricao = trim((string)($os['descricao'] ?? ''));
$tipo = (string)$os['tipo'];
$prio = (string)$os['prioridade'];
$est  = (string)$os['estado'];
$dataP = (string)($os['data_prevista'] ?? '');
$horaP = (string)($os['hora_prevista'] ?? '');
$iniReal = (string)($os['inicio_real'] ?? '');
$fimReal = (string)($os['fim_real'] ?? '');
$obsOperario = trim((string)($os['observacoes_operario'] ?? ''));
$obsGestor = trim((string)($os['observacoes_gestor'] ?? ''));

$pill  = pillClassOS($est);
$badge = badgePrioridadeOS($prio);
?>

<div class="page-max-5xl space-y-6">
  
  <a class="back-link" href="index.php">← Voltar às ordens de serviço</a>

  <?php if ($sucesso): ?>
    <div class="alert alert-success"><?php echo h($sucesso); ?></div>
  <?php endif; ?>

  <?php if (count($erros) > 0): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($erros as $e): ?>
          <li><?php echo h($e); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
    <div>
      <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
        <span class="text-muted small fw-semibold"><?php echo h($codigo); ?></span>
        <span class="badge-pill <?php echo $badge; ?> text-uppercase" style="font-size: 10px;"><?php echo h($prio); ?></span>
      </div>
      <h1 class="page-title mb-1"><?php echo h($titulo); ?></h1>
      <div class="page-subtitle">Detalhe completo e acompanhamento da Ordem de Serviço</div>
    </div>

    <div>
      <span class="pill <?php echo h($pill); ?> text-uppercase fs-6"><?php echo h(str_replace('_', ' ', $est)); ?></span>
    </div>
  </div>

  <div class="row g-3">
    
    <!-- Detalhes do Escopo -->
    <div class="col-12 col-md-7">
      <div class="glass-card p-4 h-100">
        <h3 class="section-title mb-3">Informações da Ordem</h3>
        
        <div class="mb-4">
          <label class="form-label form-label-soft text-uppercase" style="font-size: 11px;">Tipo de Serviço</label>
          <div class="fw-semibold text-light text-capitalize"><?php echo h(str_replace('_', ' ', $tipo)); ?></div>
        </div>

        <div class="mb-4">
          <label class="form-label form-label-soft text-uppercase" style="font-size: 11px;">Instruções / Descrição</label>
          <div class="text-light text-pre-line bg-dark bg-opacity-25 p-3 rounded border border-secondary border-opacity-10 min-h-120">
            <?php echo $descricao !== '' ? nl2br(h($descricao)) : '<span class="text-muted italic">Sem descrição registrada.</span>'; ?>
          </div>
        </div>

        <?php if ($obsGestor !== ''): ?>
          <div class="mb-0">
            <label class="form-label form-label-soft text-uppercase" style="font-size: 11px;">Notas Internas do Gestor</label>
            <div class="small text-muted p-2 rounded bg-secondary bg-opacity-10 border border-secondary border-opacity-10">
              <?php echo nl2br(h($obsGestor)); ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Atribuições e Prazos -->
    <div class="col-12 col-md-5">
      <div class="glass-card p-4 h-100 d-flex flex-column justify-content-between">
        <div>
          <h3 class="section-title mb-3">Execução & Cronograma</h3>

          <div class="mb-3">
            <label class="form-label form-label-soft text-uppercase" style="font-size: 11px;">Local</label>
            <div class="fw-semibold text-light"><?php echo h($os['infraestrutura_nome'] ?? 'Sem local definido'); ?></div>
            <div class="small text-muted"><?php echo h(($os['infraestrutura_tipo'] ? $os['infraestrutura_tipo'] . " · " : "") . ($os['infraestrutura_localidade'] ?? '')); ?></div>
          </div>

          <div class="mb-3">
            <label class="form-label form-label-soft text-uppercase" style="font-size: 11px;">Viatura</label>
            <div class="fw-semibold text-light"><?php echo $os['viatura_marca_modelo'] ? h($os['viatura_marca_modelo']) : '<span class="text-muted italic">Nenhuma viatura designada</span>'; ?></div>
            <div class="small text-muted"><?php echo h($os['viatura_matricula'] ?? ''); ?></div>
          </div>

          <div class="mb-3">
            <label class="form-label form-label-soft text-uppercase" style="font-size: 11px;">Motorista Designado</label>
            <div class="fw-semibold text-light"><?php echo $os['motorista_nome'] ? h($os['motorista_nome']) : '<span class="text-muted italic">Não atribuído</span>'; ?></div>
            <div class="small text-muted"><?php echo h($os['motorista_telefone'] ?? ''); ?></div>
          </div>

          <div class="border-top border-secondary border-opacity-20 my-3"></div>

          <div class="row g-2">
            <div class="col-6">
              <label class="form-label form-label-soft text-uppercase" style="font-size: 10px;">Previsto</label>
              <div class="small fw-semibold text-light">
                <?php echo $dataP ? date('d/m/Y', strtotime($dataP)) : '—'; ?> <br>
                <span class="text-muted"><?php echo $horaP ? substr($horaP, 0, 5) : ''; ?></span>
              </div>
            </div>

            <div class="col-6">
              <label class="form-label form-label-soft text-uppercase" style="font-size: 10px;">Início Real</label>
              <div class="small fw-semibold text-light">
                <?php echo $iniReal ? date('d/m/Y', strtotime($iniReal)) : '—'; ?> <br>
                <span class="text-muted"><?php echo $iniReal ? date('H:i', strtotime($iniReal)) : ''; ?></span>
              </div>
            </div>
            
            <div class="col-6 mt-2">
              <label class="form-label form-label-soft text-uppercase" style="font-size: 10px;">Fim Real</label>
              <div class="small fw-semibold text-light">
                <?php echo $fimReal ? date('d/m/Y', strtotime($fimReal)) : '—'; ?> <br>
                <span class="text-muted"><?php echo $fimReal ? date('H:i', strtotime($fimReal)) : ''; ?></span>
              </div>
            </div>

            <?php if ($os['gestor_atribuicao_nome']): ?>
              <div class="col-6 mt-2">
                <label class="form-label form-label-soft text-uppercase" style="font-size: 10px;">Atribuído por</label>
                <div class="small text-muted"><?php echo h($os['gestor_atribuicao_nome']); ?></div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="pt-4 border-top border-secondary border-opacity-10 mt-3 d-flex gap-2">
          <?php if (!in_array($est, ['concluida', 'cancelada'], true)): ?>
            <a class="btn btn-primary btn-sm flex-grow-1" href="edit.php?id=<?php echo $id; ?>">
              <i class="bi bi-pencil-fill me-1"></i>Editar Ordem
            </a>
            <form method="post" class="flex-grow-1" onsubmit="return confirm('Deseja realmente cancelar esta Ordem de Serviço?');">
              <input type="hidden" name="acao" value="cancelar">
              <button class="btn btn-danger btn-sm w-100" type="submit">
                <i class="bi bi-x-circle-fill me-1"></i>Cancelar Ordem
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Relatório do Operário (se concluída ou impedida) -->
    <?php if (in_array($est, ['concluida', 'impedida'], true)): ?>
      <div class="col-12">
        <div class="glass-card p-4" style="border-left: 4px solid <?php echo ($est==='concluida') ? 'hsl(152, 60%, 40%)' : 'hsl(0, 72%, 51%)'; ?>">
          <h3 class="section-title mb-3 d-flex align-items-center gap-2">
            <i class="bi <?php echo ($est==='concluida') ? 'bi-check-circle-fill text-success' : 'bi-exclamation-octagon-fill text-danger'; ?>"></i>
            Relatório de Execução (Operário)
          </h3>
          <div class="text-light text-pre-line p-3 rounded bg-dark bg-opacity-25 border border-secondary border-opacity-10 min-h-80">
            <?php echo $obsOperario !== '' ? nl2br(h($obsOperario)) : '<span class="text-muted italic">Nenhuma observação reportada pelo operário.</span>'; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
