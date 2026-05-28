<?php
require_once __DIR__ . "/../inc/database.php";

echo "=== INFRAESTRUTURAS ===\n";
$res = mysqli_query($ligacao, "SELECT id, nome, tipo FROM infraestruturas");
while ($r = mysqli_fetch_assoc($res)) {
    echo "ID: {$r['id']} | Nome: {$r['nome']} | Tipo: {$r['tipo']}\n";
}
