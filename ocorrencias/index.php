<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'ocorrencias';

require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fmtData($d) {
  if (!$d) return '—';
  $dt = DateTime::createFromFormat('Y-m-d H:i:s', $d);
  if (!$dt) $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
  return $dt ? $dt->format('d/m/Y H:i') : h($d);
}

function badgeEstadoOcorrencia($estado) {
  $estado = (string)$estado;

  return match ($estado) {
    'aberta' => '<span class="badge-pill badge-info-soft">Aberta</span>',
    'em_analise' => '<span class="badge-pill badge-warning-soft">Em análise</span>',
    'convertida_manutencao' => '<span class="badge-pill badge-danger-soft">Manutenção</span>',
    'resolvida' => '<span class="badge-pill badge-success-soft">Resolvida</span>',
    'rejeitada' => '<span class="badge-pill badge-danger-soft">Rejeitada</span>',
    default => '<span class="badge-pill badge-info-soft">' . h($estado) . '</span>',
  };
}

function badgeGravidadeOcorrencia($gravidade) {
  $gravidade = (string)$gravidade;

  return match ($gravidade) {
    'baixa' => '<span class="badge-pill badge-info-soft" style="font-size: 11px;">Baixa</span>',
    'media' => '<span class="badge-pill badge-warning-soft" style="font-size: 11px;">Média</span>',
    'alta' => '<span class="badge-pill badge-danger-soft" style="font-size: 11px; background-color: rgba(239, 68, 68, 0.1); color: #ef4444;">Alta</span>',
    'critica' => '<span class="badge-pill badge-danger-soft" style="font-size: 11px; font-weight: 700; background-color: rgba(220, 38, 38, 0.15); color: #dc2626; border: 1px dashed #dc2626;">Crítica</span>',
    default => '<span class="badge-pill badge-info-soft" style="font-size: 11px;">' . h($gravidade) . '</span>',
  };
}

// Filtros
$q = trim($_GET['q'] ?? '');
$estadoFiltro = trim($_GET['estado'] ?? '');
$gravidadeFiltro = trim($_GET['gravidade'] ?? '');

$where = [];

if ($q !== '') {
  $q_safe = mysqli_real_escape_string($ligacao, $q);
  $where[] = "(
        o.codigo LIKE '%$q_safe%'
     OR o.titulo LIKE '%$q_safe%'
     OR o.descricao LIKE '%$q_safe%'
     OR v.matricula LIKE '%$q_safe%'
     OR v.marca_modelo LIKE '%$q_safe%'
     OR m.nome LIKE '%$q_safe%'
  )";
}

if ($estadoFiltro !== '') {
  $estado_safe = mysqli_real_escape_string($ligacao, $estadoFiltro);
  $where[] = "o.estado = '$estado_safe'";
}

if ($gravidadeFiltro !== '') {
  $gravidade_safe = mysqli_real_escape_string($ligacao, $gravidadeFiltro);
  $where[] = "o.gravidade = '$gravidade_safe'";
}

$filtro_zona = sql_filtro_zona_ocorrencia("o");
if ($filtro_zona !== "") {
  $where[] = substr($filtro_zona, 5); // remove leading " AND "
}

// KPIs
function fetchOneInt($ligacao, $sql) {
  $r = mysqli_query($ligacao, $sql);
  if (!$r) return 0;
  $row = mysqli_fetch_assoc($r);
  return (int)($row['n'] ?? 0);
}

$filtro_zona_kpi = sql_filtro_zona_ocorrencia("o");
$kpiTotal = fetchOneInt($ligacao, "SELECT COUNT(*) AS n FROM ocorrencias o WHERE 1=1{$filtro_zona_kpi}");
$kpiPendentes = fetchOneInt($ligacao, "SELECT COUNT(*) AS n FROM ocorrencias o WHERE o.estado IN ('aberta', 'em_analise'){$filtro_zona_kpi}");
$kpiCriticas = fetchOneInt($ligacao, "SELECT COUNT(*) AS n FROM ocorrencias o WHERE o.gravidade IN ('alta', 'critica') AND o.estado <> 'resolvida'{$filtro_zona_kpi}");
$kpiResolvidas = fetchOneInt($ligacao, "SELECT COUNT(*) AS n FROM ocorrencias o WHERE o.estado = 'resolvida'{$filtro_zona_kpi}");

// Consulta Principal
$sql = "
  SELECT
    o.id,
    o.codigo,
    o.tipo,
    o.gravidade,
    o.titulo,
    o.descricao,
    o.estado,
    o.criado_em,
    v.matricula,
    v.marca_modelo,
    m.nome AS motorista_nome,
    s.codigo AS servico_codigo
  FROM ocorrencias o
  LEFT JOIN viaturas v ON v.id = o.viatura_id
  LEFT JOIN motoristas m ON m.id = o.motorista_id
  LEFT JOIN servicos_operacionais s ON s.id = o.servico_id
";

if ($where) {
  $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY o.criado_em DESC, o.id DESC";

$rows = [];
$res = mysqli_query($ligacao, $sql);
if ($res) {
  while ($r = mysqli_fetch_assoc($res)) {
    $rows[] = $r;
  }
}

$msg = $_GET['msg'] ?? '';
?>

<div class="page-max-6xl space-y-6">

  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
      <h1 class="page-title">Ocorrências</h1>
      <div class="page-subtitle">
        Triagem e tratamento de incidentes relatados pelos operários
      </div>
    </div>
  </div>



  <!-- KPIs -->
  <div class="row g-3">
    <div class="col-12 col-sm-6 col-md-3">
      <div class="glass-card p-3 h-100">
        <div class="kpi-label">Total Reportado</div>
        <div class="kpi-value"><?php echo $kpiTotal; ?></div>
        <div class="kpi-sub">Registos totais</div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="glass-card p-3 h-100" style="border-left: 3px solid hsl(205,80%,40%);">
        <div class="kpi-label">Pendentes</div>
        <div class="kpi-value text-info"><?php echo $kpiPendentes; ?></div>
        <div class="kpi-sub">Por analisar</div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="glass-card p-3 h-100" style="border-left: 3px solid #dc2626;">
        <div class="kpi-label">Alta / Crítica Pendentes</div>
        <div class="kpi-value text-danger"><?php echo $kpiCriticas; ?></div>
        <div class="kpi-sub">Exigem atenção imediata</div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-md-3">
      <div class="glass-card p-3 h-100" style="border-left: 3px solid hsl(152,60%,40%);">
        <div class="kpi-label">Resolvidas</div>
        <div class="kpi-value text-success"><?php echo $kpiResolvidas; ?></div>
        <div class="kpi-sub">Incidentes fechados</div>
      </div>
    </div>
  </div>

  <!-- Filtros -->
  <div class="glass-card p-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-12 col-lg-5">
        <label class="form-label form-label-soft">Pesquisar</label>
        <div class="searchbox">
          <span class="search-ico"><i class="bi bi-search"></i></span>
          <input
            type="text"
            class="form-control"
            name="q"
            placeholder="Matrícula, veículo, motorista, código ou título..."
            value="<?php echo h($q); ?>"
          >
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-2">
        <label class="form-label form-label-soft">Estado</label>
        <select name="estado" class="form-select">
          <option value="">Todos</option>
          <option value="aberta" <?php echo $estadoFiltro === 'aberta' ? 'selected' : ''; ?>>Aberta</option>
          <option value="em_analise" <?php echo $estadoFiltro === 'em_analise' ? 'selected' : ''; ?>>Em análise</option>
          <option value="convertida_manutencao" <?php echo $estadoFiltro === 'convertida_manutencao' ? 'selected' : ''; ?>>Manutenção</option>
          <option value="resolvida" <?php echo $estadoFiltro === 'resolvida' ? 'selected' : ''; ?>>Resolvida</option>
          <option value="rejeitada" <?php echo $estadoFiltro === 'rejeitada' ? 'selected' : ''; ?>>Rejeitada</option>
        </select>
      </div>

      <div class="col-12 col-md-4 col-lg-2">
        <label class="form-label form-label-soft">Gravidade</label>
        <select name="gravidade" class="form-select">
          <option value="">Todas</option>
          <option value="baixa" <?php echo $gravidadeFiltro === 'baixa' ? 'selected' : ''; ?>>Baixa</option>
          <option value="media" <?php echo $gravidadeFiltro === 'media' ? 'selected' : ''; ?>>Média</option>
          <option value="alta" <?php echo $gravidadeFiltro === 'alta' ? 'selected' : ''; ?>>Alta</option>
          <option value="critica" <?php echo $gravidadeFiltro === 'critica' ? 'selected' : ''; ?>>Crítica</option>
        </select>
      </div>

      <div class="col-12 col-md-4 col-lg-3 d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit">Filtrar</button>
        <a class="btn btn-outline-secondary" href="index.php">Limpar</a>
      </div>
    </form>
  </div>

  <!-- Lista -->
  <div class="vstack gap-3">
    <?php if (count($rows) > 0): ?>
      <?php foreach ($rows as $o): ?>
        <?php
          $id = (int)$o['id'];
          $mat = (string)($o['matricula'] ?? '—');
          $nomeV = (string)($o['marca_modelo'] ?? '');
          $motorista = $o['motorista_nome'] ?: 'Operário desconhecido';
          $data = (string)$o['criado_em'];
        ?>

        <div class="glass-card p-4">
          <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">

            <div class="d-flex gap-3 align-items-start">
              <div class="list-ico warn bg-danger-soft text-danger" style="width:40px;height:40px;">
                <i class="bi bi-exclamation-triangle-fill"></i>
              </div>

              <div class="min-w-0">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                  <span class="fw-bold text-dark fs-5"><?php echo h($o['titulo']); ?></span>
                  <span class="badge-pill bg-light text-muted small" style="font-size: 11px;"><?php echo h($o['codigo']); ?></span>
                  <?php echo badgeGravidadeOcorrencia($o['gravidade']); ?>
                </div>

                <div class="small text-muted">
                  <strong>Viatura:</strong> <?php echo h($mat); ?> • <?php echo h($nomeV); ?>
                </div>

                <div class="small text-muted">
                  <strong>Reportado por:</strong> <?php echo h($motorista); ?>
                </div>

                <div class="small text-muted">
                  <strong>Tipo:</strong> <?php echo h(ucfirst($o['tipo'])); ?>
                </div>

                <div class="small text-muted mt-1">
                  <?php if (!empty($o['servico_codigo'])): ?>
                    Serviço de Origem: <span class="fw-semibold"><?php echo h($o['servico_codigo']); ?></span>
                  <?php endif; ?>
                </div>

                <div class="mt-2 text-muted text-break small rounded bg-light p-2 border-start border-3 border-secondary" style="max-height: 80px; overflow: hidden; text-overflow: ellipsis;">
                  <?php echo h($o['descricao']); ?>
                </div>
              </div>
            </div>

            <div class="text-lg-end d-flex flex-column justify-content-between align-items-lg-end">
              <div>
                <div class="small text-muted">
                  <?php echo fmtData($data); ?>
                </div>

                <div class="mt-2">
                  <?php echo badgeEstadoOcorrencia($o['estado']); ?>
                </div>
              </div>

              <div class="mt-3">
                <a class="btn btn-sm btn-outline-primary" href="show.php?id=<?php echo $id; ?>">
                  Analisar e Tratar
                </a>
              </div>
            </div>

          </div>
        </div>

      <?php endforeach; ?>
    <?php else: ?>
      <div class="glass-card p-4 text-center text-muted">
        Nenhuma ocorrência encontrada.
      </div>
    <?php endif; ?>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>
