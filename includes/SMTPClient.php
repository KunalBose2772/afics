<?php

class SMTPClient {
    private $host;
    private $port;
    private $encryption;
    private $username;
    private $password;
    private $timeout = 30;
    private $debug = false;
    private $socket;
    private $logs = [];

    public function __construct($host, $port, $encryption, $username, $password) {
        $this->host = $host;
        $this->port = $port;
        $this->encryption = $encryption;
        $this->username = $username;
        $this->password = $password;
    }

    public function send($to, $subject, $body, $fromName = 'Documantraa', $attachments = [], $fromEmail = null) {
        try {
            $this->connect();
            $this->auth();
            
            $boundary = md5(uniqid(time()));
            $senderEmail = $fromEmail ?: $this->username;
            
            $headers = [
                'Date: ' . date('r'),
                'From: ' . "=?UTF-8?B?" . base64_encode($fromName) . "?= <" . $senderEmail . ">",
                'To: ' . $to,
                'Subject: ' . "=?UTF-8?B?" . base64_encode($subject) . "?=",
                'MIME-Version: 1.0',
                'Content-Type: multipart/mixed; boundary="' . $boundary . '"'
            ];
            
            $this->sendCommand('MAIL FROM: <' . $this->username . '>');
            $this->sendCommand('RCPT TO: <' . $to . '>');
            $this->sendCommand('DATA');
            
            // Construct the Body
            $message = implode("\r\n", $headers) . "\r\n\r\n";
            
            // 1. Valid HTML Body
            $message .= "--" . $boundary . "\r\n";
            $message .= "Content-Type: text/html; charset=UTF-8\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $message .= chunk_split(base64_encode($body)) . "\r\n";
            
            // 2. Attachments
            if (!empty($attachments)) {
                foreach ($attachments as $filePath) {
                    if (file_exists($filePath)) {
                        $fileName = basename($filePath);
                        $fileData = chunk_split(base64_encode(file_get_contents($filePath)));
                        
                        $message .= "--" . $boundary . "\r\n";
                        $message .= "Content-Type: application/octet-stream; name=\"" . $fileName . "\"\r\n";
                        $message .= "Content-Transfer-Encoding: base64\r\n";
                        $message .= "Content-Disposition: attachment; filename=\"" . $fileName . "\"\r\n\r\n";
                        $message .= $fileData . "\r\n";
                    }
                }
            }
            
            $message .= "--" . $boundary . "--\r\n.";
            
            $this->sendCommand($message);
            
            $this->sendCommand('QUIT');
            fclose($this->socket);
            
            return true;
        } catch (Exception $e) {
            $this->logs[] = "Error: " . $e->getMessage();
            if ($this->socket) fclose($this->socket);
            throw $e;
        }
    }

    private function connect() {
        $protocol = '';
        if ($this->encryption === 'ssl') {
            $protocol = 'ssl://';
        } else {
            $protocol = 'tcp://';
        }

        $remote = $protocol . $this->host . ':' . $this->port;
        $this->socket = fsockopen($remote, $this->timeout, $errno, $errstr, $this->timeout);

        if (!$this->socket) {
            throw new Exception("Connection failed: $errstr ($errno)");
        }
        
        $this->getResponse();
        $this->sendCommand('EHLO ' . $_SERVER['SERVER_NAME']);
        
        if ($this->encryption === 'tls') {
            $this->sendCommand('STARTTLS');
            if (stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) === false) {
                 throw new Exception("TLS Encryption failed");
            }
            $this->sendCommand('EHLO ' . $_SERVER['SERVER_NAME']);
        }
    }

    private function auth() {
        $this->sendCommand('AUTH LOGIN');
        $this->sendCommand(base64_encode($this->username));
        $this->sendCommand(base64_encode($this->password));
    }

    private function sendCommand($cmd) {
        $this->logs[] = "> " . substr($cmd, 0, 100); // Log command (truncated)
        fputs($this->socket, $cmd . "\r\n");
        return $this->getResponse();
    }

    private function getResponse() {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        $this->logs[] = "< " . $response;
        
        // Basic error checking (4xx or 5xx are errors)
        if (substr($response, 0, 1) >= 4) {
            throw new Exception("SMTP Error: " . $response);
        }
        
        return $response;
    }
    
    public function getLogs() {
        return $this->logs;
    }
}
?>
