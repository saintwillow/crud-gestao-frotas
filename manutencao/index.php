<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'manutencao';
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

function pillClassMan($status) {
  $e = mb_strtolower(trim((string)$status));
  if ($e === 'concluída' || $e === 'concluida' || $e === 'concluído' || $e === 'concluido') return 'pill-success';
  if (str_contains($e, 'andamento') || str_contains($e, 'progress')) return 'pill-warning';
  if ($e === 'agendada' || $e === 'pendente' || $e === 'scheduled') return 'pill-info';
  if ($e === 'cancelada' || $e === 'cancelado') return 'pill-danger';
  return 'pill-info';
}

$q = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? '');
$tipo = trim($_GET['tipo'] ?? '');

$kpiTotal = 0;
$kpiAg = 0;
$kpiAnd = 0;
$kpiConc = 0;

$rows = [];

/* KPIs */
$r1 = mysqli_query($ligacao, "SELECT COUNT(*) AS t FROM manutencoes");
if ($r1) $kpiTotal = (int)(mysqli_fetch_assoc($r1)['t'] ?? 0);

$r2 = mysqli_query($ligacao, "SELECT COUNT(*) AS t FROM manutencoes WHERE status IN ('Agendada','Pendente','scheduled')");
if ($r2) $kpiAg = (int)(mysqli_fetch_assoc($r2)['t'] ?? 0);

$r3 = mysqli_query($ligacao, "SELECT COUNT(*) AS t FROM manutencoes WHERE status IN ('Em andamento','in-progress')");
if ($r3) $kpiAnd = (int)(mysqli_fetch_assoc($r3)['t'] ?? 0);

$r4 = mysqli_query($ligacao, "SELECT COUNT(*) AS t FROM manutencoes WHERE status IN ('Concluída','Concluida','Concluído','Concluido')");
if ($r4) $kpiConc = (int)(mysqli_fetch_assoc($r4)['t'] ?? 0);

/* Query principal */
$where = [];
if ($q !== '') {
  $q_safe = mysqli_real_escape_string($ligacao, $q);
  $where[] = "(m.descricao LIKE '%$q_safe%' OR v.matricula LIKE '%$q_safe%' OR v.marca_modelo LIKE '%$q_safe%')";
}
if ($status !== '') {
  $status_safe = mysqli_real_escape_string($ligacao, $status);
  $where[] = "m.status = '$status_safe'";
}
if ($tipo !== '') {
  $tipo_safe = mysqli_real_escape_string($ligacao, $tipo);
  $where[] = "m.tipo = '$tipo_safe'";
}

$sql = "SELECT 
          m.id, m.viatura_id, m.tipo, m.descricao, m.data_inicio, m.data_fim, m.custo, m.oficina, m.status, m.criado_em,
          v.matricula, v.marca_modelo
        FROM manutencoes m
        LEFT JOIN viaturas v ON v.id = m.viatura_id";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY m.data_inicio DESC, m.id DESC";

$res = mysqli_query($ligacao, $sql);
if ($res) while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
?>

<div class="page-max-6xl space-y-6">

  <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
    <div>
      <h1 class="page-title">Manutenção</h1>
      <div class="page-subtitle">Controle de manutenções da frota</div>
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap">
      <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'criada'): ?>
          <div class="alert alert-success py-2 px-3 mb-0">Manutenção criada </div>
        <?php elseif ($_GET['msg'] === 'editada'): ?>
          <div class="alert alert-success py-2 px-3 mb-0">Manutenção atualizada </div>
        <?php elseif ($_GET['msg'] === 'apagada'): ?>
          <div class="alert alert-success py-2 px-3 mb-0">Manutenção apagada </div>
        <?php endif; ?>
      <?php endif; ?>

      <a href="create.php" class="btn btn-primary">Nova Manutenção</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="glass-card stat-gradient p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Total</div>
            <div class="kpi-value"><?php echo (int)$kpiTotal; ?></div>
            <div class="kpi-sub">registros</div>
          </div>
          <div class="kpi-ico"><i class="bi bi-card-text"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
      <div class="glass-card stat-gradient p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Agendadas/Pendentes</div>
            <div class="kpi-value"><?php echo (int)$kpiAg; ?></div>
            <div class="kpi-sub">para fazer</div>
          </div>
          <div class="kpi-ico"><i class="bi bi-calendar-event"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
      <div class="glass-card stat-gradient p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Em andamento</div>
            <div class="kpi-value"><?php echo (int)$kpiAnd; ?></div>
            <div class="kpi-sub">em execução</div>
          </div>
          <div class="kpi-ico"><i class="bi bi-gear-fill"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
      <div class="glass-card stat-gradient p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Concluídas</div>
            <div class="kpi-value"><?php echo (int)$kpiConc; ?></div>
            <div class="kpi-sub">finalizadas</div>
          </div>
          <div class="kpi-ico"><i class="bi bi-check-circle-fill"></i></div>
        </div>
      </div>
    </div>
  </div>

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
            placeholder="Buscar por descrição, placa ou veículo..."
            value="<?php echo h($q); ?>"
          >
        </div>
      </div>

      <form id="filtersForm" method="get" class="d-flex gap-2 flex-wrap justify-content-lg-end">
        <input type="hidden" name="q" value="<?php echo h($q); ?>">

        <select class="form-select" name="status" style="min-width: 180px;">
          <option value="">Status</option>
          <?php foreach (['Agendada','Pendente','Em andamento','Concluída','Cancelada'] as $es): ?>
            <option value="<?php echo h($es); ?>" <?php echo ($status===$es)?'selected':''; ?>>
              <?php echo h($es); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <select class="form-select" name="tipo" style="min-width: 170px;">
          <option value="">Tipo</option>
          <?php foreach (['Preventiva','Corretiva','Revisão','Outro'] as $t): ?>
            <option value="<?php echo h($t); ?>" <?php echo ($tipo===$t)?'selected':''; ?>>
              <?php echo h($t); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <button class="btn btn-outline-primary" type="submit">Filtrar</button>
        <a class="btn btn-outline-secondary" href="index.php">Limpar</a>
      </form>
    </div>

    <div class="chips mt-3">
      <a class="chip <?php echo ($status==='' ? 'active' : ''); ?>" href="index.php<?php echo qs(['status'=>null]); ?>">Todas</a>
      <a class="chip <?php echo ($status==='Agendada' ? 'active' : ''); ?>" href="index.php<?php echo qs(['status'=>'Agendada']); ?>">Agendadas</a>
      <a class="chip <?php echo ($status==='Em andamento' ? 'active' : ''); ?>" href="index.php<?php echo qs(['status'=>'Em andamento']); ?>">Em andamento</a>
      <a class="chip <?php echo ($status==='Concluída' ? 'active' : ''); ?>" href="index.php<?php echo qs(['status'=>'Concluída']); ?>">Concluídas</a>
    </div>
  </div>

  <div class="row g-3">
    <?php if (count($rows) > 0): ?>
      <?php foreach ($rows as $m): ?>
        <?php
          $mid = (int)$m['id'];
          $desc = (string)($m['descricao'] ?? 'Manutenção');
          $tipoM = (string)($m['tipo'] ?? '—');
          $ini = (string)($m['data_inicio'] ?? '—');
          $fim = (string)($m['data_fim'] ?? '');
          $custo = (float)($m['custo'] ?? 0);
          $oficina = (string)($m['oficina'] ?? '');
          $st = (string)($m['status'] ?? '—');
          $pill = pillClassMan($st);

          $mat = (string)($m['matricula'] ?? '-');
          $nomeV = (string)($m['marca_modelo'] ?? '');
        ?>

        <div class="col-12">
          <div class="glass-card p-4">
            <div class="d-flex align-items-start justify-content-between gap-3">
              <div class="d-flex gap-3 align-items-start">
                <div class="list-ico warn" style="width:40px;height:40px;"><i class="bi bi-gear-fill"></i></div>
                <div class="min-w-0">
                  <div class="fw-semibold"><?php echo h($desc); ?></div>
                  <div class="small text-muted">
                    <?php echo h($nomeV ?: 'Veículo'); ?> • <?php echo h($mat); ?>
                    • <?php echo h($tipoM); ?>
                    <?php if ($oficina !== ''): ?> • <?php echo h($oficina); ?><?php endif; ?>
                  </div>
                </div>
              </div>

              <div class="d-flex align-items-center gap-3 flex-wrap justify-content-end">
                <div class="text-end">
                  <div class="fw-semibold">€ <?php echo number_format($custo, 0, ',', '.'); ?></div>
                  <div class="small text-muted">
                    <?php echo h($ini); ?>
                    <?php if ($fim !== ''): ?> → <?php echo h($fim); ?><?php endif; ?>
                  </div>
                </div>
                <span class="pill <?php echo h($pill); ?>"><?php echo h($st); ?></span>
              </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-3">
              <a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?php echo $mid; ?>">Editar</a>
              <a class="btn btn-sm btn-outline-danger" href="delete.php?id=<?php echo $mid; ?>">Apagar</a>
            </div>
          </div>
        </div>

      <?php endforeach; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="glass-card p-4 text-center text-muted">Nenhuma manutenção encontrada.</div>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>