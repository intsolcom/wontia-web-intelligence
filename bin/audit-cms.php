<?php
/**
 * WWI — Monitor de Código / Contrato CMS.
 * Escanea themes, widgets y bricks buscando CONTENIDO HARDCODEADO que debería
 * venir del admin (settings/menús/sections/config de tema).
 * Uso: php bin/audit-cms.php   (exit 1 si hay hallazgos)
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
define('ROOT_DIR', dirname(__DIR__));

$targets = [
    ROOT_DIR . '/templates/themes',
    ROOT_DIR . '/src/Widgets',
    ROOT_DIR . '/src/Bricks',
];
$patterns = [
    'title fijo'          => '/<title>[^<{]+<\/title>/i',
    'enlace legal/footer' => '#href="/[^"]*(envio|devolucion|garantia|privacidad|terminos|cookies|contacto)#i',
    'tel/email'           => '/(\+?[0-9][0-9\s\-]{7,}[0-9])|([a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,})/i',
    'frase ES larga'      => '/[\'"][A-ZÁÉÍÓÚÑ][^\'"]{30,}[\'"]/u',
];
$skipFragments = ['/_shared/', 'theme.json', '/Bricks/AiBrick/'];
$helperHints = ['wwi_tc', 'wwi_nav', 'wwi_setting', 'wwi_brand', 'wwi_logo_img', 'wwi_favicon_links', 'wwi_site_name'];

$findings = 0;
foreach ($targets as $base) {
    if (!is_dir($base)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
        $path = str_replace('\\', '/', $file->getPathname());
        foreach ($skipFragments as $s) { if (strpos($path, $s) !== false) continue 2; }
        $lines = @file($file->getPathname()) ?: [];
        foreach ($lines as $i => $line) {
            $t = trim($line);
            if ($t === '' || strpos($t, '//') === 0 || strpos($t, '*') === 0) continue;
            $usesHelper = false;
            foreach ($helperHints as $h) { if (stripos($line, $h) !== false) { $usesHelper = true; break; } }
            if ($usesHelper) continue;
            foreach ($patterns as $label => $re) {
                if (preg_match($re, $line)) {
                    echo sprintf("%s:%d  [%s]  %s\n", str_replace(ROOT_DIR . '/', '', $path), $i + 1, $label, mb_substr($t, 0, 110));
                    $findings++;
                }
            }
        }
    }
}

echo "\n=== Monitor de Código WWI — Contrato CMS ===\n";
echo $findings === 0
    ? "OK: sin hardcodes evidentes de contenido editable.\n"
    : "Hallazgos: $findings — mueve ese contenido a settings/menús/sections (admin), no al código.\n";
exit($findings > 0 ? 1 : 0);
