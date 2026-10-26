<?php
/**
 * WWI Adaptive Lint — Constitución de Adaptabilidad (docs/CONSTITUCION-ADAPTABILIDAD.md)
 * Uso:
 *   php tools/adaptive-lint.php [--paths=templates,src/Widgets] [--fail-on=error|warn|none] [--json]
 */
$args = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('~^--([a-z-]+)=(.*)$~', $a, $m)) $args[$m[1]] = $m[2];
    elseif (preg_match('~^--([a-z-]+)$~', $a, $m)) $args[$m[1]] = '1';
}
$root = dirname(__DIR__);
$paths = array_filter(array_map('trim', explode(',', $args['paths'] ?? 'templates,src/Widgets')));
$failOn = $args['fail-on'] ?? 'error';
$json = isset($args['json']);

$findings = [];
$files = 0;

$walk = function ($dir) use (&$walk, &$findings, &$files, $root) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . DIRECTORY_SEPARATOR . $f;
        if (is_dir($p)) { $walk($p); continue; }
        if (!preg_match('~\.(php|css|js|html)$~i', $f)) continue;
        $files++;
        $lines = @file($p) ?: [];
        $imp = 0;
        foreach ($lines as $n => $line) {
            $ln = $n + 1;
            $s = trim($line);
            if ($s === '') continue;
            $imp += substr_count($line, '!important');
            $add = function ($rule, $sev, $msg) use (&$findings, $p, $ln, $s, $root) {
                $findings[] = [
                    'rule' => $rule, 'severity' => $sev, 'file' => str_replace($root . DIRECTORY_SEPARATOR, '', $p),
                    'line' => $ln, 'message' => $msg, 'snippet' => mb_substr($s, 0, 160),
                ];
            };
            if (preg_match('~user-scalable\s*=\s*["\']?no~i', $line) || preg_match('~maximum-scale\s*=\s*["\']?1(\.0)?["\']?~i', $line)) {
                $add('zoom-locked', 'error', 'No bloquear el zoom del usuario (WCAG 1.4.4).');
            }
            if (preg_match_all('~<img\b[^>]*>~i', $line, $mm)) {
                foreach ($mm[0] as $tag) { if (!preg_match('~\balt\s*=~i', $tag)) $add('img-no-alt', 'warn', 'Imagen sin alt (usa alt="" si es decorativa).'); }
            }
            if (str_contains($line, '100vh')) $add('vh-100', 'warn', '100vh falla en móvil; usa 100dvh/100svh.');
            if (str_contains($line, '100vw')) $add('vw-100', 'warn', '100vw incluye scrollbar y provoca overflow; usa width:100%.');
            if (preg_match('~position\s*:\s*absolute~i', $line) && preg_match('~(right|left)\s*:\s*0~i', $line)) {
                $sel = strtolower(substr($line, 0, (int)strpos($line, '{')));
                if (preg_match('~menu|dropdown|panel|popover|cart|acc|drawer~', $sel)) {
                    $add('abs-dropdown', 'warn', 'Dropdown anclado al icono: puede salirse de pantalla. En ≤900px usa bottom sheet.');
                }
            }
            if (preg_match('~([^{}]*?(btn|button|icon|tab)[^{}]*)\{([^}]*)\}~i', $line, $m)) {
                if (preg_match('~width\s*:\s*(\d{1,2})px~i', $m[3], $w) && (int)$w[1] < 44) $add('tap-target', 'warn', 'Objetivo táctil < 44px de ancho (Constitución §5).');
                if (preg_match('~height\s*:\s*(\d{1,2})px~i', $m[3], $h) && (int)$h[1] < 44) $add('tap-target', 'warn', 'Objetivo táctil < 44px de alto (Constitución §5).');
            }
            if (preg_match('~([^{}]*?(input|textarea|select)[^{}]*)\{([^}]*)\}~i', $line, $m)) {
                if (preg_match('~font-size\s*:\s*(\d{1,2})px~i', $m[3], $fs) && (int)$fs[1] < 16) $add('input-font', 'warn', 'Inputs < 16px provocan zoom automático en iOS; sube a 16px en móvil.');
            }
            if (preg_match('~<script\b[^>]*\bsrc\s*=~i', $line) && !preg_match('~\b(defer|async)\b~i', $line) && !preg_match('~type\s*=\s*["\']module~i', $line)) {
                $add('script-blocking', 'warn', 'Script externo sin defer/async bloquea el render.');
            }
            if (preg_match('~<iframe\b[^>]*>~i', $line, $m) && !preg_match('~loading\s*=~i', $m[0])) {
                $add('iframe-lazy', 'warn', 'Iframe sin loading="lazy".');
            }
            if (preg_match('~<a\b[^>]*target\s*=\s*["\']_blank["\'][^>]*>~i', $line, $m) && !preg_match('~\brel\s*=~i', $m[0])) {
                $add('blank-no-rel', 'warn', 'target="_blank" sin rel="noopener".');
            }
            if (preg_match('~outline\s*:\s*(none|0)~i', $line) && !preg_match('~focus-visible~i', $line)) {
                $add('outline-none', 'info', 'outline:none detectado: garantiza :focus-visible alternativo.');
            }
            if (preg_match('~overflow-x\s*:\s*hidden~i', $line)) {
                $add('overflow-x-hidden', 'info', 'overflow-x:hidden en html/body puede romper sticky; prefiere overflow-x:clip.');
            }
        }
        if ($imp > 60) {
            $findings[] = ['rule' => 'important-heavy', 'severity' => 'info', 'file' => str_replace($root . DIRECTORY_SEPARATOR, '', $p), 'line' => 1, 'message' => 'Uso intensivo de !important (' . $imp . ').', 'snippet' => ''];
        }
    }
};

foreach ($paths as $rel) $walk($root . DIRECTORY_SEPARATOR . ltrim($rel, '/\\'));

$counts = ['error' => 0, 'warn' => 0, 'info' => 0];
foreach ($findings as $f) $counts[$f['severity']]++;

if ($json) {
    echo json_encode(['files' => $files, 'counts' => $counts, 'findings' => $findings], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
} else {
    $byFile = [];
    foreach ($findings as $f) $byFile[$f['file']][] = $f;
    ksort($byFile);
    foreach ($byFile as $file => $items) {
        echo "\n" . $file . "\n";
        foreach ($items as $f) {
            echo '  [' . strtoupper($f['severity']) . '] ' . $f['rule'] . ' L' . $f['line'] . ': ' . $f['message'] . "\n";
            if ($f['snippet'] !== '') echo '    > ' . $f['snippet'] . "\n";
        }
    }
    echo "\n== Adaptive Lint ==\n";
    echo 'Archivos: ' . $files . ' | errores: ' . $counts['error'] . ' | avisos: ' . $counts['warn'] . ' | info: ' . $counts['info'] . "\n";
}
$exit = ($failOn === 'none') ? 0 : (($failOn === 'warn' && ($counts['warn'] + $counts['error']) > 0) || ($failOn === 'error' && $counts['error'] > 0) ? 1 : 0);
exit($exit);
