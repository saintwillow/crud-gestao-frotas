<?php
// inc/auth.php

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/** Base URL do projeto (ex: /crud-gestao-frotas) */
function base_url(): string {
  $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
  if (substr_count($base, '/') >= 2) {
    $base = rtrim(dirname($base), '/');
  }
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

function exigir_perfil(array $permitidos): void {
  exigir_login();
  $p = perfil_atual();
  $permitidos = array_map(fn($x) => strtolower(trim($x)), $permitidos);

  if (!in_array($p, $permitidos, true)) {
    http_response_code(403);
    echo '<div class="glass-card p-4 text-center text-muted">Acesso negado (403).</div>';
    exit;
  }
}

// atalhos
function exigir_admin(): void { exigir_perfil(['admin']); }
function exigir_gestor_ou_admin(): void { exigir_perfil(['admin','gestor']); }

/** Login (sem hash por enquanto) */
function login(mysqli $ligacao, string $username, string $senha): bool {
  $u = mysqli_real_escape_string($ligacao, trim($username));
  $s = mysqli_real_escape_string($ligacao, trim($senha));

  $sql = "SELECT id, nome, username, perfil
          FROM usuarios
          WHERE username='$u' AND senha='$s' AND ativo=1
          LIMIT 1";

  $res = mysqli_query($ligacao, $sql);
  if ($res && mysqli_num_rows($res) === 1) {
    $user = mysqli_fetch_assoc($res);

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_nome'] = $user['nome'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_perfil'] = $user['perfil'];

    return true;
  }
  return false;
}

function logout(): void {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params["path"], $params["domain"], $params["secure"], $params["httponly"]
    );
  }
  session_destroy();
  header("Location: " . base_url() . "/login.php");
  exit;
}
