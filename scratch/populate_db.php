<?php
require_once __DIR__ . '/../inc/database.php';

echo "Populando a base de dados...\n";

// Password is 'password' for everyone (hashed using password_hash('password', PASSWORD_DEFAULT))
// For simplicity, let's just generate it once
$senha = password_hash('password', PASSWORD_DEFAULT);

// 1. Criar Gestores (se não existirem)
$gestores = [
    ['nome' => 'Gestor Centro', 'username' => 'gestor_centro', 'perfil' => 'gestor', 'nivel_gestao' => 'zona', 'zona_id' => 2],
    ['nome' => 'Gestor Sotavento', 'username' => 'gestor_sotavento', 'perfil' => 'gestor', 'nivel_gestao' => 'zona', 'zona_id' => 3],
];

foreach ($gestores as $g) {
    $q = mysqli_query($ligacao, "SELECT id FROM usuarios WHERE username = '{$g['username']}'");
    if (mysqli_num_rows($q) == 0) {
        $stmt = mysqli_prepare($ligacao, "INSERT INTO usuarios (nome, username, senha, perfil, nivel_gestao, zona_operacional_id, ativo) VALUES (?, ?, ?, ?, ?, ?, 1)");
        mysqli_stmt_bind_param($stmt, "sssssi", $g['nome'], $g['username'], $senha, $g['perfil'], $g['nivel_gestao'], $g['zona_id']);
        mysqli_stmt_execute($stmt);
        echo "Criado utilizador gestor: {$g['nome']}\n";
    }
}

// 2. Criar Viaturas (se não existirem)
$viaturas = [
    ['matricula' => 'AA-11-BB', 'marca_modelo' => 'Renault Kangoo', 'ano' => 2020, 'combustivel' => 'Diesel', 'estado' => 'Disponível', 'zona_id' => 1],
    ['matricula' => 'CC-22-DD', 'marca_modelo' => 'Peugeot Partner', 'ano' => 2021, 'combustivel' => 'Diesel', 'estado' => 'Disponível', 'zona_id' => 2],
    ['matricula' => 'EE-33-FF', 'marca_modelo' => 'Citroen Berlingo', 'ano' => 2019, 'combustivel' => 'Diesel', 'estado' => 'Disponível', 'zona_id' => 3],
];

foreach ($viaturas as $v) {
    $q = mysqli_query($ligacao, "SELECT id FROM viaturas WHERE matricula = '{$v['matricula']}'");
    if (mysqli_num_rows($q) == 0) {
        $stmt = mysqli_prepare($ligacao, "INSERT INTO viaturas (matricula, marca_modelo, ano, combustivel, estado, zona_operacional_id, quilometragem) VALUES (?, ?, ?, ?, ?, ?, 0)");
        mysqli_stmt_bind_param($stmt, "ssissi", $v['matricula'], $v['marca_modelo'], $v['ano'], $v['combustivel'], $v['estado'], $v['zona_id']);
        mysqli_stmt_execute($stmt);
        echo "Criada viatura: {$v['matricula']}\n";
    }
}

// 3. Criar Motoristas/Operarios (se não existirem)
$motoristas = [
    ['nome' => 'Operário Barlavento', 'email' => 'operario_barla@aqua.pt', 'telefone' => '910000001', 'zona_id' => 1],
    ['nome' => 'Operário Centro', 'email' => 'operario_centro@aqua.pt', 'telefone' => '910000002', 'zona_id' => 2],
    ['nome' => 'Operário Sotavento', 'email' => 'operario_sota@aqua.pt', 'telefone' => '910000003', 'zona_id' => 3],
];

foreach ($motoristas as $m) {
    // Inserir motorista
    $q = mysqli_query($ligacao, "SELECT id FROM motoristas WHERE email = '{$m['email']}'");
    $motorista_id = 0;
    if (mysqli_num_rows($q) == 0) {
        $stmt = mysqli_prepare($ligacao, "INSERT INTO motoristas (nome, email, telefone, status, zona_operacional_id) VALUES (?, ?, ?, 'Ativo', ?)");
        mysqli_stmt_bind_param($stmt, "sssi", $m['nome'], $m['email'], $m['telefone'], $m['zona_id']);
        mysqli_stmt_execute($stmt);
        $motorista_id = mysqli_insert_id($ligacao);
        echo "Criado motorista: {$m['nome']}\n";
    } else {
        $row = mysqli_fetch_assoc($q);
        $motorista_id = $row['id'];
    }

    // Inserir usuario associado ao motorista
    $username = explode('@', $m['email'])[0]; // username ex: operario_barla
    $qu = mysqli_query($ligacao, "SELECT id FROM usuarios WHERE username = '{$username}'");
    if (mysqli_num_rows($qu) == 0 && $motorista_id > 0) {
        $stmt = mysqli_prepare($ligacao, "INSERT INTO usuarios (nome, username, senha, perfil, nivel_gestao, zona_operacional_id, ativo, motorista_id) VALUES (?, ?, ?, 'operario', 'nenhum', ?, 1, ?)");
        mysqli_stmt_bind_param($stmt, "ssssi", $m['nome'], $username, $senha, $m['zona_id'], $motorista_id);
        mysqli_stmt_execute($stmt);
        echo "Criado utilizador operario: {$username}\n";
    }
}

echo "Concluido.\n";
