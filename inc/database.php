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

if (!function_exists('obter_viatura_atual_motorista')) {
    function obter_viatura_atual_motorista($ligacao, $motorista_id) {
        $motorista_id = (int)$motorista_id;
        if ($motorista_id <= 0) return null;
        $stmt = mysqli_prepare($ligacao,
            "SELECT a.id AS atribuicao_id, a.viatura_id, a.km_inicio, v.matricula, v.marca_modelo, v.quilometragem, v.estado AS viatura_estado
             FROM atribuicoes a
             JOIN viaturas v ON v.id = a.viatura_id
             WHERE a.motorista_id = ? AND a.estado = 'aberta' AND a.data_fim IS NULL
             LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "i", $motorista_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        mysqli_stmt_close($stmt);
        return $row;
    }
}

if (!function_exists('recalcular_estado_viatura')) {
    function recalcular_estado_viatura($ligacao, $viatura_id) {
        $viatura_id = (int)$viatura_id;
        if ($viatura_id <= 0) return false;

        $res = mysqli_query($ligacao, "SELECT estado FROM viaturas WHERE id = $viatura_id LIMIT 1");
        if (!$res) return false;
        $v = mysqli_fetch_assoc($res);
        if (!$v) return false;

        if ($v['estado'] === 'Inativo') {
            return 'Inativo';
        }

        // Verificar manutenção ativa ('Em andamento')
        $resM = mysqli_query($ligacao, "SELECT id FROM manutencoes WHERE viatura_id = $viatura_id AND status = 'Em andamento' LIMIT 1");
        if ($resM && mysqli_num_rows($resM) > 0) {
            $novo_estado = 'Em Manutenção';
        } else {
            // Verificar atribuição ativa
            $resA = mysqli_query($ligacao, "SELECT id FROM atribuicoes WHERE viatura_id = $viatura_id AND estado = 'aberta' LIMIT 1");
            if ($resA && mysqli_num_rows($resA) > 0) {
                $novo_estado = 'Atribuída';
            } else {
                $novo_estado = 'Disponível';
            }
        }

        if ($v['estado'] !== $novo_estado) {
            $stmt = mysqli_prepare($ligacao, "UPDATE viaturas SET estado = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "si", $novo_estado, $viatura_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        return $novo_estado;
    }
}
