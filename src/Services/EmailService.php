<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

class EmailService
{
    public function send(string $to, string $subject, string $body): bool
    {
        return $this->deliver($to, $subject, $body, false);
    }

    public function sendHtml(string $to, string $subject, string $html): bool
    {
        return $this->deliver($to, $subject, $html, true);
    }

    public function configured(): bool
    {
        $c = $this->resolve();
        return $c['host'] !== '' && $c['host'] !== 'smtp.example.com' && $c['user'] !== '' && $c['pass'] !== '';
    }

    private function deliver(string $to, string $subject, string $body, bool $html): bool
    {
        $c = $this->resolve();
        if ($c['host'] === '' || $c['host'] === 'smtp.example.com' || $c['user'] === '' || $c['pass'] === '') {
            return false;
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client("ssl://{$c['host']}:{$c['port']}", $errno, $errstr, 15);
        if (!$fp) return false;
        stream_set_timeout($fp, 15);

        if ($this->code($this->read($fp)) !== 220) { fclose($fp); return false; }
        if ($this->code($this->cmd($fp, 'EHLO wontia')) !== 250) { fclose($fp); return false; }
        if ($this->code($this->cmd($fp, 'AUTH LOGIN')) !== 334) { fclose($fp); return false; }
        if ($this->code($this->cmd($fp, base64_encode($c['user']))) !== 334) { fclose($fp); return false; }
        if ($this->code($this->cmd($fp, base64_encode($c['pass']))) !== 235) { fclose($fp); return false; }

        $fromAddr = preg_replace('/.*<([^>]+)>.*/', '$1', $c['from']);
        if (!filter_var($fromAddr, FILTER_VALIDATE_EMAIL)) $fromAddr = $c['user'];

        if ($this->code($this->cmd($fp, 'MAIL FROM:<' . $fromAddr . '>')) !== 250) { fclose($fp); return false; }
        $rcpt = $this->code($this->cmd($fp, 'RCPT TO:<' . $to . '>'));
        if ($rcpt !== 250 && $rcpt !== 251) { fclose($fp); return false; }
        if ($this->code($this->cmd($fp, 'DATA')) !== 354) { fclose($fp); return false; }

        $fromHeader = $c['from_name'] !== '' ? ('=?UTF-8?B?' . base64_encode($c['from_name']) . '?= <' . $fromAddr . '>') : $fromAddr;
        $ctype = $html ? 'text/html' : 'text/plain';
        $headers = "From: $fromHeader\r\nTo: $to\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
            . "MIME-Version: 1.0\r\nContent-Type: $ctype; charset=UTF-8\r\n";
        if ($html) {
            $headers .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $payload = rtrim(chunk_split(base64_encode($body), 76, "\r\n"));
        } else {
            $headers .= "\r\n";
            $payload = $body;
        }
        fwrite($fp, $headers . $payload . "\r\n.\r\n");
        $sent = $this->code($this->read($fp)) === 250;
        $this->cmd($fp, 'QUIT');
        fclose($fp);
        return $sent;
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
                $stmt = Database::instance()->prepare("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` IN ('mail_host','mail_port','mail_user','mail_pass','mail_from','mail_from_name','wwi.mail_host','wwi.mail_port','wwi.mail_user','wwi.mail_pass','wwi.mail_from','wwi.mail_from_name')");
                $stmt->execute();
                $map = [
                    'mail_host' => 'host', 'mail_port' => 'port', 'mail_user' => 'user', 'mail_pass' => 'pass', 'mail_from' => 'from', 'mail_from_name' => 'from_name',
                    'wwi.mail_host' => 'host', 'wwi.mail_port' => 'port', 'wwi.mail_user' => 'user', 'wwi.mail_pass' => 'pass', 'wwi.mail_from' => 'from', 'wwi.mail_from_name' => 'from_name',
                ];
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

    private function cmd($fp, string $cmd): string
    {
        fwrite($fp, $cmd . "\r\n");
        return $this->read($fp);
    }

    private function code(string $response): int
    {
        $lines = preg_split('/\r?\n/', trim($response));
        $last = end($lines);
        if ($last === false || $last === '') return 0;
        return (int)substr((string)$last, 0, 3);
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
