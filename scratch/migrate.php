<?php
require_once __DIR__ . "/../inc/database.php";

echo "Iniciando migração...\n";

// Adicionar coluna infraestrutura_id à tabela usuarios se não existir
$res = mysqli_query($ligacao, "SHOW COLUMNS FROM usuarios LIKE 'infraestrutura_id'");
if (mysqli_num_rows($res) == 0) {
    $alter = mysqli_query($ligacao, "ALTER TABLE usuarios ADD COLUMN infraestrutura_id INT(11) NULL DEFAULT NULL AFTER motorista_id");
    if ($alter) {
        echo "Coluna infraestrutura_id adicionada com sucesso!\n";
    } else {
        echo "Erro ao adicionar coluna: " . mysqli_error($ligacao) . "\n";
    }
} else {
    echo "Coluna infraestrutura_id já existe na tabela usuarios.\n";
}

// Opcional: Adicionar chave estrangeira para infraestrutura_id
// Primeiro removemos se já existir (para evitar erros)
mysqli_query($ligacao, "ALTER TABLE usuarios DROP FOREIGN KEY fk_usuarios_infraestrutura");
$fk = mysqli_query($ligacao, "ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_infraestrutura FOREIGN KEY (infraestrutura_id) REFERENCES infraestruturas(id) ON DELETE SET NULL ON UPDATE CASCADE");
if ($fk) {
    echo "Chave estrangeira fk_usuarios_infraestrutura adicionada com sucesso!\n";
} else {
    echo "Erro ao adicionar chave estrangeira: " . mysqli_error($ligacao) . "\n";
}

// Atualizar o gestor Alberto Cunha para ter uma infraestrutura_id para testes (por exemplo, infraestrutura_id = 1)
$upd = mysqli_query($ligacao, "UPDATE usuarios SET infraestrutura_id = 1 WHERE username = 'gestor'");
if ($upd) {
    echo "Usuario gestor associado à infraestrutura 1.\n";
}

echo "Migração concluída!\n";
