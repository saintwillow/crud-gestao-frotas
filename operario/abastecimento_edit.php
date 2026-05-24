<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_login();

if (is_gestor_ou_admin()) {
  header("Location: " . base_url() . "/abastecimentos/index.php");
  exit;
}

header("Location: " . base_url() . "/operario/abastecimentos.php");
exit;