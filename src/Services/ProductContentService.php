<?php
namespace App\Services;

/**
 * SONIKA Product Content System (SPCS).
 *
 * Convierte el HTML heredado de las descripciones de producto en un formato
 * editorial único: resumen, beneficios, ficha técnica, video + texto, galería
 * y detalles largos. No modifica el catálogo: normaliza al renderizar.
 */
class ProductContentService
{
    public const ENABLED_SETTING = 'product_content_v2';

    private const LEAD_MAX = 420;
    private const MORE_THRESHOLD = 900;
    private const MAX_BENEFITS = 5;
    private const MAX_GALLERY = 9;

    public static function enabled(): bool
    {
        if (function_exists('wwi_setting')) {
            return (string)wwi_setting(self::ENABLED_SETTING, '1') !== '0';
        }
        try {
            $stmt = \App\Core\Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1");
            $stmt->execute([self::ENABLED_SETTING]);
            $value = $stmt->fetchColumn();
            return $value === false || (string)$value !== '0';
        } catch (\Throwable $e) {
            return true;
        }
    }

    public function readingMinutes(array $product): int
    {
        $text = $this->plainText((string)($product['body_html'] ?? ''));
        $words = preg_split('/\s+/u', $text) ?: [];
        $words = array_values(array_filter($words, static fn($w) => trim((string)$w) !== ''));
        return max(1, (int)ceil(count($words) / 200));
    }

    public function render(array $product): string
    {
        $html = trim((string)($product['body_html'] ?? ''));
        if ($html === '') return $this->emptyState($product);

        $parsed = $this->parse($html, $product);
        if (!$parsed || !$this->hasParsedContent($parsed)) {
            return $this->fallback($html, $product);
        }

        return $this->build($parsed, $product);
    }

    /**
     * Análisis ligero para el reporte de salud de contenido del admin.
     * No parsea el DOM completo: usa etiquetas y texto para poder recorrer
     * miles de productos sin penalizar el panel.
     */
    public function analyze(array $product): array
    {
        $html = (string)($product['body_html'] ?? '');
        $text = $this->plainText($html);
        $chars = mb_strlen($text);
        $empty = $chars < 20;
        $hasVideo = (bool)preg_match('~<(iframe|video)\b|youtu\.?be|youtube\.com|vimeo\.com~i', $html);
        $hasImage = (bool)preg_match('~<img\b~i', $html);
        $hasTable = (bool)preg_match('~<table\b~i', $html);
        $hasList = (bool)preg_match('~<(ul|ol)\b~i', $html);
        $hasHeading = (bool)preg_match('~<h[1-6]\b~i', $html);
        $hasStrong = (bool)preg_match('~<(strong|b)\b~i', $html);
        $inlineStyles = (int)preg_match_all('~\sstyle\s*=\s*("[^"]*"|\'[^\']*\')~i', $html);
        $fontTags = (int)preg_match_all('~<font\b~i', $html);
        $specs = $hasTable || $this->hasSpecList($html);
        $onlyText = !$hasVideo && !$hasImage && !$hasTable && !$hasList && !$hasHeading;

        $score = 0;
        if (!$empty) $score += 20;
        if ($hasList) $score += 10;
        if ($specs) $score += 15;
        if ($hasVideo) $score += 15;
        if ($hasImage) $score += 10;
        if ($inlineStyles === 0 && $fontTags === 0) $score += 15;
        if ($chars >= 200 && $chars <= 2500) $score += 10;
        if ($hasHeading) $score += 5;
        if ($chars >= 60 && $chars <= 800) $score += 5;
        $score = max(0, min(100, $score));

        $issues = [];
        $issueKeys = [];
        if ($empty) { $issues[] = 'Sin descripción'; $issueKeys[] = 'empty'; }
        if (!$empty && $onlyText) { $issues[] = 'Solo texto'; $issueKeys[] = 'only_text'; }
        if (!$empty && !$specs) { $issues[] = 'Sin ficha técnica'; $issueKeys[] = 'no_specs'; }
        if (!$empty && !$hasVideo) { $issues[] = 'Sin video'; $issueKeys[] = 'no_video'; }
        if ($inlineStyles > 0) { $issues[] = 'Estilos inline (' . $inlineStyles . ')'; $issueKeys[] = 'inline_styles'; }
        if ($fontTags > 0) { $issues[] = 'Etiquetas font'; $issueKeys[] = 'font_tags'; }
        if ($chars > 2500) { $issues[] = 'Muy larga'; $issueKeys[] = 'too_long'; }
        if ($chars > 0 && $chars < 120) { $issues[] = 'Muy corta'; $issueKeys[] = 'too_short'; }
        if ($onlyText && $chars > 1200) { $issues[] = 'Texto extenso'; $issueKeys[] = 'long_text'; }

        return [
            'empty' => $empty,
            'only_text' => $onlyText,
            'has_video' => $hasVideo,
            'has_image' => $hasImage,
            'has_table' => $hasTable,
            'has_list' => $hasList,
            'has_heading' => $hasHeading,
            'has_strong' => $hasStrong,
            'has_specs' => $specs,
            'inline_styles' => $inlineStyles,
            'font_tags' => $fontTags,
            'chars' => $chars,
            'words' => count(preg_split('/\s+/u', $text) ?: []),
            'score' => $score,
            'issues' => $issues,
            'issue_keys' => $issueKeys,
        ];
    }

    /**
     * Auditoría editorial de todo el catálogo. Cachea el resultado en disco
     * para que el panel de administración pueda filtrar y paginar sin
     * reanalizar 3.000 descripciones en cada clic.
     */
    public function auditCatalog(string $path, int $ttl = 300): array
    {
        $cacheFile = rtrim(sys_get_temp_dir(), '/\\') . '/sonika_content_audit_' . md5($path) . '.json';
        $mtime = is_file($path) ? (int)filemtime($path) : 0;
        if ($mtime > 0 && is_file($cacheFile)) {
            $cacheTime = (int)filemtime($cacheFile);
            if ($cacheTime >= $mtime && (time() - $cacheTime) < $ttl) {
                $cached = json_decode((string)@file_get_contents($cacheFile), true);
                if (is_array($cached) && isset($cached['rows'], $cached['summary'])) return $cached;
            }
        }

        $raw = is_file($path) ? (string)@file_get_contents($path) : '';
        $data = json_decode($raw, true);
        $products = is_array($data) ? (array)($data['products'] ?? []) : [];
        $rows = [];
        $chars = [];
        $scores = [];
        $issueCounts = [];
        $formatCounts = ['only_text' => 0, 'video' => 0, 'image' => 0, 'table' => 0, 'list' => 0, 'styled' => 0];
        $inlineStyles = 0;
        $fontTags = 0;
        $withDescription = 0;
        $empty = 0;

        foreach ($products as $product) {
            $analysis = $this->analyze($product);
            $handle = (string)($product['handle'] ?? '');
            $title = trim((string)($product['title'] ?? ''));
            $body = (string)($product['body_html'] ?? '');
            if (!$analysis['empty']) $withDescription++;
            if ($analysis['empty']) $empty++;
            if ($analysis['only_text']) $formatCounts['only_text']++;
            if ($analysis['has_video']) $formatCounts['video']++;
            if ($analysis['has_image']) $formatCounts['image']++;
            if ($analysis['has_table']) $formatCounts['table']++;
            if ($analysis['has_list']) $formatCounts['list']++;
            if ($analysis['inline_styles'] > 0) $formatCounts['styled']++;
            $inlineStyles += $analysis['inline_styles'];
            $fontTags += $analysis['font_tags'];
            foreach ($analysis['issue_keys'] as $key) {
                if (!isset($issueCounts[$key])) $issueCounts[$key] = 0;
                $issueCounts[$key]++;
            }
            $chars[] = $analysis['chars'];
            $scores[] = $analysis['score'];
            $rows[] = [
                'handle' => $handle,
                'title' => $title,
                'vendor' => (string)($product['vendor'] ?? ''),
                'type' => (string)($product['product_type'] ?? ''),
                'chars' => $analysis['chars'],
                'words' => $analysis['words'],
                'score' => $analysis['score'],
                'issues' => $analysis['issues'],
                'issue_keys' => $analysis['issue_keys'],
                'only_text' => $analysis['only_text'],
                'has_video' => $analysis['has_video'],
                'has_image' => $analysis['has_image'],
                'has_table' => $analysis['has_table'],
                'has_list' => $analysis['has_list'],
                'inline_styles' => $analysis['inline_styles'],
                'font_tags' => $analysis['font_tags'],
                'preview' => mb_substr($this->plainText($body), 0, 180),
                'url' => '/producto/' . rawurlencode($handle),
            ];
        }

        $total = count($rows);
        $sortedChars = $chars;
        sort($sortedChars, SORT_NUMERIC);
        $median = $total > 0 ? $sortedChars[(int)floor(($total - 1) / 2)] : 0;
        $avgChars = $total > 0 ? (int)round(array_sum($chars) / $total) : 0;
        $avgScore = $total > 0 ? (int)round(array_sum($scores) / $total) : 0;
        $buckets = ['0-39' => 0, '40-59' => 0, '60-79' => 0, '80-100' => 0];
        foreach ($scores as $score) {
            if ($score < 40) $buckets['0-39']++;
            elseif ($score < 60) $buckets['40-59']++;
            elseif ($score < 80) $buckets['60-79']++;
            else $buckets['80-100']++;
        }

        $summary = [
            'total' => $total,
            'with_description' => $withDescription,
            'empty' => $empty,
            'only_text' => $formatCounts['only_text'],
            'video' => $formatCounts['video'],
            'image' => $formatCounts['image'],
            'table' => $formatCounts['table'],
            'list' => $formatCounts['list'],
            'styled' => $formatCounts['styled'],
            'inline_styles' => $inlineStyles,
            'font_tags' => $fontTags,
            'avg_chars' => $avgChars,
            'median_chars' => $median,
            'avg_score' => $avgScore,
            'issues' => $issueCounts,
            'formats' => $formatCounts,
            'score_buckets' => $buckets,
        ];
        $out = ['summary' => $summary, 'rows' => $rows, 'generated_at' => date('c')];
        @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $out;
    }

    private function parse(string $html, array $product): ?array
    {
        if (!class_exists('\DOMDocument')) return null;
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $ok = $doc->loadHTML('<?xml encoding="UTF-8"><html><body><div id="pdp-root">' . $html . '</div></body></html>');
            if (!$ok) return null;
            $xpath = new \DOMXPath($doc);
            $root = $xpath->query('//*[@id="pdp-root"]')->item(0);
            if (!$root) return null;

            $videos = [];
            foreach (iterator_to_array($xpath->query('.//iframe | .//video', $root)) as $node) {
                $video = $this->videoFromNode($node, $product);
                if ($video) $videos[] = $video;
                if ($node->parentNode) $node->parentNode->removeChild($node);
            }

            $images = [];
            $seenImages = [];
            foreach (iterator_to_array($xpath->query('.//img', $root)) as $node) {
                $image = $this->imageFromNode($node, $product);
                if ($image && !isset($seenImages[$image['src']])) {
                    $seenImages[$image['src']] = true;
                    $images[] = $image;
                }
                if ($node->parentNode) $node->parentNode->removeChild($node);
            }

            foreach (iterator_to_array($xpath->query('.//script | .//style | .//noscript | .//template | .//object | .//embed | .//form | .//input | .//button | .//select | .//textarea | .//svg', $root)) as $node) {
                if ($node->parentNode) $node->parentNode->removeChild($node);
            }

            $blocks = [];
            foreach (iterator_to_array($root->childNodes) as $child) {
                $blocks = array_merge($blocks, $this->parseNode($child));
            }

            return ['blocks' => $blocks, 'videos' => $videos, 'images' => $images];
        } catch (\Throwable $e) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function parseNode(\DOMNode $node): array
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            $text = $this->nodeText($node);
            if ($text === '') return [];
            return [$this->paragraphOrHeading(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $text)];
        }
        if (!$node instanceof \DOMElement) return [];

        $tag = strtolower($node->nodeName);
        if (in_array($tag, ['script', 'style', 'noscript', 'template', 'form', 'input', 'button', 'select', 'textarea', 'svg', 'img', 'iframe', 'video', 'object', 'embed', 'br', 'hr'], true)) {
            return [];
        }

        if (preg_match('/^h([1-6])$/', $tag, $m)) {
            $text = $this->nodeText($node);
            if ($text === '') return [];
            $level = (int)$m[1];
            $outTag = $level <= 2 ? 'h2' : ($level === 3 ? 'h3' : 'h4');
            return [['type' => 'heading', 'tag' => $outTag, 'text' => $text, 'html' => $this->cleanInline($node)]];
        }

        if ($tag === 'p') {
            $html = trim($this->cleanInline($node));
            $text = $this->nodeText($node);
            if ($text === '' || !$this->hasVisibleText($html)) return [];
            return [$this->paragraphOrHeading($html, $text)];
        }

        if ($tag === 'ul' || $tag === 'ol') {
            $items = [];
            $this->collectListItems($node, $items);
            return $items ? [['type' => 'list', 'ordered' => $tag === 'ol', 'items' => $items]] : [];
        }

        if ($tag === 'table') {
            $rows = [];
            $hasHeader = false;
            foreach ($this->tableRows($node) as $tr) {
                $cells = [];
                $thCount = 0;
                $tdCount = 0;
                foreach ($tr->childNodes as $cell) {
                    if (!$cell instanceof \DOMElement) continue;
                    $cellTag = strtolower($cell->nodeName);
                    if ($cellTag !== 'td' && $cellTag !== 'th') continue;
                    if ($cellTag === 'th') $thCount++; else $tdCount++;
                    $html = trim($this->cellHtml($cell));
                    $text = $this->nodeText($cell);
                    if ($text === '' && !$this->hasVisibleText($html)) continue;
                    $cells[] = ['html' => $html, 'text' => $text];
                }
                if (!$cells) continue;
                if (!$rows && $thCount > 0 && $tdCount === 0) $hasHeader = true;
                $rows[] = $cells;
            }
            if (!$rows) return [];
            if (count($rows) === 1 && count($rows[0]) === 1) {
                $text = $rows[0][0]['text'];
                return $text !== '' ? [$this->paragraphOrHeading($rows[0][0]['html'], $text)] : [];
            }
            return [['type' => 'table', 'rows' => $rows, 'header' => $hasHeader]];
        }

        if ($tag === 'figure') {
            return [];
        }

        if ($tag === 'blockquote') {
            $html = trim($this->cleanInline($node));
            $text = $this->nodeText($node);
            return $text !== '' ? [['type' => 'quote', 'html' => $html, 'text' => $text]] : [];
        }

        if ($tag === 'details') {
            $out = [];
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMElement && strtolower($child->nodeName) === 'summary') {
                    $text = $this->nodeText($child);
                    if ($text !== '') $out[] = ['type' => 'heading', 'tag' => 'h3', 'text' => $text, 'html' => $this->cleanInline($child)];
                } else {
                    $out = array_merge($out, $this->parseNode($child));
                }
            }
            return $out;
        }

        if ($tag === 'a') {
            $html = trim($this->cleanInline($node));
            $text = $this->nodeText($node);
            return $text !== '' ? [$this->paragraphOrHeading($html, $text)] : [];
        }

        if ($this->hasBlockChild($node)) {
            $out = [];
            foreach ($node->childNodes as $child) {
                $out = array_merge($out, $this->parseNode($child));
            }
            return $out;
        }

        $html = trim($this->cleanInline($node));
        $text = $this->nodeText($node);
        if ($text === '' || !$this->hasVisibleText($html)) return [];
        return [$this->paragraphOrHeading($html, $text)];
    }

    private function paragraphOrHeading(string $html, string $text): array
    {
        $text = $this->normalizeText($text);
        $upper = mb_strtoupper($text, 'UTF-8') === $text;
        $wordCount = count(preg_split('/\s+/u', $text) ?: []);
        $looksLikeNote = (bool)preg_match('/\b(nota|aviso|importante|advertencia|atenci[oó]n|promoci[oó]n)\b/iu', $text);
        if ($upper && !$looksLikeNote && mb_strlen($text) <= 90 && $wordCount <= 14 && !preg_match('/[.!?:]$/u', $text)) {
            return ['type' => 'heading', 'tag' => 'h3', 'text' => $text, 'html' => htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')];
        }
        if (preg_match('~^<strong>(.*?)</strong>$~is', $html, $m)) {
            $inner = $this->normalizeText(strip_tags($m[1]));
            if ($inner !== '' && !$looksLikeNote && mb_strlen($inner) <= 90 && $wordCount <= 12 && !preg_match('/[.!?:]$/u', $inner)) {
                return ['type' => 'heading', 'tag' => 'h3', 'text' => $inner, 'html' => htmlspecialchars($inner, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')];
            }
            if ($inner !== '') $html = $m[1];
        }
        return ['type' => 'paragraph', 'html' => $html, 'text' => $text];
    }

    private function cleanInline(\DOMNode $node, array $skip = [], bool $normalize = true): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $out .= htmlspecialchars((string)$child->nodeValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }
            if (!$child instanceof \DOMElement) continue;
            $tag = strtolower($child->nodeName);
            if (in_array($tag, $skip, true)) continue;
            switch ($tag) {
                case 'br':
                    $out .= '<br>';
                    break;
                case 'strong':
                case 'b':
                    $out .= '<strong>' . $this->cleanInline($child, $skip, false) . '</strong>';
                    break;
                case 'em':
                case 'i':
                    $out .= '<em>' . $this->cleanInline($child, $skip, false) . '</em>';
                    break;
                case 'u':
                    $out .= '<u>' . $this->cleanInline($child, $skip, false) . '</u>';
                    break;
                case 's':
                case 'strike':
                case 'del':
                    $out .= '<s>' . $this->cleanInline($child, $skip, false) . '</s>';
                    break;
                case 'code':
                    $out .= '<code>' . $this->cleanInline($child, $skip, false) . '</code>';
                    break;
                case 'a':
                    $href = $this->safeUrl($child->getAttribute('href'));
                    $inner = $this->cleanInline($child, $skip, false);
                    if ($href === '') {
                        $out .= $inner;
                    } else {
                        $external = (bool)preg_match('~^https?://~i', $href);
                        $out .= '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' . ($external ? ' target="_blank" rel="noopener"' : '') . '>' . $inner . '</a>';
                    }
                    break;
                case 'script':
                case 'style':
                case 'noscript':
                case 'template':
                case 'img':
                case 'iframe':
                case 'video':
                case 'object':
                case 'embed':
                case 'svg':
                    break;
                default:
                    $out .= $this->cleanInline($child, $skip, false);
                    break;
            }
        }
        return $normalize ? trim((string)preg_replace('/\s+/u', ' ', $out)) : $out;
    }

    private function hasBlockChild(\DOMNode $node): bool
    {
        foreach ($node->childNodes as $child) {
            if (!$child instanceof \DOMElement) continue;
            $tag = strtolower($child->nodeName);
            if (in_array($tag, ['p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'table', 'figure', 'blockquote', 'section', 'article', 'header', 'footer', 'details'], true)) {
                return true;
            }
            if ($child->hasChildNodes() && $this->hasBlockChild($child)) return true;
        }
        return false;
    }

    private function hasParsedContent(array $parsed): bool
    {
        if (!empty($parsed['videos']) || !empty($parsed['images']) || !empty($parsed['blocks'])) return true;
        return false;
    }

    private function build(array $parsed, array $product): string
    {
        $sections = [];
        $current = ['title' => '', 'type' => 'details', 'tag' => 'h2', 'blocks' => []];
        foreach ($parsed['blocks'] as $block) {
            if ($block['type'] === 'heading') {
                if ($current['blocks'] || $current['title'] !== '') {
                    $sections[] = $current;
                    $current = ['title' => '', 'type' => 'details', 'tag' => 'h2', 'blocks' => []];
                }
                $current['title'] = $block['text'];
                $current['tag'] = $block['tag'];
                $current['type'] = $this->headingType($block['text']);
                continue;
            }
            $current['blocks'][] = $block;
        }
        if ($current['blocks'] || $current['title'] !== '') $sections[] = $current;

        $lead = [];
        $leadTitle = '';
        $benefits = [];
        $specs = [];
        $specExtras = [];
        $specTables = [];
        $includes = [];
        $details = [];

        // 1. Resumen
        foreach ($sections as &$section) {
            if (in_array($section['type'], ['specs', 'includes'], true)) continue;
            foreach ($section['blocks'] as $i => $block) {
                if ($block['type'] !== 'paragraph') continue;
                $chunks = $this->splitParagraph($block['html'], $block['text']);
                if (!$chunks) { unset($section['blocks'][$i]); continue; }
                if ($leadTitle === '' && $section['title'] !== '' && !$this->isGenericHeading($section['title'])) {
                    $leadTitle = $section['title'];
                    $section['title'] = '';
                }
                $lead[] = $chunks[0];
                if (count($chunks) > 1) {
                    $replacement = [];
                    foreach (array_slice($chunks, 1) as $chunk) $replacement[] = ['type' => 'paragraph', 'html' => $chunk, 'text' => strip_tags($chunk)];
                    array_splice($section['blocks'], $i, 1, $replacement);
                } else {
                    unset($section['blocks'][$i]);
                }
                break 2;
            }
        }
        unset($section);

        // 2. Beneficios y 3. especificaciones
        foreach ($sections as &$section) {
            $section['blocks'] = array_values($section['blocks']);
            foreach ($section['blocks'] as $i => $block) {
                if ($block['type'] === 'table') {
                    $pairs = $this->tablePairs($block);
                    if ($pairs) {
                        foreach ($pairs as $pair) $this->addSpec($specs, $pair);
                    } else {
                        $specTables[] = $block;
                    }
                    unset($section['blocks'][$i]);
                    continue;
                }
                if ($block['type'] !== 'list') continue;

                if ($section['type'] === 'includes') {
                    foreach ($block['items'] as $item) $includes[] = $item['html'];
                    unset($section['blocks'][$i]);
                    continue;
                }

                $parsedList = $this->listPairs($block);
                if ($parsedList) {
                    foreach ($parsedList['pairs'] as $pair) $this->addSpec($specs, $pair);
                    foreach ($parsedList['extras'] as $extra) $specExtras[] = $extra;
                    unset($section['blocks'][$i]);
                    continue;
                }

                if ($section['type'] === 'specs') {
                    foreach ($block['items'] as $item) $specExtras[] = $item['html'];
                    unset($section['blocks'][$i]);
                    continue;
                }

                $count = count($block['items']);
                if (!$benefits && $count >= 3 && $count <= self::MAX_BENEFITS) {
                    foreach ($block['items'] as $item) $benefits[] = $item['html'];
                    unset($section['blocks'][$i]);
                }
            }
            $section['blocks'] = array_values($section['blocks']);
        }
        unset($section);

        // 4. Detalles restantes
        foreach ($sections as $section) {
            if (in_array($section['type'], ['specs', 'includes'], true) && !$section['blocks']) continue;
            if (!$section['blocks']) {
                if ($section['title'] === '' || $this->isGenericHeading($section['title'])) continue;
                $details[] = ['title' => $section['title'], 'tag' => $section['tag'] ?: 'h2', 'blocks' => []];
                continue;
            }
            $title = $section['title'];
            $tag = $section['tag'] ?: 'h2';
            if ($section['type'] === 'specs' || $section['type'] === 'includes') $title = '';
            if ($section['type'] === 'video') $title = '';
            if ($title === '' && count($details) > 0) $title = 'Información adicional';
            $details[] = ['title' => $title, 'tag' => $tag, 'blocks' => $section['blocks']];
        }

        $hasVideo = !empty($parsed['videos']);
        $featureVideo = $hasVideo ? $parsed['videos'][0] : null;
        $featureImage = (!$hasVideo && !empty($parsed['images'])) ? $parsed['images'][0] : null;
        $gallery = $parsed['images'];
        if ($featureImage) array_shift($gallery);
        $gallery = array_slice($gallery, 0, self::MAX_GALLERY);

        $out = '<div class="pdp-content">';
        $hasIntro = $lead || $benefits;
        if ($hasVideo || $featureImage) {
            $out .= '<section class="pdp-feature' . ($hasVideo ? ' pdp-feature--video' : ' pdp-feature--image') . '"' . ($hasVideo ? ' id="video"' : '') . '>';
            $out .= '<div class="pdp-feature__copy">';
            $out .= '<span class="pdp-eyebrow">' . ($hasVideo ? 'Video' : 'Producto') . '</span>';
            $out .= '<h2>' . ($hasVideo ? 'Conócelo en video' : 'Conócelo') . '</h2>';
            if ($lead) {
                if ($leadTitle !== '') $out .= '<h3 class="pdp-lead__title">' . htmlspecialchars($leadTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h3>';
                $out .= '<div class="pdp-lead">' . implode('', array_map(fn($p) => '<p>' . $p . '</p>', $lead)) . '</div>';
            } elseif (!$benefits) {
                $title = trim((string)($product['title'] ?? ''));
                $out .= '<p>' . htmlspecialchars($hasVideo ? 'Mira ' . $title . ' en video.' : 'Conoce los detalles de ' . $title . '.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
            }
            if ($benefits) {
                $out .= '<ul class="pdp-checks">';
                foreach ($benefits as $item) $out .= '<li>' . $item . '</li>';
                $out .= '</ul>';
            }
            $out .= '</div><div class="pdp-feature__media">';
            $out .= $hasVideo ? $this->videoHtml($featureVideo, $product) : $this->imageHtml($featureImage);
            $out .= '</div></section>';
        } elseif ($hasIntro) {
            $out .= '<section class="pdp-intro">';
            if ($leadTitle !== '') $out .= '<h2 class="pdp-lead__title">' . htmlspecialchars($leadTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</h2>';
            if ($lead) $out .= '<div class="pdp-lead">' . implode('', array_map(fn($p) => '<p>' . $p . '</p>', $lead)) . '</div>';
            if ($benefits) {
                $out .= '<ul class="pdp-checks">';
                foreach ($benefits as $item) $out .= '<li>' . $item . '</li>';
                $out .= '</ul>';
            }
            $out .= '</section>';
        }

        if ($specs || $specExtras || $specTables) {
            $out .= '<section class="pdp-section pdp-specs" id="especificaciones"><h2>Ficha técnica</h2>';
            if ($specs) {
                $out .= '<dl class="pdp-specs__grid">';
                foreach ($specs as $pair) {
                    $out .= '<div class="pdp-specs__row"><dt>' . $pair[0] . '</dt><dd>' . $pair[1] . '</dd></div>';
                }
                $out .= '</dl>';
            }
            if ($specExtras) {
                $out .= '<ul class="pdp-checks pdp-checks--compact">';
                foreach ($specExtras as $item) $out .= '<li>' . $item . '</li>';
                $out .= '</ul>';
            }
            foreach ($specTables as $table) $out .= $this->tableHtml($table);
            $out .= '</section>';
        }

        if ($includes) {
            $out .= '<section class="pdp-section pdp-includes" id="incluye"><h2>Qué incluye</h2><ul class="pdp-checks pdp-checks--grid">';
            foreach ($includes as $item) $out .= '<li>' . $item . '</li>';
            $out .= '</ul></section>';
        }

        if ($gallery) {
            $out .= '<section class="pdp-section pdp-media-gallery" id="galeria"><h2>Galería</h2><div class="pdp-media-gallery__grid">';
            foreach ($gallery as $image) {
                $out .= '<figure><img src="' . htmlspecialchars($image['src'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="' . htmlspecialchars($image['alt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" loading="lazy" decoding="async">';
                if ($image['caption'] !== '') $out .= '<figcaption>' . htmlspecialchars($image['caption'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</figcaption>';
                $out .= '</figure>';
            }
            $out .= '</div></section>';
        }

        if ($details) {
            $detailsHtml = $this->detailsHtml($details);
            $detailsText = strip_tags($detailsHtml);
            if (mb_strlen($this->normalizeText($detailsText)) > self::MORE_THRESHOLD || count($details) > 2) {
                $out .= '<details class="pdp-more" id="descripcion-completa"><summary>Leer descripción completa</summary><div class="pdp-more__body">' . $detailsHtml . '</div></details>';
            } else {
                $out .= '<div class="pdp-details">' . $detailsHtml . '</div>';
            }
        }

        if (count($parsed['videos']) > 1) {
            $out .= '<section class="pdp-section pdp-more-videos"><h2>Más videos</h2><div class="pdp-more-videos__grid">';
            foreach (array_slice($parsed['videos'], 1) as $video) $out .= $this->videoHtml($video, $product);
            $out .= '</div></section>';
        }

        if (!$lead && !$benefits && !$specs && !$specExtras && !$specTables && !$includes && !$gallery && !$details && !$parsed['videos'] && !$featureImage) {
            return $this->fallback((string)($product['body_html'] ?? ''), $product);
        }

        $out .= '</div>';
        return $out;
    }

    private function detailsHtml(array $details): string
    {
        $out = '';
        foreach ($details as $section) {
            if (!empty($section['title'])) {
                $tag = in_array($section['tag'], ['h2', 'h3', 'h4'], true) ? $section['tag'] : 'h2';
                $out .= '<' . $tag . '>' . htmlspecialchars((string)$section['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</' . $tag . '>';
            }
            foreach ($section['blocks'] as $block) $out .= $this->blockHtml($block);
        }
        return $out;
    }

    private function blockHtml(array $block): string
    {
        if ($block['type'] === 'paragraph') return '<p>' . $block['html'] . '</p>';
        if ($block['type'] === 'quote') return '<blockquote>' . $block['html'] . '</blockquote>';
        if ($block['type'] === 'list') {
            $tag = !empty($block['ordered']) ? 'ol' : 'ul';
            $class = count($block['items']) > 5 ? ' class="pdp-list pdp-list--cols"' : ' class="pdp-list"';
            $out = '<' . $tag . $class . '>';
            foreach ($block['items'] as $item) $out .= '<li>' . $item['html'] . '</li>';
            return $out . '</' . $tag . '>';
        }
        if ($block['type'] === 'table') return $this->tableHtml($block);
        return '';
    }

    private function tableHtml(array $table): string
    {
        $rows = $table['rows'] ?? [];
        if (!$rows) return '';
        $out = '<div class="pdp-table-wrap"><table class="pdp-table">';
        foreach ($rows as $index => $row) {
            $tag = ($index === 0 && !empty($table['header'])) ? 'th' : 'td';
            $out .= '<tr>';
            foreach ($row as $cell) $out .= '<' . $tag . '>' . $cell['html'] . '</' . $tag . '>';
            $out .= '</tr>';
        }
        return $out . '</table></div>';
    }

    private function addSpec(array &$specs, array $pair): void
    {
        $label = $this->normalizeText(strip_tags($pair[0]));
        $key = mb_strtolower($label, 'UTF-8');
        if ($key === '') return;
        if (!isset($specs[$key])) {
            $specs[$key] = $pair;
            return;
        }
        $current = mb_strtolower($this->normalizeText(strip_tags($specs[$key][1])), 'UTF-8');
        $new = mb_strtolower($this->normalizeText(strip_tags($pair[1])), 'UTF-8');
        if ($new === '' || $new === $current || mb_strpos($current, $new) !== false) return;
        if (mb_strpos($new, $current) !== false) {
            $specs[$key][1] = $pair[1];
            return;
        }
        $specs[$key][1] = rtrim($specs[$key][1], " .;") . ' · ' . $pair[1];
    }

    private function tablePairs(array $table): array
    {
        $rows = $table['rows'] ?? [];
        if (!$rows) return [];
        if (!empty($table['header'])) array_shift($rows);
        if (count($rows) < 2) return [];
        $pairs = [];
        foreach ($rows as $row) {
            if (count($row) !== 2) return [];
            $pairs[] = [$row[0]['text'], $row[1]['html']];
        }
        return $pairs;
    }

    /**
     * Filas propias de una tabla, sin incluir tablas anidadas.
     * DOMElement no expone querySelectorAll en PHP, así que se recorre
     * thead/tbody/tfoot de primer nivel.
     */
    private function tableRows(\DOMElement $table): array
    {
        $rows = [];
        foreach ($table->childNodes as $child) {
            if (!$child instanceof \DOMElement) continue;
            $tag = strtolower($child->nodeName);
            if ($tag === 'tr') {
                $rows[] = $child;
                continue;
            }
            if (!in_array($tag, ['thead', 'tbody', 'tfoot'], true)) continue;
            foreach ($child->childNodes as $tr) {
                if ($tr instanceof \DOMElement && strtolower($tr->nodeName) === 'tr') $rows[] = $tr;
            }
        }
        return $rows;
    }

    /**
     * Aplana listas anidadas (<ul><ul><li>…) conservando cada ítem.
     */
    private function collectListItems(\DOMElement $list, array &$items): void
    {
        foreach ($list->childNodes as $child) {
            if (!$child instanceof \DOMElement) continue;
            $tag = strtolower($child->nodeName);
            if ($tag === 'li') {
                $html = trim($this->cleanInline($child, ['ul', 'ol']));
                $text = $this->textExcludingLists($child);
                if ($text !== '' && $this->hasVisibleText($html)) {
                    $items[] = ['html' => $html, 'text' => $text];
                }
                foreach ($child->childNodes as $sub) {
                    if ($sub instanceof \DOMElement && in_array(strtolower($sub->nodeName), ['ul', 'ol'], true)) {
                        $this->collectListItems($sub, $items);
                    }
                }
                continue;
            }
            if (in_array($tag, ['ul', 'ol'], true)) $this->collectListItems($child, $items);
        }
    }

    private function textExcludingLists(\DOMNode $node, bool $normalize = true): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $out .= (string)$child->nodeValue;
                continue;
            }
            if (!$child instanceof \DOMElement) continue;
            if (in_array(strtolower($child->nodeName), ['ul', 'ol'], true)) continue;
            $out .= $this->textExcludingLists($child, false);
        }
        return $normalize ? $this->normalizeText($out) : $out;
    }

    /**
     * HTML de una celda de tabla. Las listas internas se separan con <br>
     * para que el valor no quede como una sola cadena ilegible.
     */
    private function cellHtml(\DOMElement $cell): string
    {
        $parts = [];
        foreach ($cell->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $text = $this->normalizeText((string)$child->nodeValue);
                if ($text !== '') $parts[] = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                continue;
            }
            if (!$child instanceof \DOMElement) continue;
            $tag = strtolower($child->nodeName);
            if (in_array($tag, ['ul', 'ol'], true)) {
                $items = [];
                foreach ($this->listItemTexts($child) as $text) {
                    $items[] = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }
                if ($items) $parts[] = implode('<br>', $items);
                continue;
            }
            if (in_array($tag, ['div', 'section', 'article', 'header', 'footer'], true)) {
                $inner = $this->cellHtml($child);
                if ($inner !== '') $parts[] = $inner;
                continue;
            }
            if (in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote'], true)) {
                $html = trim($this->cleanInline($child, ['ul', 'ol']));
                if ($html !== '') $parts[] = $html;
                foreach ($child->childNodes as $sub) {
                    if (!$sub instanceof \DOMElement || !in_array(strtolower($sub->nodeName), ['ul', 'ol'], true)) continue;
                    $items = [];
                    foreach ($this->listItemTexts($sub) as $text) {
                        $items[] = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    }
                    if ($items) $parts[] = implode('<br>', $items);
                }
                continue;
            }
            $html = trim($this->cleanInline($child, ['ul', 'ol']));
            if ($html !== '') $parts[] = $html;
        }
        return implode('<br>', array_values(array_filter($parts, static fn($v) => $v !== '')));
    }

    private function listItemTexts(\DOMElement $list): array
    {
        $out = [];
        foreach ($list->childNodes as $child) {
            if (!$child instanceof \DOMElement) continue;
            $tag = strtolower($child->nodeName);
            if ($tag === 'li') {
                $text = $this->textExcludingLists($child);
                if ($text !== '') $out[] = $text;
                foreach ($child->childNodes as $sub) {
                    if ($sub instanceof \DOMElement && in_array(strtolower($sub->nodeName), ['ul', 'ol'], true)) {
                        $out = array_merge($out, $this->listItemTexts($sub));
                    }
                }
                continue;
            }
            if (in_array($tag, ['ul', 'ol'], true)) $out = array_merge($out, $this->listItemTexts($child));
        }
        return $out;
    }

    private function listPairs(array $list): array
    {
        $items = $list['items'] ?? [];
        $count = count($items);
        if ($count < 3) return [];
        $pairs = [];
        $extras = [];
        foreach ($items as $item) {
            if (!preg_match('/^\s*([^:]{1,60}?)\s*:\s*(.+)$/u', $item['text'], $m)) {
                $extras[] = $item['html'];
                continue;
            }
            $label = trim($m[1]);
            $value = trim($m[2]);
            if ($label === '' || $value === '') {
                $extras[] = $item['html'];
                continue;
            }
            $pairs[] = [htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')];
        }
        if (count($pairs) < 3 || count($pairs) / $count < 0.6) return [];
        return ['pairs' => $pairs, 'extras' => $extras];
    }

    private function isGenericHeading(string $title): bool
    {
        $t = $this->normalizeText(mb_strtolower($title, 'UTF-8'));
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
        return (bool)preg_match('/^(descripcion( del producto)?|detalles?|informacion( adicional)?|caracteristicas( principales)?|especificaciones|resumen|contenido|producto)$/u', $t);
    }

    private function headingType(string $title): string
    {
        $t = $this->normalizeText(mb_strtolower($title, 'UTF-8'));
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
        if (preg_match('/[?¿]/u', $title)) return 'faq';
        if (preg_match('/\b(no incluye|nota|aviso|importante|advertencia|atenci[oó]n)\b/u', $t)) return 'details';
        if (preg_match('/\b(especificaciones|ficha tecnic|caracteristicas|funciones principales|detalles tecnicos|specs)\b/u', $t)) return 'specs';
        if (preg_match('/\b(contenido de la caja|que incluye|incluye|en la caja|contenido)\b/u', $t)) return 'includes';
        if (preg_match('/\b(preguntas frecuentes|faq|preguntas)\b/u', $t)) return 'faq';
        if (preg_match('/\b(garantia|cuidado|mantenimiento)\b/u', $t)) return 'care';
        if (preg_match('/\b(video|conocelo|en accion)\b/u', $t)) return 'video';
        return 'details';
    }

    private function splitParagraph(string $html, string $text): array
    {
        $plain = $this->normalizeText($text);
        if ($plain === '') return [];
        if (preg_match('~<br\s*/?>~i', $html)) {
            $lines = preg_split('~<br\s*/?>~i', $html) ?: [];
            $chunks = [];
            $buffer = [];
            $length = 0;
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                $lineLength = mb_strlen($this->plainText($line));
                if ($buffer && $length + $lineLength > self::LEAD_MAX) {
                    $chunks[] = implode('<br>', $buffer);
                    $buffer = [];
                    $length = 0;
                }
                $buffer[] = $line;
                $length += $lineLength;
            }
            if ($buffer) $chunks[] = implode('<br>', $buffer);
            return $chunks ?: [trim($html)];
        }
        $hasInlineTags = (bool)preg_match('~<(?!br\b)[a-z][^>]*>~i', $html);
        if ($hasInlineTags) return [trim($html)];
        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/u', $plain) ?: [$plain];
        $buffer = '';
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') continue;
            if ($buffer !== '' && mb_strlen($buffer . ' ' . $sentence) > self::LEAD_MAX) {
                $chunks[] = $buffer;
                $buffer = $sentence;
            } else {
                $buffer = $buffer === '' ? $sentence : $buffer . ' ' . $sentence;
            }
        }
        if ($buffer !== '') $chunks[] = $buffer;
        if (!$chunks) $chunks[] = $plain;
        return array_map(fn($chunk) => htmlspecialchars($chunk, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $chunks);
    }

    private function videoFromNode(\DOMElement $node, array $product): ?array
    {
        $title = trim((string)($node->getAttribute('title') ?? ''));
        if ($title === '') $title = trim((string)($product['title'] ?? 'Video del producto'));
        if (strtolower($node->nodeName) === 'video') {
            $src = trim((string)$node->getAttribute('src'));
            if ($src === '') {
                foreach ($node->childNodes as $child) {
                    if ($child instanceof \DOMElement && strtolower($child->nodeName) === 'source') {
                        $src = trim((string)$child->getAttribute('src'));
                        if ($src !== '') break;
                    }
                }
            }
            $src = $this->safeUrl($src);
            if ($src === '') return null;
            return ['provider' => 'file', 'id' => '', 'url' => $src, 'embed' => '', 'poster' => $this->safeUrl((string)$node->getAttribute('poster')), 'title' => $title];
        }
        if (strtolower($node->nodeName) !== 'iframe') return null;
        $src = trim((string)$node->getAttribute('src'));
        if ($src === '') return null;
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})~i', $src, $m)) {
            $id = $m[1];
            return [
                'provider' => 'youtube', 'id' => $id,
                'url' => 'https://www.youtube.com/watch?v=' . $id,
                'embed' => 'https://www.youtube-nocookie.com/embed/' . $id . '?autoplay=1&rel=0&modestbranding=1&playsinline=1',
                'poster' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
                'title' => $title,
            ];
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d{6,})~i', $src, $m)) {
            $id = $m[1];
            return [
                'provider' => 'vimeo', 'id' => $id,
                'url' => 'https://vimeo.com/' . $id,
                'embed' => 'https://player.vimeo.com/video/' . $id . '?autoplay=1',
                'poster' => '',
                'title' => $title,
            ];
        }
        return null;
    }

    private function imageFromNode(\DOMElement $node, array $product): ?array
    {
        $src = $this->safeImage((string)$node->getAttribute('src'));
        if ($src === '') $src = $this->safeImage((string)$node->getAttribute('data-src'));
        if ($src === '') return null;
        $width = (int)$node->getAttribute('width');
        $height = (int)$node->getAttribute('height');
        if ($width > 0 && $height > 0 && $width <= 2 && $height <= 2) return null;
        $alt = trim((string)$node->getAttribute('alt'));
        if ($alt === '') $alt = trim((string)($product['title'] ?? 'Producto SONIKA'));
        $caption = '';
        $parent = $node->parentNode;
        if ($parent instanceof \DOMElement && strtolower($parent->nodeName) === 'figure') {
            foreach ($parent->childNodes as $child) {
                if ($child instanceof \DOMElement && strtolower($child->nodeName) === 'figcaption') {
                    $caption = $this->nodeText($child);
                    break;
                }
            }
        }
        return ['src' => $src, 'alt' => $alt, 'caption' => $caption];
    }

    private function videoHtml(array $video, array $product): string
    {
        $title = $video['title'] !== '' ? $video['title'] : 'Video del producto';
        if ($video['provider'] === 'file') {
            return '<video class="pdp-video pdp-video--file" controls preload="metadata" playsinline' . ($video['poster'] !== '' ? ' poster="' . htmlspecialchars($video['poster'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '') . '><source src="' . htmlspecialchars($video['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></video>';
        }
        $poster = $video['poster'] !== '' ? '<img class="pdp-video__poster" src="' . htmlspecialchars($video['poster'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="" loading="lazy" decoding="async">' : '';
        return '<div class="pdp-video' . ($poster === '' ? ' pdp-video--empty' : '') . '" data-pdp-video data-provider="' . htmlspecialchars($video['provider'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" data-id="' . htmlspecialchars($video['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" data-src="' . htmlspecialchars($video['embed'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" data-title="' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . $poster
            . '<button type="button" class="pdp-video__play" data-pdp-video-play aria-label="Reproducir video: ' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"><svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13l11-6.5z"/></svg></button>'
            . '<a class="pdp-video__fallback" href="' . htmlspecialchars($video['url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" target="_blank" rel="noopener">Ver video</a>'
            . '</div>';
    }

    private function imageHtml(array $image): string
    {
        return '<figure class="pdp-feature__image"><img src="' . htmlspecialchars($image['src'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="' . htmlspecialchars($image['alt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" loading="lazy" decoding="async">' . ($image['caption'] !== '' ? '<figcaption>' . htmlspecialchars($image['caption'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</figcaption>' : '') . '</figure>';
    }

    private function emptyState(array $product): string
    {
        $title = htmlspecialchars((string)($product['title'] ?? 'este producto'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<div class="pdp-content pdp-content--empty"><div class="pdp-empty"><h2>Descripción en preparación</h2><p>Estamos ampliando la información de ' . $title . '. Si necesitas asesoría para elegir, nuestro equipo puede ayudarte.</p><a class="pdp-empty__cta" href="/soporte">Solicitar asesoría</a></div></div>';
    }

    private function fallback(string $html, array $product): string
    {
        $clean = preg_replace('~<(script|style|iframe|object|embed|form|input|button|select|textarea)\b.*?</\1>~is', '', $html) ?? '';
        $clean = strip_tags($clean, '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><a>');
        $clean = preg_replace('~\s(style|class|id|width|height|align|valign|bgcolor|color|face|size)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $clean) ?? $clean;
        $clean = preg_replace('~<h1\b~i', '<h2', $clean) ?? $clean;
        $clean = preg_replace('~</h1>~i', '</h2>', $clean) ?? $clean;
        $text = $this->plainText($clean);
        if ($text === '') return $this->emptyState($product);
        return '<div class="pdp-content pdp-content--fallback">' . $clean . '</div>';
    }

    private function hasSpecList(string $html): bool
    {
        if (!preg_match_all('~<li\b[^>]*>(.*?)</li>~is', $html, $m)) return false;
        $pairs = 0;
        foreach ($m[1] as $item) {
            $text = $this->plainText($item);
            if (preg_match('/^\s*[^:]{1,60}:\s*\S/u', $text)) $pairs++;
        }
        return $pairs >= 3;
    }

    private function plainText(string $html): string
    {
        $html = (string)preg_replace('/<!--.*?-->/s', ' ', $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);
        return $this->normalizeText($text);
    }

    private function nodeText(\DOMNode $node): string
    {
        return $this->normalizeText((string)$node->textContent);
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace("\xC2\xA0", ' ', $text);
        return trim((string)preg_replace('/\s+/u', ' ', $text));
    }

    private function hasVisibleText(string $html): bool
    {
        $text = $this->plainText($html);
        return $text !== '';
    }

    private function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if ($url[0] === '/' && !str_starts_with($url, '//')) return $url;
        if (str_starts_with($url, '#')) return $url;
        if (preg_match('~^https?://~i', $url)) return $url;
        if (preg_match('~^(mailto|tel):~i', $url)) return $url;
        return '';
    }

    private function safeImage(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (str_starts_with($url, '//')) return 'https:' . $url;
        if ($url[0] === '/' && !str_starts_with($url, '//')) return $url;
        if (preg_match('~^https?://~i', $url)) return $url;
        return '';
    }
}
