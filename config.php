<?php
// Configuração de ligação à base de dados MySQL

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'laboratorio');
define('DB_PORT', 3306);

// Criar ligação
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Verificar ligação
if ($conn->connect_error) {
    die('Erro ao ligar à base de dados: ' . $conn->connect_error);
}

// Definir charset para UTF-8
$conn->set_charset('utf8mb4');

// Função auxiliar para executar queries
function db_query($sql, $params = []) {
    global $conn;
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return ['error' => 'Erro na preparação da query: ' . $conn->error];
    }
    
    if (!empty($params)) {
        $types = '';
        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        return ['error' => 'Erro na execução da query: ' . $stmt->error];
    }
    
    return $stmt;
}
?>
