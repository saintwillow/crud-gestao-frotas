<?php
require_once __DIR__ . "/config.php";

function conectar($host, $user, $password, $bd){
    $conn = mysqli_connect($host, $user, $password, $bd);
    if (!$conn) {
        die("Erro ao ligar a bd! " . mysqli_connect_error());
    }
    mysqli_set_charset($conn, "utf8mb4");
    return $conn;
}

$ligacao = conectar($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
