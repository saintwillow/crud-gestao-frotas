<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'abastecimento';

require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fmtData($d) {
  if (!$d) return '—';
  $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
  return $dt ? $dt->format('d/m/Y') : h($d);
}

function badgeEstadoAbastecimento($a) {
  if (!empty($a['aprovado_por_usuario_id'])) {
    return '<span class="badge-pill badge-success-soft"><i class="bi bi-check-circle-fill me-1"></i>Aprovado</span>';
  }

  $estado = (string)($a['estado'] ?? 'registado');
  if ($estado === 'registado') {
    return '<span class="badge-pill badge-success-soft">Registado</span>';
  }

  if ($estado === 'em_analise') {
    return '<span class="badge-pill badge-warning-soft">Em análise</span>';
  }

  if ($estado === 'corrigido') {
    return '<span class="badge-pill badge-info-soft">Corrigido</span>';
  }

  if ($estado === 'anulado') {
    return '<span class="badge-pill badge-danger-soft">Anulado</span>';
  }

  return '<span class="badge-pill badge-info-soft">' . h($estado) . '</span>';
}

$q = trim($_GET['q'] ?? '');
$estadoFiltro = trim($_GET['estado'] ?? '');
$alertaFiltro = trim($_GET['alerta'] ?? '');

$estadoPermitidos = ['registado','em_analise','corrigido','anulado'];

if ($estadoFiltro !== '' && !in_array($estadoFiltro, $estadoPermitidos, true)) {
  $estadoFiltro = '';
}

$where = [];

if ($q !== '') {
  $q_safe = mysqli_real_escape_string($ligacao, $q);

  $where[] = "(
        v.matricula LIKE '%$q_safe%'
     OR v.marca_modelo LIKE '%$q_safe%'
     OR a.posto LIKE '%$q_safe%'
     OR a.combustivel LIKE '%$q_safe%'
     OR m.nome LIKE '%$q_safe%'
     OR s.codigo LIKE '%$q_safe%'
  )";
}

if ($estadoFiltro !== '') {
  $estado_safe = mysqli_real_escape_string($ligacao, $estadoFiltro);
  $where[] = "a.estado = '$estado_safe'";
}

if ($alertaFiltro === 'sem_posto') {
  $where[] = "(a.posto IS NULL OR a.posto = '')";
}

if ($alertaFiltro === 'sem_km') {
  $where[] = "a.km_atual IS NULL";
}

if ($alertaFiltro === 'sem_servico') {
  $where[] = "a.servico_id IS NULL";
}

/* KPIs principais: anulados não contam em custo operacional */
function fetchOneFloat($ligacao, $sql, $field = 'v') {
  $r = mysqli_query($ligacao, $sql);
  if (!$r) return 0.0;
  $row = mysqli_fetch_assoc($r);
  return (float)($row[$field] ?? 0);
}

function fetchOneInt($ligacao, $sql, $field = 'v') {
  $r = mysqli_query($ligacao, $sql);
  if (!$r) return 0;
  $row = mysqli_fetch_assoc($r);
  return (int)($row[$field] ?? 0);
}

$totalLitros = fetchOneFloat($ligacao,
  "SELECT COALESCE(SUM(litros),0) AS v
   FROM abastecimentos
   WHERE estado <> 'anulado'"
);

$custoTotal = fetchOneFloat($ligacao,
  "SELECT COALESCE(SUM(total),0) AS v
   FROM abastecimentos
   WHERE estado <> 'anulado'"
);

$precoMedio = fetchOneFloat($ligacao,
  "SELECT COALESCE(AVG(preco_litro),0) AS v
   FROM abastecimentos
   WHERE estado <> 'anulado'"
);

$totalRegistos = fetchOneInt($ligacao,
  "SELECT COUNT(*) AS v
   FROM abastecimentos"
);

$totalAnalise = fetchOneInt($ligacao,
  "SELECT COUNT(*) AS v
   FROM abastecimentos
   WHERE estado = 'em_analise'"
);

$totalSemPosto = fetchOneInt($ligacao,
  "SELECT COUNT(*) AS v
   FROM abastecimentos
   WHERE posto IS NULL OR posto = ''"
);

$totalSemKm = fetchOneInt($ligacao,
  "SELECT COUNT(*) AS v
   FROM abastecimentos
   WHERE km_atual IS NULL"
);

/* Lista */
$sql = "
  SELECT
    a.id,
    a.viatura_id,
    a.colaborador_id,
    a.motorista_id,
    a.registado_por_usuario_id,
    a.servico_id,
    a.posto,
    a.combustivel,
    a.litros,
    a.preco_litro,
    a.total,
    a.km_atual,
    a.data_abastecimento,
    a.latitude,
    a.longitude,
    a.estado,
    a.aprovado_por_usuario_id,
    a.comprovativo,
    a.criado_em,
    v.matricula,
    v.marca_modelo,
    m.nome AS motorista_nome,
    c.nome AS colaborador_nome,
    u.nome AS registado_por_nome,
    s.codigo AS servico_codigo
  FROM abastecimentos a
  LEFT JOIN viaturas v ON v.id = a.viatura_id
  LEFT JOIN motoristas m ON m.id = a.motorista_id
  LEFT JOIN colaboradores c ON c.id = a.colaborador_id
  LEFT JOIN usuarios u ON u.id = a.registado_por_usuario_id
  LEFT JOIN servicos_operacionais s ON s.id = a.servico_id
";

if ($where) {
  $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY a.data_abastecimento DESC, a.id DESC";

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
      <h1 class="page-title">Abastecimentos</h1>
      <div class="page-subtitle">
        Registos de combustível da frota
      </div>
    </div>

    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="mapa.php">
        <i class="bi bi-map me-1"></i> Mapa
      </a>

      <a class="btn btn-primary" href="create.php">
        Novo abastecimento
      </a>
    </div>
  </div>

  <?php if ($msg === 'criado'): ?>
    <div class="alert alert-success py-2 px-3 mb-0">Abastecimento criado.</div>
  <?php elseif ($msg === 'editado'): ?>
    <div class="alert alert-success py-2 px-3 mb-0">Abastecimento atualizado.</div>
  <?php elseif ($msg === 'analisado'): ?>
    <div class="alert alert-success py-2 px-3 mb-0">Abastecimento marcado para análise.</div>
  <?php elseif ($msg === 'anulado'): ?>
    <div class="alert alert-success py-2 px-3 mb-0">Abastecimento anulado.</div>
  <?php elseif ($msg === 'corrigido'): ?>
    <div class="alert alert-success py-2 px-3 mb-0">Abastecimento corrigido.</div>
  <?php endif; ?>

  <!-- KPIs -->
  <div class="row g-3">
    <div class="col-12 col-md-3">
      <div class="glass-card p-3 h-100">
        <div class="kpi-label">Total Abastecido</div>
        <div class="kpi-value"><?php echo number_format($totalLitros, 1, ',', '.'); ?> L</div>
        <div class="kpi-sub">Sem anulados</div>
      </div>
    </div>

    <div class="col-12 col-md-3">
      <div class="glass-card p-3 h-100">
        <div class="kpi-label">Custo Total</div>
        <div class="kpi-value">€ <?php echo number_format($custoTotal, 2, ',', '.'); ?></div>
        <div class="kpi-sub">Sem anulados</div>
      </div>
    </div>

    <div class="col-12 col-md-3">
      <div class="glass-card p-3 h-100">
        <div class="kpi-label">Preço Médio/Litro</div>
        <div class="kpi-value">€ <?php echo number_format($precoMedio, 3, ',', '.'); ?></div>
        <div class="kpi-sub">Registos válidos</div>
      </div>
    </div>

    <div class="col-12 col-md-3">
      <div class="glass-card p-3 h-100">
        <div class="kpi-label">Registos</div>
        <div class="kpi-value"><?php echo number_format($totalRegistos, 0, ',', '.'); ?></div>
        <div class="kpi-sub"><?php echo (int)$totalAnalise; ?> em análise</div>
      </div>
    </div>
  </div>

  <!-- Alertas rápidos -->
  <div class="row g-3">
    <div class="col-12 col-md-6">
      <div class="glass-card p-3 h-100">
        <div class="d-flex justify-content-between align-items-center gap-3">
          <div>
            <div class="kpi-label">Sem posto informado</div>
            <div class="kpi-value"><?php echo (int)$totalSemPosto; ?></div>
            <div class="kpi-sub">Não é obrigatório, mas pode ser revisto.</div>
          </div>
          <a class="btn btn-outline-primary btn-sm" href="index.php?alerta=sem_posto">Ver</a>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-6">
      <div class="glass-card p-3 h-100">
        <div class="d-flex justify-content-between align-items-center gap-3">
          <div>
            <div class="kpi-label">Sem km informado</div>
            <div class="kpi-value"><?php echo (int)$totalSemKm; ?></div>
            <div class="kpi-sub">Útil para analisar consumo médio.</div>
          </div>
          <a class="btn btn-outline-primary btn-sm" href="index.php?alerta=sem_km">Ver</a>
        </div>
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
            placeholder="Pesquisar por matrícula, veículo, posto, motorista ou serviço..."
            value="<?php echo h($q); ?>"
          >
        </div>
      </div>

      <div class="col-12 col-md-4 col-lg-3">
        <label class="form-label form-label-soft">Estado</label>
        <select name="estado" class="form-select">
          <option value="">Todos</option>
          <option value="registado" <?php echo $estadoFiltro === 'registado' ? 'selected' : ''; ?>>Registado</option>
          <option value="em_analise" <?php echo $estadoFiltro === 'em_analise' ? 'selected' : ''; ?>>Em análise</option>
          <option value="corrigido" <?php echo $estadoFiltro === 'corrigido' ? 'selected' : ''; ?>>Corrigido</option>
          <option value="anulado" <?php echo $estadoFiltro === 'anulado' ? 'selected' : ''; ?>>Anulado</option>
        </select>
      </div>

      <div class="col-12 col-md-4 col-lg-2">
        <label class="form-label form-label-soft">Alerta</label>
        <select name="alerta" class="form-select">
          <option value="">Todos</option>
          <option value="sem_posto" <?php echo $alertaFiltro === 'sem_posto' ? 'selected' : ''; ?>>Sem posto</option>
          <option value="sem_km" <?php echo $alertaFiltro === 'sem_km' ? 'selected' : ''; ?>>Sem km</option>
          <option value="sem_servico" <?php echo $alertaFiltro === 'sem_servico' ? 'selected' : ''; ?>>Sem serviço</option>
        </select>
      </div>

      <div class="col-12 col-md-4 col-lg-2 d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit">Filtrar</button>
        <a class="btn btn-outline-secondary" href="index.php">Limpar</a>
      </div>
    </form>
  </div>

  <!-- Lista -->
  <div class="vstack gap-3">
    <?php if (count($rows) > 0): ?>
      <?php foreach ($rows as $a): ?>
        <?php
          $id = (int)$a['id'];
          $litros = (float)$a['litros'];
          $comb = (string)$a['combustivel'];
          $posto = trim((string)($a['posto'] ?? ''));
          $total = (float)$a['total'];
          $preco = (float)$a['preco_litro'];
          $data = (string)$a['data_abastecimento'];

          $mat = (string)($a['matricula'] ?? '—');
          $nomeV = (string)($a['marca_modelo'] ?? '');
          $motorista = $a['motorista_nome'] ?: ($a['colaborador_nome'] ?: 'Não associado');

          $temAlertas = false;
          $alertas = [];

          if ($posto === '') {
            $temAlertas = true;
            $alertas[] = 'sem posto';
          }

          if ($a['km_atual'] === null || $a['km_atual'] === '') {
            $temAlertas = true;
            $alertas[] = 'sem km';
          }

          if (empty($a['servico_id'])) {
            $temAlertas = true;
            $alertas[] = 'sem serviço';
          }
        ?>

        <div class="glass-card p-4">
          <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">

            <div class="d-flex gap-3">
              <div class="list-ico" style="width:40px;height:40px;">
                <i class="bi bi-fuel-pump-fill"></i>
              </div>

              <div class="min-w-0">
                <div class="fw-semibold">
                  <?php echo number_format($litros, 1, ',', '.'); ?> L — <?php echo h($comb); ?>
                </div>

                <div class="small text-muted">
                  <?php echo h($mat); ?> • <?php echo h($nomeV); ?>
                </div>

                <div class="small text-muted">
                  Motorista: <?php echo h($motorista); ?>
                </div>

                <div class="small text-muted">
                  <i class="bi bi-geo-alt-fill"></i>
                  <?php echo h($posto !== '' ? $posto : 'Posto não informado'); ?>
                </div>

                <div class="small text-muted">
                  <?php if (!empty($a['km_atual'])): ?>
                    Km <?php echo number_format((int)$a['km_atual'], 0, ',', '.'); ?>
                  <?php else: ?>
                    Km não informado
                  <?php endif; ?>

                  <?php if (!empty($a['servico_codigo'])): ?>
                    · Serviço <?php echo h($a['servico_codigo']); ?>
                  <?php endif; ?>
                </div>

                <?php if ($temAlertas): ?>
                  <div class="small mt-2">
                    <?php foreach ($alertas as $al): ?>
                      <span class="badge-pill badge-warning-soft me-1"><?php echo h($al); ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>

            <div class="text-lg-end">
              <div class="fw-semibold">
                € <?php echo number_format($total, 2, ',', '.'); ?>
              </div>

              <div class="small text-muted">
                € <?php echo number_format($preco, 3, ',', '.'); ?>/L
              </div>

              <div class="small text-muted">
                <?php echo fmtData($data); ?>
              </div>

              <div class="mt-2">
                <?php echo badgeEstadoAbastecimento($a); ?>
              </div>

              <div class="d-flex gap-2 justify-content-lg-end mt-3">
                <?php if (!empty($a['comprovativo'])): ?>
                  <a class="btn btn-sm btn-outline-info" href="<?php echo base_url() . '/' . h($a['comprovativo']); ?>" target="_blank">
                    <i class="bi bi-file-earmark-text me-1"></i> Comprovativo
                  </a>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?php echo $id; ?>">
                  Gerir
                </a>
              </div>
            </div>

          </div>
        </div>

      <?php endforeach; ?>
    <?php else: ?>
      <div class="glass-card p-4 text-center text-muted">
        Nenhum abastecimento encontrado.
      </div>
    <?php endif; ?>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>