<?php
namespace App\Services;

use App\Core\Config;

class QuotePdfService
{
    public function generate(array $quote): string
    {
        $svc = new QuoteService();
        $printUrl = $svc->signedPath($quote, 'print', 300);
        $parsed = parse_url($printUrl);
        $path = (string)($parsed['path'] ?? '');
        $query = (string)($parsed['query'] ?? '');
        if ($path === '') throw new \RuntimeException('No se pudo crear la ruta del PDF.');
        $internal = 'http://127.0.0.1' . $path . ($query !== '' ? '?' . $query : '');
        $dir = ROOT_DIR . '/cache/quote-pdf';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (!is_dir($dir) || !is_writable($dir)) throw new \RuntimeException('No hay carpeta de PDF.');
        $cssVersion = sha1((string)@file_get_contents(ROOT_DIR . '/public/assets/themes/sonika/cotizacion.css'));
        $brand = json_encode([$svc->settings()['quote_company_city'] ?? '', $svc->settings()['quote_company_nit'] ?? '', $svc->settings()['quote_public_base_url'] ?? ''], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $seed = (string)($quote['id'] ?? '') . '|' . (string)($quote['created_at'] ?? '') . '|' . (string)($quote['items_json'] ?? '') . '|' . (string)($quote['total_cents'] ?? '') . '|' . sha1(json_encode($quote['customer'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '|' . sha1($brand) . '|' . $cssVersion;
        $file = $dir . '/' . (int)($quote['id'] ?? 0) . '-' . sha1($seed) . '.pdf';
        if (is_file($file) && filesize($file) > 1000 && $this->isPdf($file)) return $file;
        $bin = $this->binary();
        if ($bin === '') throw new \RuntimeException('El generador de PDF no está disponible.');
        $target = $file . '.' . getmypid() . '.' . bin2hex(random_bytes(3)) . '.tmp';
        @unlink($target);
        $cmd = 'timeout 35s ' . escapeshellarg($bin)
            . ' --headless=new --no-sandbox --disable-gpu --disable-dev-shm-usage'
            . ' --no-pdf-header-footer --print-to-pdf=' . escapeshellarg($target)
            . ' --virtual-time-budget=7000 ' . escapeshellarg($internal) . ' 2>/dev/null';
        @exec($cmd, $out, $code);
        if (!is_file($target) || filesize($target) < 1000 || !$this->isPdf($target)) {
            @unlink($target);
            throw new \RuntimeException('No se pudo renderizar el PDF.');
        }
        @rename($target, $file);
        return $file;
    }

    public function purge(int $quoteId): void
    {
        foreach (glob(ROOT_DIR . '/cache/quote-pdf/' . max(0, $quoteId) . '-*.pdf') ?: [] as $file) @unlink($file);
    }

    private function binary(): string
    {
        foreach (['/usr/bin/chromium-browser', '/usr/bin/chromium', '/usr/bin/google-chrome', '/usr/bin/google-chrome-stable'] as $candidate) {
            if (is_executable($candidate)) return $candidate;
        }
        return '';
    }

    private function isPdf(string $file): bool
    {
        $fh = @fopen($file, 'rb');
        if (!$fh) return false;
        $head = (string)fread($fh, 5);
        fclose($fh);
        return $head === '%PDF-';
    }
}
