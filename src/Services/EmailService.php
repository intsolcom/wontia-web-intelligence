<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

class EmailService
{
    public function send(string $to, string $subject, string $body): bool
    {
        $c = $this->resolve();
        if ($c['host'] === '' || $c['host'] === 'smtp.example.com' || $c['user'] === '' || $c['pass'] === '') {
            return false;
        }
        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client("ssl://{$c['host']}:{$c['port']}", $errno, $errstr, 15);
        if (!$fp) return false;
        stream_set_timeout($fp, 15);
        $this->read($fp);
        $this->cmd($fp, "EHLO wontia");
        $this->cmd($fp, 'AUTH LOGIN');
        $this->cmd($fp, base64_encode($c['user']));
        $this->cmd($fp, base64_encode($c['pass']));
        $fromAddr = preg_replace('/.*<([^>]+)>.*/', '$1', $c['from']);
        if (!filter_var($fromAddr, FILTER_VALIDATE_EMAIL)) $fromAddr = $c['user'];
        $this->cmd($fp, 'MAIL FROM:<' . $fromAddr . '>');
        $this->cmd($fp, 'RCPT TO:<' . $to . '>');
        $this->cmd($fp, 'DATA');
        $fromHeader = $c['from_name'] !== '' ? ('=?UTF-8?B?' . base64_encode($c['from_name']) . '?= <' . $fromAddr . '>') : $fromAddr;
        $headers = "From: $fromHeader\r\nTo: $to\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n";
        fwrite($fp, $headers . $body . "\r\n.\r\n");
        $this->read($fp);
        $this->cmd($fp, 'QUIT');
        fclose($fp);
        return true;
    }

    public function configured(): bool
    {
        $c = $this->resolve();
        return $c['host'] !== '' && $c['host'] !== 'smtp.example.com' && $c['user'] !== '' && $c['pass'] !== '';
    }

    private function resolve(): array
    {
        $c = [
            'host' => (string)Config::get('MAIL_HOST', ''),
            'port' => (int)Config::get('MAIL_PORT', 465),
            'user' => (string)Config::get('MAIL_USER', ''),
            'pass' => (string)Config::get('MAIL_PASS', ''),
            'from' => (string)Config::get('MAIL_FROM', 'no-reply@wontia.com'),
            'from_name' => (string)Config::get('MAIL_FROM_NAME', 'Wontia'),
        ];
        if ($c['host'] === '' || $c['user'] === '' || $c['pass'] === '') {
            try {
                $stmt = Database::instance()->prepare("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` IN ('mail_host','mail_port','mail_user','mail_pass','mail_from','mail_from_name')");
                $stmt->execute();
                $map = ['mail_host' => 'host', 'mail_port' => 'port', 'mail_user' => 'user', 'mail_pass' => 'pass', 'mail_from' => 'from', 'mail_from_name' => 'from_name'];
                foreach ($stmt->fetchAll() as $row) {
                    if (isset($map[$row['key']]) && (string)$row['value'] !== '') $c[$map[$row['key']]] = $row['value'];
                }
            } catch (\Throwable $e) {
            }
        }
        $c['port'] = (int)$c['port'] ?: 465;
        if ($c['from'] === '') $c['from'] = 'no-reply@wontia.com';
        return $c;
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
