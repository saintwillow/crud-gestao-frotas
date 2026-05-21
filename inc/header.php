<?php
// inc/header.php
if (!isset($active)) { $active = ''; }

// Base URL do projeto (ex: /crud-gestao-frotas)
$BASE_URL = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// Se estiver dentro de subpastas (ex.: /viaturas/index.php), sobe um nível
if (substr_count($BASE_URL, '/') >= 2) {
  $BASE_URL = rtrim(dirname($BASE_URL), '/');
}

$iniciais = '';
$nomeUser = $_SESSION['user_nome'] ?? '';
if ($nomeUser) {
  $parts = preg_split('/\s+/', trim($nomeUser));
  $iniciais = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
} else {
  $iniciais = 'GS';
}
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="<?php echo $BASE_URL; ?>/css/bootstrap_css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?php echo $BASE_URL; ?>/css/style_css/style.css">

  <title>AquaFleet</title>
</head>
<body>

<div class="app">

  <!-- Sidebar -->
  <aside class="sidebar d-flex flex-column">
    <div class="brand">
      <div class="brand-badge"><i class="bi bi-droplet-fill"></i></div>
      <div>
        <p class="brand-title mb-0">AquaFleet</p>
        <p class="brand-sub mb-0">Gestão de Frotas</p>
      </div>
    </div>

    <?php $perfil_nav = perfil_atual(); ?>
    <nav class="nav flex-column nav-side">

    <?php if ($perfil_nav === 'operario'): ?>

      <!-- MENU DO OPERÁRIO -->
      <a class="nav-link <?php echo ($active==='operario_painel') ? 'active' : ''; ?>" href="<?php echo $BASE_URL; ?>/operario/index.php">
        <span class="nav-ico"><i class="bi bi-person-circle"></i></span>
        <span>O meu painel</span>
      </a>

      <a class="nav-link <?php echo ($active==='operario_abastecimentos') ? 'active' : ''; ?>" href="<?php echo $BASE_URL; ?>/operario/abastecimentos.php">
        <span class="nav-ico"><i class="bi bi-fuel-pump-fill"></i></span>
        <span>Os meus abastecimentos</span>
      </a>

      <a class="nav-link <?php echo ($active==='mapa_frota') ? 'active' : ''; ?>" href="<?php echo $BASE_URL; ?>/mapa-frota/index.php">
        <span class="nav-ico"><i class="bi bi-map-fill"></i></span>
        <span>Mapa da Frota</span>
      </a>

      <a class="nav-link <?php echo ($active==='config') ? 'active' : ''; ?>" href="<?php echo $BASE_URL; ?>/configuracoes/index.php">
        <span class="nav-ico"><i class="bi bi-gear-fill"></i></span>
        <span>Configurações</span>
      </a>

    <?php else: ?>

      <!-- MENU DO GESTOR / ADMIN -->
      <a class="nav-link <?php echo ($active==='dashboard') ? 'active' : ''; ?>" href="<?php echo $BASE_URL; ?>/index.php">
        <span class="nav-ico"><i class="bi bi-grid-1x2-fill"></i></span>
        <span>Painel</span>
      </a>

      <a class="nav-link <?php echo ($active==='viaturas') ? 'active' : ''; ?>" href="<?php echo $BASE_URL; ?>/viaturas/index.php">
        <span class="nav-ico"><i class="bi bi-car-front-fill"></i></span>
        <span>Veículos</span>
      </a>

      <a class="nav-link <?php echo ($active==='manutencao') ? 'active' : ''; ?>" href="<?php echo $BASE_URL; ?>/manutencao/index.php">
        <span class="nav-ico"><i class="bi bi-tools"></i></span>
        <span>Manutenção</span>
      </a>

      <a class="nav-link <?php echo ($active==='abastecimentos') ? 'active' : ''; ?>" href="<?php echo $BASE_URL; ?>/abastecimentos/index.php">
        <span class="nav-ico"><i class="bi bi-fuel-pump-fill"></i></span>
        <span>Abastecimento</span>
      </a>

      <a class="nav-link <?php echo ($active==='mapa_abastecimentos') ? 'active' : ''; ?>" href="<?php echo $BASE_URL; ?>/abastecimentos/mapa.php">
        <span class="nav-ico"><i class="bi bi-geo-alt-fill"></i></span>
        <span>Mapa Abastecimento</span>
      </a>

      <a class="nav-link <?php echo ($active==='mapa_frota') ? 'active' : ''; ?>" href="<?php echo $BASE_URL; ?>/mapa-frota/index.php">
        <span class="nav-ico"><i class="bi bi-map-fill"></i></span>
        <span>Mapa da Frota</span>
      </a>

      <a class="nav-link <?php echo ($active==='motoristas') ? 'active' : ''; ?>" href="<?php echo $BASE_URL; ?>/motoristas/index.php">
        <span class="nav-ico"><i class="bi bi-person-fill"></i></span>
        <span>Motoristas</span>
      </a>

      <a class="nav-link <?php echo ($active==='config') ? 'active' : ''; ?>" href="<?php echo $BASE_URL; ?>/configuracoes/index.php">
        <span class="nav-ico"><i class="bi bi-gear-fill"></i></span>
        <span>Configurações</span>
      </a>

    <?php endif; ?>

    </nav>

    <div class="sidebar-user mt-auto pt-3">
      <?php
        $perfil_badge = perfil_atual();
        $badge_class  = match($perfil_badge) {
          'admin'   => 'badge-perfil badge-admin',
          'gestor'  => 'badge-perfil badge-gestor',
          default   => 'badge-perfil badge-operario',
        };
        $badge_icon = match($perfil_badge) {
          'admin'  => 'bi-shield-fill',
          'gestor' => 'bi-briefcase-fill',
          default  => 'bi-person-fill',
        };
        $badge_label = match($perfil_badge) {
          'admin'  => 'Administrador',
          'gestor' => 'Gestor de Frotas',
          default  => 'Operário',
        };
      ?>
      <div class="small" style="opacity:.65; font-size:11px; text-transform:uppercase; letter-spacing:.06em;">Utilizador</div>
      <div class="fw-semibold mt-1"><?php echo htmlspecialchars($_SESSION['user_nome'] ?? ''); ?></div>
      <div class="mt-2">
        <span class="<?php echo $badge_class; ?>">
          <i class="bi <?php echo $badge_icon; ?>"></i>
          <?php echo $badge_label; ?>
        </span>
      </div>
      <a class="btn btn-outline-light w-100 mt-3" href="<?php echo $BASE_URL; ?>/logout.php">Sair</a>
    </div>
  </aside>

  <!-- Main -->
  <div class="mainwrap">
    <div class="topbar">
      <div class="avatar"><?php echo htmlspecialchars($iniciais); ?></div>
    </div>

    <div class="content">