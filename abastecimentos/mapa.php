<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

$active = 'mapa_abastecimentos';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$filtro_abastecimentos = "";
if (perfil_atual() === 'gestor' && infraestrutura_id_sessao() !== null) {
  $infra_id = (int)infraestrutura_id_sessao();
  $filtro_abastecimentos = " AND v.infraestrutura_id = {$infra_id} ";
}

$rows = [];
$sql = "SELECT
          a.id,
          a.posto,
          a.combustivel,
          a.litros,
          a.total,
          a.data_abastecimento,
          a.latitude,
          a.longitude,
          v.matricula,
          v.marca_modelo,
          c.nome AS colaborador_nome
        FROM abastecimentos a
        LEFT JOIN viaturas v ON v.id = a.viatura_id
        LEFT JOIN colaboradores c ON c.id = a.colaborador_id
        WHERE a.latitude IS NOT NULL
          AND a.longitude IS NOT NULL
          {$filtro_abastecimentos}
        ORDER BY a.data_abastecimento DESC, a.id DESC";

$res = mysqli_query($ligacao, $sql);
if ($res) {
  while ($r = mysqli_fetch_assoc($res)) {
    $rows[] = [
      'id' => (int)$r['id'],
      'posto' => (string)($r['posto'] ?? ''),
      'combustivel' => (string)($r['combustivel'] ?? ''),
      'litros' => (float)($r['litros'] ?? 0),
      'total' => (float)($r['total'] ?? 0),
      'data_abastecimento' => (string)($r['data_abastecimento'] ?? ''),
      'latitude' => (float)$r['latitude'],
      'longitude' => (float)$r['longitude'],
      'matricula' => (string)($r['matricula'] ?? ''),
      'marca_modelo' => (string)($r['marca_modelo'] ?? ''),
      'colaborador_nome' => (string)($r['colaborador_nome'] ?? '')
    ];
  }
}

$semLocalizacao = 0;
$rSem = mysqli_query($ligacao, "
  SELECT COUNT(*) AS total 
  FROM abastecimentos a 
  LEFT JOIN viaturas v ON v.id = a.viatura_id
  WHERE (a.latitude IS NULL OR a.longitude IS NULL) {$filtro_abastecimentos}
");
if ($rSem) {
  $semLocalizacao = (int)(mysqli_fetch_assoc($rSem)['total'] ?? 0);
}
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<style>
  #mapaAbastecimentos {
    height: 560px;
    border-radius: 18px;
    overflow: hidden;
    border: 1px solid rgba(15, 23, 42, 0.08);
  }
  .side-card {
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 16px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.04);
    color: #1e293b;
  }
  .abast-item {
    display: block;
    padding: 12px 14px;
    border-radius: 12px;
    text-decoration: none;
    color: inherit;
    border: 1px solid rgba(15, 23, 42, 0.06);
    background: rgba(248, 250, 252, 0.9);
    transition: all 0.2s ease-in-out;
  }
  .abast-item:hover {
    background: rgba(59, 130, 246, 0.1);
    border-color: rgba(59, 130, 246, 0.3);
  }
  .mini-label {
    font-size: 12px;
    color: #64748b;
  }
  .mini-value {
    font-size: 22px;
    font-weight: 800;
    color: #0f172a;
  }
  .small-muted {
    color: #64748b;
  }
  .scrollable-list {
    max-height: 400px;
    overflow-y: auto;
    padding-right: 4px;
  }
  .scrollable-list::-webkit-scrollbar {
    width: 6px;
  }
  .scrollable-list::-webkit-scrollbar-track {
    background: rgba(15, 23, 42, 0.05);
    border-radius: 999px;
  }
  .scrollable-list::-webkit-scrollbar-thumb {
    background: rgba(15, 23, 42, 0.15);
    border-radius: 999px;
  }
  
  /* Dark Leaflet Popup */
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
</style>

<div class="space-y-6">

  <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4">
    <div>
      <h1 class="page-title">Mapa de Abastecimentos</h1>
      <div class="page-subtitle">Visualização geográfica dos abastecimentos registados</div>
    </div>

    <div class="d-flex gap-2">
      <a href="index.php" class="btn btn-outline-secondary">Voltar à lista</a>
      <a href="create.php" class="btn btn-primary">Novo abastecimento</a>
    </div>
  </div>

  <!-- Real-time search and filter controls -->
  <div class="glass-card p-3 mb-4">
    <div class="row g-3">
      <div class="col-12 col-md-8">
        <label class="form-label small-muted mb-1">Pesquisa Textual</label>
        <input type="text" id="searchFilter" class="form-control" placeholder="Pesquisar por posto, matrícula ou marca/modelo...">
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label small-muted mb-1">Tipo de Combustível</label>
        <select id="combustivelFilter" class="form-select">
          <option value="">Todos os combustíveis</option>
          <option value="Gasóleo">Gasóleo</option>
          <option value="Gasolina">Gasolina</option>
          <option value="Elétrico">Elétrico</option>
          <option value="Híbrido">Híbrido</option>
          <option value="GPL">GPL</option>
        </select>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-9">
      <div class="side-card p-3">
        <div id="mapaAbastecimentos"></div>
      </div>
    </div>

    <div class="col-12 col-lg-3">
      <div class="side-card p-3 mb-3">
        <div class="mini-label">Com localização</div>
        <div class="mini-value" id="lblComLocalizacao"><?php echo count($rows); ?></div>
      </div>

      <div class="side-card p-3 mb-3">
        <div class="mini-label">Sem localização</div>
        <div class="mini-value"><?php echo $semLocalizacao; ?></div>
      </div>

      <div class="side-card p-3">
        <div class="fw-semibold mb-3">Últimos registos com mapa</div>

        <div class="scrollable-list d-flex flex-column gap-2" id="abastList">
          <?php if ($rows): ?>
            <?php foreach ($rows as $r): ?>
              <div id="item-<?php echo $r['id']; ?>" class="abast-item" onclick="zoomToAbastecimento(<?php echo $r['id']; ?>, <?php echo $r['latitude']; ?>, <?php echo $r['longitude']; ?>)" style="cursor:pointer;">
                <div class="d-flex justify-content-between align-items-start gap-1">
                  <div class="fw-semibold text-truncate" style="max-width: 140px;"><?php echo h($r['posto']); ?></div>
                  <a href="edit.php?id=<?php echo (int)$r['id']; ?>" class="text-primary text-decoration-none" onclick="event.stopPropagation();" title="Editar Abastecimento">
                    📝
                  </a>
                </div>
                <div class="small text-muted text-truncate">
                  <?php echo h($r['matricula']); ?> · <?php echo date('d/m/Y', strtotime($r['data_abastecimento'])); ?>
                </div>
                <div class="small text-muted">
                  <strong><?php echo number_format((float)$r['litros'], 2, ',', '.'); ?> L</strong> ·
                  <strong>€ <?php echo number_format((float)$r['total'], 2, ',', '.'); ?></strong>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="text-muted small">Ainda não existem abastecimentos com coordenadas.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  const rows = <?php echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

  const map = L.map('mapaAbastecimentos').setView([37.2301, -8.0653], 9);

  // CartoDB Dark Matter tile layer
  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
    maxZoom: 20,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>'
  }).addTo(map);

  const bounds = [];
  const markers = {};

  rows.forEach((row) => {
    const html = `
      <div style="min-width:220px; color:#f8fafc; font-family:sans-serif;">
        <strong style="font-size:14px; color:#fff;">${row.posto}</strong><br>
        <span style="color:#94a3b8;">${row.marca_modelo || ''} ${row.matricula ? '· ' + row.matricula : ''}</span><br>
        <span style="color:#94a3b8;">${Number(row.litros).toFixed(2)} L · € ${Number(row.total).toFixed(2)}</span><br>
        <span style="color:#94a3b8;">${row.combustivel} · ${row.data_abastecimento}</span>
        ${row.colaborador_nome ? '<br><span style="color:#cbd5e1;">Motorista: ' + row.colaborador_nome + '</span>' : ''}
      </div>
    `;

    const marker = L.circleMarker([row.latitude, row.longitude], {
      radius: 7.5,
      color: '#ffffff',
      weight: 1.5,
      fillColor: '#f59e0b',
      fillOpacity: 0.95
    }).addTo(map).bindPopup(html);

    markers[row.id] = marker;
    bounds.push([row.latitude, row.longitude]);
  });

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [40, 40] });
  }

  window.zoomToAbastecimento = function(id, lat, lng) {
    if (markers[id]) {
      map.flyTo([lat, lng], 14);
      markers[id].openPopup();
    }
  };

  // Live filter function
  const searchInput = document.getElementById('searchFilter');
  const combustivelFilter = document.getElementById('combustivelFilter');

  function applyFilters() {
    const term = searchInput.value.toLowerCase();
    const type = combustivelFilter.value;

    let visibleCount = 0;
    const newBounds = [];

    rows.forEach((row) => {
      const matchSearch = row.posto.toLowerCase().includes(term) || 
                          row.matricula.toLowerCase().includes(term) || 
                          (row.marca_modelo && row.marca_modelo.toLowerCase().includes(term));
      const matchType = type === "" || row.combustivel === type;

      const itemEl = document.getElementById(`item-${row.id}`);
      
      if (matchSearch && matchType) {
        if (itemEl) itemEl.style.display = 'block';
        if (!map.hasLayer(markers[row.id])) {
          markers[row.id].addTo(map);
        }
        newBounds.push([row.latitude, row.longitude]);
        visibleCount++;
      } else {
        if (itemEl) itemEl.style.display = 'none';
        if (map.hasLayer(markers[row.id])) {
          map.removeLayer(markers[row.id]);
        }
      }
    });

    document.getElementById('lblComLocalizacao').innerText = visibleCount;

    if (newBounds.length > 0) {
      map.fitBounds(newBounds, { padding: [40, 40] });
    }
  }

  searchInput.addEventListener('input', applyFilters);
  combustivelFilter.addEventListener('change', applyFilters);

  setTimeout(function () {
    map.invalidateSize();
  }, 200);
</script>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>