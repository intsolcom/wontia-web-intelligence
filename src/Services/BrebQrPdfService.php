<?php
namespace App\Services;

/**
 * Genera un PDF imprimible con el QR oficial de Bre-B de SONIKA.
 * Sin dependencias externas: embebe la imagen en FlateDecode (sin perdida)
 * y usa las fuentes base Helvetica del formato PDF.
 */
class BrebQrPdfService
{
    private const PAGE_W = 595.28; // A4 en puntos
    private const PAGE_H = 841.89;
    private const MARGIN = 48.0;
    private const MAX_IMAGE_SIDE = 1200;
    /** Subir al cambiar el layout para invalidar PDFs cacheados. */
    private const LAYOUT_VERSION = 2;

    private ?string $tempImage = null;

    /** Anchos AFM de Helvetica (unidades /1000) para ASCII 32-126. */
    private const HELVETICA_WIDTHS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /**
     * @param array $order Pedido completo (order_number, uuid, total_cents, currency...)
     * @param array $breb  Bloque "breb" de BrebService::paymentInfo()
     */
    public function pdf(array $order, array $breb): string
    {
        $this->tempImage = null;
        $qrUrl = (string)($breb['qr_image'] ?? '');
        if ($qrUrl === '') {
            throw new \RuntimeException('El QR de Bre-B no esta configurado.');
        }
        $path = $this->resolveImagePath($qrUrl);
        if ($path === '') {
            throw new \RuntimeException('No se encontro el archivo del QR de Bre-B.');
        }

        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        $dir = $root . '/cache/breb-qr';
        $stamp = sha1(implode('|', [
            (string)self::LAYOUT_VERSION,
            (string)($order['uuid'] ?? ''),
            $qrUrl,
            (string)@filemtime($path),
            (string)($order['order_number'] ?? ''),
            (string)($order['total_cents'] ?? ''),
            (string)($order['currency'] ?? ''),
            (string)($breb['key'] ?? ''),
            (string)($breb['account_name'] ?? ''),
        ]));
        $slug = preg_replace('/[^a-z0-9-]/i', '', (string)($order['uuid'] ?? 'order')) ?: 'order';
        $file = $dir . '/' . $slug . '-' . substr($stamp, 0, 12) . '.pdf';

        if (is_file($file) && filesize($file) > 1000) {
            $cached = (string)@file_get_contents($file);
            if (str_starts_with($cached, '%PDF-')) return $cached;
        }

        try {
            $binary = $this->build($order, $breb, $path);
        } finally {
            if ($this->tempImage !== null) {
                @unlink($this->tempImage);
                $this->tempImage = null;
            }
        }

        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        if (is_dir($dir) && is_writable($dir)) {
            $tmp = $file . '.' . getmypid() . '.' . bin2hex(random_bytes(3)) . '.tmp';
            if (@file_put_contents($tmp, $binary) !== false) {
                @rename($tmp, $file);
            } else {
                @unlink($tmp);
            }
            foreach (glob($dir . '/' . $slug . '-*.pdf') ?: [] as $old) {
                if ($old !== $file) @unlink($old);
            }
        }

        return $binary;
    }

    public function filename(array $order): string
    {
        $number = (string)($order['order_number'] ?? 'pedido');
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $number) ?: 'pedido';
        return 'SONIKA-BreB-' . $safe . '.pdf';
    }

    private function build(array $order, array $breb, string $imagePath): string
    {
        [$imgW, $imgH, $imgData] = $this->imageStream($imagePath);

        $currency = (string)($order['currency'] ?? 'COP');
        $total = $this->money((int)($order['total_cents'] ?? 0), $currency);
        $orderNumber = (string)($order['order_number'] ?? '');
        $key = trim((string)($breb['key'] ?? ''));
        $owner = trim((string)($breb['account_name'] ?? ''));
        $uuid = (string)($order['uuid'] ?? '');
        $createdAt = $this->dateLabel((string)($order['created_at'] ?? ''));

        $content = '';
        // Barra superior de marca
        $content .= "0.949 0.051 0.086 rg\n0 " . $this->n(self::PAGE_H - 6) . ' ' . $this->n(self::PAGE_W) . " 6 re f\n";

        // Cabecera
        $content .= "0.07 0.07 0.07 rg\n";
        $content .= $this->text(self::MARGIN, 800, 20, 'F2', 'SONIKA');
        $content .= "0.42 0.45 0.50 rg\n";
        $content .= $this->text(self::MARGIN, 785, 8.5, 'F1', 'El Sonido que te mueve');
        $content .= $this->textRight(self::PAGE_W - self::MARGIN, 800, 11, 'F2', 'Pago Bre-B');
        if ($orderNumber !== '') {
            $content .= $this->textRight(self::PAGE_W - self::MARGIN, 785, 9, 'F1', 'Pedido ' . $orderNumber);
        }

        // Separador
        $content .= "0.88 0.88 0.88 RG\n0.8 w\n" . $this->n(self::MARGIN) . ' 770 m ' . $this->n(self::PAGE_W - self::MARGIN) . " 770 l S\n";

        // Titulo
        $content .= "0.07 0.07 0.07 rg\n";
        $content .= $this->text(self::MARGIN, 738, 16, 'F2', 'Escanea y paga con Bre-B');
        $content .= "0.42 0.45 0.50 rg\n";
        $content .= $this->text(self::MARGIN, 721, 9.5, 'F1', 'Transfiere el monto exacto para confirmar tu pedido.');

        // Zona de imagen: las tarjetas verticales de Bre-B usan casi toda la pagina
        $isCard = ($imgH / max(1, $imgW)) >= 1.15;
        $boxTop = 700.0;
        $boxBottom = $isCard ? 196.0 : 322.0;
        $boxW = $isCard ? 340.0 : 430.0;
        $boxH = $boxTop - $boxBottom;
        $scale = min($boxW / $imgW, $boxH / $imgH);
        $drawW = $imgW * $scale;
        $drawH = $imgH * $scale;
        $qrX = (self::PAGE_W - $drawW) / 2;
        $qrY = $boxBottom + (($boxH - $drawH) / 2);
        $content .= "0.90 0.90 0.90 RG\n0.8 w\n" . $this->n($qrX - 9) . ' ' . $this->n($qrY - 9) . ' ' . $this->n($drawW + 18) . ' ' . $this->n($drawH + 18) . " re S\n";
        $content .= "q\n" . $this->n($drawW) . " 0 0 " . $this->n($drawH) . ' ' . $this->n($qrX) . ' ' . $this->n($qrY) . " cm\n/Im0 Do\nQ\n";

        // Monto
        $amountLabelY = $boxBottom - 14;
        $amountValueY = $boxBottom - 40;
        $content .= "0.42 0.45 0.50 rg\n";
        $content .= $this->textCenter($amountLabelY, 8.5, 'F1', 'Monto exacto');
        $content .= "0.07 0.07 0.07 rg\n";
        $content .= $this->textCenter($amountValueY, 24, 'F2', $total);

        // Datos
        $rows = [];
        if ($key !== '') $rows[] = ['Llave Bre-B', $key];
        if ($owner !== '') $rows[] = ['Titular', $owner];
        if ($orderNumber !== '') $rows[] = ['Referencia', $orderNumber];
        $y = $amountValueY - 30;
        $content .= "0.90 0.90 0.90 RG\n0.6 w\n";
        foreach ($rows as $i => $row) {
            [$label, $value] = $row;
            $content .= "0.42 0.45 0.50 rg\n";
            $content .= $this->text(self::MARGIN, $y, 9.5, 'F1', $label);
            $maxW = 330.0;
            $size = $this->fitSize($value, 10, 'F2', $maxW);
            $content .= "0.07 0.07 0.07 rg\n";
            $content .= $this->textRight(self::PAGE_W - self::MARGIN, $y, $size, 'F2', $value);
            if ($i < count($rows) - 1) {
                $content .= "0.92 0.92 0.92 RG\n0.6 w\n" . $this->n(self::MARGIN) . ' ' . $this->n($y - 13) . ' m ' . $this->n(self::PAGE_W - self::MARGIN) . " " . $this->n($y - 13) . " l S\n";
            }
            $y -= 24;
        }

        if ($isCard) {
            // La tarjeta ya trae instrucciones: solo el recordatorio del comprobante
            if ($uuid !== '') {
                $content .= "0.30 0.33 0.38 rg\n";
                $content .= $this->textCenter(58, 7.8, 'F1', 'Adjunta tu comprobante en sonika.com.co/pedido/' . $uuid);
            }
        } else {
            // Caja de instrucciones
            $boxTop2 = 190.0;
            $boxH2 = 100.0;
            $content .= "0.97 0.97 0.98 rg\n" . $this->n(self::MARGIN) . ' ' . $this->n($boxTop2 - $boxH2) . ' ' . $this->n(self::PAGE_W - (self::MARGIN * 2)) . ' ' . $this->n($boxH2) . " re f\n";
            $content .= "0.07 0.07 0.07 rg\n";
            $content .= $this->text(self::MARGIN + 14, $boxTop2 - 22, 10, 'F2', 'Cómo pagar');
            $content .= "0.30 0.33 0.38 rg\n";
            $steps = [
                '1. Abre tu app bancaria y elige pagar con Bre-B.',
                '2. Escanea este QR o usa la llave ' . ($key !== '' ? $key : 'Bre-B') . '.',
                '3. Transfiere exactamente ' . $total . '.',
            ];
            if ($uuid !== '') {
                $steps[] = '4. Adjunta el comprobante en sonika.com.co/pedido/' . $uuid;
            }
            $lineY = $boxTop2 - 38;
            foreach ($steps as $step) {
                $content .= $this->text(self::MARGIN + 14, $lineY, 8.6, 'F1', $this->truncate($step, 108));
                $lineY -= 13;
            }
        }

        // Pie
        $footerLineY = $isCard ? 44.0 : 60.0;
        $footerTextY = $isCard ? 30.0 : 46.0;
        $content .= "0.90 0.90 0.90 RG\n0.6 w\n" . $this->n(self::MARGIN) . ' ' . $this->n($footerLineY) . ' m ' . $this->n(self::PAGE_W - self::MARGIN) . ' ' . $this->n($footerLineY) . " l S\n";
        $content .= "0.45 0.48 0.53 rg\n";
        $content .= $this->text(self::MARGIN, $footerTextY, 8, 'F1', 'Documento generado el ' . $createdAt . ' (hora Colombia).');
        $content .= $this->textRight(self::PAGE_W - self::MARGIN, $footerTextY, 8, 'F1', 'SONIKA - sonika.com.co');

        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . $this->n(self::PAGE_W) . ' ' . $this->n(self::PAGE_H) . "]"
            . " /Resources << /Font << /F1 5 0 R /F2 6 0 R >> /XObject << /Im0 4 0 R >> >>"
            . " /Contents 7 0 R >>";
        $objects[4] = "<< /Type /XObject /Subtype /Image /Width " . $imgW . " /Height " . $imgH
            . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($imgData) . " >>\nstream\n"
            . $imgData . "\nendstream";
        $objects[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";
        $objects[6] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>";
        $objects[7] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";

        return $this->assemble($objects);
    }

    /** @param array<int,string> $objects */
    private function assemble(array $objects): string
    {
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        $count = 0;
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $num . " 0 obj\n" . $body . "\nendobj\n";
            $count = max($count, (int)$num);
        }
        $xrefAt = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . ($count + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $count; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($count + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefAt . "\n%%EOF\n";
        return $pdf;
    }

    /** @return array{0:int,1:int,2:string} */
    private function imageStream(string $path): array
    {
        $raw = (string)@file_get_contents($path);
        if ($raw === '') {
            throw new \RuntimeException('No se pudo leer el QR de Bre-B.');
        }
        $src = @imagecreatefromstring($raw);
        if (!$src) {
            throw new \RuntimeException('El QR de Bre-B no es una imagen valida.');
        }
        if (!imageistruecolor($src)) {
            @imagepalettetotruecolor($src);
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            throw new \RuntimeException('El QR de Bre-B no tiene dimensiones validas.');
        }
        $maxSide = max($w, $h);
        if ($maxSide > self::MAX_IMAGE_SIDE) {
            $scale = self::MAX_IMAGE_SIDE / $maxSide;
            $nw = max(1, (int)round($w * $scale));
            $nh = max(1, (int)round($h * $scale));
            $resized = @imagescale($src, $nw, $nh, IMG_BICUBIC);
            if ($resized) {
                $src = $resized;
                $w = $nw;
                $h = $nh;
            }
        }

        // Aplanar sobre blanco (por si el PNG trae transparencia)
        $flat = imagecreatetruecolor($w, $h);
        imagefilledrectangle($flat, 0, 0, $w, $h, (int)imagecolorallocate($flat, 255, 255, 255));
        imagealphablending($flat, true);
        imagecopy($flat, $src, 0, 0, 0, 0, $w, $h);

        $bytes = '';
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = (int)imagecolorat($flat, $x, $y);
                $bytes .= chr(($c >> 16) & 0xFF) . chr(($c >> 8) & 0xFF) . chr($c & 0xFF);
            }
        }

        return [$w, $h, (string)gzcompress($bytes, 9)];
    }

    private function resolveImagePath(string $url): string
    {
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        if (str_starts_with($url, '/')) {
            $path = $root . '/public' . $url;
            return is_file($path) ? $path : '';
        }
        if (preg_match('~^https?://~i', $url)) {
            $ctx = stream_context_create([
                'http' => ['timeout' => 6, 'follow_location' => 1, 'max_redirects' => 3],
                'https' => ['timeout' => 6, 'follow_location' => 1, 'max_redirects' => 3],
            ]);
            $tmp = tempnam(sys_get_temp_dir(), 'skqr');
            if ($tmp === false) return '';
            $ok = @file_put_contents($tmp, (string)@file_get_contents($url, false, $ctx));
            if ($ok === false || $ok < 32) {
                @unlink($tmp);
                return '';
            }
            $this->tempImage = $tmp;
            return $tmp;
        }
        return '';
    }

    private function money(int $cents, string $currency): string
    {
        $sym = $currency === 'USD' ? 'US$' : ($currency === 'COP' ? '$' : $currency . ' ');
        return $sym . number_format($cents / 100, $currency === 'COP' ? 0 : 2, ',', '.');
    }

    private function dateLabel(string $date): string
    {
        try {
            $dt = new \DateTime($date !== '' ? $date : 'now');
            $dt->setTimezone(new \DateTimeZone('America/Bogota'));
            return $dt->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            return date('d/m/Y H:i');
        }
    }

    private function text(float $x, float $y, float $size, string $font, string $value): string
    {
        $value = $this->encode($value);
        if ($value === '') return '';
        return 'BT /' . $font . ' ' . $this->n($size) . ' Tf 1 0 0 1 ' . $this->n($x) . ' ' . $this->n($y) . ' Tm (' . $this->escape($value) . ") Tj ET\n";
    }

    private function textRight(float $right, float $y, float $size, string $font, string $value): string
    {
        $w = $this->width($value, $size, $font === 'F2');
        return $this->text($right - $w, $y, $size, $font, $value);
    }

    private function textCenter(float $y, float $size, string $font, string $value): string
    {
        $w = $this->width($value, $size, $font === 'F2');
        return $this->text((self::PAGE_W - $w) / 2, $y, $size, $font, $value);
    }

    private function width(string $value, float $size, bool $bold = false): float
    {
        $value = $this->encode($value);
        $ascci = @iconv('Windows-1252', 'ASCII//TRANSLIT', $value);
        if ($ascci === false) $ascci = $value;
        $total = 0;
        $len = strlen($ascci);
        for ($i = 0; $i < $len; $i++) {
            $code = ord($ascci[$i]);
            $total += self::HELVETICA_WIDTHS[$code - 32] ?? 556;
        }
        $factor = $bold ? 1.045 : 1.0;
        return ($total / 1000) * $size * $factor;
    }

    private function fitSize(string $value, float $preferred, string $font, float $maxWidth): float
    {
        $size = $preferred;
        while ($size > 7.5 && $this->width($value, $size, $font === 'F2') > $maxWidth) {
            $size -= 0.5;
        }
        return $size;
    }

    private function truncate(string $value, int $max): string
    {
        $value = trim($value);
        $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false || count($chars) <= $max) return $value;
        return rtrim(implode('', array_slice($chars, 0, $max - 1))) . '…';
    }

    private function encode(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/u', '', $value) ?? $value;
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
        if ($converted === false) $converted = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
        return $converted;
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $value);
    }

    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
