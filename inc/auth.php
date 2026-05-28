<?php
if (session_status() === PHP_SESSION_NONE) session_start();

function base_url(): string {
  $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
  if (substr_count($base, '/') >= 2) $base = rtrim(dirname($base), '/');
  return $base;
}

function esta_logado(): bool {
  return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

function exigir_login(): void {
  if (!esta_logado()) {
    header("Location: " . base_url() . "/login.php");
    exit;
  }
}

function perfil_atual(): string {
  return strtolower(trim($_SESSION['user_perfil'] ?? 'operario'));
}

function is_operario(): bool { return perfil_atual() === 'operario'; }
function is_gestor():  bool { return perfil_atual() === 'gestor'; }
function is_admin():   bool { return perfil_atual() === 'admin'; }

function is_gestor_ou_admin(): bool {
  return in_array(perfil_atual(), ['admin','gestor'], true);
}

function exigir_perfil(array $permitidos): void {
  exigir_login();

  $p = perfil_atual();
  $permitidos = array_map(fn($x) => strtolower(trim($x)), $permitidos);

  if (!in_array($p, $permitidos, true)) {
    http_response_code(403);

    if ($p === 'operario') {
      header("Location: " . base_url() . "/operario/index.php");
      exit;
    }

    echo '<div class="glass-card p-4 text-center text-muted">Acesso negado (403).</div>';
    exit;
  }
}

function exigir_admin(): void {
  exigir_perfil(['admin']);
}

function exigir_gestor_ou_admin(): void {
  exigir_perfil(['admin','gestor']);
}

function exigir_nao_operario(): void {
  exigir_perfil(['admin','gestor']);
}

function usuario_id_sessao(): ?int {
  $id = $_SESSION['user_id'] ?? null;
  return $id !== null ? (int)$id : null;
}

function colaborador_id_sessao(): ?int {
  $cid = $_SESSION['user_colaborador_id'] ?? null;
  return $cid !== null ? (int)$cid : null;
}

function motorista_id_sessao(): ?int {
  $mid = $_SESSION['user_motorista_id'] ?? null;
  return $mid !== null ? (int)$mid : null;
}

/*
  Mantemos esta função por compatibilidade,
  mas agora ela deve ser usada apenas como fallback.
  A lógica profissional deve buscar a viatura em atribuicoes.
*/
function viatura_id_sessao(): ?int {
  $vid = $_SESSION['user_viatura_id'] ?? null;
  return $vid !== null ? (int)$vid : null;
}

function infraestrutura_id_sessao(): ?int {
  $infra_id = $_SESSION['user_infraestrutura_id'] ?? null;
  return $infra_id !== null ? (int)$infra_id : null;
}

function is_gestor_global(): bool {
  return perfil_atual() === 'gestor' && (($_SESSION['user_nivel_gestao'] ?? '') === 'global');
}

function is_gestor_zona(): bool {
  return perfil_atual() === 'gestor' && (($_SESSION['user_nivel_gestao'] ?? '') === 'zona');
}

function zona_id_sessao(): ?int {
  $zid = $_SESSION['user_zona_operacional_id'] ?? null;
  return $zid !== null ? (int)$zid : null;
}

// Filtros SQL baseados em Zona Operacional
function sql_filtro_zona_viatura(string $alias = 'v'): string {
  if (is_admin() || is_gestor_global()) return "";
  $zid = zona_id_sessao();
  if ($zid !== null) {
    return " AND {$alias}.zona_operacional_id = {$zid}";
  }
  if (is_operario()) {
    $vid = viatura_id_sessao() ?? 0;
    return " AND {$alias}.id = {$vid}";
  }
  return " AND 1=0";
}

function sql_filtro_zona_motorista(string $alias = 'm'): string {
  if (is_admin() || is_gestor_global()) return "";
  $zid = zona_id_sessao();
  if ($zid !== null) {
    return " AND {$alias}.zona_operacional_id = {$zid}";
  }
  if (is_operario()) {
    $mid = motorista_id_sessao() ?? 0;
    return " AND {$alias}.id = {$mid}";
  }
  return " AND 1=0";
}

function sql_filtro_zona_ocorrencia(string $alias = 'o'): string {
  if (is_admin() || is_gestor_global()) return "";
  $zid = zona_id_sessao();
  if ($zid !== null) {
    return " AND EXISTS (SELECT 1 FROM viaturas v_f WHERE v_f.id = {$alias}.viatura_id AND v_f.zona_operacional_id = {$zid})";
  }
  if (is_operario()) {
    $mid = motorista_id_sessao() ?? 0;
    return " AND {$alias}.motorista_id = {$mid}";
  }
  return " AND 1=0";
}

function sql_filtro_zona_abastecimento(string $alias = 'ab'): string {
  if (is_admin() || is_gestor_global()) return "";
  $zid = zona_id_sessao();
  if ($zid !== null) {
    return " AND EXISTS (SELECT 1 FROM viaturas v_f WHERE v_f.id = {$alias}.viatura_id AND v_f.zona_operacional_id = {$zid})";
  }
  if (is_operario()) {
    $mid = motorista_id_sessao() ?? 0;
    return " AND {$alias}.motorista_id = {$mid}";
  }
  return " AND 1=0";
}

function sql_filtro_zona_servico(string $alias = 's'): string {
  if (is_admin() || is_gestor_global()) return "";
  $zid = zona_id_sessao();
  if ($zid !== null) {
    return " AND EXISTS (SELECT 1 FROM viaturas v_f WHERE v_f.id = {$alias}.viatura_id AND v_f.zona_operacional_id = {$zid})";
  }
  if (is_operario()) {
    $mid = motorista_id_sessao() ?? 0;
    return " AND {$alias}.motorista_id = {$mid}";
  }
  return " AND 1=0";
}

function sql_filtro_zona_manutencao(string $alias = 'ma'): string {
  if (is_admin() || is_gestor_global()) return "";
  $zid = zona_id_sessao();
  if ($zid !== null) {
    return " AND EXISTS (SELECT 1 FROM viaturas v_f WHERE v_f.id = {$alias}.viatura_id AND v_f.zona_operacional_id = {$zid})";
  }
  return " AND 1=0";
}

function sql_filtro_zona_infraestrutura(string $alias = 'inf'): string {
  if (is_admin() || is_gestor_global()) return "";
  $zid = zona_id_sessao();
  if ($zid !== null) {
    return " AND {$alias}.zona_operacional_id = {$zid}";
  }
  return " AND 1=0";
}

function sql_filtro_viatura_gestor(string $prefix = 'v'): string {
  return sql_filtro_zona_viatura($prefix);
}

// Exigir permissão com base em Zona (Proteção de URL direta)
function exigir_permissao_zona($ligacao, string $tabela, int $registo_id) {
  if (is_admin() || is_gestor_global()) return;
  
  if (is_operario()) {
    // Operário não pode ver detalhes de gestão
    http_response_code(403);
    echo '<div style="font-family: sans-serif; text-align: center; margin-top: 50px;"><h2>Acesso Negado (403)</h2><p>Operários não têm permissão para ver este tipo de registo.</p><a href="' . base_url() . '/operario/index.php">Voltar</a></div>';
    exit;
  }

  $zid = zona_id_sessao();
  if ($zid === null) {
    http_response_code(403);
    echo '<div style="font-family: sans-serif; text-align: center; margin-top: 50px;"><h2>Acesso Negado (403)</h2><p>Não tem uma zona operacional associada.</p><a href="' . base_url() . '">Voltar ao Painel</a></div>';
    exit;
  }
  
  $registo_id = (int)$registo_id;
  $permitido = false;
  
  switch ($tabela) {
    case 'viaturas':
      $q = "SELECT zona_operacional_id FROM viaturas WHERE id = $registo_id";
      break;
    case 'motoristas':
      $q = "SELECT zona_operacional_id FROM motoristas WHERE id = $registo_id";
      break;
    case 'infraestruturas':
      $q = "SELECT zona_operacional_id FROM infraestruturas WHERE id = $registo_id";
      break;
    case 'ocorrencias':
      $q = "SELECT o.id, o.motorista_id, v.zona_operacional_id FROM ocorrencias o JOIN viaturas v ON v.id = o.viatura_id WHERE o.id = $registo_id";
      break;
    case 'abastecimentos':
      $q = "SELECT ab.id, ab.motorista_id, v.zona_operacional_id FROM abastecimentos ab JOIN viaturas v ON v.id = ab.viatura_id WHERE ab.id = $registo_id";
      break;
    case 'servicos_operacionais':
      $q = "SELECT s.id, s.motorista_id, v.zona_operacional_id FROM servicos_operacionais s JOIN viaturas v ON v.id = s.viatura_id WHERE s.id = $registo_id";
      break;
    case 'manutencoes':
      $q = "SELECT m.id, v.zona_operacional_id FROM manutencoes m JOIN viaturas v ON v.id = m.viatura_id WHERE m.id = $registo_id";
      break;
    case 'pedidos_manutencao':
      $q = "SELECT p.id, v.zona_operacional_id FROM pedidos_manutencao p JOIN viaturas v ON v.id = p.viatura_id WHERE p.id = $registo_id";
      break;
    case 'ordens_servico':
      $q = "SELECT os.id, v.zona_operacional_id FROM ordens_servico os JOIN viaturas v ON v.id = os.viatura_id WHERE os.id = $registo_id";
      break;
    default:
      $q = "";
  }
  
  if ($q) {
    $res = mysqli_query($ligacao, $q);
    if ($res && $row = mysqli_fetch_assoc($res)) {
      if ($row['zona_operacional_id'] !== null && (int)$row['zona_operacional_id'] === $zid) {
        $permitido = true;
      }
    }
  }
  
  if (!$permitido) {
    http_response_code(403);
    echo '<div style="font-family: sans-serif; text-align: center; margin-top: 50px;"><h2>Acesso Negado (403)</h2><p>Não tem permissão para aceder a este registo noutra zona operacional.</p><a href="' . base_url() . '">Voltar ao Painel</a></div>';
    exit;
  }
}

function pode_ver_viatura($ligacao, $id) { exigir_permissao_zona($ligacao, 'viaturas', $id); }
function pode_ver_motorista($ligacao, $id) { exigir_permissao_zona($ligacao, 'motoristas', $id); }
function pode_ver_ocorrencia($ligacao, $id) { exigir_permissao_zona($ligacao, 'ocorrencias', $id); }
function pode_ver_abastecimento($ligacao, $id) { exigir_permissao_zona($ligacao, 'abastecimentos', $id); }
function pode_ver_servico($ligacao, $id) { exigir_permissao_zona($ligacao, 'servicos_operacionais', $id); }
function pode_ver_infraestrutura($ligacao, $id) { exigir_permissao_zona($ligacao, 'infraestruturas', $id); }
function pode_ver_manutencao($ligacao, $id) { exigir_permissao_zona($ligacao, 'manutencoes', $id); }
function pode_ver_pedido_manutencao($ligacao, $id) { exigir_permissao_zona($ligacao, 'pedidos_manutencao', $id); }
function pode_ver_ordem($ligacao, $id) { exigir_permissao_zona($ligacao, 'ordens_servico', $id); }


/*
  Nova função central:
  retorna a atribuição aberta do motorista logado.
*/
function atribuicao_aberta_motorista(mysqli $ligacao, int $motorista_id): ?array {
  $stmt = mysqli_prepare($ligacao,
    "SELECT
        a.id AS atribuicao_id,
        a.viatura_id,
        a.motorista_id,
        a.colaborador_id,
        a.km_inicio,
        a.data_inicio,
        v.matricula,
        v.marca_modelo,
        v.tipo,
        v.combustivel,
        v.quilometragem,
        v.estado AS viatura_estado
     FROM atribuicoes a
     JOIN viaturas v ON v.id = a.viatura_id
     WHERE a.motorista_id = ?
       AND a.estado = 'aberta'
       AND a.data_fim IS NULL
     ORDER BY a.data_inicio DESC, a.id DESC
     LIMIT 1"
  );

  mysqli_stmt_bind_param($stmt, "i", $motorista_id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $row = $res ? mysqli_fetch_assoc($res) : null;
  mysqli_stmt_close($stmt);

  return $row ?: null;
}

function login(mysqli $ligacao, string $username, string $senha): bool {
  $stmt = mysqli_prepare($ligacao,
    "SELECT
        u.id,
        u.nome,
        u.username,
        u.perfil,
        u.senha,
        u.motorista_id,
        u.colaborador_id,
        u.infraestrutura_id,
        u.nivel_gestao,
        u.zona_operacional_id
     FROM usuarios u
     WHERE u.username = ?
       AND u.ativo = 1
     LIMIT 1"
  );

  mysqli_stmt_bind_param($stmt, "s", $username);
  mysqli_stmt_execute($stmt);
  $res  = mysqli_stmt_get_result($stmt);
  $user = $res ? mysqli_fetch_assoc($res) : null;
  mysqli_stmt_close($stmt);

  if (!$user) return false;

  $hash = $user['senha'];

  if (str_starts_with($hash, '$2y$')) {
    $ok = password_verify($senha, $hash);
  } else {
    $ok = ($senha === $hash);

    if ($ok) {
      $novoHash = password_hash($senha, PASSWORD_BCRYPT);
      $upd = mysqli_prepare($ligacao, "UPDATE usuarios SET senha=? WHERE id=?");
      mysqli_stmt_bind_param($upd, "si", $novoHash, $user['id']);
      mysqli_stmt_execute($upd);
      mysqli_stmt_close($upd);
    }
  }

  if (!$ok) return false;

  $_SESSION['user_id']             = (int)$user['id'];
  $_SESSION['user_nome']           = $user['nome'];
  $_SESSION['user_username']       = $user['username'];
  $_SESSION['user_perfil']         = $user['perfil'];
  $_SESSION['user_motorista_id']   = $user['motorista_id'] !== null ? (int)$user['motorista_id'] : null;
  $_SESSION['user_colaborador_id'] = $user['colaborador_id'] !== null ? (int)$user['colaborador_id'] : null;
  $_SESSION['user_infraestrutura_id'] = $user['infraestrutura_id'] !== null ? (int)$user['infraestrutura_id'] : null;
  $_SESSION['user_nivel_gestao']   = $user['nivel_gestao']; // 'global' ou 'zona'
  $_SESSION['user_zona_operacional_id'] = $user['zona_operacional_id'] !== null ? (int)$user['zona_operacional_id'] : null;

  /*
    Compatibilidade:
    se for operário com motorista, tentamos descobrir a viatura ativa
    através de atribuicoes e guardar também na sessão.
  */
  $_SESSION['user_viatura_id'] = null;

  if ($_SESSION['user_motorista_id']) {
    $atr = atribuicao_aberta_motorista($ligacao, (int)$_SESSION['user_motorista_id']);
    if ($atr && !empty($atr['viatura_id'])) {
      $_SESSION['user_viatura_id'] = (int)$atr['viatura_id'];
    }
    
    // Herdando zona do motorista caso não esteja definida no usuário
    if (empty($_SESSION['user_zona_operacional_id'])) {
      $stmtZone = mysqli_prepare($ligacao, "SELECT zona_operacional_id FROM motoristas WHERE id = ?");
      mysqli_stmt_bind_param($stmtZone, "i", $_SESSION['user_motorista_id']);
      mysqli_stmt_execute($stmtZone);
      $resZone = mysqli_stmt_get_result($stmtZone);
      if ($resZone && $mRow = mysqli_fetch_assoc($resZone)) {
        $_SESSION['user_zona_operacional_id'] = $mRow['zona_operacional_id'] !== null ? (int)$mRow['zona_operacional_id'] : null;
      }
      mysqli_stmt_close($stmtZone);
    }
  }

  return true;
}

function logout(): void {
  $_SESSION = [];

  if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(
      session_name(),
      '',
      time() - 42000,
      $p["path"],
      $p["domain"],
      $p["secure"],
      $p["httponly"]
    );
  }

  session_destroy();
  header("Location: " . base_url() . "/login.php");
  exit;
}

function get_configuracoes_sistema(): array {
  $file = __DIR__ . '/../configuracoes/settings.json';
  $defaults = [
    'nome_empresa' => 'AquaFleet Gestão de Frotas',
    'moeda' => '€',
    'alerta_carta_dias' => 60,
    'mapa_estilo' => 'dark'
  ];
  if (file_exists($file)) {
    $json = json_decode(file_get_contents($file), true);
    if (is_array($json)) {
      return array_merge($defaults, $json);
    }
  }
  return $defaults;
}