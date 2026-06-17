<?php
// Configuração base dados
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'laboratorio');
define('DB_PORT', 3306);

// Ligar base dados
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Validar a ligação
if ($conn->connect_error) {
    die('Erro ao ligar a base de dados: ' . $conn->connect_error);
}

// Definir o charset
$conn->set_charset('utf8mb4');

// Email de destino
define('LAB_EMAIL', 'labinsmile@gmail.com');

// Carregar autoload Composer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Enviar email SMTP
function send_smtp($host, $port, $username, $password, $from, $to, $subject, $body, $secure = 'tls') {
    $timeout = 30;
    $remote = ($secure === 'ssl') ? 'ssl://' . $host : $host;
    $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    
    // Abrir socket rede
    $fp = @stream_socket_client($remote . ':' . $port, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        error_log("SMTP connect failed: {$errno} {$errstr}");
        return false;
    }

    // Obter resposta SMTP
    $get = function() use ($fp) {
        $data = '';
        while ($str = fgets($fp, 515)) {
            $data .= $str;
            if (isset($str[3]) && $str[3] === ' ') break;
        }
        return $data;
    };

    // Enviar comando SMTP
    $send = function($cmd) use ($fp) {
        fwrite($fp, $cmd . "\r\n");
    };

    // Validar handshake inicial
    $res = $get();
    if (strpos($res, '220') !== 0) { fclose($fp); error_log('SMTP handshake failed: ' . $res); return false; }

    // Enviar comando EHLO
    $hostname = gethostname() ?: 'localhost';
    $send('EHLO ' . $hostname);
    $res = $get();

    // Iniciar ligação TLS
    if ($secure === 'tls') {
        $send('STARTTLS');
        $res = $get();
        if (strpos($res, '220') !== 0) { fclose($fp); error_log('SMTP STARTTLS failed: ' . $res); return false; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp); error_log('Unable to enable TLS on SMTP socket'); return false;
        }
        $send('EHLO ' . $hostname);
        $res = $get();
    }

    // Autenticar no SMTP
    if ($username) {
        $send('AUTH LOGIN');
        $res = $get();
        $send(base64_encode($username));
        $res = $get();
        $send(base64_encode($password));
        $res = $get();
        if (strpos($res, '235') !== 0) { fclose($fp); error_log('SMTP auth failed: ' . $res); return false; }
    }

    // Definir remetente
    $send('MAIL FROM:<' . $from . '>');
    $res = $get();
    if (strpos($res, '250') !== 0) { fclose($fp); error_log('MAIL FROM failed: ' . $res); return false; }

    // Definir destinatário
    $send('RCPT TO:<' . $to . '>');
    $res = $get();
    if (strpos($res, '250') !== 0 && strpos($res, '251') !== 0) { fclose($fp); error_log('RCPT TO failed: ' . $res); return false; }

    // Enviar dados email
    $send('DATA');
    $res = $get();
    if (strpos($res, '354') !== 0) { fclose($fp); error_log('DATA command failed: ' . $res); return false; }

    // Enviar cabeçalhos corpo
    $headers = "From: {$from}\r\nReply-To: {$from}\r\nTo: {$to}\r\nSubject: {$subject}\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n";
    $send($headers . $body . "\r\n.");
    $res = $get();
    
    // Fechar ligação SMTP
    $send('QUIT');
    fclose($fp);
    if (strpos($res, '250') === 0) return true;
    error_log('SMTP send failed: ' . $res);
    return false;
}

// Notificar o laboratório
function send_lab_email($subject, $body, $from_email = null) {
    $to = LAB_EMAIL;
    $from = $from_email ? $from_email : 'no-reply@' . ($_SERVER['SERVER_NAME'] ?? 'localhost');
    
    // Enviar com PHPMailer
    if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';

            // Configurar envio SMTP
            if (defined('SMTP_HOST') && SMTP_HOST) {
                $mail->isSMTP();
                $mail->Host = SMTP_HOST;
                $smtp_user = defined('SMTP_USER') ? constant('SMTP_USER') : null;
                $smtp_pass = defined('SMTP_PASS') ? constant('SMTP_PASS') : null;
                $mail->SMTPAuth = !empty($smtp_user) && !empty($smtp_pass);
                if ($mail->SMTPAuth) {
                    $mail->Username = $smtp_user;
                    $mail->Password = $smtp_pass;
                }
                $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
                $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
            }

            // Configurar detalhes mensagem
            $mail->setFrom($from, 'Lab in Smile');
            $mail->addAddress($to);
            $mail->addReplyTo($from);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);

            return $mail->send();
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log('PHPMailer error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
        }
    }

    // Envio SMTP nativo
    if (defined('SMTP_HOST') && SMTP_HOST) {
        $smtp_user = defined('SMTP_USER') ? constant('SMTP_USER') : null;
        $smtp_pass = defined('SMTP_PASS') ? constant('SMTP_PASS') : null;
        $smtp_port = defined('SMTP_PORT') ? SMTP_PORT : (defined('SMTP_SECURE') && SMTP_SECURE === 'ssl' ? 465 : 587);
        $smtp_secure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
        $smtp_ok = send_smtp(SMTP_HOST, $smtp_port, $smtp_user, $smtp_pass, $from, $to, $subject, $body, $smtp_secure);
        if ($smtp_ok) return true;
        error_log('Native SMTP fallback failed for ' . SMTP_HOST);
    }

    // Usar função nativa
    if (function_exists('mail')) {
        $headers = "From: {$from}\r\nReply-To: {$from}\r\nMIME-Version: 1.0\r\nContent-type: text/plain; charset=UTF-8\r\n";
        $ok = mail($to, $subject, $body, $headers);
        if (!$ok) {
            error_log("mail() failed sending to {$to} (subject: {$subject})");
        }
        return $ok;
    }

    // Gravar em ficheiro
    $logdir = __DIR__ . '/emails';
    if (!is_dir($logdir)) @mkdir($logdir, 0755, true);
    $filename = $logdir . '/email_' . date('Ymd_His') . '.txt';
    $content = "To: {$to}\nFrom: {$from}\nSubject: {$subject}\n\n{$body}\n";
    file_put_contents($filename, $content);
    error_log('Email logged to file (no mail transport available): ' . $filename);
    return true;
}
?>
