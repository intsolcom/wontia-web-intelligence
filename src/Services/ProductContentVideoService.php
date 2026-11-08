<?php
namespace App\Services;

use App\Core\Config;

/**
 * ProductContentVideoService — video oficial sin hotlinks.
 *
 * Descarga a un sandbox privado (storage/_content_lab) y, si YouTube está
 * conectado, sube el archivo a la cuenta SONIKA. Nunca inserta la URL del
 * fabricante como video del producto.
 */
class ProductContentVideoService
{
    public function status(): array
    {
        $ytDlp = trim((string)@shell_exec('command -v yt-dlp 2>/dev/null'));
        $ffmpeg = trim((string)@shell_exec('command -v ffmpeg 2>/dev/null'));
        $youtube = (new YoutubeService())->status();
        $cookies = $this->cookiesPath();
        return [
            'ok' => true,
            'yt_dlp' => $ytDlp !== '',
            'ffmpeg' => $ffmpeg !== '',
            'cookies_configured' => $cookies !== '',
            'youtube' => $youtube,
        ];
    }

    public function prepare(array $job, array $video, bool $rightsConfirmed, string $by = 'admin'): array
    {
        if (!$rightsConfirmed) return ['ok' => false, 'message' => 'Confirma que SONIKA tiene derechos de uso del video.'];
        $provider = (string)($video['provider'] ?? '');
        $url = (string)($video['url'] ?? '');
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return ['ok' => false, 'message' => 'URL de video inválida.'];
        $dir = $this->sandboxDir((int)$job['id']);
        if (!is_dir($dir) && !@mkdir($dir, 0777, true)) return ['ok' => false, 'message' => 'No se pudo crear el sandbox de video.'];
        $target = $dir . '/video.mp4';
        if ($provider === 'file') {
            $result = $this->downloadDirect($url, $target);
            if (empty($result['ok'])) return $result;
        } else {
            $result = $this->downloadWithYtDlp($url, $dir);
            if (empty($result['ok'])) return $result;
            $target = (string)$result['file'];
        }
        $size = is_file($target) ? (int)filesize($target) : 0;
        if ($size < 10240) return ['ok' => false, 'message' => 'El video descargado está vacío o es demasiado pequeño.'];
        if ($size > 536870912) {
            @unlink($target);
            return ['ok' => false, 'message' => 'El video supera el límite de 500 MB.'];
        }
        $relative = ltrim(str_replace('\\', '/', substr($target, strlen($this->root()))), '/');
        return [
            'ok' => true,
            'status' => 'sandbox',
            'file' => $relative,
            'size' => $size,
            'message' => 'Video descargado al sandbox privado. Ya puedes subirlo a YouTube si la cuenta está conectada.',
        ];
    }

    public function uploadToYoutube(array $job, bool $rightsConfirmed, string $by = 'admin'): array
    {
        if (!$rightsConfirmed) return ['ok' => false, 'message' => 'Confirma los derechos antes de subir el video.'];
        $file = $this->resolveFile((string)($job['video_file'] ?? ''));
        if ($file === '') return ['ok' => false, 'message' => 'Primero descarga el video al sandbox.'];
        $title = trim((string)($job['title'] ?? 'Producto SONIKA'));
        $summary = '';
        $json = $job['generated_json'] ?? [];
        if (is_array($json)) $summary = trim((string)($json['summary'] ?? ''));
        if ($summary === '') $summary = 'Descripción y características de ' . $title . ' en SONIKA.';
        $live = (string)($job['live_url'] ?? '');
        if ($live === '') {
            $base = '';
            try {
                $row = \App\Core\Database::instance()->query('SELECT domain, name FROM sites WHERE id = @site_id LIMIT 1')->fetch();
                $domain = trim((string)($row['domain'] ?? ''));
                if ($domain === '') {
                    $name = trim((string)($row['name'] ?? ''));
                    if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $name)) $domain = $name;
                }
                if ($domain !== '') $base = 'https://' . preg_replace('~^https?://~i', '', rtrim($domain, '/'));
            } catch (\Throwable $e) {
            }
            if ($base === '') $base = rtrim((string)Config::get('APP_URL', 'https://sonika.com.co'), '/');
            $live = $base . '/producto/' . rawurlencode((string)($job['handle'] ?? ''));
        }
        $description = $title . "\n\n" . $summary . "\n\nMás información: " . $live . "\nFuente oficial: " . (string)($job['source_url'] ?? '') . "\n\nVideo publicado por SONIKA con autorización de uso.";
        $tags = ['SONIKA', (string)($job['vendor'] ?? ''), (string)($job['product_type'] ?? ''), $title, (string)($job['handle'] ?? '')];
        $result = (new YoutubeService())->upload($file, $title, $description, $tags, 'public');
        if (!empty($result['ok'])) {
            $result['message'] = 'Video subido a YouTube y disponible en la cuenta SONIKA.';
        }
        return $result;
    }

    private function downloadDirect(string $url, string $target): array
    {
        $tmp = $target . '.part';
        $fh = @fopen($tmp, 'wb');
        if (!$fh) return ['ok' => false, 'message' => 'No se pudo abrir el archivo temporal del video.'];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_MAXFILESIZE => 536870912,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124 Safari/537.36',
        ]);
        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fh);
        if (!$ok || $code >= 400 || !is_file($tmp) || filesize($tmp) < 10240) {
            @unlink($tmp);
            return ['ok' => false, 'message' => 'No se pudo descargar el video (' . ($err ?: ('HTTP ' . $code)) . ').'];
        }
        @rename($tmp, $target);
        return ['ok' => true, 'file' => $target];
    }

    private function downloadWithYtDlp(string $url, string $dir): array
    {
        $ytDlp = trim((string)@shell_exec('command -v yt-dlp 2>/dev/null'));
        if ($ytDlp === '') return ['ok' => false, 'message' => 'yt-dlp no está instalado en el servidor. Añádelo al contenedor para descargar videos de YouTube/Vimeo.'];
        $ffmpeg = trim((string)@shell_exec('command -v ffmpeg 2>/dev/null'));
        $format = $ffmpeg !== ''
            ? 'bv*[height<=1080][ext=mp4]+ba[ext=m4a]/b[ext=mp4]/b'
            : 'b[ext=mp4]/b';
        $out = $dir . '/video.%(ext)s';
        $cookies = $this->cookiesPath();
        $cmd = 'timeout 300 ' . escapeshellarg($ytDlp)
            . ' --no-playlist --no-warnings --restrict-filenames'
            . ' --extractor-args ' . escapeshellarg('youtube:player_client=android,web_safari')
            . ($cookies !== '' ? ' --cookies ' . escapeshellarg($cookies) : '')
            . ' -f ' . escapeshellarg($format)
            . ($ffmpeg !== '' ? ' --merge-output-format mp4' : '')
            . ' -o ' . escapeshellarg($out)
            . ' ' . escapeshellarg($url)
            . ' 2>&1';
        $lines = [];
        $code = 0;
        @exec($cmd, $lines, $code);
        if ($code !== 0) {
            return ['ok' => false, 'message' => 'yt-dlp no pudo descargar el video: ' . mb_substr(implode(' ', array_slice($lines, -4)), 0, 400)];
        }
        $files = glob($dir . '/video.*') ?: [];
        $candidates = array_values(array_filter($files, static fn($f) => !str_ends_with($f, '.part') && is_file($f) && filesize($f) > 10240));
        if (!$candidates) return ['ok' => false, 'message' => 'yt-dlp terminó pero no generó un archivo de video válido.'];
        usort($candidates, static fn($a, $b) => filesize($b) <=> filesize($a));
        return ['ok' => true, 'file' => $candidates[0]];
    }

    private function cookiesPath(): string
    {
        try {
            $st = \App\Core\Database::instance()->prepare('SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1');
            $st->execute(['youtube_cookies_file']);
            $path = trim((string)$st->fetchColumn());
            if ($path !== '' && is_file($path)) return $path;
        } catch (\Throwable $e) {
        }
        return '';
    }

    private function sandboxDir(int $jobId): string
    {
        return $this->root() . '/storage/_content_lab/videos/' . max(1, $jobId);
    }

    private function root(): string
    {
        return defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
    }

    private function resolveFile(string $relative): string
    {
        $relative = str_replace(['..', '\\'], ['', '/'], trim($relative));
        if ($relative === '') return '';
        $path = $this->root() . '/' . ltrim($relative, '/');
        return is_file($path) ? $path : '';
    }
}
