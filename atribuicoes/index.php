<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'atribuicoes';
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

function pillClassAtr($estado) {
  switch ($estado) {
    case 'aberta': return 'pill-info';
    case 'encerrada': return 'pill-success';
    case 'cancelada': return 'pill-danger';
    default: return 'pill-info';
  }
}

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? 'aberta'); // padrão: abertas

// KPIs
$kpiAbertas = 0;
$kpiEncerradas = 0;
$kpiMotoristasSem = 0;
$kpiViaturasSem = 0;

$filtro_zona = sql_filtro_zona_viatura("v");

$r1 = mysqli_query($ligacao, "SELECT COUNT(*) AS t FROM atribuicoes a JOIN viaturas v ON v.id = a.viatura_id WHERE a.estado = 'aberta' {$filtro_zona}");
if ($r1) $kpiAbertas = (int)(mysqli_fetch_assoc($r1)['t'] ?? 0);

$r2 = mysqli_query($ligacao, "SELECT COUNT(*) AS t FROM atribuicoes a JOIN viaturas v ON v.id = a.viatura_id WHERE a.estado = 'encerrada' {$filtro_zona}");
if ($r2) $kpiEncerradas = (int)(mysqli_fetch_assoc($r2)['t'] ?? 0);

$filtro_zona_mot = sql_filtro_zona_motorista("m");
$r3 = mysqli_query($ligacao, "SELECT COUNT(*) AS t FROM motoristas m WHERE m.status = 'Ativo' {$filtro_zona_mot} AND NOT EXISTS (SELECT 1 FROM atribuicoes a WHERE a.motorista_id = m.id AND a.estado = 'aberta')");
if ($r3) $kpiMotoristasSem = (int)(mysqli_fetch_assoc($r3)['t'] ?? 0);

$r4 = mysqli_query($ligacao, "SELECT COUNT(*) AS t FROM viaturas v WHERE v.estado = 'Disponível' {$filtro_zona}");
if ($r4) $kpiViaturasSem = (int)(mysqli_fetch_assoc($r4)['t'] ?? 0);

// Query Principal
$where = [];
if ($q !== '') {
  $q_safe = mysqli_real_escape_string($ligacao, $q);
  $where[] = "(m.nome LIKE '%$q_safe%' OR v.matricula LIKE '%$q_safe%' OR v.marca_modelo LIKE '%$q_safe%')";
}
if ($status !== '' && $status !== 'todas') {
  $status_safe = mysqli_real_escape_string($ligacao, $status);
  $where[] = "a.estado = '$status_safe'";
}
if ($filtro_zona !== "") {
  $where[] = substr($filtro_zona, 5);
}

$sql = "SELECT 
          a.id, a.viatura_id, a.motorista_id, a.data_inicio, a.km_inicio, a.data_fim, a.km_fim, a.estado, a.notas,
          m.nome AS motorista_nome,
          v.matricula AS viatura_matricula, v.marca_modelo AS viatura_marca_modelo
        FROM atribuicoes a
        JOIN motoristas m ON m.id = a.motorista_id
        JOIN viaturas v ON v.id = a.viatura_id";

if ($where) {
  $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY (a.estado = 'aberta') DESC, a.data_inicio DESC";

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
      <h1 class="page-title">Atribuições de Viaturas</h1>
      <div class="page-subtitle">Controle de veículos vinculados a motoristas da frota</div>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
      

      <a href="create.php" class="btn btn-primary">Nova Atribuição</a>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3">
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="glass-card stat-gradient p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Ativas / Abertas</div>
            <div class="kpi-value"><?php echo $kpiAbertas; ?></div>
            <div class="kpi-sub">viaturas em uso</div>
          </div>
          <div class="kpi-ico text-info"><i class="bi bi-link-45deg"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
      <div class="glass-card stat-gradient p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Encerradas</div>
            <div class="kpi-value"><?php echo $kpiEncerradas; ?></div>
            <div class="kpi-sub">histórico concluído</div>
          </div>
          <div class="kpi-ico text-success"><i class="bi bi-check-circle-fill"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
      <div class="glass-card stat-gradient p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Motoristas Livres</div>
            <div class="kpi-value"><?php echo $kpiMotoristasSem; ?></div>
            <div class="kpi-sub">sem veículo ativo</div>
          </div>
          <div class="kpi-ico text-warning"><i class="bi bi-person-exclamation"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
      <div class="glass-card stat-gradient p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Veículos Livres</div>
            <div class="kpi-value"><?php echo $kpiViaturasSem; ?></div>
            <div class="kpi-sub">disponíveis na base</div>
          </div>
          <div class="kpi-ico text-success"><i class="bi bi-car-front-fill"></i></div>
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
            placeholder="Buscar por motorista, matrícula ou veículo..."
            value="<?php echo h($q); ?>"
          >
        </div>
      </div>

      <form id="filtersForm" method="get" class="d-flex gap-2 flex-wrap justify-content-lg-end">
        <input type="hidden" name="q" value="<?php echo h($q); ?>">

        <select class="form-select" name="status" style="min-width: 180px;" onchange="this.form.submit()">
          <option value="todas" <?php echo ($status==='todas')?'selected':''; ?>>Todos os Estados</option>
          <option value="aberta" <?php echo ($status==='aberta')?'selected':''; ?>>Abertas</option>
          <option value="encerrada" <?php echo ($status==='encerrada')?'selected':''; ?>>Encerradas</option>
          <option value="cancelada" <?php echo ($status==='cancelada')?'selected':''; ?>>Canceladas</option>
        </select>

        <button class="btn btn-outline-primary" type="submit">Filtrar</button>
        <a class="btn btn-outline-secondary" href="index.php">Limpar</a>
      </form>
    </div>

    <div class="chips mt-3">
      <a class="chip <?php echo ($status==='todas' ? 'active' : ''); ?>" href="index.php<?php echo qs(['status'=>'todas']); ?>">Todas</a>
      <a class="chip <?php echo ($status==='aberta' ? 'active' : ''); ?>" href="index.php<?php echo qs(['status'=>'aberta']); ?>">Ativas</a>
      <a class="chip <?php echo ($status==='encerrada' ? 'active' : ''); ?>" href="index.php<?php echo qs(['status'=>'encerrada']); ?>">Encerradas</a>
    </div>
  </div>

  <!-- Lista de Resultados -->
  <div class="row g-3">
    <?php if (count($rows) > 0): ?>
      <?php foreach ($rows as $atr): ?>
        <?php
          $idAtr = (int)$atr['id'];
          $mNome = (string)($atr['motorista_nome'] ?? 'Desconhecido');
          $vNome = (string)($atr['viatura_marca_modelo'] ?? 'Veículo');
          $vMat  = (string)($atr['viatura_matricula'] ?? '-');
          $ini   = (string)($atr['data_inicio'] ?? '—');
          $fim   = (string)($atr['data_fim'] ?? '');
          $kmIni = (int)($atr['km_inicio'] ?? 0);
          $kmFim = (int)($atr['km_fim'] ?? 0);
          $st    = (string)($atr['estado'] ?? 'aberta');
          $pill  = pillClassAtr($st);
          $notas = trim((string)($atr['notas'] ?? ''));
        ?>

        <div class="col-12">
          <div class="glass-card p-4">
            <div class="d-flex align-items-start justify-content-between gap-3">
              <div class="d-flex gap-3 align-items-start">
                <div class="list-ico <?php echo ($st === 'aberta') ? 'info' : 'success'; ?>" style="width:40px;height:40px;">
                  <i class="bi bi-link-45deg"></i>
                </div>
                <div class="min-w-0">
                  <div class="fw-semibold fs-5"><?php echo h($mNome); ?></div>
                  <div class="small text-muted">
                    <i class="bi bi-car-front-fill me-1"></i><?php echo h($vNome); ?> • <strong class="text-light"><?php echo h($vMat); ?></strong>
                  </div>
                  <div class="small text-muted mt-1">
                    <span>Km Inicial: <strong><?php echo number_format($kmIni, 0, ',', '.'); ?> km</strong></span>
                    <?php if ($st === 'encerrada'): ?>
                      <span class="ms-3">Km Final: <strong><?php echo number_format($kmFim, 0, ',', '.'); ?> km</strong></span>
                      <span class="ms-3 text-success">Percorrido: <strong><?php echo number_format($kmFim - $kmIni, 0, ',', '.'); ?> km</strong></span>
                    <?php endif; ?>
                  </div>
                  <?php if ($notas !== ''): ?>
                    <div class="small text-muted mt-2 bg-dark bg-opacity-25 p-2 rounded border border-secondary border-opacity-10">
                      <span class="fw-semibold">Notas:</span> <?php echo h($notas); ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <div class="d-flex align-items-center gap-3 flex-wrap justify-content-end">
                <div class="text-end">
                  <div class="small fw-semibold text-light">Início: <?php echo date('d/m/Y H:i', strtotime($ini)); ?></div>
                  <?php if ($fim !== ''): ?>
                    <div class="small text-muted">Fim: <?php echo date('d/m/Y H:i', strtotime($fim)); ?></div>
                  <?php endif; ?>
                </div>
                <span class="pill <?php echo h($pill); ?> text-uppercase" style="font-size: 11px;"><?php echo h($st); ?></span>
              </div>
            </div>

            <?php if ($st === 'aberta'): ?>
              <div class="d-flex justify-content-end gap-2 mt-3">
                <a class="btn btn-sm btn-outline-danger" href="encerrar.php?id=<?php echo $idAtr; ?>">
                  <i class="bi bi-lock-fill me-1"></i>Encerrar Atribuição
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>

      <?php endforeach; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="glass-card p-4 text-center text-muted">Nenhuma atribuição de viatura encontrada.</div>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
