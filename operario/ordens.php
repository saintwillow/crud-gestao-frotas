<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

if (is_gestor_ou_admin()) {
  header("Location: " . base_url() . "/ordens/index.php");
  exit;
}

$active = 'operario_ordens';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function pillClassOS($estado) {
  switch ($estado) {
    case 'atribuida': return 'badge-info-soft';
    case 'aceite': return 'badge-primary-soft';
    case 'em_deslocacao':
    case 'em_execucao': return 'badge-warning-soft';
    case 'concluida': return 'badge-success-soft';
    case 'impedida':
    case 'cancelada': return 'badge-danger-soft';
    default: return 'badge-secondary-soft';
  }
}

$motorista_id = motorista_id_sessao();

if (!$motorista_id) {
  echo '<div class="glass-card p-4 text-center text-muted">Conta de utilizador não associada a motorista.</div>';
  mysqli_close($ligacao);
  require_once __DIR__ . "/../inc/footer.php";
  exit;
}

// 1. Carregar Ordens Ativas (atribuida, aceite, em_deslocacao, em_execucao)
$stmtAtivas = mysqli_prepare($ligacao, "
  SELECT 
    os.id, os.codigo, os.titulo, os.prioridade, os.estado, os.data_prevista, os.hora_prevista,
    v.matricula AS viatura_matricula, v.marca_modelo AS viatura_marca_modelo,
    inf.nome AS infraestrutura_nome, inf.localidade AS infraestrutura_localidade
  FROM ordens_servico os
  LEFT JOIN viaturas v ON v.id = os.viatura_id
  LEFT JOIN infraestruturas inf ON inf.id = os.infraestrutura_id
  WHERE os.motorista_id = ? AND os.estado IN ('atribuida', 'aceite', 'em_deslocacao', 'em_execucao')
  ORDER BY os.prioridade = 'critica' DESC, os.prioridade = 'alta' DESC, os.data_prevista ASC, os.id DESC
");
mysqli_stmt_bind_param($stmtAtivas, "i", $motorista_id);
mysqli_stmt_execute($stmtAtivas);
$resAtivas = mysqli_stmt_get_result($stmtAtivas);
$ativas = [];
if ($resAtivas) while ($row = mysqli_fetch_assoc($resAtivas)) $ativas[] = $row;
mysqli_stmt_close($stmtAtivas);

// 2. Carregar Histórico (concluida, impedida, cancelada)
$stmtHist = mysqli_prepare($ligacao, "
  SELECT 
    os.id, os.codigo, os.titulo, os.prioridade, os.estado, os.fim_real,
    v.matricula AS viatura_matricula,
    inf.nome AS infraestrutura_nome
  FROM ordens_servico os
  LEFT JOIN viaturas v ON v.id = os.viatura_id
  LEFT JOIN infraestruturas inf ON inf.id = os.infraestrutura_id
  WHERE os.motorista_id = ? AND os.estado IN ('concluida', 'impedida', 'cancelada')
  ORDER BY os.fim_real DESC, os.id DESC
  LIMIT 20
");
mysqli_stmt_bind_param($stmtHist, "i", $motorista_id);
mysqli_stmt_execute($stmtHist);
$resHist = mysqli_stmt_get_result($stmtHist);
$historico = [];
if ($resHist) while ($row = mysqli_fetch_assoc($resHist)) $historico[] = $row;
mysqli_stmt_close($stmtHist);
?>

<div class="page-max-4xl space-y-6">

  <div>
    <h1 class="page-title">As Minhas Ordens de Serviço</h1>
    <div class="page-subtitle">Tarefas e ordens de trabalho atribuídas a mim</div>
  </div>



  <!-- Abas/Tabs de visualização -->
  <ul class="nav nav-pills gap-2 mb-4" id="pills-tab" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="pills-ativas-tab" data-bs-toggle="pill" data-bs-target="#pills-ativas" type="button" role="tab">
        Ativas (<?php echo count($ativas); ?>)
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="pills-historico-tab" data-bs-toggle="pill" data-bs-target="#pills-historico" type="button" role="tab">
        Histórico Recente
      </button>
    </li>
  </ul>

  <div class="tab-content" id="pills-tabContent">
    
    <!-- ABA TAREFAS ATIVAS -->
    <div class="tab-pane fade show active" id="pills-ativas" role="tabpanel">
      <div class="vstack gap-3">
        <?php if (count($ativas) > 0): ?>
          <?php foreach ($ativas as $os): ?>
            <?php
              $idOS = (int)$os['id'];
              $cod = (string)$os['codigo'];
              $tit = (string)$os['titulo'];
              $prio = (string)$os['prioridade'];
              $est  = (string)$os['estado'];
              
              $vNome = (string)$os['viatura_marca_modelo'];
              $vMat  = (string)$os['viatura_matricula'];
              $infra = (string)($os['infraestrutura_nome'] ?? 'Sem local');
              $dataP = (string)($os['data_prevista'] ?? '');
              $horaP = (string)($os['hora_prevista'] ?? '');
              
              $pill = pillClassOS($est);
            ?>
            <a href="ordem_detalhe.php?id=<?php echo $idOS; ?>" class="glass-card p-3 d-block text-decoration-none hover-card transition-all">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="min-w-0">
                  <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <span class="text-muted small fw-semibold" style="font-size: 11px;"><?php echo h($cod); ?></span>
                    <span class="badge-pill <?php echo $pill; ?> text-uppercase" style="font-size: 9px; padding: 2px 6px;"><?php echo h(str_replace('_', ' ', $est)); ?></span>
                    
                    <?php if ($prio === 'critica' || $prio === 'alta'): ?>
                      <span class="badge bg-danger text-uppercase" style="font-size: 8px; font-weight: 700; padding: 3px 6px; border-radius: 4px;">
                        <i class="bi bi-fire me-1"></i><?php echo h($prio); ?>
                      </span>
                    <?php endif; ?>
                  </div>
                  <div class="fw-semibold text-light fs-5 mt-1"><?php echo h($tit); ?></div>
                  
                  <div class="small text-muted mt-2">
                    <i class="bi bi-geo-alt-fill me-1"></i><?php echo h($infra); ?>
                  </div>
                  <?php if ($vNome): ?>
                    <div class="small text-muted mt-1">
                      <i class="bi bi-car-front-fill me-1"></i><?php echo h($vNome); ?> [<strong class="text-light"><?php echo h($vMat); ?></strong>]
                    </div>
                  <?php endif; ?>
                </div>

                <div class="text-end ms-auto ps-2 d-none d-sm-block">
                  <i class="bi bi-chevron-right text-muted fs-4"></i>
                  <?php if ($dataP): ?>
                    <div class="small text-muted mt-2" style="font-size: 11px;">Previsto: <?php echo date('d/m', strtotime($dataP)) . ($horaP ? ' ' . substr($horaP, 0, 5) : ''); ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="glass-card p-4 text-center text-muted">
            <i class="bi bi-clipboard-check fs-2 mb-2 d-block"></i>
            Excelente! Não tem nenhuma Ordem de Serviço pendente.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ABA HISTÓRICO -->
    <div class="tab-pane fade" id="pills-historico" role="tabpanel">
      <div class="vstack gap-2">
        <?php if (count($historico) > 0): ?>
          <?php foreach ($historico as $os): ?>
            <?php
              $idOS = (int)$os['id'];
              $cod = (string)$os['codigo'];
              $tit = (string)$os['titulo'];
              $est  = (string)$os['estado'];
              $infra = (string)($os['infraestrutura_nome'] ?? 'Sem local');
              $fimReal = (string)($os['fim_real'] ?? '');
              
              $pill = pillClassOS($est);
            ?>
            <a href="ordem_detalhe.php?id=<?php echo $idOS; ?>" class="glass-card p-3 d-block text-decoration-none hover-card transition-all">
              <div class="d-flex justify-content-between align-items-center gap-2">
                <div class="min-w-0">
                  <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <span class="text-muted small fw-semibold" style="font-size: 11px;"><?php echo h($cod); ?></span>
                    <span class="badge-pill <?php echo $pill; ?> text-uppercase" style="font-size: 9px; padding: 2px 6px;"><?php echo h($est); ?></span>
                  </div>
                  <div class="fw-semibold text-light small text-truncate"><?php echo h($tit); ?></div>
                  <div class="small text-muted" style="font-size: 11px;">Local: <?php echo h($infra); ?></div>
                </div>

                <div class="text-end ms-auto ps-2">
                  <span class="text-muted small" style="font-size: 11px;"><?php echo $fimReal ? date('d/m/Y', strtotime($fimReal)) : '—'; ?></span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="glass-card p-4 text-center text-muted">
            Não possui ordens finalizadas no histórico recente.
          </div>
        <?php endif; ?>
      </div>
    </div>

  </div>

</div>

<!-- Bootstrap JS Tab triggering fallback (caso dependa de bootstrap.bundle.js já importado no footer) -->
<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
