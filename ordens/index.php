<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'ordens_servico';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function qs(array $extra = []) {
  $base = $_GET;
  foreach ($extra as $k => $v) {
    if ($v === null) unset($base[$k]);
    else $base[$k] = $v;
  }
  $q = http_build_query($base);
  return $q ? ('?' . $q) : '';
}

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

$q = trim($_GET['q'] ?? '');
$estadoFiltro = trim($_GET['estado'] ?? '');
$prioridadeFiltro = trim($_GET['prioridade'] ?? '');

// KPIs
$kpiTotal = 0;
$kpiPendentes = 0;
$kpiExecucao = 0;
$kpiConcluidas = 0;
$kpiImpedidas = 0;

$r1 = mysqli_query($ligacao, "SELECT COUNT(*) AS t FROM ordens_servico");
if ($r1) $kpiTotal = (int)(mysqli_fetch_assoc($r1)['t'] ?? 0);

$r2 = mysqli_query($ligacao, "SELECT COUNT(*) AS t FROM ordens_servico WHERE estado IN ('atribuida','aceite')");
if ($r2) $kpiPendentes = (int)(mysqli_fetch_assoc($r2)['t'] ?? 0);

$r3 = mysqli_query($ligacao, "SELECT COUNT(*) AS t FROM ordens_servico WHERE estado IN ('em_deslocacao','em_execucao')");
if ($r3) $kpiExecucao = (int)(mysqli_fetch_assoc($r3)['t'] ?? 0);

$r4 = mysqli_query($ligacao, "SELECT COUNT(*) AS t FROM ordens_servico WHERE estado = 'concluida'");
if ($r4) $kpiConcluidas = (int)(mysqli_fetch_assoc($r4)['t'] ?? 0);

$r5 = mysqli_query($ligacao, "SELECT COUNT(*) AS t FROM ordens_servico WHERE estado = 'impedida'");
if ($r5) $kpiImpedidas = (int)(mysqli_fetch_assoc($r5)['t'] ?? 0);

// Cláusulas WHERE
$where = [];
if ($q !== '') {
  $q_safe = mysqli_real_escape_string($ligacao, $q);
  $where[] = "(os.codigo LIKE '%$q_safe%' OR os.titulo LIKE '%$q_safe%' OR os.descricao LIKE '%$q_safe%' OR m.nome LIKE '%$q_safe%' OR v.matricula LIKE '%$q_safe%')";
}
if ($estadoFiltro !== '') {
  $estado_safe = mysqli_real_escape_string($ligacao, $estadoFiltro);
  $where[] = "os.estado = '$estado_safe'";
}
if ($prioridadeFiltro !== '') {
  $prioridade_safe = mysqli_real_escape_string($ligacao, $prioridadeFiltro);
  $where[] = "os.prioridade = '$prioridade_safe'";
}

$sql = "SELECT 
          os.id, os.codigo, os.titulo, os.descricao, os.tipo, os.prioridade, os.estado, os.data_prevista, os.hora_prevista,
          m.nome AS motorista_nome,
          v.matricula AS viatura_matricula, v.marca_modelo AS viatura_marca_modelo,
          inf.nome AS infraestrutura_nome
        FROM ordens_servico os
        LEFT JOIN motoristas m ON m.id = os.motorista_id
        LEFT JOIN viaturas v ON v.id = os.viatura_id
        LEFT JOIN infraestruturas inf ON inf.id = os.infraestrutura_id";

if ($where) {
  $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY os.criado_em DESC, os.id DESC";

$rows = [];
$res = mysqli_query($ligacao, $sql);
if ($res) {
  while ($row = mysqli_fetch_assoc($res)) {
    $rows[] = $row;
  }
}
?>

<div class="page-max-6xl space-y-6">

  <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
    <div>
      <h1 class="page-title">Ordens de Serviço</h1>
      <div class="page-subtitle">Planeamento e distribuição de tarefas operacionais em campo</div>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
      <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'criada'): ?>
          <div class="alert alert-success py-2 px-3 mb-0">Ordem de Serviço criada com sucesso!</div>
        <?php elseif ($_GET['msg'] === 'editada'): ?>
          <div class="alert alert-success py-2 px-3 mb-0">Ordem de Serviço atualizada!</div>
        <?php elseif ($_GET['msg'] === 'cancelada'): ?>
          <div class="alert alert-success py-2 px-3 mb-0">Ordem de Serviço cancelada.</div>
        <?php endif; ?>
      <?php endif; ?>

      <a href="create.php" class="btn btn-primary">Nova Ordem de Serviço</a>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3">
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="glass-card stat-gradient p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Pendentes</div>
            <div class="kpi-value"><?php echo $kpiPendentes; ?></div>
            <div class="kpi-sub">atribuídas / aceites</div>
          </div>
          <div class="kpi-ico text-info"><i class="bi bi-clock-history"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
      <div class="glass-card stat-gradient p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Em Execução</div>
            <div class="kpi-value"><?php echo $kpiExecucao; ?></div>
            <div class="kpi-sub">trabalho em progresso</div>
          </div>
          <div class="kpi-ico text-warning"><i class="bi bi-gear-fill"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
      <div class="glass-card stat-gradient p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Concluídas</div>
            <div class="kpi-value"><?php echo $kpiConcluidas; ?></div>
            <div class="kpi-sub">tarefas finalizadas</div>
          </div>
          <div class="kpi-ico text-success"><i class="bi bi-check-circle-fill"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
      <div class="glass-card stat-gradient p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Impedidas</div>
            <div class="kpi-value"><?php echo $kpiImpedidas; ?></div>
            <div class="kpi-sub">requerem atenção</div>
          </div>
          <div class="kpi-ico text-danger"><i class="bi bi-exclamation-triangle-fill"></i></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filtros -->
  <div class="glass-card p-3">
    <div class="d-flex flex-column flex-lg-row gap-3 align-items-stretch align-items-lg-center">
      <div class="flex-grow-1">
        <div class="searchbox">
          <span class="search-ico"><i class="bi bi-search"></i></span>
          <input
            type="text"
            class="form-control form-control-lg"
            name="q"
            form="filtersForm"
            placeholder="Buscar por código, título, motorista ou matrícula..."
            value="<?php echo h($q); ?>"
          >
        </div>
      </div>

      <form id="filtersForm" method="get" class="d-flex gap-2 flex-wrap justify-content-lg-end">
        <input type="hidden" name="q" value="<?php echo h($q); ?>">

        <select class="form-select" name="estado" style="min-width: 160px;" onchange="this.form.submit()">
          <option value="">Todos os Estados</option>
          <option value="rascunho" <?php echo ($estadoFiltro==='rascunho')?'selected':''; ?>>Rascunho</option>
          <option value="atribuida" <?php echo ($estadoFiltro==='atribuida')?'selected':''; ?>>Atribuída</option>
          <option value="aceite" <?php echo ($estadoFiltro==='aceite')?'selected':''; ?>>Aceite</option>
          <option value="em_deslocacao" <?php echo ($estadoFiltro==='em_deslocacao')?'selected':''; ?>>Em deslocação</option>
          <option value="em_execucao" <?php echo ($estadoFiltro==='em_execucao')?'selected':''; ?>>Em execução</option>
          <option value="concluida" <?php echo ($estadoFiltro==='concluida')?'selected':''; ?>>Concluída</option>
          <option value="impedida" <?php echo ($estadoFiltro==='impedida')?'selected':''; ?>>Impedida</option>
          <option value="cancelada" <?php echo ($estadoFiltro==='cancelada')?'selected':''; ?>>Cancelada</option>
        </select>

        <select class="form-select" name="prioridade" style="min-width: 140px;" onchange="this.form.submit()">
          <option value="">Prioridades</option>
          <option value="baixa" <?php echo ($prioridadeFiltro==='baixa')?'selected':''; ?>>Baixa</option>
          <option value="media" <?php echo ($prioridadeFiltro==='media')?'selected':''; ?>>Média</option>
          <option value="alta" <?php echo ($prioridadeFiltro==='alta')?'selected':''; ?>>Alta</option>
          <option value="critica" <?php echo ($prioridadeFiltro==='critica')?'selected':''; ?>>Crítica</option>
        </select>

        <button class="btn btn-outline-primary" type="submit">Filtrar</button>
        <a class="btn btn-outline-secondary" href="index.php">Limpar</a>
      </form>
    </div>

    <div class="chips mt-3">
      <a class="chip <?php echo ($estadoFiltro==='' ? 'active' : ''); ?>" href="index.php<?php echo qs(['estado'=>null]); ?>">Todas</a>
      <a class="chip <?php echo ($estadoFiltro==='atribuida' ? 'active' : ''); ?>" href="index.php<?php echo qs(['estado'=>'atribuida']); ?>">Atribuídas</a>
      <a class="chip <?php echo ($estadoFiltro==='em_execucao' ? 'active' : ''); ?>" href="index.php<?php echo qs(['estado'=>'em_execucao']); ?>">Em Execução</a>
      <a class="chip <?php echo ($estadoFiltro==='impedida' ? 'active' : ''); ?>" href="index.php<?php echo qs(['estado'=>'impedida']); ?>">Impedidas</a>
    </div>
  </div>

  <!-- Lista de Resultados -->
  <div class="row g-3">
    <?php if (count($rows) > 0): ?>
      <?php foreach ($rows as $os): ?>
        <?php
          $idOS = (int)$os['id'];
          $cod = (string)$os['codigo'];
          $tit = (string)$os['titulo'];
          $tipo = (string)$os['tipo'];
          $prio = (string)$os['prioridade'];
          $est  = (string)$os['estado'];
          
          $mNome = (string)($os['motorista_nome'] ?? 'Não atribuído');
          $vNome = (string)($os['viatura_marca_modelo'] ?? '');
          $vMat  = (string)($os['viatura_matricula'] ?? '');
          $infra = (string)($os['infraestrutura_nome'] ?? 'Sem local');
          $dataP = (string)($os['data_prevista'] ?? '');
          $horaP = (string)($os['hora_prevista'] ?? '');

          $pill  = pillClassOS($est);
          $badge = badgePrioridadeOS($prio);
        ?>

        <div class="col-12">
          <div class="glass-card p-4">
            <div class="d-flex flex-column flex-md-row align-items-start justify-content-between gap-3">
              <div class="d-flex gap-3 align-items-start">
                <div class="list-ico <?php echo ($est === 'concluida') ? 'success' : (($est === 'impedida' || $est === 'cancelada') ? 'danger' : 'info'); ?>" style="width:40px;height:40px;">
                  <i class="bi bi-clipboard2-check"></i>
                </div>
                <div class="min-w-0">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="text-muted small fw-semibold"><?php echo h($cod); ?></span>
                    <span class="badge-pill <?php echo $badge; ?> text-uppercase" style="font-size: 9px; padding: 2px 6px;"><?php echo h($prio); ?></span>
                  </div>
                  <div class="fw-semibold fs-5 mt-1"><?php echo h($tit); ?></div>
                  <div class="small text-muted mt-1">
                    <i class="bi bi-geo-alt-fill me-1"></i><?php echo h($infra); ?> 
                    <?php if ($vNome): ?>
                      · <i class="bi bi-car-front-fill mx-1"></i><?php echo h($vNome); ?> [<strong class="text-light"><?php echo h($vMat); ?></strong>]
                    <?php endif; ?>
                  </div>
                  <div class="small text-muted mt-1">
                    <i class="bi bi-person-fill me-1"></i>Motorista: <strong><?php echo h($mNome); ?></strong>
                  </div>
                </div>
              </div>

              <div class="d-flex flex-column align-items-end justify-content-between gap-3 ms-md-auto text-md-end w-100 w-md-auto">
                <div class="text-md-end">
                  <span class="pill <?php echo h($pill); ?> text-uppercase" style="font-size: 11px;"><?php echo h(str_replace('_', ' ', $est)); ?></span>
                  <?php if ($dataP): ?>
                    <div class="small text-muted mt-1">Previsto: <?php echo date('d/m/Y', strtotime($dataP)) . ($horaP ? ' às ' . substr($horaP, 0, 5) : ''); ?></div>
                  <?php endif; ?>
                </div>

                <div class="d-flex gap-2">
                  <a class="btn btn-sm btn-outline-primary" href="show.php?id=<?php echo $idOS; ?>">
                    <i class="bi bi-eye-fill me-1"></i>Detalhes
                  </a>
                </div>
              </div>
            </div>
          </div>
        </div>

      <?php endforeach; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="glass-card p-4 text-center text-muted">Nenhuma Ordem de Serviço encontrada.</div>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
