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
  if (!esta_logado()) { header("Location: " . base_url() . "/login.php"); exit; }
}

function perfil_atual(): string {
  return strtolower(trim($_SESSION['user_perfil'] ?? 'operario'));
}

function is_operario(): bool { return perfil_atual() === 'operario'; }
function is_gestor():  bool { return perfil_atual() === 'gestor'; }
function is_admin():   bool { return perfil_atual() === 'admin'; }
function is_gestor_ou_admin(): bool { return in_array(perfil_atual(), ['admin','gestor'], true); }

function exigir_perfil(array $permitidos): void {
  exigir_login();
  $p = perfil_atual();
  $permitidos = array_map(fn($x) => strtolower(trim($x)), $permitidos);
  if (!in_array($p, $permitidos, true)) {
    http_response_code(403);
    // Redirecionar operário para o seu painel em vez de mostrar 403
    if ($p === 'operario') {
      header("Location: " . base_url() . "/operario/index.php");
      exit;
    }
    echo '<div class="glass-card p-4 text-center text-muted">Acesso negado (403).</div>';
    exit;
  }
}

function exigir_admin(): void           { exigir_perfil(['admin']); }
function exigir_gestor_ou_admin(): void { exigir_perfil(['admin','gestor']); }
function exigir_nao_operario(): void    { exigir_perfil(['admin','gestor']); }

// Retorna o motorista_id do operário logado (null se não associado)
function motorista_id_sessao(): ?int {
  $mid = $_SESSION['user_motorista_id'] ?? null;
  return $mid !== null ? (int)$mid : null;
}

// Retorna a viatura_id do operário logado (null se não associado)
function viatura_id_sessao(): ?int {
  $vid = $_SESSION['user_viatura_id'] ?? null;
  return $vid !== null ? (int)$vid : null;
}

function login(mysqli $ligacao, string $username, string $senha): bool {
  $stmt = mysqli_prepare($ligacao,
    "SELECT u.id, u.nome, u.username, u.perfil, u.senha, u.motorista_id,
            m.viatura_id
     FROM usuarios u
     LEFT JOIN motoristas m ON m.id = u.motorista_id
     WHERE u.username=? AND u.ativo=1 LIMIT 1"
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

  $_SESSION['user_id']          = (int)$user['id'];
  $_SESSION['user_nome']        = $user['nome'];
  $_SESSION['user_username']    = $user['username'];
  $_SESSION['user_perfil']      = $user['perfil'];
  $_SESSION['user_motorista_id']= $user['motorista_id'] !== null ? (int)$user['motorista_id'] : null;
  $_SESSION['user_viatura_id']  = $user['viatura_id']   !== null ? (int)$user['viatura_id']   : null;

  return true;
}

function logout(): void {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time()-42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
  }
  session_destroy();
  header("Location: " . base_url() . "/login.php");
  exit;
}
