<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'viaturas';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function fetchOneInt($ligacao, $sql, $field = 'total') {
  $r = mysqli_query($ligacao, $sql);
  if (!$r) return 0;
  $row = mysqli_fetch_assoc($r);
  return (int)($row[$field] ?? 0);
}

function badge_estado_class($estado) {
  switch ((string)$estado) {
    case 'Disponível': return 'badge-disponivel';
    case 'Atribuída': return 'badge-atribuida';
    case 'Em Manutenção': return 'badge-manutencao';
    case 'Inativo': return 'badge-inativo';
    default: return 'badge-default';
  }
}

function badge_tipo_infra_class($tipo) {
  return ((string)$tipo === 'ETA') ? 'badge-eta' : 'badge-etar';
}

function qs(array $changes = []) {
  $params = $_GET;

  foreach ($changes as $k => $v) {
    if ($v === null || $v === '') {
      unset($params[$k]);
    } else {
      $params[$k] = $v;
    }
  }

  $query = http_build_query($params);
  return $query ? ('?' . $query) : '';
}

// filtros
$q = trim($_GET['q'] ?? '');
$estado = trim($_GET['estado'] ?? '');
$tipo = trim($_GET['tipo'] ?? '');
$combustivel = trim($_GET['combustivel'] ?? '');
$sub_regiao = trim($_GET['sub_regiao'] ?? '');

// KPIs
$filtro_zona_kpi = sql_filtro_zona_viatura("v");
$where_kpi = "1=1" . $filtro_zona_kpi;
$total = fetchOneInt($ligacao, "SELECT COUNT(*) AS total FROM viaturas v WHERE $where_kpi");
$disponiveis = fetchOneInt($ligacao, "SELECT COUNT(*) AS total FROM viaturas v WHERE v.estado='Disponível'" . $filtro_zona_kpi);
$atribuida = fetchOneInt($ligacao, "SELECT COUNT(*) AS total FROM viaturas v WHERE v.estado='Atribuída'" . $filtro_zona_kpi);
$manutencao = fetchOneInt($ligacao, "SELECT COUNT(*) AS total FROM viaturas v WHERE v.estado='Em Manutenção'" . $filtro_zona_kpi);

// sub-regiões para filtro
$subRegioes = [];
$resSub = mysqli_query($ligacao, "SELECT DISTINCT sub_regiao FROM infraestruturas WHERE ativo=1 AND sub_regiao IS NOT NULL AND sub_regiao <> '' ORDER BY sub_regiao ASC");
if ($resSub) {
  while ($r = mysqli_fetch_assoc($resSub)) {
    $subRegioes[] = $r['sub_regiao'];
  }
}

// alertas
$msg = trim($_GET['msg'] ?? '');
$alerta = '';
$alertaTipo = 'success';

if ($msg === 'criada') $alerta = 'Veículo criado com sucesso.';
elseif ($msg === 'editada') $alerta = 'Veículo atualizado com sucesso.';
elseif ($msg === 'apagada') $alerta = 'Veículo apagado com sucesso.';

// WHERE dinâmico
$where = ["1=1"];
$filtro_zona = sql_filtro_zona_viatura("v");
if ($filtro_zona !== "") {
  $where[] = substr($filtro_zona, 5); // remove leading " AND "
}

if ($q !== '') {
  $qSafe = mysqli_real_escape_string($ligacao, $q);
  $where[] = "(
    v.matricula LIKE '%$qSafe%' OR
    v.marca_modelo LIKE '%$qSafe%' OR
    v.tipo LIKE '%$qSafe%' OR
    v.combustivel LIKE '%$qSafe%' OR
    v.observacoes LIKE '%$qSafe%' OR
    i.nome LIKE '%$qSafe%' OR
    i.sub_regiao LIKE '%$qSafe%' OR
    i.localidade LIKE '%$qSafe%' OR
    i.concelho LIKE '%$qSafe%'
  )";
}

if ($estado !== '') {
  $estadoSafe = mysqli_real_escape_string($ligacao, $estado);
  $where[] = "v.estado = '$estadoSafe'";
}

if ($tipo !== '') {
  $tipoSafe = mysqli_real_escape_string($ligacao, $tipo);
  $where[] = "v.tipo = '$tipoSafe'";
}

if ($combustivel !== '') {
  $combSafe = mysqli_real_escape_string($ligacao, $combustivel);
  $where[] = "v.combustivel = '$combSafe'";
}

if ($sub_regiao !== '') {
  $subSafe = mysqli_real_escape_string($ligacao, $sub_regiao);
  $where[] = "i.sub_regiao = '$subSafe'";
}

$whereSql = implode(' AND ', $where);

$sql = "
  SELECT
    v.*,
    i.nome AS infraestrutura_nome,
    i.tipo AS infraestrutura_tipo,
    i.sub_regiao AS infraestrutura_sub_regiao,
    i.localidade AS infraestrutura_localidade,
    i.concelho AS infraestrutura_concelho,
    zo.nome AS zona_nome
  FROM viaturas v
  LEFT JOIN infraestruturas i ON i.id = v.infraestrutura_id
  LEFT JOIN zonas_operacionais zo ON zo.id = v.zona_operacional_id
  WHERE $whereSql
  ORDER BY v.id DESC
";

$res = mysqli_query($ligacao, $sql);
?>

<style>
  .searchbox {
    position: relative;
  }

  .search-ico {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
  }

  .searchbox .form-control {
    padding-left: 42px;
  }

  .chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  .chip {
    display: inline-flex;
    align-items: center;
    padding: 8px 12px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid rgba(15,23,42,.08);
    color: #0f172a;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
  }

  .chip:hover {
    background: rgba(15,23,42,.03);
    color: #0f172a;
  }

  .chip.active {
    background: var(--primary, #0b4a6f);
    color: #fff;
    border-color: var(--primary, #0b4a6f);
  }

  .soft-stat {
    background: rgba(255,255,255,.96);
    border: 1px solid rgba(226,232,240,.9);
    box-shadow: 0 10px 30px rgba(15,23,42,.06);
    border-radius: 16px;
    padding: 18px;
    height: 100%;
  }

  .soft-stat-label {
    font-size: 13px;
    color: #64748b;
    font-weight: 600;
  }

  .soft-stat-value {
    font-size: 34px;
    line-height: 1;
    font-weight: 800;
    color: #0f172a;
    margin-top: 8px;
  }

  .soft-stat-sub {
    font-size: 13px;
    color: #64748b;
    margin-top: 8px;
  }

  .vehicle-card {
    background: rgba(255,255,255,.97);
    border: 1px solid rgba(226,232,240,.9);
    box-shadow: 0 10px 28px rgba(15,23,42,.06);
    border-radius: 18px;
    padding: 18px;
    height: 100%;
  }

  .vehicle-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
  }

  .vehicle-plate {
    font-size: 14px;
    font-weight: 800;
    letter-spacing: .02em;
    color: #2563eb;
    margin-bottom: 4px;
  }

  .vehicle-name {
    font-size: 20px;
    font-weight: 800;
    color: #0f172a;
    line-height: 1.2;
  }

  .vehicle-meta {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 14px;
  }

  .vehicle-meta-item {
    background: rgba(248,250,252,.92);
    border: 1px solid rgba(15,23,42,.06);
    border-radius: 14px;
    padding: 12px;
  }

  .vehicle-meta-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #64748b;
    margin-bottom: 6px;
  }

  .vehicle-meta-value {
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
  }

  .infra-box {
    margin-top: 14px;
    padding: 14px;
    border-radius: 14px;
    background: rgba(248,250,252,.92);
    border: 1px solid rgba(15,23,42,.06);
  }

  .infra-name {
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 6px;
  }

  .infra-sub {
    font-size: 13px;
    color: #64748b;
  }

  .vehicle-actions {
    margin-top: 16px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .badge-estado {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
  }

  .badge-disponivel {
    background: rgba(34,197,94,.12);
    color: #16a34a;
  }

  .badge-atribuida {
    background: rgba(59,130,246,.12);
    color: #2563eb;
  }

  .badge-manutencao {
    background: rgba(245,158,11,.14);
    color: #d97706;
  }

  .badge-inativo {
    background: rgba(148,163,184,.18);
    color: #475569;
  }

  .badge-default {
    background: rgba(15,23,42,.08);
    color: #334155;
  }

  .badge-tipo {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 5px 10px;
    font-size: 11px;
    font-weight: 700;
  }

  .badge-eta {
    background: rgba(34,197,94,.12);
    color: #16a34a;
  }

  .badge-etar {
    background: rgba(59,130,246,.12);
    color: #2563eb;
  }

  .empty-soft {
    color: #94a3b8;
    font-style: italic;
  }
</style>

<div class="page-max-6xl space-y-6">

  <div class="d-flex flex-column flex-lg-row align-items-start align-items-lg-center justify-content-between gap-3">
    <div>
      <h1 class="page-title">Veículos</h1>
      <div class="page-subtitle">Gestão de viaturas da frota com base operacional e estado atual</div>
    </div>

    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="<?php echo $BASE_URL; ?>/mapa-frota/index.php">Mapa da Frota</a>
      <?php if (in_array(perfil_atual(), ['admin', 'gestor'], true)): ?>
        <a class="btn btn-primary" href="create.php">Novo Veículo</a>
      <?php endif; ?>
    </div>
  </div>



  <div class="row g-3">
    <div class="col-12 col-sm-6 col-lg-3">
      <div class="soft-stat">
        <div class="soft-stat-label">Total de veículos</div>
        <div class="soft-stat-value"><?php echo (int)$total; ?></div>
        <div class="soft-stat-sub">Registos na frota</div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
      <div class="soft-stat">
        <div class="soft-stat-label">Disponíveis</div>
        <div class="soft-stat-value"><?php echo (int)$disponiveis; ?></div>
        <div class="soft-stat-sub">Prontos para operação</div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
      <div class="soft-stat">
        <div class="soft-stat-label">Atribuídas</div>
        <div class="soft-stat-value"><?php echo (int)$atribuida; ?></div>
        <div class="soft-stat-sub">Em uso operacional</div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
      <div class="soft-stat">
        <div class="soft-stat-label">Em manutenção</div>
        <div class="soft-stat-value"><?php echo (int)$manutencao; ?></div>
        <div class="soft-stat-sub">Necessitam acompanhamento</div>
      </div>
    </div>
  </div>

  <div class="glass-card p-3">
    <form method="get" id="filtersForm">
      <div class="d-flex flex-column flex-lg-row gap-3 align-items-stretch align-items-lg-center">
        <div class="flex-grow-1">
          <div class="searchbox">
            <span class="search-ico"><i class="bi bi-search"></i></span>
            <input
              type="text"
              class="form-control form-control-lg"
              name="q"
              placeholder="Buscar por matrícula, modelo, combustível, infraestrutura ou sub-região..."
              value="<?php echo h($q); ?>"
            >
          </div>
        </div>

        <div class="d-flex gap-2 flex-wrap justify-content-lg-end">
          <select class="form-select" name="estado" style="min-width: 160px;">
            <option value="">Estado</option>
            <?php foreach (['Disponível','Atribuída','Em Manutenção','Inativo'] as $es): ?>
              <option value="<?php echo h($es); ?>" <?php echo ($estado === $es) ? 'selected' : ''; ?>>
                <?php echo h($es); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <select class="form-select" name="tipo" style="min-width: 160px;">
            <option value="">Tipo</option>
            <?php foreach (['Ligeiro','Pick-up','Carrinha','Camião','Elétrico','Outro'] as $t): ?>
              <option value="<?php echo h($t); ?>" <?php echo ($tipo === $t) ? 'selected' : ''; ?>>
                <?php echo h($t); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <select class="form-select" name="combustivel" style="min-width: 160px;">
            <option value="">Combustível</option>
            <?php foreach (['Diesel','Gasolina','Elétrico','Híbrido','Outro'] as $c): ?>
              <option value="<?php echo h($c); ?>" <?php echo ($combustivel === $c) ? 'selected' : ''; ?>>
                <?php echo h($c); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <select class="form-select" name="sub_regiao" style="min-width: 180px;">
            <option value="">Sub-região</option>
            <?php foreach ($subRegioes as $sr): ?>
              <option value="<?php echo h($sr); ?>" <?php echo ($sub_regiao === $sr) ? 'selected' : ''; ?>>
                <?php echo h($sr); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <button class="btn btn-outline-primary" type="submit">Filtrar</button>
          <a class="btn btn-outline-secondary" href="index.php">Limpar</a>
        </div>
      </div>
    </form>

    <div class="chips mt-3">
      <a class="chip <?php echo ($estado === '' ? 'active' : ''); ?>" href="index.php<?php echo qs(['estado' => null]); ?>">Todos</a>
      <a class="chip <?php echo ($estado === 'Disponível' ? 'active' : ''); ?>" href="index.php<?php echo qs(['estado' => 'Disponível']); ?>">Disponíveis</a>
      <a class="chip <?php echo ($estado === 'Atribuída' ? 'active' : ''); ?>" href="index.php<?php echo qs(['estado' => 'Atribuída']); ?>">Em rota</a>
      <a class="chip <?php echo ($estado === 'Em Manutenção' ? 'active' : ''); ?>" href="index.php<?php echo qs(['estado' => 'Em Manutenção']); ?>">Manutenção</a>
      <a class="chip <?php echo ($estado === 'Inativo' ? 'active' : ''); ?>" href="index.php<?php echo qs(['estado' => 'Inativo']); ?>">Inativos</a>
    </div>
  </div>

  <div class="row g-3">
    <?php if ($res && mysqli_num_rows($res) > 0): ?>
      <?php while ($v = mysqli_fetch_assoc($res)): ?>
        <?php
          $infraNome = trim((string)($v['infraestrutura_nome'] ?? ''));
          $infraTipo = trim((string)($v['infraestrutura_tipo'] ?? ''));
          $infraSub = trim((string)($v['infraestrutura_sub_regiao'] ?? ''));
          $infraLoc = trim((string)($v['infraestrutura_localidade'] ?? ''));
          $infraConcelho = trim((string)($v['infraestrutura_concelho'] ?? ''));

          $baseOperacional = trim(
            ($infraLoc !== '' ? $infraLoc : '') .
            ($infraConcelho !== '' ? ', ' . $infraConcelho : '')
          );
        ?>
        <div class="col-12 col-xl-6">
          <div class="vehicle-card">
            <div class="vehicle-card-top">
              <div>
                <div class="vehicle-plate d-flex align-items-center gap-2">
                  <span><?php echo h($v['matricula'] ?? ''); ?></span>
                  <span class="badge bg-light text-primary border" style="font-size: 11px;"><?php echo h($v['zona_nome'] ?: 'Geral / Sem Zona'); ?></span>
                </div>
                <div class="vehicle-name"><?php echo h($v['marca_modelo'] ?? ''); ?></div>
              </div>

              <span class="badge-estado <?php echo h(badge_estado_class($v['estado'] ?? '')); ?>">
                <?php echo h($v['estado'] ?? ''); ?>
              </span>
            </div>

            <div class="vehicle-meta">
              <div class="vehicle-meta-item">
                <div class="vehicle-meta-label">Tipo</div>
                <div class="vehicle-meta-value">
                  <?php echo ($v['tipo'] ?? '') !== '' ? h($v['tipo']) : '<span class="empty-soft">Não definido</span>'; ?>
                </div>
              </div>

              <div class="vehicle-meta-item">
                <div class="vehicle-meta-label">Combustível</div>
                <div class="vehicle-meta-value">
                  <?php echo ($v['combustivel'] ?? '') !== '' ? h($v['combustivel']) : '<span class="empty-soft">Não definido</span>'; ?>
                </div>
              </div>

              <div class="vehicle-meta-item">
                <div class="vehicle-meta-label">Quilometragem</div>
                <div class="vehicle-meta-value">
                  <?php echo number_format((int)($v['quilometragem'] ?? 0), 0, ',', '.'); ?> km
                </div>
              </div>

              <div class="vehicle-meta-item">
                <div class="vehicle-meta-label">ID</div>
                <div class="vehicle-meta-value">#<?php echo (int)$v['id']; ?></div>
              </div>
            </div>

            <div class="infra-box">
              <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <div class="infra-name mb-0">
                  <?php echo $infraNome !== '' ? h($infraNome) : '<span class="empty-soft">Sem infraestrutura atribuída</span>'; ?>
                </div>

                <?php if ($infraTipo !== ''): ?>
                  <span class="badge-tipo <?php echo h(badge_tipo_infra_class($infraTipo)); ?>">
                    <?php echo h($infraTipo); ?>
                  </span>
                <?php endif; ?>
              </div>

              <div class="infra-sub">
                <?php if ($infraSub !== ''): ?>
                  <div><strong>Sub-região:</strong> <?php echo h($infraSub); ?></div>
                <?php else: ?>
                  <div><span class="empty-soft">Sem sub-região definida</span></div>
                <?php endif; ?>

                <?php if ($baseOperacional !== ''): ?>
                  <div><strong>Base:</strong> <?php echo h($baseOperacional); ?></div>
                <?php endif; ?>
              </div>
            </div>

            <?php if (trim((string)($v['observacoes'] ?? '')) !== ''): ?>
              <div class="small text-muted mt-3">
                <?php echo h(mb_strimwidth((string)$v['observacoes'], 0, 140, '...')); ?>
              </div>
            <?php endif; ?>

            <div class="vehicle-actions">
              <a class="btn btn-primary btn-sm" href="show.php?id=<?php echo (int)$v['id']; ?>">Ver detalhe</a>
              <?php if (in_array(perfil_atual(), ['admin', 'gestor'], true)): ?>
                <a class="btn btn-outline-secondary btn-sm" href="edit.php?id=<?php echo (int)$v['id']; ?>">Editar</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="glass-card p-4 text-center text-muted">
          Nenhum veículo encontrado com os filtros informados.
        </div>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>