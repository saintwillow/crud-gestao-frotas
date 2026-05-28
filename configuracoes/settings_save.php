<?php
require_once __DIR__ . "/../inc/auth.php";
exigir_gestor_ou_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_empresa = trim($_POST['nome_empresa'] ?? 'AquaFleet Gestão de Frotas');
    $moeda = trim($_POST['moeda'] ?? '€');
    $alerta_carta_dias = isset($_POST['alerta_carta_dias']) ? (int)$_POST['alerta_carta_dias'] : 60;
    $mapa_estilo = trim($_POST['mapa_estilo'] ?? 'dark');

    // Validation
    if ($nome_empresa === '') {
        $nome_empresa = 'AquaFleet Gestão de Frotas';
    }
    if ($moeda === '') {
        $moeda = '€';
    }
    if ($alerta_carta_dias <= 0) {
        $alerta_carta_dias = 60;
    }

    $configData = [
        'nome_empresa' => $nome_empresa,
        'moeda' => $moeda,
        'alerta_carta_dias' => $alerta_carta_dias,
        'mapa_estilo' => $mapa_estilo
    ];

    $file = __DIR__ . '/settings.json';
    if (file_put_contents($file, json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        header("Location: index.php?msg=salvo");
        exit;
    } else {
        header("Location: index.php?err=Erro ao gravar ficheiro de configurações.");
        exit;
    }
} else {
    header("Location: index.php");
    exit;
}
