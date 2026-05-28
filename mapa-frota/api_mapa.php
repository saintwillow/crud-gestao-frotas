<?php
require_once __DIR__ . "/../inc/auth.php";
require_once __DIR__ . "/../inc/database.php";

// Definir cabeçalho JSON
header('Content-Type: application/json; charset=utf-8');

// Exigir login
if (!esta_logado()) {
  http_response_code(401);
  echo json_encode(['error' => 'Não autorizado']);
  exit;
}

// Filtros recebidos por GET
$filtros_zona_id = isset($_GET['zona_id']) && $_GET['zona_id'] !== '' ? (int)$_GET['zona_id'] : null;

// Se for gestor de zona, forçar a zona dele na sessão
if (is_gestor_zona()) {
  $filtros_zona_id = zona_id_sessao();
}

$response = [
  'viaturas' => [],
  'infraestruturas' => [],
  'ocorrencias' => [],
  'abastecimentos' => [],
  'ordens' => []
];

// Helper para construir cláusula where de zona
function get_where_zona_id($campo_tabela, $zona_id) {
  if ($zona_id === null) return "1=1";
  return "$campo_tabela = $zona_id";
}

$mesAtual = date('Y-m');

// 1. CARREGAR VIATURAS
$whereV = get_where_zona_id("v.zona_operacional_id", $filtros_zona_id);
if (is_operario()) {
  // Operário vê todas as viaturas pertencentes à sua zona operacional
  $zid = zona_id_sessao();
  if ($zid !== null) {
    $whereV = "v.zona_operacional_id = $zid";
  } else {
    $vid = (int)(viatura_id_sessao() ?? 0);
    $whereV = "v.id = $vid";
  }
}
$sqlV = "
  SELECT 
    v.id, v.matricula, v.marca_modelo, v.tipo, v.combustivel, v.quilometragem, v.estado,
    v.lat_atual, v.lng_atual, v.data_localizacao, v.origem_localizacao,
    zo.nome AS zona_nome, zo.cor AS zona_cor,
    i.nome AS base_nome, i.latitude AS base_lat, i.longitude AS base_lng
  FROM viaturas v
  LEFT JOIN zonas_operacionais zo ON zo.id = v.zona_operacional_id
  LEFT JOIN infraestruturas i ON i.id = v.infraestrutura_id
  WHERE $whereV
";
$resV = mysqli_query($ligacao, $sqlV);
if ($resV) {
  while ($r = mysqli_fetch_assoc($resV)) {
    // Estimativa de localização se estiver nula
    $lat = $r['lat_atual'];
    $lng = $r['lng_atual'];
    $origem = $r['origem_localizacao'] ?? 'Estimada (Base)';

    if (($lat === null || $lng === null) && $r['base_lat'] !== null && $r['base_lng'] !== null) {
      $lat = $r['base_lat'];
      $lng = $r['base_lng'];
      $origem = 'Estimada (Base)';
    }

    // Se ainda for nulo, tentar do último abastecimento
    if ($lat === null || $lng === null) {
      $qA = mysqli_query($ligacao, "SELECT latitude, longitude FROM abastecimentos WHERE viatura_id = {$r['id']} AND latitude IS NOT NULL LIMIT 1");
      if ($qA && $rowA = mysqli_fetch_assoc($qA)) {
        $lat = $rowA['latitude'];
        $lng = $rowA['longitude'];
        $origem = 'Estimada (Último Abastecimento)';
      }
    }

    // Se ainda nulo, usar localizações padrão espalhadas por zona (no interior para evitar o mar)
    if ($lat === null || $lng === null) {
      $z_nome = $r['zona_nome'] ?? '';
      if ($z_nome === 'Barlavento') {
          $lat = 37.25 + (random_int(0, 50) / 1000.0);
          $lng = -8.55 + (random_int(-100, 100) / 1000.0);
      } elseif ($z_nome === 'Sotavento') {
          $lat = 37.25 + (random_int(0, 50) / 1000.0);
          $lng = -7.60 + (random_int(-100, 100) / 1000.0);
      } else { // Centro ou Geral
          $lat = 37.20 + (random_int(0, 50) / 1000.0);
          $lng = -8.05 + (random_int(-100, 100) / 1000.0);
      }
      $origem = 'Padrão (Sem coordenadas)';
    }

    $response['viaturas'][] = [
      'id' => (int)$r['id'],
      'matricula' => $r['matricula'],
      'marca_modelo' => $r['marca_modelo'],
      'tipo' => $r['tipo'],
      'combustivel' => $r['combustivel'],
      'quilometragem' => (int)$r['quilometragem'],
      'estado' => $r['estado'],
      'latitude' => (float)$lat,
      'longitude' => (float)$lng,
      'data_localizacao' => $r['data_localizacao'],
      'origem_localizacao' => $origem,
      'zona_nome' => $r['zona_nome'] ?? 'Geral',
      'zona_cor' => $r['zona_cor'] ?? '#64748b',
      'base_nome' => $r['base_nome'] ?? 'Sem base atribuída'
    ];
  }
}

// 2. CARREGAR INFRAESTRUTURAS
$whereI = get_where_zona_id("i.zona_operacional_id", $filtros_zona_id);
if (is_operario()) {
  $whereI = "1=1"; // Operários vêem todas as ETAs e ETARs
}
$sqlI = "
  SELECT 
    i.id, i.nome, i.tipo, i.concelho, i.localidade, i.latitude, i.longitude,
    zo.nome AS zona_nome
  FROM infraestruturas i
  LEFT JOIN zonas_operacionais zo ON zo.id = i.zona_operacional_id
  WHERE i.ativo = 1 AND $whereI
";
$resI = mysqli_query($ligacao, $sqlI);
if ($resI) {
  while ($r = mysqli_fetch_assoc($resI)) {
    // Buscar ocorrências abertas
    $qOc = mysqli_query($ligacao, "SELECT COUNT(*) AS total FROM ocorrencias WHERE viatura_id IN (SELECT id FROM viaturas WHERE infraestrutura_id = {$r['id']}) AND estado IN ('aberta','em_analise')");
    $oc_abertas = $qOc ? (int)(mysqli_fetch_assoc($qOc)['total'] ?? 0) : 0;

    // Buscar ordens de serviço pendentes
    $qOr = mysqli_query($ligacao, "SELECT COUNT(*) AS total FROM ordens_servico WHERE infraestrutura_id = {$r['id']} AND estado IN ('rascunho','atribuida','aceite','em_deslocacao','em_execucao')");
    $ordens_pendentes = $qOr ? (int)(mysqli_fetch_assoc($qOr)['total'] ?? 0) : 0;

    if ($r['latitude'] !== null && $r['longitude'] !== null) {
      $response['infraestruturas'][] = [
        'id' => (int)$r['id'],
        'nome' => $r['nome'],
        'tipo' => $r['tipo'],
        'concelho' => $r['concelho'],
        'localidade' => $r['localidade'],
        'latitude' => (float)$r['latitude'],
        'longitude' => (float)$r['longitude'],
        'zona_nome' => $r['zona_nome'] ?? 'Geral',
        'ocorrencias_abertas' => $oc_abertas,
        'ordens_pendentes' => $ordens_pendentes
      ];
    }
  }
}

// Se for operário, ele não vê as restantes camadas administrativas (ocorrências, abastecimentos, etc.)
if (is_operario()) {
  echo json_encode($response, JSON_UNESCAPED_UNICODE);
  exit;
}

// 3. CARREGAR OCORRÊNCIAS
$whereOc = "o.estado IN ('aberta','em_analise')";
if ($filtros_zona_id !== null) {
  $whereOc .= " AND v.zona_operacional_id = $filtros_zona_id";
}
$sqlOc = "
  SELECT 
    o.id, o.codigo, o.titulo, o.tipo, o.gravidade, o.estado, o.latitude, o.longitude, o.criado_em,
    v.matricula, v.marca_modelo
  FROM ocorrencias o
  JOIN viaturas v ON v.id = o.viatura_id
  WHERE $whereOc
";
$resOc = mysqli_query($ligacao, $sqlOc);
if ($resOc) {
  while ($r = mysqli_fetch_assoc($resOc)) {
    if ($r['latitude'] !== null && $r['longitude'] !== null) {
      $response['ocorrencias'][] = [
        'id' => (int)$r['id'],
        'codigo' => $r['codigo'],
        'titulo' => $r['titulo'],
        'tipo' => $r['tipo'],
        'gravidade' => $r['gravidade'],
        'estado' => $r['estado'],
        'latitude' => (float)$r['latitude'],
        'longitude' => (float)$r['longitude'],
        'viatura' => $r['marca_modelo'] . " (" . $r['matricula'] . ")",
        'criado_em' => $r['criado_em']
      ];
    }
  }
}

// 4. CARREGAR ABASTECIMENTOS
$whereAb = "ab.estado <> 'anulado'";
if ($filtros_zona_id !== null) {
  $whereAb .= " AND v.zona_operacional_id = $filtros_zona_id";
}
$sqlAb = "
  SELECT 
    ab.id, ab.posto, ab.combustivel, ab.litros, ab.total, ab.data_abastecimento, ab.latitude, ab.longitude,
    v.matricula
  FROM abastecimentos ab
  JOIN viaturas v ON v.id = ab.viatura_id
  WHERE $whereAb AND ab.latitude IS NOT NULL
  ORDER BY ab.id DESC LIMIT 50
";
$resAb = mysqli_query($ligacao, $sqlAb);
if ($resAb) {
  while ($r = mysqli_fetch_assoc($resAb)) {
    $response['abastecimentos'][] = [
      'id' => (int)$r['id'],
      'posto' => $r['posto'] ?? 'Posto Desconhecido',
      'combustivel' => $r['combustivel'],
      'litros' => (float)$r['litros'],
      'total' => (float)$r['total'],
      'data_abastecimento' => $r['data_abastecimento'],
      'latitude' => (float)$r['latitude'],
      'longitude' => (float)$r['longitude'],
      'matricula' => $r['matricula']
    ];
  }
}

// 5. CARREGAR ORDENS DE SERVIÇO EM CURSO
$whereOr = "os.estado IN ('em_deslocacao','em_execucao')";
if ($filtros_zona_id !== null) {
  $whereOr .= " AND v.zona_operacional_id = $filtros_zona_id";
}
$sqlOr = "
  SELECT 
    os.id, os.codigo, os.titulo, os.estado, os.tipo,
    m.nome AS motorista_nome,
    v.matricula, v.marca_modelo,
    inf.nome AS local_nome, inf.latitude, inf.longitude
  FROM ordens_servico os
  JOIN viaturas v ON v.id = os.viatura_id
  LEFT JOIN motoristas m ON m.id = os.motorista_id
  LEFT JOIN infraestruturas inf ON inf.id = os.infraestrutura_id
  WHERE $whereOr AND inf.latitude IS NOT NULL
";
$resOr = mysqli_query($ligacao, $sqlOr);
if ($resOr) {
  while ($r = mysqli_fetch_assoc($resOr)) {
    $response['ordens'][] = [
      'id' => (int)$r['id'],
      'codigo' => $r['codigo'],
      'titulo' => $r['titulo'],
      'estado' => $r['estado'],
      'tipo' => $r['tipo'],
      'motorista' => $r['motorista_nome'] ?? 'Sem motorista',
      'viatura' => $r['marca_modelo'] . " (" . $r['matricula'] . ")",
      'local_nome' => $r['local_nome'],
      'latitude' => (float)$r['latitude'],
      'longitude' => (float)$r['longitude']
    ];
  }
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
mysqli_close($ligacao);
?>
