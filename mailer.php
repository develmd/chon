<?php
// mailer.php - простой SMTP класс без сторонних библиотек
class SimpleSMTP {
    private $host;
    private $port;
    private $username;
    private $password;
    private $encryption;
    private $socket;
    
    public function __construct($host, $port, $username, $password, $encryption = 'ssl') {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->encryption = $encryption;
    }
    
    public function send($to, $subject, $message, $from_email, $from_name) {
        $this->connect();
        $this->auth();
        $this->sendMail($to, $subject, $message, $from_email, $from_name);
        $this->disconnect();
        return true;
    }
    
    private function connect() {
        $address = ($this->encryption == 'ssl' ? 'ssl://' : 'tcp://') . $this->host;
        $this->socket = fsockopen($address, $this->port, $errno, $errstr, 30);
        if (!$this->socket) {
            throw new Exception("SMTP connection failed: $errstr");
        }
        $this->getResponse();
        fputs($this->socket, "EHLO " . gethostname() . "\r\n");
        $this->getResponse();
    }
    
    private function auth() {
        fputs($this->socket, "AUTH LOGIN\r\n");
        $this->getResponse();
        fputs($this->socket, base64_encode($this->username) . "\r\n");
        $this->getResponse();
        fputs($this->socket, base64_encode($this->password) . "\r\n");
        $this->getResponse();
    }
    
    private function sendMail($to, $subject, $message, $from_email, $from_name) {
        fputs($this->socket, "MAIL FROM: <{$from_email}>\r\n");
        $this->getResponse();
        fputs($this->socket, "RCPT TO: <{$to}>\r\n");
        $this->getResponse();
        fputs($this->socket, "DATA\r\n");
        $this->getResponse();
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/plain; charset=utf-8\r\n";
        $headers .= "From: {$from_name} <{$from_email}>\r\n";
        $headers .= "To: <{$to}>\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        
        fputs($this->socket, $headers . "\r\n" . $message . "\r\n.\r\n");
        $this->getResponse();
    }
    
    private function disconnect() {
        fputs($this->socket, "QUIT\r\n");
        fclose($this->socket);
    }
    
    private function getResponse() {
        $response = '';
        while ($str = fgets($this->socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == ' ') break;
        }
        return $response;
    }
}
?>
