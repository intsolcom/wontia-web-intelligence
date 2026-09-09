<?php
namespace App\Services;

use App\Core\Config;

class EmailService
{
    public function send(string $to, string $subject, string $body): bool
    {
        $host = Config::get('MAIL_HOST', '');
        $port = (int)Config::get('MAIL_PORT', 465);
        $user = Config::get('MAIL_USER', '');
        $pass = Config::get('MAIL_PASS', '');
        $from = Config::get('MAIL_FROM', 'no-reply@wontia.com');
        if ($host === '' || $host === 'smtp.example.com' || $user === '' || $pass === '') {
            return false;
        }
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client("ssl://$host:$port", $errno, $errstr, 15);
        if (!$fp) return false;
        stream_set_timeout($fp, 15);
        $this->read($fp);
        $this->cmd($fp, "EHLO wontia");
        $this->cmd($fp, 'AUTH LOGIN');
        $this->cmd($fp, base64_encode($user));
        $this->cmd($fp, base64_encode($pass));
        $this->cmd($fp, 'MAIL FROM:<' . preg_replace('/.*<([^>]+)>.*/', '$1', $from) . '>');
        $this->cmd($fp, 'RCPT TO:<' . $to . '>');
        $this->cmd($fp, 'DATA');
        $headers = "From: $from\r\nTo: $to\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n";
        fwrite($fp, $headers . $body . "\r\n.\r\n");
        $this->read($fp);
        $this->cmd($fp, 'QUIT');
        fclose($fp);
        return true;
    }

    private function cmd($fp, string $cmd): void
    {
        fwrite($fp, $cmd . "\r\n");
        $this->read($fp);
    }

    private function read($fp): string
    {
        $out = '';
        while (($line = fgets($fp, 512)) !== false) {
            $out .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $out;
    }
}
