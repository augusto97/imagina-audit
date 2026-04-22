<?php
/**
 * Envío de emails por SMTP
 * Compatible con hosting compartido (sin librerías externas)
 * Usa fsockopen para conectar directamente al servidor SMTP
 */

class Mailer {
    /**
     * Envía un email usando SMTP configurado en settings
     * Fallback a mail() si SMTP no está configurado
     */
    public static function send(string $to, string $subject, string $body): bool {
        // Obtener configuración SMTP de la DB
        $smtp = self::getSmtpConfig();

        // Si no hay SMTP configurado, intentar con mail()
        if (empty($smtp['host'])) {
            $headers = "Content-Type: text/plain; charset=UTF-8\r\n";
            if (!empty($smtp['from_email'])) {
                $headers .= "From: {$smtp['from_name']} <{$smtp['from_email']}>\r\n";
            }
            return @mail($to, $subject, $body, $headers);
        }

        // Enviar por SMTP
        try {
            return self::sendSmtp($smtp, $to, $subject, $body);
        } catch (Throwable $e) {
            Logger::error('Error enviando email SMTP: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene la configuración SMTP desde la tabla settings
     */
    private static function getSmtpConfig(): array {
        $config = [
            'host' => '',
            'port' => 587,
            'username' => '',
            'password' => '',
            'encryption' => 'tls', // tls, ssl, o vacío
            'from_email' => '',
            'from_name' => 'Imagina Audit',
        ];

        try {
            $db = Database::getInstance();
            $keys = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name'];
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $rows = $db->query("SELECT key, value FROM settings WHERE key IN ($placeholders)", $keys);

            foreach ($rows as $row) {
                $field = str_replace('smtp_', '', $row['key']);
                $config[$field] = $row['value'];
            }

            $config['port'] = (int) $config['port'] ?: 587;
        } catch (Throwable $e) {
            Logger::warning('Error leyendo config SMTP: ' . $e->getMessage());
        }

        return $config;
    }

    /**
     * Envía un email directamente por SMTP usando sockets
     */
    private static function sendSmtp(array $smtp, string $to, string $subject, string $body): bool {
        $host = $smtp['host'];
        $port = $smtp['port'];
        $username = $smtp['username'];
        $password = $smtp['password'];
        $encryption = $smtp['encryption'];
        $fromEmail = $smtp['from_email'] ?: $username;
        $fromName = $smtp['from_name'] ?: 'Imagina Audit';

        // Conectar al servidor SMTP
        $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
        $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);

        if (!$socket) {
            Logger::error("SMTP conexión fallida: $errstr ($errno)");
            return false;
        }

        // Leer saludo del servidor
        self::smtpRead($socket);

        // EHLO
        self::smtpWrite($socket, "EHLO " . gethostname());
        self::smtpRead($socket);

        // STARTTLS si es TLS
        if ($encryption === 'tls') {
            self::smtpWrite($socket, "STARTTLS");
            $response = self::smtpRead($socket);
            if (strpos($response, '220') !== 0) {
                fclose($socket);
                Logger::error('SMTP STARTTLS falló');
                return false;
            }

            // Habilitar TLS en el socket
            $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if (!$crypto) {
                fclose($socket);
                Logger::error('SMTP TLS crypto falló');
                return false;
            }

            // Re-enviar EHLO después de TLS
            self::smtpWrite($socket, "EHLO " . gethostname());
            self::smtpRead($socket);
        }

        // AUTH LOGIN
        if (!empty($username)) {
            self::smtpWrite($socket, "AUTH LOGIN");
            self::smtpRead($socket);

            self::smtpWrite($socket, base64_encode($username));
            self::smtpRead($socket);

            self::smtpWrite($socket, base64_encode($password));
            $authResponse = self::smtpRead($socket);

            if (strpos($authResponse, '235') === false) {
                fclose($socket);
                Logger::error('SMTP autenticación fallida: ' . trim($authResponse));
                return false;
            }
        }

        // MAIL FROM
        self::smtpWrite($socket, "MAIL FROM:<$fromEmail>");
        self::smtpRead($socket);

        // RCPT TO
        self::smtpWrite($socket, "RCPT TO:<$to>");
        self::smtpRead($socket);

        // DATA
        self::smtpWrite($socket, "DATA");
        self::smtpRead($socket);

        // Construir mensaje
        $message = "From: $fromName <$fromEmail>\r\n"
            . "To: $to\r\n"
            . "Subject: $subject\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Date: " . date('r') . "\r\n"
            . "\r\n"
            . $body . "\r\n"
            . ".";

        self::smtpWrite($socket, $message);
        $sendResponse = self::smtpRead($socket);

        // QUIT
        self::smtpWrite($socket, "QUIT");
        fclose($socket);

        $success = strpos($sendResponse, '250') !== false;
        if (!$success) {
            Logger::error('SMTP envío falló: ' . trim($sendResponse));
        }

        return $success;
    }

    /**
     * Envía un email de prueba para verificar la configuración
     */
    public static function sendTest(string $to): bool {
        return self::send(
            $to,
            Translator::t('email.test.subject'),
            Translator::t('email.test.body', ['date' => date('d/m/Y H:i:s')])
        );
    }

    private static function smtpWrite($socket, string $data): void {
        fwrite($socket, $data . "\r\n");
    }

    private static function smtpRead($socket): string {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            // Si el 4to carácter es espacio, es la última línea
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }
}
