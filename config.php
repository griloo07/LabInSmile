<?php
// Configuracao de ligacao a base de dados MySQL.

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'laboratorio');
define('DB_PORT', 3306);

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    die('Erro ao ligar a base de dados: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');

// Email do laboratório (destino para notificações de formulários)
define('LAB_EMAIL', 'labinsmile@gmail.com');

/**
 * Envia um email para o endereço do laboratório.
 * Retorna true em caso de sucesso, false caso contrário.
 */
function send_lab_email($subject, $body, $from_email = null) {
    $to = LAB_EMAIL;
    $from = $from_email ? $from_email : 'no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $headers = "From: {$from}\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/plain; charset=UTF-8\r\n";

    if (function_exists('mail')) {
        return @mail($to, $subject, $body, $headers);
    }

    return false;
}
?>
