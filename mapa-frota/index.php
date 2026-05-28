<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

$active = 'mapa_frota';
require_once __DIR__ . "/../inc/database.php";
require_once __DIR__ . "/../inc/header.php";

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Carregar zonas operacionais para filtro
$zonas = [];
$resZ = mysqli_query($ligacao, "SELECT id, nome, cor FROM zonas_operacionais WHERE ativo=1 ORDER BY nome ASC");
if ($resZ) while ($r = mysqli_fetch_assoc($resZ)) $zonas[] = $r;

// Determinar o âmbito exibido no título
$ambito = "Visão Global da Operação";
if (is_gestor_zona()) {
  $zid = (int)zona_id_sessao();
  $resZN = mysqli_query($ligacao, "SELECT nome FROM zonas_operacionais WHERE id = $zid LIMIT 1");
  if ($resZN && $rowZN = mysqli_fetch_assoc($resZN)) {
    $ambito = "Gestor de Zona — " . h($rowZN['nome']);
  }
} elseif (is_operario()) {
  $ambito = "Operário — Viatura Atribuída";
}
?>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<style>
  .map-wrapper {
    display: flex;
    height: 700px;
    border-radius: 20px;
    overflow: hidden;
    background: rgba(30, 41, 59, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
  }

  .map-sidebar {
    width: 320px;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(12px);
    border-right: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    flex-column: column;
    height: 100%;
    z-index: 1000;
  }

  .sidebar-content {
    flex-grow: 1;
    overflow-y: auto;
    padding: 16px;
  }

  .sidebar-header {
    padding: 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(15, 23, 42, 0.5);
  }

  #map-container {
    flex-grow: 1;
    height: 100%;
    position: relative;
  }

  #map {
    height: 100%;
    width: 100%;
  }

  .layer-checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s;
  }

  .layer-checkbox:hover {
    background: rgba(255, 255, 255, 0.08);
  }

  .layer-checkbox input {
    cursor: pointer;
  }

  /* Custom marker styles */
  .custom-div-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 10px rgba(0,0,0,0.5);
    color: #fff;
    font-weight: bold;
    transition: transform 0.2s;
  }
  .custom-div-icon:hover {
    transform: scale(1.15);
  }

  /* Quick Actions Panel */
  .detail-panel {
    background: rgba(30, 41, 59, 0.95);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 12px;
    padding: 14px;
    margin-top: 14px;
  }

  .alert-item {
    padding: 10px;
    border-radius: 8px;
    background: rgba(239, 68, 68, 0.08);
    border-left: 3px solid #ef4444;
    margin-bottom: 8px;
    font-size: 12px;
  }
  .alert-item.warning {
    background: rgba(245, 158, 11, 0.08);
    border-left-color: #f59e0b;
  }

  .legend-pill {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 99px;
    font-weight: 700;
  }

  /* Suggestion block */
  .suggestion-box {
    background: rgba(16, 185, 129, 0.08);
    border: 1px dashed rgba(16, 185, 129, 0.3);
    border-radius: 8px;
    padding: 10px;
    margin-top: 10px;
    font-size: 12px;
  }
</style>

<div class="space-y-4">
  
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h1 class="page-title mb-1">Mapa Operacional da Frota</h1>
      <div class="page-subtitle d-flex align-items-center gap-2">
        <span class="badge bg-primary"><?php echo $ambito; ?></span>
        <span>Visualização espacial em tempo real de infraestruturas, ocorrências e veículos</span>
      </div>
    </div>

    <!-- Filtro de Zona para Admin / Gestor Global -->
    <?php if (is_admin() || is_gestor_global()): ?>
      <div class="d-flex align-items-center gap-2">
        <label class="small text-muted fw-semibold">Filtrar por Zona:</label>
        <select class="form-select form-select-sm" id="selectZonaFiltro" style="width: 180px;">
          <option value="">Todas as Zonas</option>
          <?php foreach ($zonas as $z): ?>
            <option value="<?php echo (int)$z['id']; ?>"><?php echo h($z['nome']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
  </div>

  <!-- Wrapper do Mapa -->
  <div class="map-wrapper">
    
    <!-- Sidebar do Mapa -->
    <div class="map-sidebar d-flex flex-column">
      <div class="sidebar-header">
        <div class="searchbox">
          <input type="text" id="mapSearch" class="form-control form-control-sm bg-dark text-light border-secondary" placeholder="Pesquisar matrícula, local, concelho...">
        </div>
      </div>
      
      <div class="sidebar-content">
        <!-- Camadas Ativáveis -->
        <h6 class="text-uppercase text-muted fw-bold mb-2 small" style="letter-spacing: .05em;">Camadas do Mapa</h6>
        <div class="mb-4">
          <label class="layer-checkbox">
            <input type="checkbox" id="layerViaturas" checked>
            <i class="bi bi-car-front-fill text-primary"></i>
            <span class="small text-light">Viaturas</span>
          </label>
          
          <label class="layer-checkbox">
            <input type="checkbox" id="layerInfra" checked>
            <i class="bi bi-recycle text-success"></i>
            <span class="small text-light">Infraestruturas</span>
          </label>

          <?php if (!is_operario()): ?>
            <label class="layer-checkbox">
              <input type="checkbox" id="layerOcorrencias" checked>
              <i class="bi bi-exclamation-octagon-fill text-danger"></i>
              <span class="small text-light">Ocorrências Ativas</span>
            </label>
            
            <label class="layer-checkbox">
              <input type="checkbox" id="layerAbastecimentos">
              <i class="bi bi-fuel-pump-fill text-warning"></i>
              <span class="small text-light">Abastecimentos</span>
            </label>
            
            <label class="layer-checkbox">
              <input type="checkbox" id="layerOrdens">
              <i class="bi bi-clipboard-check-fill text-info"></i>
              <span class="small text-light">Serviços em Curso</span>
            </label>
          <?php endif; ?>
        </div>

        <!-- Painel de Detalhes e Ações Rápidas -->
        <div id="detailPanelContainer" style="display: none;">
          <div class="detail-panel">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h6 class="fw-bold mb-0 text-light" id="detailTitle">Detalhes do Elemento</h6>
              <button class="btn btn-close btn-close-white btn-sm" onclick="closeDetailPanel()"></button>
            </div>
            <div id="detailBody" class="small text-light">
              Selecione um marcador no mapa para ver mais detalhes.
            </div>
          </div>
        </div>

        <!-- Alertas Inteligentes (Apenas Gestão) -->
        <?php if (!is_operario()): ?>
          <div class="mt-4">
            <h6 class="text-uppercase text-muted fw-bold mb-2 small" style="letter-spacing: .05em;">Alertas Operacionais</h6>
            <div id="alertsContainer" class="vstack gap-2">
              <div class="text-muted small italic">Nenhum alerta crítico ativo.</div>
            </div>
          </div>
        <?php endif; ?>

      </div>
    </div>

    <!-- Canvas do Leaflet -->
    <div id="map-container">
      <div id="map"></div>
    </div>

  </div>

</div>

<script>
  let map;
  let markers = {
    viaturas: [],
    infraestruturas: [],
    ocorrencias: [],
    abastecimentos: [],
    ordens: []
  };

  // Coordenadas centrais padrão do Algarve
  const defaultCenter = [37.1300, -8.2500];
  const defaultZoom = 10;

  document.addEventListener("DOMContentLoaded", function() {
    // Inicializar mapa
    map = L.map('map', {
      zoomControl: true,
      maxZoom: 18,
      minZoom: 9
    }).setView(defaultCenter, defaultZoom);

    // Adicionar base map baseado nas configurações
    <?php
      $mapStyle = $sys_cfg['mapa_estilo'] ?? 'dark';
      if ($mapStyle === 'light') {
        $tileUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
        $tileAttr = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
      } else {
        $tileUrl = 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png';
        $tileAttr = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>';
      }
    ?>
    L.tileLayer('<?php echo $tileUrl; ?>', {
      attribution: '<?php echo $tileAttr; ?>',
      maxZoom: 18
    }).addTo(map);

    // Carregar dados iniciais
    carregarDadosMapa();

    // Eventos
    const selZona = document.getElementById('selectZonaFiltro');
    if (selZona) {
      selZona.addEventListener('change', carregarDadosMapa);
    }

    // Toggle das camadas
    document.getElementById('layerViaturas').addEventListener('change', toggleLayers);
    const lInfra = document.getElementById('layerInfra'); if (lInfra) lInfra.addEventListener('change', toggleLayers);
    const lOcor = document.getElementById('layerOcorrencias'); if (lOcor) lOcor.addEventListener('change', toggleLayers);
    const lAbas = document.getElementById('layerAbastecimentos'); if (lAbas) lAbas.addEventListener('change', toggleLayers);
    const lOrd = document.getElementById('layerOrdens'); if (lOrd) lOrd.addEventListener('change', toggleLayers);

    // Pesquisa
    document.getElementById('mapSearch').addEventListener('input', function(e) {
      const q = e.target.value.toLowerCase().trim();
      filtrarMarcadores(q);
    });
  });

  // Função central para buscar dados via API
  let apiData = null;
  function carregarDadosMapa() {
    let url = 'api_mapa.php';
    const selZona = document.getElementById('selectZonaFiltro');
    if (selZona && selZona.value !== '') {
      url += '?zona_id=' + selZona.value;
    }

    fetch(url)
      .then(res => res.json())
      .then(data => {
        apiData = data;
        renderizarMapa(data);
        gerarAlertasInteligentes(data);
      })
      .catch(err => console.error("Erro ao carregar dados do mapa:", err));
  }

  // Limpar marcadores anteriores
  function limparMarcadores() {
    Object.keys(markers).forEach(key => {
      markers[key].forEach(m => map.removeLayer(m.marker));
      markers[key] = [];
    });
  }

  // Renderizar camadas no mapa Leaflet
  function renderizarMapa(data) {
    limparMarcadores();

    // 1. VIATURAS
    if (data.viaturas && document.getElementById('layerViaturas').checked) {
      data.viaturas.forEach(v => {
        // Cor do marcador baseada no estado
        let color = '#3b82f6'; // Azul padrão (Atribuída)
        if (v.estado === 'Disponível') color = '#22c55e'; // Verde
        else if (v.estado === 'Em Manutenção') color = '#f59e0b'; // Laranja
        else if (v.estado === 'Inativo') color = '#94a3b8'; // Cinza

        const icon = L.divIcon({
          className: 'custom-div-icon',
          html: `<div style="background-color: ${color}; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px;"><i class="bi bi-car-front-fill"></i></div>`,
          iconSize: [28, 28]
        });

        const marker = L.marker([v.latitude, v.longitude], { icon: icon });
        
        // Popup content
        const popupHtml = `
          <div class="p-2" style="font-family: sans-serif; color: #0f172a; min-width: 200px;">
            <div class="fw-bold mb-1">${v.marca_modelo}</div>
            <div class="small text-muted mb-2">Matrícula: <strong>${v.matricula}</strong></div>
            <div class="small mb-1">Estado: <span class="badge" style="background-color: ${color};">${v.estado}</span></div>
            <div class="small mb-1">Zona: <strong>${v.zona_nome}</strong></div>
            <div class="small mb-2">Base: <strong>${v.base_nome}</strong></div>
            <div class="small text-muted">Origem Localização: <br><em>${v.origem_localizacao}</em></div>
            <div class="mt-2 pt-2 border-top d-flex gap-1">
              <a href="../viaturas/show.php?id=${v.id}" class="btn btn-xs btn-primary text-white text-decoration-none px-2 py-1 small rounded">Ver Detalhe</a>
            </div>
          </div>
        `;
        marker.bindPopup(popupHtml);
        marker.on('click', () => showDetailPanel('viatura', v));

        if (document.getElementById('layerViaturas').checked) {
          marker.addTo(map);
        }

        markers.viaturas.push({
          data: v,
          marker: marker
        });
      });
    }

    // 2. INFRAESTRUTURAS
    if (data.infraestruturas && document.getElementById('layerInfra') && document.getElementById('layerInfra').checked) {
      data.infraestruturas.forEach(inf => {
        const isETA = (inf.tipo || '').toUpperCase() === 'ETA';
        const bgColor = isETA ? '#0284c7' : '#10b981';
        const iconClass = isETA ? 'bi-droplet-fill' : 'bi-recycle';
        
        const icon = L.divIcon({
          className: 'custom-div-icon',
          html: `<div style="background-color: ${bgColor}; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; color: white;"><i class="bi ${iconClass}"></i></div>`,
          iconSize: [28, 28]
        });

        const marker = L.marker([inf.latitude, inf.longitude], { icon: icon });
        const popupHtml = `
          <div class="p-2" style="font-family: sans-serif; color: #0f172a; min-width: 220px;">
            <div class="fw-bold mb-1">${inf.nome}</div>
            <div class="small text-muted mb-2">${inf.tipo} · ${inf.localidade}</div>
            <div class="small mb-1">Ocorrências Abertas: <span class="badge bg-danger">${inf.ocorrencias_abertas}</span></div>
            <div class="small mb-1">Serviços Pendentes: <span class="badge bg-info">${inf.ordens_pendentes}</span></div>
            <div class="small">Zona: <strong>${inf.zona_nome}</strong></div>
          </div>
        `;
        marker.bindPopup(popupHtml);
        marker.on('click', () => showDetailPanel('infraestrutura', inf));
        marker.addTo(map);

        markers.infraestruturas.push({
          data: inf,
          marker: marker
        });
      });
    }

    // 3. OCORRÊNCIAS
    if (data.ocorrencias && document.getElementById('layerOcorrencias') && document.getElementById('layerOcorrencias').checked) {
      data.ocorrencias.forEach(oc => {
        let color = '#f59e0b'; // Média
        if (oc.gravidade === 'baixa') color = '#3b82f6';
        else if (oc.gravidade === 'alta') color = '#ef4444';
        else if (oc.gravidade === 'critica') color = '#dc2626';

        const icon = L.divIcon({
          className: 'custom-div-icon',
          html: `<div style="background-color: ${color}; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px;"><i class="bi bi-exclamation-octagon-fill"></i></div>`,
          iconSize: [28, 28]
        });

        const marker = L.marker([oc.latitude, oc.longitude], { icon: icon });
        const popupHtml = `
          <div class="p-2" style="font-family: sans-serif; color: #0f172a; min-width: 220px;">
            <div class="fw-bold mb-1 text-danger">${oc.codigo}</div>
            <div class="fw-bold mb-1">${oc.titulo}</div>
            <div class="small text-muted mb-2">Gravidade: <span class="badge" style="background-color: ${color};">${oc.gravidade}</span></div>
            <div class="small mb-2">Viatura: <strong>${oc.viatura}</strong></div>
            <div class="mt-2 pt-2 border-top d-flex gap-1">
              <a href="../ocorrencias/show.php?id=${oc.id}" class="btn btn-xs btn-primary text-white text-decoration-none px-2 py-1 small rounded">Ver Detalhes</a>
            </div>
          </div>
        `;
        marker.bindPopup(popupHtml);
        marker.on('click', () => showDetailPanel('ocorrencia', oc));
        marker.addTo(map);

        markers.ocorrencias.push({
          data: oc,
          marker: marker
        });
      });
    }

    // 4. ABASTECIMENTOS
    if (data.abastecimentos && document.getElementById('layerAbastecimentos') && document.getElementById('layerAbastecimentos').checked) {
      data.abastecimentos.forEach(ab => {
        const icon = L.divIcon({
          className: 'custom-div-icon',
          html: `<div style="background-color: #eab308; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px;"><i class="bi bi-fuel-pump-fill"></i></div>`,
          iconSize: [24, 24]
        });

        const marker = L.marker([ab.latitude, ab.longitude], { icon: icon });
        const popupHtml = `
          <div class="p-2" style="font-family: sans-serif; color: #0f172a; min-width: 200px;">
            <div class="fw-bold mb-1">${ab.posto}</div>
            <div class="small text-muted mb-2">${ab.data_abastecimento}</div>
            <div class="small mb-1">Litros: <strong>${ab.litros} L</strong></div>
            <div class="small mb-1">Custo: <strong>€ ${ab.total}</strong></div>
            <div class="small">Viatura: <strong>${ab.matricula}</strong></div>
          </div>
        `;
        marker.bindPopup(popupHtml);
        marker.addTo(map);

        markers.abastecimentos.push({
          data: ab,
          marker: marker
        });
      });
    }

    // 5. ORDENS EM CURSO
    if (data.ordens && document.getElementById('layerOrdens') && document.getElementById('layerOrdens').checked) {
      data.ordens.forEach(os => {
        const icon = L.divIcon({
          className: 'custom-div-icon',
          html: `<div style="background-color: #06b6d4; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px;"><i class="bi bi-clipboard-check-fill"></i></div>`,
          iconSize: [26, 26]
        });

        const marker = L.marker([os.latitude, os.longitude], { icon: icon });
        const popupHtml = `
          <div class="p-2" style="font-family: sans-serif; color: #0f172a; min-width: 220px;">
            <div class="fw-bold mb-1 text-info">${os.codigo}</div>
            <div class="fw-bold mb-1">${os.titulo}</div>
            <div class="small text-muted mb-2">${os.tipo}</div>
            <div class="small mb-1">Local: <strong>${os.local_nome}</strong></div>
            <div class="small mb-1">Motorista: <strong>${os.motorista}</strong></div>
            <div class="small">Viatura: <strong>${os.viatura}</strong></div>
            <div class="mt-2 pt-2 border-top">
              <a href="../ordens/show.php?id=${os.id}" class="btn btn-xs btn-primary text-white text-decoration-none px-2 py-1 small rounded">Acompanhar</a>
            </div>
          </div>
        `;
        marker.bindPopup(popupHtml);
        marker.addTo(map);

        markers.ordens.push({
          data: os,
          marker: marker
        });
      });
    }
  }

  // Toggle das camadas baseado em checkbox
  function toggleLayers() {
    if (apiData) {
      renderizarMapa(apiData);
    }
  }

  // Filtrar marcadores pela barra de pesquisa
  function filtrarMarcadores(query) {
    Object.keys(markers).forEach(key => {
      markers[key].forEach(m => {
        let match = false;
        const d = m.data;

        if (key === 'viaturas') {
          match = d.matricula.toLowerCase().includes(query) || d.marca_modelo.toLowerCase().includes(query) || d.base_nome.toLowerCase().includes(query);
        } else if (key === 'infraestruturas') {
          match = d.nome.toLowerCase().includes(query) || d.concelho.toLowerCase().includes(query) || d.localidade.toLowerCase().includes(query);
        } else if (key === 'ocorrencias') {
          match = d.codigo.toLowerCase().includes(query) || d.titulo.toLowerCase().includes(query) || d.viatura.toLowerCase().includes(query);
        }

        if (match || query === '') {
          if (!map.hasLayer(m.marker)) {
            m.marker.addTo(map);
          }
        } else {
          if (map.hasLayer(m.marker)) {
            map.removeLayer(m.marker);
          }
        }
      });
    });
  }

  // Fechar painel lateral
  function closeDetailPanel() {
    document.getElementById('detailPanelContainer').style.display = 'none';
  }

  // Exibir informações detalhadas e ações rápidas no painel lateral
  function showDetailPanel(tipo, item) {
    const container = document.getElementById('detailPanelContainer');
    const title = document.getElementById('detailTitle');
    const body = document.getElementById('detailBody');
    container.style.display = '';

    if (tipo === 'viatura') {
      title.innerHTML = `<i class="bi bi-car-front-fill text-primary me-2"></i>${item.marca_modelo}`;
      body.innerHTML = `
        <div class="vstack gap-2 text-light">
          <div>Matrícula: <strong class="text-white">${item.matricula}</strong></div>
          <div>Quilometragem: <strong class="text-white">${item.quilometragem.toLocaleString('pt-PT')} km</strong></div>
          <div>Zona: <strong class="text-white">${item.zona_nome}</strong></div>
          <div>Base: <strong class="text-white">${item.base_nome}</strong></div>
          <div class="mt-2">
            <a href="../viaturas/show.php?id=${item.id}" class="btn btn-xs btn-outline-primary w-100 mb-1">Ficha da Viatura</a>
            <a href="../abastecimentos/create.php?viatura_id=${item.id}" class="btn btn-xs btn-outline-warning w-100">Registrar Abastecimento</a>
          </div>
        </div>
      `;
    } else if (tipo === 'infraestrutura') {
      title.innerHTML = `<i class="bi bi-building-fill text-success me-2"></i>${item.nome}`;
      body.innerHTML = `
        <div class="vstack gap-2 text-light">
          <div>Tipo: <strong class="text-white">${item.tipo}</strong></div>
          <div>Localidade: <strong class="text-white">${item.localidade} (${item.concelho})</strong></div>
          <div>Zona: <strong class="text-white">${item.zona_nome}</strong></div>
          <div class="border-top border-secondary border-opacity-20 my-2"></div>
          <div>Ocorrências Ativas: <span class="badge bg-danger">${item.ocorrencias_abertas}</span></div>
          <div>Serviços Pendentes: <span class="badge bg-info">${item.ordens_pendentes}</span></div>
          ${<?php echo !is_operario() ? 'true' : 'false'; ?> ? `
          <div class="mt-2">
            <a href="../ordens/create.php?infraestrutura_id=${item.id}" class="btn btn-xs btn-outline-primary w-100">Criar Ordem de Serviço</a>
          </div>
          ` : ''}
        </div>
      `;
    } else if (tipo === 'ocorrencia') {
      title.innerHTML = `<i class="bi bi-exclamation-octagon-fill text-danger me-2"></i>${item.codigo}`;
      
      // Encontrar a viatura disponível mais próxima usando a fórmula de Haversine
      let viaturaMaisProxima = null;
      let menorDistancia = Infinity;

      if (apiData && apiData.viaturas) {
        apiData.viaturas.forEach(v => {
          if (v.estado === 'Disponível') {
            const dist = calcularDistancia(item.latitude, item.longitude, v.latitude, v.longitude);
            if (dist < menorDistancia) {
              menorDistancia = dist;
              viaturaMaisProxima = v;
            }
          }
        });
      }

      let sugestaoHtml = '<div class="text-white-50 italic mt-2">Nenhuma viatura disponível para sugerir.</div>';
      if (viaturaMaisProxima) {
        sugestaoHtml = `
          <div class="suggestion-box">
            <div class="fw-bold text-success"><i class="bi bi-patch-check-fill me-1"></i>Viatura Sugerida:</div>
            <div>${viaturaMaisProxima.marca_modelo} (${viaturaMaisProxima.matricula})</div>
            <div>Distância Estimada: <strong>${menorDistancia.toFixed(1)} km</strong></div>
            <a href="../ordens/create.php?viatura_id=${viaturaMaisProxima.id}&infraestrutura_id=" class="btn btn-xs btn-success w-100 text-white mt-2">Atribuir Viatura</a>
          </div>
        `;
      }

      body.innerHTML = `
        <div class="vstack gap-2">
          <div class="fw-bold text-light">${item.titulo}</div>
          <div>Gravidade: <span class="badge-pill bg-danger bg-opacity-25 text-danger">${item.gravidade}</span></div>
          <div>Viatura ligada: <strong class="text-light">${item.viatura}</strong></div>
          <div class="text-white-50 small">Registado em: ${item.criado_em}</div>
          ${sugestaoHtml}
          <div class="mt-2 border-top border-secondary border-opacity-20 pt-2">
            <a href="../ocorrencias/show.php?id=${item.id}" class="btn btn-xs btn-outline-primary w-100">Ficha da Ocorrência</a>
          </div>
        </div>
      `;
    }
  }

  // Gerar alertas operacionais inteligentes baseados nos dados recebidos
  function gerarAlertasInteligentes(data) {
    const container = document.getElementById('alertsContainer');
    if (!container) return;

    let alertas = [];

    // 1. Viaturas fora de sua zona operacional (Estimado por coordenadas vs Zona)
    // Para simplificar, assumimos que se estiver mais de 45km da base, está fora de zona
    if (data.viaturas) {
      data.viaturas.forEach(v => {
        if (v.base_nome !== 'Sem base atribuída' && v.origem_localizacao !== 'Padrão (Sem coordenadas)') {
          // Procurar coordenadas da base
          const base = data.infraestruturas.find(i => i.nome === v.base_nome);
          if (base) {
            const dist = calcularDistancia(v.latitude, v.longitude, base.latitude, base.longitude);
            if (dist > 45) {
              alertas.push({
                tipo: 'danger',
                msg: `Viatura <strong>${v.matricula}</strong> fora da zona operacional (${dist.toFixed(0)} km de distância de sua base).`
              });
            }
          }
        }
      });
    }

    // 2. Ocorrências críticas sem viaturas disponíveis próximas
    if (data.ocorrencias) {
      data.ocorrencias.forEach(oc => {
        if (oc.gravidade === 'critica' || oc.gravidade === 'alta') {
          let viaturasPerto = 0;
          if (data.viaturas) {
            data.viaturas.forEach(v => {
              if (v.estado === 'Disponível') {
                const dist = calcularDistancia(oc.latitude, oc.longitude, v.latitude, v.longitude);
                if (dist < 20) viaturasPerto++;
              }
            });
          }
          if (viaturasPerto === 0) {
            alertas.push({
              tipo: 'warning',
              msg: `Ocorrência crítica <strong>${oc.codigo}</strong> sem nenhuma viatura disponível a menos de 20 km.`
            });
          }
        }
      });
    }

    // Renderizar
    if (alertas.length === 0) {
      container.innerHTML = '<div class="text-muted small italic">Nenhum alerta crítico ativo.</div>';
    } else {
      container.innerHTML = alertas.map(al => `
        <div class="alert-item ${al.tipo === 'warning' ? 'warning' : ''}">
          <i class="bi bi-exclamation-triangle-fill me-1"></i>
          ${al.msg}
        </div>
      `).join('');
    }
  }

  // Fórmula de Haversine para calcular distância entre duas coordenadas em km
  function calcularDistancia(lat1, lon1, lat2, lon2) {
    const R = 6371; // Raio da Terra em km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = 
      Math.sin(dLat/2) * Math.sin(dLat/2) +
      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
      Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
  }
</script>

<?php
mysqli_close($ligacao);
require_once __DIR__ . "/../inc/footer.php";
?>