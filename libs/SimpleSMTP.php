<?php
class SimpleSMTP {
    private $host;
    private $port;
    private $user;
    private $pass;
    private $socket;
    
    public function __construct($host, $port, $user, $pass) {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->pass = $pass;
    }
    
    public function send($to, $subject, $message, $fromEmail, $fromName = "Admin") {
        $this->socket = fsockopen("tcp://" . $this->host, $this->port, $errno, $errstr, 15);
        if (!$this->socket) return false;
        
        $this->getServerResponse(); // Read greeting
        
        $this->sendCommand("EHLO localhost");
        $this->sendCommand("STARTTLS");
        stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
        $this->sendCommand("EHLO localhost");
        
        $this->sendCommand("AUTH LOGIN");
        $this->sendCommand(base64_encode($this->user));
        $this->sendCommand(base64_encode($this->pass));
        
        $this->sendCommand("MAIL FROM: <$fromEmail>");
        $this->sendCommand("RCPT TO: <$to>");
        $this->sendCommand("DATA");
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8\r\n";
        $headers .= "From: $fromName <$fromEmail>\r\n";
        $headers .= "Subject: $subject\r\n";
        
        $body = $headers . "\r\n" . $message . "\r\n.";
        $this->sendCommand($body);
        $this->sendCommand("QUIT");
        
        fclose($this->socket);
        return true;
    }
    
    private function sendCommand($cmd) {
        fwrite($this->socket, $cmd . "\r\n");
        return $this->getServerResponse();
    }
    
    private function getServerResponse() {
        $response = "";
        while ($str = fgets($this->socket, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") break;
        }
        return $response;
    }
}
?>
