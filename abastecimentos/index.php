<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'abastecimento';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$q = trim($_GET['q'] ?? '');

/* KPIs */
function fetchOneFloat($ligacao, $sql, $field='v') {
  $r = mysqli_query($ligacao, $sql);
  if (!$r) return 0.0;
  $row = mysqli_fetch_assoc($r);
  return (float)($row[$field] ?? 0);
}

$totalLitros = fetchOneFloat($ligacao, "SELECT COALESCE(SUM(litros),0) AS v FROM abastecimentos");
$custoTotal  = fetchOneFloat($ligacao, "SELECT COALESCE(SUM(total),0) AS v FROM abastecimentos");
$precoMedio  = fetchOneFloat($ligacao, "SELECT COALESCE(AVG(preco_litro),0) AS v FROM abastecimentos");

/* Lista */
$where = [];
if ($q !== '') {
  $q_safe = mysqli_real_escape_string($ligacao, $q);
  $where[] = "(v.matricula LIKE '%$q_safe%'
          OR v.marca_modelo LIKE '%$q_safe%'
          OR a.posto LIKE '%$q_safe%'
          OR a.combustivel LIKE '%$q_safe%')";
}

$sql = "SELECT
          a.id, a.viatura_id, a.colaborador_id, a.posto, a.combustivel,
          a.litros, a.preco_litro, a.total, a.data_abastecimento,
          v.matricula, v.marca_modelo
        FROM abastecimentos a
        LEFT JOIN viaturas v ON v.id = a.viatura_id";

if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY a.data_abastecimento DESC, a.id DESC";

$rows = [];
$res = mysqli_query($ligacao, $sql);
if ($res) while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
?>

<div class="page-max-6xl space-y-6">

  <div>
    <h1 class="page-title">Abastecimento</h1>
    <div class="page-subtitle">Controle de abastecimentos da frota</div>
  </div>

  <?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg'] === 'criado'): ?>
      <div class="alert alert-success py-2 px-3 mb-0">Abastecimento criado </div>
    <?php elseif ($_GET['msg'] === 'editado'): ?>
      <div class="alert alert-success py-2 px-3 mb-0">Abastecimento atualizado </div>
    <?php elseif ($_GET['msg'] === 'apagado'): ?>
      <div class="alert alert-success py-2 px-3 mb-0">Abastecimento apagado </div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- KPIs -->
  <div class="row g-3">
    <div class="col-12 col-md-4">
      <div class="glass-card p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Total Abastecido</div>
            <div class="kpi-value"><?php echo number_format($totalLitros, 0, ',', '.'); ?> L</div>
            <div class="kpi-sub">Período atual</div>
          </div>
          <div class="kpi-ico"><i class="bi bi-droplet-fill"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-4">
      <div class="glass-card p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Custo Total</div>
            <div class="kpi-value">€ <?php echo number_format($custoTotal, 2, ',', '.'); ?></div>
            <div class="kpi-sub">Período atual</div>
          </div>
          <div class="kpi-ico"><i class="bi bi-currency-euro"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-md-4">
      <div class="glass-card p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="kpi-label">Preço Médio/Litro</div>
            <div class="kpi-value">€ <?php echo number_format($precoMedio, 2, ',', '.'); ?></div>
            <div class="kpi-sub">Todos os combustíveis</div>
          </div>
          <div class="kpi-ico"><i class="bi bi-graph-up"></i></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Search + botão -->
  <div class="glass-card p-3">
    <div class="d-flex flex-column flex-lg-row gap-3 align-items-stretch align-items-lg-center">
      <div class="flex-grow-1">
        <form method="get">
          <div class="searchbox">
            <span class="search-ico"><i class="bi bi-search"></i></span>
            <input
              type="text"
              class="form-control"
              name="q"
              placeholder="Buscar por placa, veículo ou posto..."
              value="<?php echo h($q); ?>"
            >
          </div>
        </form>
      </div>

      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="index.php">Limpar</a>
        <a class="btn btn-primary" href="create.php">Novo Abastecimento</a>
      </div>
    </div>
  </div>

  <!-- Lista -->
  <div class="vstack gap-3">
    <?php if (count($rows) > 0): ?>
      <?php foreach ($rows as $a): ?>
        <?php
          $id = (int)$a['id'];
          $litros = (float)$a['litros'];
          $comb = (string)$a['combustivel'];
          $posto = (string)$a['posto'];
          $total = (float)$a['total'];
          $preco = (float)$a['preco_litro'];
          $data = (string)$a['data_abastecimento'];

          $mat = (string)($a['matricula'] ?? '-');
          $nomeV = (string)($a['marca_modelo'] ?? '');
        ?>

        <div class="glass-card p-4">
          <div class="d-flex justify-content-between gap-3">
            <div class="d-flex gap-3">
              <div class="list-ico" style="width:40px;height:40px;"><i class="bi bi-fuel-pump-fill"></i></div>

              <div class="min-w-0">
                <div class="fw-semibold"><?php echo h(number_format($litros, 0, ',', '.')); ?>L — <?php echo h($comb); ?></div>
                <div class="small text-muted"><?php echo h($mat); ?> • <?php echo h($nomeV); ?></div>
                <div class="small text-muted"><i class="bi bi-geo-alt-fill"></i> <?php echo h($posto); ?></div>
              </div>
            </div>

            <div class="text-end">
              <div class="fw-semibold">€ <?php echo number_format($total, 2, ',', '.'); ?></div>
              <div class="small text-muted">€ <?php echo number_format($preco, 2, ',', '.'); ?>/L</div>
              <div class="small text-muted"><?php echo h($data); ?></div>

              <div class="d-flex gap-2 justify-content-end mt-2">
                <a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?php echo $id; ?>">Editar</a>
                <a class="btn btn-sm btn-outline-danger" href="delete.php?id=<?php echo $id; ?>">Apagar</a>
              </div>
            </div>
          </div>
        </div>

      <?php endforeach; ?>
    <?php else: ?>
      <div class="glass-card p-4 text-center text-muted">Nenhum abastecimento encontrado.</div>
    <?php endif; ?>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>