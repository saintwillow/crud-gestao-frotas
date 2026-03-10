<?php
require_once __DIR__ . "/inc/auth.php";
exigir_login();

$active = 'dashboard';
require_once __DIR__ . "/inc/database.php";
require_once __DIR__ . "/inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function fetchOneInt($ligacao, $sql, $field = 'total') {
  $r = mysqli_query($ligacao, $sql);
  if (!$r) return 0;
  $row = mysqli_fetch_assoc($r);
  return (int)($row[$field] ?? 0);
}

$totalVeiculos = fetchOneInt($ligacao, "SELECT COUNT(*) AS total FROM viaturas");
$emManutencao  = fetchOneInt($ligacao, "SELECT COUNT(*) AS total FROM viaturas WHERE estado='Em Manutenção'");
$ativos        = fetchOneInt($ligacao, "SELECT COUNT(*) AS total FROM viaturas WHERE estado IN ('Disponível','Atribuída')");
$inativos      = fetchOneInt($ligacao, "SELECT COUNT(*) AS total FROM viaturas WHERE estado='Inativo'");

$combustivelMedio = 58;
$custoMensal = 22870;

$fuelData = [
  ["month" => "Set", "value" => 15200],
  ["month" => "Out", "value" => 17800],
  ["month" => "Nov", "value" => 16400],
  ["month" => "Dez", "value" => 19100],
  ["month" => "Jan", "value" => 17500],
  ["month" => "Fev", "value" => 18750],
];

$statusData = [
  ["name" => "Ativos",     "value" => $ativos,       "color" => "hsl(152, 60%, 40%)"],
  ["name" => "Manutenção", "value" => $emManutencao, "color" => "hsl(38, 92%, 50%)"],
  ["name" => "Inativos",   "value" => $inativos,     "color" => "hsl(0, 72%, 51%)"],
];

$recentSql = "
  SELECT 
    v.id, v.matricula, v.marca_modelo, v.estado,
    m.nome AS motorista_nome
  FROM viaturas v
  LEFT JOIN motoristas m 
    ON m.viatura_id = v.id AND m.status='Ativo'
  ORDER BY v.id DESC
  LIMIT 5
";
$recentRes = mysqli_query($ligacao, $recentSql);

$upcoming = [];
$checkMan = mysqli_query($ligacao, "SHOW TABLES LIKE 'manutencoes'");
if ($checkMan && mysqli_num_rows($checkMan) > 0) {
  $manSql = "
    SELECT m.id, m.descricao, m.data_inicio, m.data_fim, m.custo, m.status, v.matricula
    FROM manutencoes m
    LEFT JOIN viaturas v ON v.id = m.viatura_id
    WHERE m.status IN ('Agendada','Em andamento','Pendente')
    ORDER BY m.data_inicio ASC
    LIMIT 5
  ";
  $manRes = mysqli_query($ligacao, $manSql);
  if ($manRes) {
    while ($row = mysqli_fetch_assoc($manRes)) $upcoming[] = $row;
  }
}

function badgeEstadoDashboard($estado) {
  $estado = trim((string)$estado);
  if ($estado === 'Disponível') return '<span class="badge-pill badge-success-soft">Ativo</span>';
  if ($estado === 'Atribuída') return '<span class="badge-pill badge-info-soft">Em rota</span>';
  if ($estado === 'Em Manutenção') return '<span class="badge-pill badge-warning-soft">Manutenção</span>';
  if ($estado === 'Inativo') return '<span class="badge-pill badge-danger-soft">Inativo</span>';
  return '<span class="badge-pill badge-info-soft">'.h($estado).'</span>';
}
?>

<div class="mb-4">
  <h1 class="page-title">Painel de Controle</h1>
  <div class="page-subtitle">Visão geral da frota — AquaFleet</div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-sm-6 col-lg-3">
    <div class="glass-card stat-gradient p-3 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="kpi-label">Total de Veículos</div>
          <div class="kpi-value"><?php echo (int)$totalVeiculos; ?></div>
          <div class="kpi-sub"><?php echo (int)$ativos; ?> operacionais</div>
        </div>
        <div class="kpi-ico"><i class="bi bi-car-front-fill fs-5"></i></div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-lg-3">
    <div class="glass-card stat-gradient p-3 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="kpi-label">Em Manutenção</div>
          <div class="kpi-value"><?php echo (int)$emManutencao; ?></div>
          <div class="kpi-sub">Manutenções pendentes</div>
          <div class="small text-success mt-1">-1 vs. mês anterior</div>
        </div>
        <div class="kpi-ico"><i class="bi bi-gear-fill fs-5"></i></div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-lg-3">
    <div class="glass-card stat-gradient p-3 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="kpi-label">Combustível Médio</div>
          <div class="kpi-value"><?php echo (int)$combustivelMedio; ?>%</div>
          <div class="kpi-sub">Nível médio da frota</div>
        </div>
        <div class="kpi-ico"><i class="bi bi-fuel-pump-fill fs-5"></i></div>
      </div>
    </div>
  </div>

  <div class="col-12 col-sm-6 col-lg-3">
    <div class="glass-card stat-gradient p-3 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <div class="kpi-label">Custo Mensal</div>
          <div class="kpi-value">€ <?php echo number_format((int)$custoMensal, 0, ',', '.'); ?></div>
          <div class="kpi-sub">Manutenção + Combustível</div>
        </div>
        <div class="kpi-ico"><i class="bi bi-currency-euro fs-5"></i></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12 col-lg-8">
    <div class="glass-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="section-title mb-0">Custo de Combustível (€)</h3>
      </div>
      <canvas id="fuelBarChart" height="110"></canvas>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="glass-card p-4 h-100">
      <h3 class="section-title mb-3">Status da Frota</h3>
      <div class="d-flex justify-content-center">
        <canvas id="statusDonut" width="220" height="220"></canvas>
      </div>

      <div class="mt-3">
        <?php foreach ($statusData as $s): ?>
          <div class="d-flex align-items-center justify-content-between small py-1">
            <div class="d-flex align-items-center gap-2">
              <span class="legend-dot" style="background: <?php echo h($s['color']); ?>;"></span>
              <span class="text-muted"><?php echo h($s['name']); ?></span>
            </div>
            <strong><?php echo (int)$s['value']; ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <div class="glass-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="section-title mb-0">Veículos Recentes</h3>
        <a class="link-primary small" href="viaturas/index.php">Ver todos</a>
      </div>

      <div class="vstack gap-2">
        <?php if ($recentRes && mysqli_num_rows($recentRes) > 0): ?>
          <?php while($v = mysqli_fetch_assoc($recentRes)): ?>
            <a class="list-row" href="viaturas/show.php?id=<?php echo (int)$v['id']; ?>">
              <div class="list-ico"><i class="bi bi-car-front-fill"></i></div>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-truncate"><?php echo h($v['marca_modelo']); ?></div>
                <div class="small text-muted">
                  <?php echo h($v['matricula']); ?>
                  <?php if (!empty($v['motorista_nome'])): ?>
                    • <?php echo h($v['motorista_nome']); ?>
                  <?php endif; ?>
                </div>
              </div>
              <?php echo badgeEstadoDashboard($v['estado']); ?>
            </a>
          <?php endwhile; ?>
        <?php else: ?>
          <div class="text-muted small">Sem veículos para mostrar.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="glass-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="section-title mb-0">Manutenções Pendentes</h3>
        <a class="link-primary small" href="manutencao/index.php">Ver todas</a>
      </div>

      <div class="vstack gap-2">
        <?php if (count($upcoming) > 0): ?>
          <?php foreach ($upcoming as $m): ?>
            <div class="list-row no-link">
              <div class="list-ico warn"><i class="bi bi-gear-fill"></i></div>
              <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold text-truncate"><?php echo h($m['descricao'] ?? 'Manutenção'); ?></div>
                <div class="small text-muted">
                  <?php echo h($m['matricula'] ?? '-'); ?> • <?php echo h($m['data_inicio'] ?? '-'); ?>
                  • € <?php echo number_format((float)($m['custo'] ?? 0), 2, ',', '.'); ?>
                </div>
              </div>

              <?php
                $st = (string)($m['status'] ?? '');
                $pill = 'badge-info-soft';
                $label = 'Agendada';
                if (stripos($st, 'andamento') !== false) {
                  $pill = 'badge-warning-soft';
                  $label = 'Em andamento';
                } elseif ($st === 'Pendente') {
                  $pill = 'badge-warning-soft';
                  $label = 'Pendente';
                }
              ?>
              <span class="badge-pill <?php echo $pill; ?>"><?php echo $label; ?></span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-muted small">Sem manutenções pendentes.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  const fuelData = <?php echo json_encode($fuelData, JSON_UNESCAPED_UNICODE); ?>;
  const statusData = <?php echo json_encode($statusData, JSON_UNESCAPED_UNICODE); ?>;

  new Chart(document.getElementById('fuelBarChart'), {
    type: 'bar',
    data: {
      labels: fuelData.map(x => x.month),
      datasets: [{
        label: 'Custo',
        data: fuelData.map(x => x.value),
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: { label: (ctx) => '€ ' + Number(ctx.raw).toLocaleString('pt-PT') }
        }
      },
      scales: {
        x: { grid: { display: false } },
        y: { ticks: { callback: (v) => '€ ' + Number(v).toLocaleString('pt-PT') } }
      }
    }
  });

  new Chart(document.getElementById('statusDonut'), {
    type: 'doughnut',
    data: {
      labels: statusData.map(x => x.name),
      datasets: [{
        data: statusData.map(x => x.value),
        backgroundColor: statusData.map(x => x.color),
        borderWidth: 2,
        hoverOffset: 4,
        cutout: '65%'
      }]
    },
    options: { plugins: { legend: { display: false } } }
  });
</script>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/inc/footer.php";
?>