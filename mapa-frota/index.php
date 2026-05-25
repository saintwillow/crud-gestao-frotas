<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'mapa_frota';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
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

function badge_estado_class($estado) {
  switch ((string)$estado) {
    case 'Disponível': return 'badge-disponivel';
    case 'Atribuída': return 'badge-atribuida';
    case 'Em Manutenção': return 'badge-manutencao';
    case 'Inativo': return 'badge-inativo';
    default: return 'badge-default';
  }
}

$infraestrutura_id = isset($_GET['infraestrutura_id']) && ctype_digit((string)$_GET['infraestrutura_id'])
  ? (int)$_GET['infraestrutura_id']
  : 0;

if (perfil_atual() === 'gestor' && infraestrutura_id_sessao() !== null) {
  $infraestrutura_id = (int)infraestrutura_id_sessao();
}

$filtro_infra = "";
if (perfil_atual() === 'gestor' && infraestrutura_id_sessao() !== null) {
  $infra_id = (int)infraestrutura_id_sessao();
  $filtro_infra = " AND i.id = {$infra_id} ";
}

$sql = "
  SELECT
    i.id,
    i.nome,
    i.tipo,
    i.concelho,
    i.localidade,
    i.sub_regiao,
    i.latitude,
    i.longitude,
    COUNT(v.id) AS total_viaturas,
    SUM(CASE WHEN v.estado = 'Disponível' THEN 1 ELSE 0 END) AS disponiveis,
    SUM(CASE WHEN v.estado = 'Atribuída' THEN 1 ELSE 0 END) AS atribuidas,
    SUM(CASE WHEN v.estado = 'Em Manutenção' THEN 1 ELSE 0 END) AS manutencao,
    SUM(CASE WHEN v.estado = 'Inativo' THEN 1 ELSE 0 END) AS inativas
  FROM infraestruturas i
  LEFT JOIN viaturas v ON v.infraestrutura_id = i.id
  WHERE i.ativo = 1 {$filtro_infra}
  GROUP BY
    i.id, i.nome, i.tipo, i.concelho, i.localidade,
    i.sub_regiao, i.latitude, i.longitude
  ORDER BY i.sub_regiao ASC, i.tipo ASC, i.nome ASC
";

$res = mysqli_query($ligacao, $sql);

$infraestruturas = [];
$subRegioes = [];

if ($res) {
  while ($row = mysqli_fetch_assoc($res)) {
    $item = [
      'id' => (int)$row['id'],
      'nome' => (string)$row['nome'],
      'tipo' => (string)$row['tipo'],
      'concelho' => (string)($row['concelho'] ?? ''),
      'localidade' => (string)($row['localidade'] ?? ''),
      'sub_regiao' => (string)$row['sub_regiao'],
      'latitude' => $row['latitude'] !== null ? (float)$row['latitude'] : null,
      'longitude' => $row['longitude'] !== null ? (float)$row['longitude'] : null,
      'total_viaturas' => (int)$row['total_viaturas'],
      'disponiveis' => (int)$row['disponiveis'],
      'atribuidas' => (int)$row['atribuidas'],
      'manutencao' => (int)$row['manutencao'],
      'inativas' => (int)$row['inativas'],
    ];

    $infraestruturas[] = $item;

    $sr = $item['sub_regiao'];
    if (!isset($subRegioes[$sr])) {
      $subRegioes[$sr] = [
        'nome' => $sr,
        'infraestruturas' => 0,
        'viaturas' => 0
      ];
    }

    $subRegioes[$sr]['infraestruturas']++;
    $subRegioes[$sr]['viaturas'] += $item['total_viaturas'];
  }
}

$subRegioes = array_values($subRegioes);

$circulos = [
  [
    'nome' => 'Faro / Loulé / São Brás',
    'lat' => 37.0720,
    'lng' => -7.9450,
    'raio' => 24000,
    'cor' => '#1d6fd8'
  ],
  [
    'nome' => 'Lagos / Portimão',
    'lat' => 37.1750,
    'lng' => -8.5950,
    'raio' => 26000,
    'cor' => '#22a45a'
  ],
  [
    'nome' => 'Tavira / Olhão / VRSA',
    'lat' => 37.1250,
    'lng' => -7.7200,
    'raio' => 34000,
    'cor' => '#f59e0b'
  ],
  [
    'nome' => 'Albufeira / Silves',
    'lat' => 37.1100,
    'lng' => -8.2500,
    'raio' => 23000,
    'cor' => '#9333ea'
  ]
];

$filtro_viaturas = sql_filtro_viatura_gestor('v');
$sql_viaturas = "
  SELECT
    v.id,
    v.matricula,
    v.marca_modelo,
    v.tipo,
    v.combustivel,
    v.quilometragem,
    v.estado,
    v.infraestrutura_id,
    i.nome AS infra_nome,
    i.latitude AS infra_lat,
    i.longitude AS infra_lng,
    os.id AS os_id,
    os.estado AS os_estado,
    so.nome_servico AS servico_nome,
    orig.id AS orig_id,
    orig.nome AS orig_nome,
    orig.latitude AS orig_lat,
    orig.longitude AS orig_lng,
    dest.id AS dest_id,
    dest.nome AS dest_nome,
    dest.latitude AS dest_lat,
    dest.longitude AS dest_lng
  FROM viaturas v
  LEFT JOIN infraestruturas i ON v.infraestrutura_id = i.id
  LEFT JOIN ordens_servico os ON os.viatura_id = v.id AND os.estado IN ('em_deslocacao', 'em_execucao')
  LEFT JOIN servicos_operacionais so ON os.servico_id = so.id
  LEFT JOIN infraestruturas orig ON so.origem_id = orig.id
  LEFT JOIN infraestruturas dest ON so.destino_id = dest.id
  WHERE v.ativo = 1 {$filtro_viaturas}
";

$resAllV = mysqli_query($ligacao, $sql_viaturas);
$viaturasMapa = [];
if ($resAllV) {
  while ($row = mysqli_fetch_assoc($resAllV)) {
    $viaturasMapa[] = [
      'id' => (int)$row['id'],
      'matricula' => (string)$row['matricula'],
      'marca_modelo' => (string)$row['marca_modelo'],
      'tipo' => (string)$row['tipo'],
      'combustivel' => (string)$row['combustivel'],
      'quilometragem' => (int)$row['quilometragem'],
      'estado' => (string)$row['estado'],
      'infraestrutura_id' => $row['infraestrutura_id'] !== null ? (int)$row['infraestrutura_id'] : null,
      'infra_nome' => (string)$row['infra_nome'],
      'infra_lat' => $row['infra_lat'] !== null ? (float)$row['infra_lat'] : null,
      'infra_lng' => $row['infra_lng'] !== null ? (float)$row['infra_lng'] : null,
      'os_id' => $row['os_id'] !== null ? (int)$row['os_id'] : null,
      'os_estado' => (string)$row['os_estado'],
      'servico_nome' => (string)$row['servico_nome'],
      'orig_id' => $row['orig_id'] !== null ? (int)$row['orig_id'] : null,
      'orig_nome' => (string)$row['orig_nome'],
      'orig_lat' => $row['orig_lat'] !== null ? (float)$row['orig_lat'] : null,
      'orig_lng' => $row['orig_lng'] !== null ? (float)$row['orig_lng'] : null,
      'dest_id' => $row['dest_id'] !== null ? (int)$row['dest_id'] : null,
      'dest_nome' => (string)$row['dest_nome'],
      'dest_lat' => $row['dest_lat'] !== null ? (float)$row['dest_lat'] : null,
      'dest_lng' => $row['dest_lng'] !== null ? (float)$row['dest_lng'] : null,
    ];
  }
}

$infraSelecionada = null;
$viaturasInfra = [];

if ($infraestrutura_id > 0) {
  $resSel = mysqli_query($ligacao, "
    SELECT *
    FROM infraestruturas
    WHERE id = $infraestrutura_id AND ativo = 1
    LIMIT 1
  ");

  if ($resSel && mysqli_num_rows($resSel) > 0) {
    $infraSelecionada = mysqli_fetch_assoc($resSel);

    $filtro_viaturas_infra = sql_filtro_viatura_gestor('viaturas');
    $resV = mysqli_query($ligacao, "
      SELECT id, matricula, marca_modelo, tipo, combustivel, quilometragem, estado
      FROM viaturas
      WHERE infraestrutura_id = $infraestrutura_id {$filtro_viaturas_infra}
      ORDER BY
        CASE estado
          WHEN 'Disponível' THEN 1
          WHEN 'Atribuída' THEN 2
          WHEN 'Em Manutenção' THEN 3
          WHEN 'Inativo' THEN 4
          ELSE 5
        END,
        marca_modelo ASC,
        matricula ASC
    ");

    if ($resV) {
      while ($row = mysqli_fetch_assoc($resV)) {
        $viaturasInfra[] = $row;
      }
    }
  }
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
  #fleetMap {
    height: 500px;
    border-radius: 18px;
    overflow: hidden;
    border: 1px solid rgba(15, 23, 42, 0.08);
  }

  .region-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 18px;
    padding: 18px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.04);
    height: 100%;
    color: #1e293b;
  }

  .region-dot {
    width: 12px;
    height: 12px;
    border-radius: 999px;
    display: inline-block;
    margin-right: 8px;
  }

  .region-number {
    font-size: 42px;
    font-weight: 800;
    line-height: 1;
    color: #0f172a;
    margin: 10px 0 8px;
  }

  .table-soft th {
    color: #64748b;
    font-size: 13px;
    font-weight: 700;
  }

  .table-soft td {
    vertical-align: middle;
  }

  .badge-mini {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 4px 10px;
    font-size: 12px;
    font-weight: 700;
  }

  .badge-eta {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
  }

  .badge-etar {
    background: rgba(59, 130, 246, 0.12);
    color: #3b82f6;
  }

  .soft-panel {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 18px;
    padding: 18px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.04);
  }

  .infra-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: rgba(248, 250, 252, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 16px;
    height: 100%;
  }

  .infra-card.active {
    border-color: rgba(59, 130, 246, 0.3);
    background: rgba(239, 246, 255, 0.9);
  }

  .vehicle-list-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: rgba(248, 250, 252, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 16px;
    height: 100%;
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
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
  }

  .badge-atribuida {
    background: rgba(59, 130, 246, 0.12);
    color: #3b82f6;
  }

  .badge-manutencao {
    background: rgba(245, 158, 11, 0.12);
    color: #f59e0b;
  }

  .badge-inativo {
    background: rgba(100, 116, 139, 0.12);
    color: #64748b;
  }

  .badge-default {
    background: rgba(15, 23, 42, 0.08);
    color: #334155;
  }

  .small-muted {
    color: #64748b;
    font-size: 13px;
  }

  /* Custom styling for dark theme Leaflet popups */
  .leaflet-popup-content-wrapper {
    background: rgba(15, 23, 42, 0.9) !important;
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
  }
  .leaflet-popup-tip {
    background: rgba(15, 23, 42, 0.9) !important;
    border: 1px solid rgba(255, 255, 255, 0.1);
  }
  .leaflet-popup-close-button {
    color: #94a3b8 !important;
  }

  @keyframes pulse-glow {
    0% {
      stroke-width: 1.5;
      stroke: #ffffff;
      stroke-opacity: 0.9;
    }
    50% {
      stroke-width: 5;
      stroke: #d946ef;
      stroke-opacity: 0.6;
    }
    100% {
      stroke-width: 1.5;
      stroke: #ffffff;
      stroke-opacity: 0.9;
    }
  }
  .glow-marker {
    animation: pulse-glow 2s infinite ease-in-out;
  }
</style>

<div class="space-y-6">

  <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
    <div>
      <h1 class="page-title">Mapa da Frota — Algarve</h1>
      <div class="page-subtitle">Distribuição de veículos e infraestruturas por sub-região</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a href="<?php echo $BASE_URL; ?>/viaturas/index.php" class="btn btn-outline-secondary">Ver viaturas</a>
      <?php if ($infraSelecionada && !(perfil_atual() === 'gestor' && infraestrutura_id_sessao() !== null)): ?>
        <a href="index.php" class="btn btn-outline-primary">Limpar seleção</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <?php
      $cores = [
        'Faro / Loulé / São Brás' => '#1d6fd8',
        'Lagos / Portimão' => '#22a45a',
        'Tavira / Olhão / VRSA' => '#f59e0b',
        'Albufeira / Silves' => '#9333ea',
      ];
    ?>
    <?php foreach ($subRegioes as $sr): ?>
      <div class="col-12 col-md-6 col-xl-3">
        <div class="region-card">
          <div class="fw-semibold">
            <span class="region-dot" style="background: <?php echo h($cores[$sr['nome']] ?? '#64748b'); ?>;"></span>
            <?php echo h($sr['nome']); ?>
          </div>

          <div class="region-number"><?php echo (int)$sr['viaturas']; ?></div>

          <div class="text-muted small">
            <?php echo (int)$sr['infraestruturas']; ?> infraestruturas nesta sub-região
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="glass-card p-3 mb-4">
    <div id="fleetMap"></div>
  </div>

  <?php if ($infraSelecionada): ?>
    <div class="soft-panel mb-4">
      <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-3">
        <div>
          <h3 class="section-title mb-1">
            <?php echo h($infraSelecionada['nome'] ?? ''); ?>
          </h3>
          <div class="small-muted">
            <?php echo h(($infraSelecionada['tipo'] ?? '') . ' • ' . ($infraSelecionada['sub_regiao'] ?? '')); ?>
            <?php if (($infraSelecionada['localidade'] ?? '') !== '' || ($infraSelecionada['concelho'] ?? '') !== ''): ?>
              · <?php echo h(trim(($infraSelecionada['localidade'] ?? '') . (($infraSelecionada['concelho'] ?? '') !== '' ? ', ' . $infraSelecionada['concelho'] : ''))); ?>
            <?php endif; ?>
          </div>
        </div>

        <?php if (!(perfil_atual() === 'gestor' && infraestrutura_id_sessao() !== null)): ?>
          <a href="index.php" class="btn btn-outline-secondary btn-sm">Fechar seleção</a>
        <?php endif; ?>
      </div>

      <div class="row g-3">
        <?php if ($viaturasInfra): ?>
          <?php foreach ($viaturasInfra as $v): ?>
            <div class="col-12 col-lg-6">
              <div class="vehicle-list-card">
                <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                  <div>
                    <div class="fw-bold"><?php echo h($v['marca_modelo'] ?? ''); ?></div>
                    <div class="small-muted"><?php echo h($v['matricula'] ?? ''); ?></div>
                  </div>

                  <span class="badge-estado <?php echo h(badge_estado_class($v['estado'] ?? '')); ?>">
                    <?php echo h($v['estado'] ?? ''); ?>
                  </span>
                </div>

                <div class="small-muted mb-2">
                  <strong>Tipo:</strong>
                  <?php echo ($v['tipo'] ?? '') !== '' ? h($v['tipo']) : 'Não definido'; ?>
                </div>

                <div class="small-muted mb-2">
                  <strong>Combustível:</strong>
                  <?php echo ($v['combustivel'] ?? '') !== '' ? h($v['combustivel']) : 'Não definido'; ?>
                </div>

                <div class="small-muted mb-3">
                  <strong>Quilometragem:</strong>
                  <?php echo number_format((int)($v['quilometragem'] ?? 0), 0, ',', '.'); ?> km
                </div>

                <div class="d-flex gap-2 flex-wrap">
                  <a class="btn btn-primary btn-sm" href="<?php echo $BASE_URL; ?>/viaturas/show.php?id=<?php echo (int)$v['id']; ?>">Ver detalhe</a>
                  <a class="btn btn-outline-secondary btn-sm" href="<?php echo $BASE_URL; ?>/viaturas/edit.php?id=<?php echo (int)$v['id']; ?>">Editar</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="col-12">
            <div class="vehicle-list-card text-muted">
              Ainda não existem viaturas atribuídas a esta infraestrutura.
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="glass-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="section-title mb-0">Infraestruturas</h3>
      <div class="small text-muted"><?php echo count($infraestruturas); ?> registos</div>
    </div>

    <div class="row g-3">
      <?php if ($infraestruturas): ?>
        <?php foreach ($infraestruturas as $i): ?>
          <?php
            $isActive = ($infraestrutura_id > 0 && $infraestrutura_id === (int)$i['id']);
            $moradaBase = trim(($i['localidade'] ?: '') . ($i['concelho'] ? ', ' . $i['concelho'] : ''));
          ?>
          <div class="col-12 col-xl-6">
            <div class="infra-card <?php echo $isActive ? 'active' : ''; ?>">
              <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-3">
                <div>
                  <div class="fw-bold"><?php echo h($i['nome']); ?></div>
                  <div class="small-muted"><?php echo h($i['sub_regiao']); ?></div>
                </div>

                <span class="badge-mini <?php echo $i['tipo'] === 'ETA' ? 'badge-eta' : 'badge-etar'; ?>">
                  <?php echo h($i['tipo']); ?>
                </span>
              </div>

              <div class="small-muted mb-2">
                <strong>Base:</strong>
                <?php echo $moradaBase !== '' ? h($moradaBase) : 'Não definida'; ?>
              </div>

              <div class="small-muted mb-2">
                <strong>Viaturas:</strong> <?php echo (int)$i['total_viaturas']; ?>
              </div>

              <div class="small-muted mb-3">
                <strong>Estado:</strong>
                <?php echo (int)$i['disponiveis']; ?> disponíveis,
                <?php echo (int)$i['atribuidas']; ?> atribuídas,
                <?php echo (int)$i['manutencao']; ?> em manutenção,
                <?php echo (int)$i['inativas']; ?> inativas
              </div>

              <div class="d-flex gap-2 flex-wrap">
                <a href="index.php<?php echo qs(['infraestrutura_id' => (int)$i['id']]); ?>" class="btn btn-primary btn-sm">
                  Ver viaturas desta base
                </a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="col-12">
          <div class="text-center text-muted py-4">Ainda não existem infraestruturas cadastradas.</div>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  const infraestruturas = <?php echo json_encode($infraestruturas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const circulos = <?php echo json_encode($circulos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  const selectedInfraId = <?php echo (int)$infraestrutura_id; ?>;
  const viaturasMapa = <?php echo json_encode($viaturasMapa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  const map = L.map('fleetMap').setView([37.1700, -8.0500], 9);

  // CartoDB Dark Matter tile layer
  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    maxZoom: 20,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
  }).addTo(map);

  // Sub-region circles
  circulos.forEach((item) => {
    L.circle([item.lat, item.lng], {
      radius: item.raio,
      color: item.cor,
      fillColor: item.cor,
      fillOpacity: 0.03,
      weight: 1.5,
      dashArray: '6, 6'
    }).addTo(map).bindPopup('<strong>' + item.nome + '</strong>');
  });

  const bounds = [];

  // 1. Draw Infraestruturas (Bases)
  infraestruturas.forEach((item) => {
    if (item.latitude === null || item.longitude === null) return;

    const isSelected = Number(item.id) === Number(selectedInfraId);
    const color = item.tipo === 'ETA' ? '#10b981' : '#3b82f6';

    const marker = L.circleMarker([item.latitude, item.longitude], {
      radius: isSelected ? 12 : 9,
      color: color,
      fillColor: '#1e293b',
      fillOpacity: 0.6,
      weight: isSelected ? 3 : 1.5,
      dashArray: '2, 2'
    }).addTo(map);

    const link = 'index.php?infraestrutura_id=' + encodeURIComponent(item.id);

    const html = `
      <div style="min-width:250px; color: #f8fafc; font-family: sans-serif;">
        <strong style="font-size:14px; color:#f1f5f9;">${item.nome}</strong><br>
        <span style="color:#94a3b8;">${item.tipo} · ${item.sub_regiao}</span><br>
        <span style="color:#94a3b8;">${item.localidade || ''}${item.concelho ? ', ' + item.concelho : ''}</span><hr style="border-color: rgba(255,255,255,0.1); margin: 6px 0;">
        <span>Viaturas Registadas: <strong>${item.total_viaturas}</strong></span><br>
        <div style="margin-top: 4px;">
          <span style="color:#10b981;">● Disp: ${item.disponiveis}</span> | 
          <span style="color:#3b82f6;">● Atrib: ${item.atribuidas}</span> | 
          <span style="color:#f59e0b;">● Manut: ${item.manutencao}</span>
        </div>
        ${!isSelected ? `<br><a href="${link}" class="btn btn-sm btn-primary text-white w-100" style="padding: 2px 8px; font-size:11px;">Filtrar viaturas desta base</a>` : ''}
      </div>
    `;

    marker.bindPopup(html);

    if (isSelected) {
      marker.openPopup();
    }

    bounds.push([item.latitude, item.longitude]);
  });

  // Helper to color code vehicles
  function getVehicleColor(estado, osEstado) {
    if (estado === 'Inativo') return '#64748b'; // Cinzento
    if (estado === 'Em Manutenção') return '#f59e0b'; // Laranja
    if (estado === 'Disponível') return '#10b981'; // Verde
    if (estado === 'Atribuída') {
      if (osEstado === 'em_deslocacao' || osEstado === 'em_execucao') return '#d946ef'; // Fuchsia (Deslocação)
      return '#3b82f6'; // Azul
    }
    return '#cbd5e1';
  }

  // 2. Draw Viaturas (Vehicles) and routes
  viaturasMapa.forEach((v) => {
    // Determine vehicle position
    let activeLat = v.infra_lat;
    let activeLng = v.infra_lng;
    const inDuty = v.os_estado === 'em_deslocacao' || v.os_estado === 'em_execucao';

    if (inDuty && v.dest_lat && v.dest_lng) {
      activeLat = v.dest_lat;
      activeLng = v.dest_lng;
    }

    if (activeLat === null || activeLng === null) return;

    // Apply coordinate jittering to prevent overlaps on the same base
    const jitterLat = (Math.random() - 0.5) * 0.0018;
    const jitterLng = (Math.random() - 0.5) * 0.0018;
    const finalLat = activeLat + jitterLat;
    const finalLng = activeLng + jitterLng;

    const vColor = getVehicleColor(v.estado, v.os_estado);

    const vMarker = L.circleMarker([finalLat, finalLng], {
      radius: 6.5,
      color: '#ffffff',
      weight: 1.5,
      fillColor: vColor,
      fillOpacity: 1.0,
      className: inDuty ? 'glow-marker' : ''
    }).addTo(map);

    // Draw route line if in execution
    if (inDuty && v.orig_lat && v.orig_lng && v.dest_lat && v.dest_lng) {
      L.polyline([[v.orig_lat, v.orig_lng], [v.dest_lat, v.dest_lng]], {
        color: '#d946ef',
        weight: 2,
        opacity: 0.85,
        dashArray: '5, 8',
        lineCap: 'round'
      }).addTo(map).bindPopup(`
        <div style="color: #f8fafc;">
          <strong style="color:#d946ef;">Rota de Serviço em Progresso</strong><br>
          <strong>Serviço:</strong> ${v.servico_nome}<br>
          <strong>Viatura:</strong> ${v.marca_modelo} (${v.matricula})<br>
          <strong>De:</strong> ${v.orig_nome}<br>
          <strong>Para:</strong> ${v.dest_nome}
        </div>
      `);
    }

    const popupHtml = `
      <div style="min-width:200px; color:#f8fafc;">
        <div style="display:flex; justify-content:between; align-items:center; margin-bottom:5px;">
          <strong style="font-size:13px; color:#fff;">${v.marca_modelo}</strong>
        </div>
        <span style="color:#94a3b8; font-size:11px;">Matrícula: <strong>${v.matricula}</strong></span><br>
        <span style="color:#94a3b8; font-size:11px;">Tipo: ${v.tipo}</span><br>
        <span style="color:#94a3b8; font-size:11px;">Combustível: ${v.combustivel}</span><br>
        <span style="color:#94a3b8; font-size:11px;">KM: ${v.quilometragem.toLocaleString('pt-PT')} km</span><br>
        <span style="color:#94a3b8; font-size:11px;">Base de Origem: ${v.infra_nome}</span><br>
        
        <div style="margin-top:8px; padding-top:8px; border-top:1px solid rgba(255,255,255,0.1);">
          <span class="badge" style="background-color: ${vColor}; color:#fff; font-size:10px; padding:3px 6px;">
            ${inDuty ? 'Em Deslocação' : v.estado}
          </span>
        </div>

        ${inDuty ? `
          <div style="margin-top:6px; padding:6px; background:rgba(217,70,239,0.1); border-radius:4px; font-size:11px; border: 1px solid rgba(217,70,239,0.2);">
            <strong style="color:#d946ef;">Serviço Ativo:</strong> ${v.servico_nome}<br>
            <strong>Destino:</strong> ${v.dest_nome}
          </div>
        ` : ''}
      </div>
    `;

    vMarker.bindPopup(popupHtml);
    bounds.push([finalLat, finalLng]);
  });

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [40, 40] });
  }

  setTimeout(function () {
    map.invalidateSize();
  }, 200);
</script>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>