<?php
namespace App\Services;

use App\Core\Database;

/**
 * ProductSourceResearchService — descubrimiento y verificación de fichas
 * oficiales de producto.
 *
 * Principio: NO se usa una fuente si no se puede demostrar que es el mismo
 * producto. Cada candidato guarda evidencia: dominio oficial, marca, tokens de
 * modelo, SKU/MPN, similitud de título, tipo de página y conflictos.
 *
 * Las tiendas que bloquean bots (Cloudflare/403) no se fuerzan: quedan como
 * "requiere revisión manual" y el admin puede pegar la URL oficial.
 */
class ProductSourceResearchService
{
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36';
    private const MAX_DOMAINS = 4;
    private const MAX_CANDIDATES = 10;
    private const MAX_SOURCE_TEXT = 22000;

    /** Dominios oficiales por marca. Se pueden ampliar en settings. */
    public const DEFAULT_DOMAINS = [
        'fender' => ['fender.com'],
        'squier' => ['fender.com'],
        'jackson' => ['jacksonguitars.com'],
        'gretsch' => ['gretschdrums.com', 'gretschguitars.com'],
        'gibson' => ['gibson.com'],
        'epiphone' => ['epiphone.com'],
        'ibanez' => ['ibanez.com'],
        'esp' => ['espguitars.com'],
        'prs' => ['prsguitars.com'],
        'yamaha' => ['yamaha.com', 'usa.yamaha.com'],
        'casio' => ['casio.com'],
        'roland' => ['roland.com'],
        'boss' => ['boss.info'],
        'korg' => ['korg.com'],
        'nord' => ['nordkeyboards.com'],
        'moog' => ['moogmusic.com'],
        'arturia' => ['arturia.com'],
        'novation' => ['novationmusic.com'],
        'akai' => ['akaipro.com'],
        'shure' => ['shure.com'],
        'audio technica' => ['audio-technica.com'],
        'sennheiser' => ['sennheiser.com'],
        'electro voice' => ['electrovoice.com'],
        'akg' => ['akg.com'],
        'beyerdynamic' => ['beyerdynamic.com'],
        'rode' => ['rode.com'],
        'takstar' => ['takstar.com'],
        'behringer' => ['behringer.com'],
        'mackie' => ['mackie.com'],
        'qsc' => ['qsc.com'],
        'jbl' => ['jbl.com'],
        'pioneer' => ['pioneerdj.com'],
        'numark' => ['numark.com'],
        'denon' => ['denondj.com'],
        'focusrite' => ['focusrite.com'],
        'universal audio' => ['uaudio.com'],
        'presonus' => ['presonus.com'],
        'allen heath' => ['allen-heath.com'],
        'soundcraft' => ['soundcraft.com'],
        'marshall' => ['marshall.com'],
        'orange' => ['orangeamps.com'],
        'vox' => ['voxamps.com'],
        'blackstar' => ['blackstaramps.com'],
        'ampeg' => ['ampeg.com'],
        'hartke' => ['hartke.com'],
        'markbass' => ['markbass.it'],
        'ernie ball' => ['ernieball.com'],
        'daddario' => ['daddario.com'],
        'd addario' => ['daddario.com'],
        'elixir' => ['elixirstrings.com'],
        'ghs' => ['ghsstrings.com'],
        'dunlop' => ['jimdunlop.com'],
        'mxr' => ['jimdunlop.com'],
        'vic firth' => ['vicfirth.com'],
        'promark' => ['promark.com'],
        'remo' => ['remo.com'],
        'evans' => ['evansdrumheads.com'],
        'zildjian' => ['zildjian.com'],
        'sabian' => ['sabian.com'],
        'meinl' => ['meinlcymbals.com', 'meinlpercussion.com'],
        'paiste' => ['paiste.com'],
        'pearl' => ['pearldrum.com'],
        'tama' => ['tama.com'],
        'ludwig' => ['ludwig-drums.com'],
        'dw' => ['dwdrums.com'],
        'sonor' => ['sonor.com'],
        'mapex' => ['mapex.com'],
        'hohner' => ['hohner.de'],
        'lp' => ['lpmusic.com'],
        'gon bops' => ['gonbops.com'],
        'tycoon' => ['tycoonpercussion.com'],
        'miche' => ['miche.com.co'],
        'quik lok' => ['quiklok.com'],
        'ultimate support' => ['ultimatesupport.com'],
        'on stage' => ['onstagestands.com'],
        'hercules' => ['herculesstands.com'],
        'gator' => ['gatorco.com'],
        'skb' => ['skbcases.com'],
        'mono' => ['monocreators.com'],
        'pedaltrain' => ['pedaltrain.com'],
        'voodoo lab' => ['voodoolab.com'],
        'strymon' => ['strymon.net'],
        'tc electronic' => ['tcelectronic.com'],
        'digitech' => ['digitech.com'],
        'electro harmonix' => ['ehx.com'],
        'line 6' => ['line6.com'],
        'fractal' => ['fractalaudio.com'],
        'kemper' => ['kemper-amps.com'],
        'neural dsp' => ['neuraldsp.com'],
        'positive grid' => ['positivegrid.com'],
        'native instruments' => ['native-instruments.com'],
        'ableton' => ['ableton.com'],
        'steinberg' => ['steinberg.net'],
        'audient' => ['audient.com'],
        'solid state logic' => ['solidstatelogic.com'],
        'neumann' => ['neumann.com'],
        'warm audio' => ['warmaudio.com'],
        'm audio' => ['m-audio.com'],
        'alesis' => ['alesis.com'],
        'reloop' => ['reloop.com'],
        'rane' => ['rane.com'],
        'ortofon' => ['ortofon.com'],
        'samson' => ['samsontech.com'],
        'cad' => ['cadaudio.com'],
        'blue' => ['bluemic.com'],
        'apogee' => ['apogeedigital.com'],
        'avid' => ['avid.com'],
        'motu' => ['motu.com'],
        'rme' => ['rme-audio.de'],
        'antelope' => ['antelopeaudio.com'],
        'big dipper' => ['bigdipperlighting.com'],
    ];

    public function domainsForProduct(array $product): array
    {
        $vendor = $this->fold((string)($product['vendor'] ?? ''));
        if ($vendor === '') return [];
        $map = self::DEFAULT_DOMAINS;
        $custom = $this->setting('product_official_domains');
        if ($custom !== '') {
            $decoded = json_decode($custom, true);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $domains) {
                    $k = $this->fold((string)$key);
                    $list = is_array($domains) ? $domains : preg_split('/[\s,]+/', (string)$domains);
                    $list = array_values(array_filter(array_map('trim', (array)$list)));
                    if ($k !== '' && $list) $map[$k] = $list;
                }
            }
        }
        $out = [];
        foreach ($map as $key => $domains) {
            if ($vendor === $key || str_contains($vendor, $key) || str_contains($key, $vendor)) {
                foreach ((array)$domains as $d) {
                    $d = $this->normalizeDomain((string)$d);
                    if ($d !== '') $out[] = $d;
                }
            }
        }
        return array_values(array_unique($out));
    }

    /** @return array<int,array> candidatos verificados, ordenados por confianza */
    public function research(array $product, string $manualUrl = ''): array
    {
        $domains = $this->domainsForProduct($product);
        $urls = [];
        $titles = [];
        if ($manualUrl !== '' && filter_var($manualUrl, FILTER_VALIDATE_URL)) {
            $urls[$manualUrl] = ['title' => 'URL manual', 'manual' => true];
        }
        if ($domains) {
            $query = trim((string)($product['title'] ?? ''));
            $models = implode(' ', array_slice($this->modelTokens($query), 0, 3));
            foreach (array_slice($domains, 0, self::MAX_DOMAINS) as $domain) {
                foreach ($this->sitemapCandidates($domain, $product) as $item) {
                    $url = $item['url'] ?? '';
                    if ($url !== '') $urls[$url] = ['title' => (string)($item['title'] ?? ''), 'manual' => false];
                }
                $suggest = $this->shopifySuggest($domain, $models !== '' ? $models : $query);
                foreach ($suggest as $item) {
                    $url = $item['url'] ?? '';
                    if ($url !== '') $urls[$url] = ['title' => (string)($item['title'] ?? ''), 'manual' => false];
                }
                $bing = $this->bingSearch($domain, $models !== '' ? $models : $query, '');
                foreach ($bing as $item) {
                    $url = $item['url'] ?? '';
                    if ($url !== '') $urls[$url] = ['title' => (string)($item['title'] ?? ''), 'manual' => false];
                }
            }
        }
        // No se buscan minoristas: si no hay dominio oficial conocido, el admin
        // debe pegar la URL oficial manualmente.
        $urls = array_slice($urls, 0, self::MAX_CANDIDATES, true);
        if (!$urls) return [];

        $pages = $this->fetchMany(array_keys($urls));
        $candidates = [];
        foreach ($urls as $url => $meta) {
            $page = $pages[$url] ?? null;
            if (!$page || (int)$page['code'] >= 400 || trim((string)$page['body']) === '') {
                $host = $this->host($url);
                $candidates[] = $this->failedCandidate($product, $url, $host, (string)($meta['title'] ?? ''), 'No se pudo descargar la página (' . (int)($page['code'] ?? 0) . ').');
                continue;
            }
            $parsed = $this->parsePage((string)$page['body'], $url);
            $parsed['url'] = $url;
            $parsed['domain'] = $this->host($url);
            $official = $this->isOfficialHost($this->host($url), $domains);
            $verification = $this->verify($product, $parsed, $official, (bool)($meta['manual'] ?? false));
            $candidates[] = array_merge($parsed, $verification, [
                'url' => $url,
                'domain' => $this->host($url),
                'official' => $official,
                'manual' => (bool)($meta['manual'] ?? false),
                'fetched_at' => date('c'),
            ]);
        }
        usort($candidates, static fn($a, $b) => ((int)($b['confidence'] ?? 0)) <=> ((int)($a['confidence'] ?? 0)));
        return $candidates;
    }

    public function modelTokens(string $title): array
    {
        $title = mb_strtoupper($title, 'UTF-8');
        $title = strtr($title, ['–' => '-', '—' => '-', '−' => '-']);
        preg_match_all('/\b(?=[A-Z0-9\-]{2,24}\b)(?=[A-Z0-9\-]*[A-Z])(?=[A-Z0-9\-]*\d)[A-Z0-9][A-Z0-9\-]{1,23}\b/u', $title, $m);
        $tokens = [];
        foreach ($m[0] as $token) {
            $token = trim($token, '-');
            if ($token === '') continue;
            if (preg_match('/^\d{4}$/', $token)) continue;
            $tokens[] = $token;
        }
        return array_values(array_unique($tokens));
    }

    public function verify(array $product, array $candidate, bool $official, bool $manual = false): array
    {
        $ourTitle = (string)($product['title'] ?? '');
        $candTitle = (string)($candidate['title'] ?? '');
        $ourVendor = $this->fold((string)($product['vendor'] ?? ''));
        $candBrand = $this->fold((string)($candidate['brand'] ?? ''));
        $candTitleFold = $this->fold($candTitle);
        $candHost = $this->fold((string)($candidate['domain'] ?? ''));
        $ourTokens = $this->modelTokens($ourTitle);
        $candTokens = $this->modelTokens($candTitle . ' ' . (string)($candidate['url'] ?? '') . ' ' . (string)($candidate['sku'] ?? '') . ' ' . (string)($candidate['mpn'] ?? '') . ' ' . (string)($candidate['model'] ?? ''));
        $ourCompact = array_map([$this, 'compactToken'], $ourTokens);
        $candCompact = array_map([$this, 'compactToken'], $candTokens);
        $vendorCompact = $this->compactToken($ourVendor);
        $brandCompact = $this->compactToken($candBrand);
        $titleCompact = $this->compactToken($candTitle);
        $hostCompact = $this->compactToken($candHost);
        $brandMatch = $ourVendor !== '' && ($candBrand !== '' || $candTitleFold !== '' || $candHost !== '')
            ? ($vendorCompact !== '' && (
                ($brandCompact !== '' && (str_contains($brandCompact, $vendorCompact) || str_contains($vendorCompact, $brandCompact)))
                || str_contains($titleCompact, $vendorCompact)
                || str_contains($hostCompact, $vendorCompact)
            ))
            : null;
        if (in_array($ourVendor, ['especial', 'mpro', 'aurora', 'kz', 'pro dj', 'milenium'], true)) $brandMatch = null;

        $missing = [];
        $conflicts = [];
        if ($ourCompact) {
            foreach ($ourCompact as $token) {
                $found = false;
                foreach ($candCompact as $ct) {
                    if ($ct === $token || str_contains($ct, $token) || str_contains($token, $ct)) { $found = true; break; }
                }
                if (!$found) $missing[] = $token;
            }
            foreach ($candCompact as $ct) {
                $ours = false;
                foreach ($ourCompact as $token) {
                    if ($ct === $token || str_contains($ct, $token) || str_contains($token, $ct)) { $ours = true; break; }
                }
                if (!$ours && preg_match('/[A-Z]/', $ct) && preg_match('/\d/', $ct) && strlen($ct) >= 3) {
                    $conflicts[] = $ct;
                }
            }
        }
        $titleSim = $this->similarity($ourTitle, $candTitle);
        $significantOk = $ourTokens ? empty($missing) : $this->significantTokensMatch($ourTitle, $candTitle);
        $pageType = (string)($candidate['page_type'] ?? 'unknown');
        $isProduct = $pageType === 'product';
        $accPattern = '/\b(kit|bundle|case|stand|mount|windscreen|replacement|cover|bag|clip|adapter|adaptador|power supply|stage performance|pack)\b/u';
        $candText = $this->fold($candTitle . ' ' . (string)($candidate['url'] ?? '') . ' ' . (string)($candidate['sku'] ?? '') . ' ' . (string)($candidate['mpn'] ?? ''));
        if (preg_match($accPattern, $candText) && !preg_match($accPattern, $this->fold($ourTitle))) {
            $conflicts[] = 'accessory_or_kit';
        }

        $score = 0;
        if ($official) $score += 20;
        if ($brandMatch === true) $score += 15;
        if ($ourCompact && empty($missing)) $score += 30;
        if (!$ourCompact && $titleSim >= 88) $score += 30;
        if ($titleSim >= 75) $score += 10;
        if ($isProduct) $score += 10;
        if (!empty($candidate['sku']) || !empty($candidate['mpn'])) $score += 5;
        if (!empty($candidate['specs'])) $score += 5;
        if (!empty($candidate['videos'])) $score += 3;
        if ($manual && !$official) $score -= 10;
        if (!$isProduct) $score -= 30;
        if ($conflicts) $score -= 50;
        if ($missing) $score -= min(35, count($missing) * 15);
        if ($brandMatch === false && $ourVendor !== '' && !in_array($ourVendor, ['especial', 'mpro', 'aurora', 'kz', 'pro dj', 'milenium'], true)) $score -= 25;
        if (!$official) $score -= 20;
        $urlPath = (string)parse_url((string)($candidate['url'] ?? ''), PHP_URL_PATH);
        $urlCompact = $this->compactToken($urlPath);
        if ($ourCompact && $urlCompact) {
            foreach ($ourCompact as $i => $token) {
                $raw = (string)($ourTokens[$i] ?? $token);
                $baseRaw = preg_split('/[-\s\/]+/', $raw)[0] ?? $raw;
                $base = $this->compactToken($baseRaw);
                if (str_contains($urlCompact, $token) || (mb_strlen($base) >= 3 && str_contains($urlCompact, $base))) { $score += 5; break; }
            }
        }
        $score = max(0, min(100, $score));

        $status = 'needs_review';
        if (!$isProduct || $conflicts || $score < 55) $status = 'rejected';
        elseif ($score >= 85 && $official && empty($missing) && ($ourCompact || $titleSim >= 90)) $status = 'verified';
        elseif ($official && $score >= 65 && empty($conflicts)) $status = 'needs_review';

        return [
            'confidence' => $score,
            'status' => $status,
            'official' => $official,
            'brand_match' => $brandMatch,
            'title_similarity' => $titleSim,
            'our_tokens' => $ourTokens,
            'candidate_tokens' => $candTokens,
            'missing_tokens' => $missing,
            'conflicting_tokens' => $conflicts,
            'significant_match' => $significantOk,
            'page_type' => $pageType,
            'evidence' => [
                'rules' => [
                    'Dominio oficial verificado',
                    'Marca compatible',
                    'Tokens de modelo coincidentes',
                    'Página de producto (schema/compra)',
                    'Sin tokens de modelo en conflicto',
                ],
                'brand' => $ourVendor,
                'candidate_brand' => $candBrand,
                'title_similarity' => $titleSim,
                'our_tokens' => $ourTokens,
                'candidate_tokens' => $candTokens,
                'missing' => $missing,
                'conflicts' => $conflicts,
                'page_type' => $pageType,
                'official' => $official,
                'manual' => $manual,
            ],
        ];
    }

    private function failedCandidate(array $product, string $url, string $host, string $title, string $error): array
    {
        return [
            'url' => $url, 'domain' => $host, 'title' => $title, 'brand' => '', 'sku' => '', 'mpn' => '', 'model' => '',
            'description_html' => '', 'description_text' => '', 'specs' => [], 'features' => [], 'videos' => [], 'images' => [],
            'page_type' => 'unknown', 'confidence' => 0, 'status' => 'rejected', 'official' => false,
            'evidence' => ['error' => $error], 'error' => $error, 'fetched_at' => date('c'),
        ];
    }

    private function parsePage(string $html, string $url): array
    {
        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $ok = $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) return ['title' => '', 'description_html' => '', 'description_text' => '', 'page_type' => 'unknown'];

        $xpath = new \DOMXPath($doc);
        $jsonLd = $this->jsonLdProducts($doc);
        $product = $jsonLd[0] ?? [];
        $meta = $this->meta($xpath);
        $titleData = $this->bestTitle($xpath, $product, $meta);
        $title = $titleData['title'];
        $brand = '';
        if (is_array($product['brand'] ?? null)) $brand = (string)($product['brand']['name'] ?? '');
        elseif (is_string($product['brand'] ?? null)) $brand = (string)$product['brand'];
        $sku = (string)($product['sku'] ?? '');
        $mpn = (string)($product['mpn'] ?? ($product['model'] ?? ''));
        $pageType = $jsonLd ? 'product' : 'unknown';
        if ($pageType !== 'product') {
            $bodyFold = $this->fold($doc->textContent ?? '');
            if (preg_match('/add to cart|añadir al carrito|agregar al carrito|precio|price/u', $bodyFold) && preg_match('/producto|product/u', $this->fold($url))) $pageType = 'product';
        }

        $descriptionHtml = $this->mainDescriptionHtml($xpath, $doc);
        if ($descriptionHtml === '' && !empty($product['description'])) {
            $descriptionHtml = '<p>' . htmlspecialchars((string)$product['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }
        if ($descriptionHtml === '' && !empty($meta['og:description'])) {
            $descriptionHtml = '<p>' . htmlspecialchars((string)$meta['og:description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }
        $descriptionText = $this->plainText($descriptionHtml);
        $specs = $this->extractSpecs($xpath, $product);
        $features = $this->extractFeatures($xpath);
        $videos = $this->extractVideos($html, $product, $url);
        $images = $this->extractImages($html, $product, $meta, $url);

        return [
            'title' => $title,
            'title_variants' => $titleData['variants'],
            'brand' => $brand,
            'sku' => $sku,
            'mpn' => $mpn,
            'model' => $mpn,
            'description_html' => $descriptionHtml,
            'description_text' => mb_substr($descriptionText, 0, self::MAX_SOURCE_TEXT),
            'specs' => $specs,
            'features' => $features,
            'videos' => $videos,
            'images' => $images,
            'page_type' => $pageType,
            'meta' => $meta,
        ];
    }

    private function bestTitle(\DOMXPath $xpath, array $product, array $meta): array
    {
        $docTitle = trim((string)($xpath->query('//title')->item(0)?->textContent));
        $h1 = trim((string)($xpath->query('//h1')->item(0)?->textContent));
        $variants = [
            ['text' => $h1, 'priority' => 3, 'source' => 'h1'],
            ['text' => trim((string)($meta['og:title'] ?? '')), 'priority' => 2, 'source' => 'og:title'],
            ['text' => trim((string)($product['name'] ?? '')), 'priority' => 1, 'source' => 'jsonld'],
            ['text' => $docTitle, 'priority' => 0, 'source' => 'title'],
        ];
        $variants = array_values(array_filter($variants, static fn($v) => $v['text'] !== ''));
        usort($variants, function ($a, $b) {
            $ta = count($this->modelTokens((string)$a['text']));
            $tb = count($this->modelTokens((string)$b['text']));
            if ($ta !== $tb) return $tb <=> $ta;
            return (int)$b['priority'] <=> (int)$a['priority'];
        });
        return ['title' => (string)($variants[0]['text'] ?? ''), 'variants' => $variants];
    }

    private function jsonLdProducts(\DOMDocument $doc): array
    {
        $out = [];
        foreach ($doc->getElementsByTagName('script') as $script) {
            if (strtolower((string)$script->getAttribute('type')) !== 'application/ld+json') continue;
            $raw = trim((string)$script->textContent);
            if ($raw === '') continue;
            $data = json_decode($raw, true);
            if (!is_array($data)) continue;
            $nodes = isset($data['@graph']) && is_array($data['@graph']) ? $data['@graph'] : [$data];
            foreach ($nodes as $node) {
                if (!is_array($node)) continue;
                $types = $node['@type'] ?? '';
                $types = is_array($types) ? $types : [$types];
                foreach ($types as $type) {
                    if (strcasecmp((string)$type, 'Product') === 0) { $out[] = $node; break; }
                }
            }
        }
        return $out;
    }

    private function meta(\DOMXPath $xpath): array
    {
        $out = [];
        foreach ($xpath->query('//meta[@property or @name]') as $meta) {
            $key = strtolower(trim((string)($meta->getAttribute('property') ?: $meta->getAttribute('name'))));
            $value = trim((string)$meta->getAttribute('content'));
            if ($key !== '' && $value !== '' && !isset($out[$key])) $out[$key] = $value;
        }
        return $out;
    }

    private function mainDescriptionHtml(\DOMXPath $xpath, \DOMDocument $doc): string
    {
        $selectors = [
            '//*[@id="product-description" or @id="description" or @id="tab-description" or @id="productDetails"]',
            '//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"product-description")]',
            '//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"product__description")]',
            '//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"product-single__description")]',
            '//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"rte")]',
            '//*[contains(translate(@class,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"description")]',
        ];
        $best = '';
        $bestLen = 0;
        foreach ($selectors as $selector) {
            foreach ($xpath->query($selector) as $node) {
                if (!$node instanceof \DOMElement) continue;
                $clone = $node->cloneNode(true);
                $this->stripNoise($clone);
                $html = $doc->saveHTML($clone) ?: '';
                $len = mb_strlen($this->plainText($html));
                if ($len > $bestLen && $len >= 80) { $bestLen = $len; $best = $html; }
            }
            if ($bestLen >= 180) break;
        }
        if ($best === '') {
            foreach (['//article', '//main', '//body'] as $selector) {
                $node = $xpath->query($selector)->item(0);
                if (!$node instanceof \DOMElement) continue;
                $clone = $node->cloneNode(true);
                $this->stripNoise($clone);
                $html = $doc->saveHTML($clone) ?: '';
                $len = mb_strlen($this->plainText($html));
                if ($len > $bestLen) { $bestLen = $len; $best = $html; }
            }
        }
        return mb_substr(trim($best), 0, 60000);
    }

    private function stripNoise(\DOMNode $node): void
    {
        $remove = [];
        $walk = function (\DOMNode $n) use (&$walk, &$remove): void {
            foreach ($n->childNodes as $child) {
                if (!$child instanceof \DOMElement) continue;
                $tag = strtolower($child->nodeName);
                if (in_array($tag, ['script', 'style', 'noscript', 'template', 'nav', 'header', 'footer', 'aside', 'form', 'button', 'input', 'select', 'textarea', 'svg', 'iframe'], true)) {
                    $remove[] = $child;
                    continue;
                }
                $walk($child);
            }
        };
        $walk($node);
        foreach ($remove as $n) if ($n->parentNode) $n->parentNode->removeChild($n);
    }

    private function extractSpecs(\DOMXPath $xpath, array $product): array
    {
        $specs = [];
        $add = function (string $label, string $value) use (&$specs): void {
            $label = trim(preg_replace('/\s+/u', ' ', strip_tags($label)) ?: '');
            $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?: '');
            if ($label === '' || $value === '' || mb_strlen($label) > 70 || mb_strlen($value) > 220) return;
            if ($this->isNoiseText($label . ' ' . $value)) return;
            $key = mb_strtolower($label, 'UTF-8');
            if (!isset($specs[$key])) $specs[$key] = ['label' => $label, 'value' => $value];
        };
        foreach ((array)($product['additionalProperty'] ?? []) as $prop) {
            if (is_array($prop) && isset($prop['name'], $prop['value'])) $add((string)$prop['name'], (string)$prop['value']);
        }
        foreach ((array)($product['hasProperty'] ?? []) as $prop) {
            if (is_array($prop) && isset($prop['name'], $prop['value'])) $add((string)$prop['name'], (string)$prop['value']);
        }
        foreach ($xpath->query('//table//tr') as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $cell) {
                if ($cell instanceof \DOMElement && in_array(strtolower($cell->nodeName), ['td', 'th'], true)) {
                    $cells[] = trim((string)$cell->textContent);
                }
            }
            if (count($cells) === 2) $add($cells[0], $cells[1]);
        }
        foreach ($xpath->query('//dl') as $dl) {
            $dt = null;
            foreach ($dl->childNodes as $child) {
                if (!$child instanceof \DOMElement) continue;
                $tag = strtolower($child->nodeName);
                if ($tag === 'dt') $dt = trim((string)$child->textContent);
                elseif ($tag === 'dd' && $dt !== null) { $add($dt, trim((string)$child->textContent)); $dt = null; }
            }
        }
        foreach ($xpath->query('//li') as $li) {
            $text = trim((string)$li->textContent);
            if (preg_match('/^\s*([^:]{2,60})\s*:\s*(.{2,300})$/u', $text, $m)) $add($m[1], $m[2]);
        }
        return array_slice(array_values($specs), 0, 40);
    }

    private function extractFeatures(\DOMXPath $xpath): array
    {
        $out = [];
        foreach ($xpath->query('//li') as $li) {
            $text = trim(preg_replace('/\s+/u', ' ', (string)$li->textContent) ?: '');
            if ($text === '' || mb_strlen($text) < 25 || mb_strlen($text) > 220) continue;
            if ($this->isNoiseText($text)) continue;
            if (preg_match('/^\s*[^:]{2,60}\s*:\s*/u', $text)) continue;
            if (count(preg_split('/\s+/u', $text) ?: []) < 4) continue;
            if (in_array($text, $out, true)) continue;
            $out[] = $text;
            if (count($out) >= 10) break;
        }
        return $out;
    }

    private function extractVideos(string $html, array $product, string $url): array
    {
        $out = [];
        $add = static function (string $provider, string $id, string $url, string $title = '') use (&$out): void {
            $key = $provider . ':' . $id;
            if (!isset($out[$key])) $out[$key] = ['provider' => $provider, 'id' => $id, 'url' => $url, 'title' => $title];
        };
        $all = $html . ' ' . json_encode($product, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (preg_match_all('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})~i', $all, $m)) {
            foreach (array_unique($m[1]) as $id) $add('youtube', $id, 'https://www.youtube.com/watch?v=' . $id, (string)($product['name'] ?? ''));
        }
        if (preg_match_all('~vimeo\.com/(?:video/)?(\d{6,})~i', $all, $m)) {
            foreach (array_unique($m[1]) as $id) $add('vimeo', $id, 'https://vimeo.com/' . $id, (string)($product['name'] ?? ''));
        }
        if (preg_match_all('~<(?:video|source)[^>]+src=["\']([^"\']+\.(?:mp4|webm|mov)(?:\?[^"\']*)?)["\']~i', $html, $m)) {
            foreach (array_unique($m[1]) as $src) {
                $absolute = $this->absoluteUrl($src, $url);
                if ($absolute !== '') $add('file', md5($absolute), $absolute, (string)($product['name'] ?? ''));
            }
        }
        return array_values($out);
    }

    private function extractImages(string $html, array $product, array $meta, string $url): array
    {
        $out = [];
        $add = static function (string $u) use (&$out): void {
            $u = trim($u);
            if ($u !== '' && !in_array($u, $out, true)) $out[] = $u;
        };
        $img = $product['image'] ?? '';
        if (is_array($img)) $img = $img['url'] ?? ($img[0] ?? '');
        if (is_string($img)) $add($this->absoluteUrl($img, $url));
        foreach (['og:image', 'twitter:image'] as $k) if (!empty($meta[$k])) $add($this->absoluteUrl((string)$meta[$k], $url));
        if (preg_match_all('~<img[^>]+src=["\']([^"\']+)["\']~i', $html, $m)) {
            foreach (array_slice(array_unique($m[1]), 0, 8) as $src) $add($this->absoluteUrl($src, $url));
        }
        return array_values(array_filter($out));
    }

    private function bingSearch(string $domain, string $query, string $models = ''): array
    {
        $q = trim(($domain !== '' ? 'site:' . $domain . ' ' : '') . $query . ' ' . $models);
        $url = 'https://www.bing.com/search?q=' . rawurlencode($q) . '&setlang=en&cc=us';
        $res = $this->httpGet($url, 18);
        if ((int)$res['code'] >= 400 || $res['body'] === '') return [];
        $out = [];
        if (preg_match_all('~<li class="b_algo".*?</li>~is', $res['body'], $blocks)) {
            foreach (array_slice($blocks[0], 0, 12) as $block) {
                if (!preg_match('~<h2[^>]*>\s*<a[^>]+href="([^"]+)"[^>]*>(.*?)</a>~is', $block, $m)) continue;
                $href = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $href = $this->decodeBingUrl($href);
                $host = $this->host($href);
                if ($host === '') continue;
                if ($domain !== '' && !$this->isOfficialHost($host, [$domain])) continue;
                if (preg_match('~/(search|collections|blogs?|pages?|account|cart|checkout|login)(/|$|\?)~i', parse_url($href, PHP_URL_PATH) ?: '/')) continue;
                $out[$href] = ['url' => $href, 'title' => trim(strip_tags($m[2]))];
            }
        }
        return array_values($out);
    }

    /** Descubre URLs de producto desde el sitemap oficial (más fiable que Bing). */
    private function sitemapCandidates(string $domain, array $product): array
    {
        $urls = $this->sitemapUrls($domain);
        if (!$urls) return [];
        $title = (string)($product['title'] ?? '');
        $rawTokens = $this->modelTokens($title);
        $tokens = array_map([$this, 'compactToken'], $rawTokens);
        $words = $this->searchWords($title);
        $out = [];
        foreach ($urls as $url) {
            $path = (string)parse_url($url, PHP_URL_PATH);
            if ($path === '') continue;
            $pathFold = $this->fold($path);
            if (preg_match('~/(newsroom|insights|blog|news|support|faq|about|careers|contact|dealers?|where-to-buy|downloads?|manuals?|warranty|press|events?|merchandise|merch|socks|shirts?|hats?|ornaments?|bottle|stickers?|posters?|apparel|bundles?)(/|$)~i', $pathFold)) continue;
            $pathCompact = $this->compactToken($path);
            $score = 0;
            if ($tokens) {
                $matched = 0;
                foreach ($tokens as $i => $t) {
                    if ($t === '') continue;
                    $raw = (string)($rawTokens[$i] ?? $t);
                    $baseRaw = preg_split('/[-\s\/]+/', $raw)[0] ?? $raw;
                    $base = $this->compactToken($baseRaw);
                    if (str_contains($pathCompact, $t) || (mb_strlen($base) >= 3 && str_contains($pathCompact, $base))) $matched++;
                }
                if ($matched === 0) continue;
                $score += min(30, $matched * 15);
            } else {
                if (!$words) continue;
                $hits = 0;
                foreach (array_slice($words, 0, 3) as $w) {
                    if (mb_strlen($w) >= 4 && str_contains($pathFold, $w)) $hits++;
                }
                if ($hits < 1) continue;
                $score += $hits * 8;
            }
            if (str_contains($pathFold, 'product') || preg_match('~/(p|item)/~', $pathFold)) $score += 10;
            $score -= max(0, (int)(mb_strlen($path) / 200));
            $out[$url] = ['url' => $url, 'title' => '', 'score' => $score];
        }
        uasort($out, static fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice(array_values($out), 0, 6);
    }

    private function sitemapUrls(string $domain): array
    {
        $cacheDir = $this->rootPath() . '/public/assets/uploads/_content_lab/cache';
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0777, true);
        $cache = $cacheDir . '/sitemap_' . md5($domain) . '.json';
        if (is_file($cache) && time() - (int)filemtime($cache) < 43200) {
            $d = json_decode((string)@file_get_contents($cache), true);
            if (is_array($d) && isset($d['urls'])) return (array)$d['urls'];
        }
        $indexUrls = ['https://' . $domain . '/sitemap.xml', 'https://' . $domain . '/sitemap_index.xml', 'https://' . $domain . '/sitemap-index.xml'];
        $flat = [];
        $sub = [];
        foreach ($indexUrls as $u) {
            $res = $this->httpGet($u, 20);
            if ((int)$res['code'] >= 400 || $res['body'] === '') continue;
            $locs = $this->sitemapLocs((string)$res['body']);
            if (!$locs) continue;
            foreach ($locs as $loc) {
                if (preg_match('~\.xml(?:\?.*)?$~i', $loc)) $sub[] = $loc;
                else $flat[] = $loc;
            }
            if ($flat || $sub) break;
        }
        if ($sub) {
            $sub = array_values(array_unique($sub));
            usort($sub, static function ($a, $b) {
                $pa = str_contains(strtolower($a), 'product') ? 0 : 1;
                $pb = str_contains(strtolower($b), 'product') ? 0 : 1;
                return $pa <=> $pb;
            });
            $sub = array_slice($sub, 0, 24);
            $pages = $this->fetchMany($sub);
            foreach ($pages as $p) {
                if ((int)$p['code'] >= 400 || $p['body'] === '') continue;
                foreach ($this->sitemapLocs((string)$p['body']) as $loc) {
                    if (!preg_match('~\.xml(?:\?.*)?$~i', $loc)) $flat[] = $loc;
                }
                if (count($flat) >= 30000) break;
            }
        }
        $flat = array_values(array_unique($flat));
        $flat = array_slice($flat, 0, 30000);
        @file_put_contents($cache, json_encode(['ts' => time(), 'urls' => $flat], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $flat;
    }

    private function sitemapLocs(string $xml): array
    {
        if (stripos($xml, '<loc') === false) return [];
        preg_match_all('~<loc>\s*(?:<!\[CDATA\[)?\s*([^<\]\s]+)~i', $xml, $m);
        $out = [];
        foreach ($m[1] as $loc) {
            $loc = trim(html_entity_decode($loc, ENT_QUOTES | ENT_XML1, 'UTF-8'));
            if ($loc !== '') $out[] = $loc;
        }
        return $out;
    }

    private function searchWords(string $title): array
    {
        $skip = ['producto','amplificador','audifono','audifonos','guitarra','guitarras','bajo','bajos','bateria','baterias','microfono','microfonos','teclado','teclados','piano','pianos','acordeon','acordeones','baqueta','baquetas','plato','platillos','cable','cables','adaptador','soporte','soportes','pedal','pedales','consola','mixer','parlante','parlantes','monitor','monitores','controlador','sintetizador','profesional','original','nuevo','edicion','serie','instrumento','musical','musicales','color','modelo','unidad','par','set','kit','con','para','por','del','las','los','una','uno'];
        $parts = preg_split('/[^a-z0-9]+/u', $this->fold($title)) ?: [];
        return array_values(array_filter($parts, static fn($w) => mb_strlen($w) >= 3 && !in_array($w, $skip, true)));
    }

    private function rootPath(): string
    {
        return defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
    }

    private function shopifySuggest(string $domain, string $query): array
    {
        if ($query === '') return [];
        $url = 'https://' . $domain . '/search/suggest.json?q=' . rawurlencode($query) . '&resources%5Btype%5D=product&resources%5Blimit%5D=8';
        $res = $this->httpGet($url, 12);
        if ((int)$res['code'] >= 400) return [];
        $data = json_decode((string)$res['body'], true);
        if (!is_array($data)) return [];
        $products = (array)($data['resources']['results']['products'] ?? []);
        $out = [];
        foreach ($products as $p) {
            if (!is_array($p)) continue;
            $href = (string)($p['url'] ?? '');
            if ($href === '' && !empty($p['handle'])) $href = '/products/' . $p['handle'];
            if ($href === '') continue;
            $out[] = ['url' => $this->absoluteUrl($href, 'https://' . $domain . '/'), 'title' => (string)($p['title'] ?? '')];
        }
        return $out;
    }

    private function fetchMany(array $urls): array
    {
        $out = [];
        $mh = curl_multi_init();
        $handles = [];
        foreach ($urls as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 4,
                CURLOPT_TIMEOUT => 18,
                CURLOPT_CONNECTTIMEOUT => 7,
                CURLOPT_USERAGENT => self::UA,
                CURLOPT_ENCODING => '',
                CURLOPT_HTTPHEADER => ['Accept-Language: es-CO,es;q=0.9,en;q=0.8'],
            ]);
            curl_multi_add_handle($mh, $ch);
            $handles[$url] = $ch;
        }
        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) curl_multi_select($mh, 0.3);
        } while ($running && $status === CURLM_OK);
        foreach ($handles as $url => $ch) {
            $body = (string)curl_multi_getcontent($ch);
            $out[$url] = [
                'body' => $body,
                'code' => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE),
                'type' => (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
                'url' => (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
                'error' => curl_error($ch),
            ];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);
        return $out;
    }

    private function httpGet(string $url, int $timeout = 15): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT => self::UA,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => ['Accept-Language: es-CO,es;q=0.9,en;q=0.8'],
        ]);
        $body = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['body' => $body, 'code' => $code, 'type' => $type, 'error' => $err];
    }

    private function decodeBingUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!isset($parts['host']) || !str_contains(strtolower($parts['host']), 'bing.com')) return $url;
        parse_str((string)($parts['query'] ?? ''), $q);
        $u = (string)($q['u'] ?? '');
        if ($u === '') return $url;
        if (str_starts_with($u, 'a1')) {
            $b64 = substr($u, 2);
            $b64 = strtr($b64, '-_', '+/');
            $b64 .= str_repeat('=', (4 - strlen($b64) % 4) % 4);
            $decoded = base64_decode($b64, true);
            if ($decoded !== false && filter_var($decoded, FILTER_VALIDATE_URL)) return $decoded;
        }
        if (filter_var($u, FILTER_VALIDATE_URL)) return $u;
        return $url;
    }

    private function isOfficialHost(string $host, array $domains): bool
    {
        $host = $this->normalizeDomain($host);
        foreach ($domains as $domain) {
            $domain = $this->normalizeDomain($domain);
            if ($domain !== '' && ($host === $domain || str_ends_with($host, '.' . $domain))) return true;
        }
        return false;
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('~^https?://~i', '', $domain) ?: $domain;
        $domain = preg_replace('~/.*$~', '', $domain) ?: $domain;
        $domain = preg_replace('~^www\.~', '', $domain) ?: $domain;
        return trim($domain, ". \t\n\r\0\x0B");
    }

    private function host(string $url): string
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        return $this->normalizeDomain($host);
    }

    private function absoluteUrl(string $url, string $base): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '' || str_starts_with($url, 'data:')) return '';
        if (str_starts_with($url, '//')) return 'https:' . $url;
        if (filter_var($url, FILTER_VALIDATE_URL)) return $url;
        $p = parse_url($base);
        if (!$p || empty($p['scheme']) || empty($p['host'])) return '';
        $root = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        if (str_starts_with($url, '/')) return $root . $url;
        $path = (string)($p['path'] ?? '/');
        $dir = substr($path, 0, strrpos($path, '/') + 1) ?: '/';
        return $root . $dir . $url;
    }

    private function isNoiseText(string $text): bool
    {
        return (bool)preg_match('/read article|read more|learn more|opens in a new tab|tech portal|shurecloud|product support|united states|newsroom|blog|podcast|careers|contact us|where to buy|privacy policy|terms of use|cookie|subscribe|newsletter|download manual|user guide|warranty information/iu', $text);
    }

    private function plainText(string $html): string
    {
        $text = preg_replace('~<(script|style|noscript|template)\b.*?</\1>~is', ' ', $html) ?? $html;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text) ?: '');
    }

    private function fold(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        return strtr($s, ['á'=>'a','à'=>'a','ä'=>'a','â'=>'a','ã'=>'a','é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','í'=>'i','ì'=>'i','ï'=>'i','î'=>'e','ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','ñ'=>'n','ç'=>'c','´'=>'','"'=>'','\''=>'']);
    }

    private function compactToken(string $s): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($s, 'UTF-8')) ?: '';
    }

    private function similarity(string $a, string $b): int
    {
        $a = $this->fold($a);
        $b = $this->fold($b);
        if ($a === '' || $b === '') return 0;
        similar_text($a, $b, $pct);
        return (int)round($pct);
    }

    private function significantTokensMatch(string $ourTitle, string $candTitle): bool
    {
        $skip = ['producto','amplificador','audifono','audifonos','guitarra','guitarras','bajo','bajos','bateria','baterias','microfono','microfonos','teclado','teclados','piano','pianos','acordeon','acordeones','baqueta','baquetas','plato','platillos','cable','cables','adaptador','soporte','soportes','pedal','pedales','consola','mixer','parlante','parlantes','monitor','monitores','controlador','sintetizador','profesional','original','nuevo','edicion','serie','instrumento','musical','musicales','color','modelo','unidad','par','set','kit','con','para','por','del','las','los','una','uno','the','and','with','for'];
        $words = function (string $s) use ($skip): array {
            $parts = preg_split('/[^a-z0-9]+/u', $this->fold($s)) ?: [];
            return array_values(array_filter($parts, static fn($w) => $w !== '' && mb_strlen($w) >= 3 && !in_array($w, $skip, true)));
        };
        $ours = $words($ourTitle);
        $cands = $words($candTitle);
        if (!$ours) return false;
        $hit = 0;
        foreach ($ours as $w) {
            foreach ($cands as $c) {
                if ($w === $c || str_contains($c, $w) || str_contains($w, $c)) { $hit++; break; }
            }
        }
        return $hit / max(1, count($ours)) >= 0.85;
    }

    private function setting(string $key): string
    {
        try {
            $st = Database::instance()->prepare('SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1');
            $st->execute([$key]);
            $v = $st->fetchColumn();
            return $v === false ? '' : (string)$v;
        } catch (\Throwable $e) {
            return '';
        }
    }
}
