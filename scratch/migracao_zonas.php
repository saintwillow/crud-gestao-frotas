<?php
require_once "inc/database.php";

echo "Iniciando migração de banco de dados...\n";

// 1. Criar tabela zonas_operacionais
$q1 = "CREATE TABLE IF NOT EXISTS zonas_operacionais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    cor VARCHAR(7) DEFAULT '#3b82f6',
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($ligacao, $q1)) {
    echo "Tabela zonas_operacionais criada ou já existente.\n";
} else {
    echo "Erro zonas_operacionais: " . mysqli_error($ligacao) . "\n";
}

// Inserir zonas se não existirem
$res = mysqli_query($ligacao, "SELECT COUNT(*) as total FROM zonas_operacionais");
$row = mysqli_fetch_assoc($res);
if ($row['total'] == 0) {
    mysqli_query($ligacao, "INSERT INTO zonas_operacionais (nome, descricao, cor) VALUES
    ('Barlavento', 'Zona Oeste do Algarve', '#3b82f6'),
    ('Centro', 'Zona Central do Algarve', '#10b981'),
    ('Sotavento', 'Zona Leste do Algarve', '#f59e0b')");
    echo "Zonas padrão inseridas.\n";
}

// 2. Criar tabela frotas
$qFrota = "CREATE TABLE IF NOT EXISTS frotas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NULL,
    zona_operacional_id INT NULL,
    gestor_usuario_id INT UNSIGNED NULL,
    ativo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (zona_operacional_id) REFERENCES zonas_operacionais(id) ON DELETE SET NULL,
    FOREIGN KEY (gestor_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($ligacao, $qFrota)) {
    echo "Tabela frotas criada ou já existente.\n";
} else {
    echo "Erro frotas: " . mysqli_error($ligacao) . "\n";
}

// 3. Adicionar campos em usuarios
$cols_usuarios = [
    "nivel_gestao" => "VARCHAR(20) NULL DEFAULT NULL",
    "zona_operacional_id" => "INT NULL DEFAULT NULL"
];
foreach ($cols_usuarios as $col => $def) {
    $res = mysqli_query($ligacao, "SHOW COLUMNS FROM usuarios LIKE '$col'");
    if (mysqli_num_rows($res) == 0) {
        $alter = "ALTER TABLE usuarios ADD COLUMN $col $def";
        if (mysqli_query($ligacao, $alter)) {
            echo "Coluna $col adicionada em usuarios.\n";
            if ($col === 'zona_operacional_id') {
                mysqli_query($ligacao, "ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_zona FOREIGN KEY (zona_operacional_id) REFERENCES zonas_operacionais(id) ON DELETE SET NULL ON UPDATE CASCADE");
            }
        } else {
            echo "Erro ao adicionar $col em usuarios: " . mysqli_error($ligacao) . "\n";
        }
    }
}

// 4. Adicionar campos em infraestruturas
$res = mysqli_query($ligacao, "SHOW COLUMNS FROM infraestruturas LIKE 'zona_operacional_id'");
if (mysqli_num_rows($res) == 0) {
    $alter = "ALTER TABLE infraestruturas ADD COLUMN zona_operacional_id INT NULL DEFAULT NULL";
    if (mysqli_query($ligacao, $alter)) {
        mysqli_query($ligacao, "ALTER TABLE infraestruturas ADD CONSTRAINT fk_infraestruturas_zona FOREIGN KEY (zona_operacional_id) REFERENCES zonas_operacionais(id) ON DELETE SET NULL ON UPDATE CASCADE");
        echo "Coluna zona_operacional_id adicionada em infraestruturas.\n";
    }
}

// 5. Adicionar campos em viaturas
$cols_viaturas = [
    "zona_operacional_id" => "INT NULL DEFAULT NULL",
    "frota_id" => "INT NULL DEFAULT NULL",
    "lat_atual" => "DECIMAL(10, 8) NULL DEFAULT NULL",
    "lng_atual" => "DECIMAL(11, 8) NULL DEFAULT NULL",
    "data_localizacao" => "DATETIME NULL DEFAULT NULL",
    "origem_localizacao" => "VARCHAR(50) NULL DEFAULT NULL"
];
foreach ($cols_viaturas as $col => $def) {
    $res = mysqli_query($ligacao, "SHOW COLUMNS FROM viaturas LIKE '$col'");
    if (mysqli_num_rows($res) == 0) {
        $alter = "ALTER TABLE viaturas ADD COLUMN $col $def";
        if (mysqli_query($ligacao, $alter)) {
            echo "Coluna $col adicionada em viaturas.\n";
            if ($col === 'zona_operacional_id') {
                mysqli_query($ligacao, "ALTER TABLE viaturas ADD CONSTRAINT fk_viaturas_zona FOREIGN KEY (zona_operacional_id) REFERENCES zonas_operacionais(id) ON DELETE SET NULL ON UPDATE CASCADE");
            }
            if ($col === 'frota_id') {
                mysqli_query($ligacao, "ALTER TABLE viaturas ADD CONSTRAINT fk_viaturas_frota FOREIGN KEY (frota_id) REFERENCES frotas(id) ON DELETE SET NULL ON UPDATE CASCADE");
            }
        } else {
            echo "Erro ao adicionar $col em viaturas: " . mysqli_error($ligacao) . "\n";
        }
    }
}

// 6. Adicionar campos em motoristas
$res = mysqli_query($ligacao, "SHOW COLUMNS FROM motoristas LIKE 'zona_operacional_id'");
if (mysqli_num_rows($res) == 0) {
    $alter = "ALTER TABLE motoristas ADD COLUMN zona_operacional_id INT NULL DEFAULT NULL";
    if (mysqli_query($ligacao, $alter)) {
        mysqli_query($ligacao, "ALTER TABLE motoristas ADD CONSTRAINT fk_motoristas_zona FOREIGN KEY (zona_operacional_id) REFERENCES zonas_operacionais(id) ON DELETE SET NULL ON UPDATE CASCADE");
        echo "Coluna zona_operacional_id adicionada em motoristas.\n";
    }
}

// 7. Criar tabela localizacoes_viaturas
$qLV = "CREATE TABLE IF NOT EXISTS localizacoes_viaturas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    viatura_id INT UNSIGNED NOT NULL,
    motorista_id INT UNSIGNED NULL,
    servico_id INT UNSIGNED NULL,
    origem VARCHAR(50) NOT NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    observacoes TEXT NULL,
    FOREIGN KEY (viatura_id) REFERENCES viaturas(id) ON DELETE CASCADE,
    FOREIGN KEY (motorista_id) REFERENCES motoristas(id) ON DELETE SET NULL,
    FOREIGN KEY (servico_id) REFERENCES servicos_operacionais(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($ligacao, $qLV)) {
    echo "Tabela localizacoes_viaturas criada ou já existente.\n";
} else {
    echo "Erro localizacoes_viaturas: " . mysqli_error($ligacao) . "\n";
}

// Configurar o gestor padrão (id 2) como gestor da zona 1 (Barlavento), nível 'zona'
mysqli_query($ligacao, "UPDATE usuarios SET nivel_gestao = 'zona', zona_operacional_id = 1 WHERE id = 2");
echo "Gestor 'gestor' atualizado para a zona 1 (Barlavento).\n";

// Configurar o administrador padrão (id 1) como nível 'global'
mysqli_query($ligacao, "UPDATE usuarios SET nivel_gestao = 'global' WHERE id = 1");

echo "Migração concluída com sucesso!\n";
?>
