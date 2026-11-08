<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\AiBrick\AiRouter;

/**
 * ProductContentLabService — Content Fix Lab de SONIKA.
 *
 * Etapas:
 *  1. Descubrir fuentes oficiales.
 *  2. Verificar identidad exacta (marca, modelo, SKU/MPN, página de producto).
 *  3. Extraer descripción, ficha y videos.
 *  4. Generar con TIA una descripción estructurada.
 *  5. Revisión humana y aplicación con rollback.
 *  6. Video: sandbox + YouTube (solo con derechos confirmados).
 */
class ProductContentLabService
{
    public const STAGES = [
        'sources' => 'Buscar fuentes oficiales',
        'source_review' => 'Confirmar identidad del producto',
        'extract' => 'Extraer contenido verificado',
        'generate' => 'Generar con TIA',
        'review' => 'Revisión humana',
        'apply' => 'Aplicar en el sitio',
        'video' => 'Video oficial / YouTube',
    ];

    public const STATUS_LABELS = [
        'new' => 'Sin iniciar',
        'researching' => 'Investigando',
        'needs_review' => 'Requiere confirmación',
        'verified' => 'Fuente confirmada',
        'generated' => 'Descripción generada',
        'applied' => 'Aplicada',
        'rolled_back' => 'Revertida',
        'miche_update' => 'Actualización Miche',
        'failed' => 'Sin coincidencia',
    ];

    private ProductSourceResearchService $research;

    public function __construct()
    {
        ProductContentLabSchemaService::ensure();
        $this->research = new ProductSourceResearchService();
    }

    public function summary(): array
    {
        $db = Database::instance();
        $scalar = static function (string $sql) use ($db) {
            try { return $db->query($sql)->fetchColumn(); } catch (\Throwable $e) { return 0; }
        };
        $jobs = [];
        try {
            foreach ($db->query("SELECT status, COUNT(*) n FROM product_content_jobs WHERE site_id = @site_id GROUP BY status")->fetchAll() as $r) $jobs[(string)$r['status']] = (int)$r['n'];
        } catch (\Throwable $e) {
        }
        $total = (int)$scalar("SELECT COUNT(*) FROM store_products WHERE site_id = @site_id AND status = 'active'");
        $empty = (int)$scalar("SELECT COUNT(*) FROM store_products WHERE site_id = @site_id AND status = 'active' AND (description IS NULL OR description = '' OR CHAR_LENGTH(TRIM(description)) < 20)");
        $withDesc = (int)$scalar("SELECT COUNT(*) FROM store_products WHERE site_id = @site_id AND status = 'active' AND CHAR_LENGTH(TRIM(COALESCE(description,''))) >= 20");
        $last = '';
        try { $last = (string)$db->query("SELECT MAX(updated_at) FROM product_content_jobs WHERE site_id = @site_id")->fetchColumn(); } catch (\Throwable $e) {}
        return [
            'ok' => true,
            'products' => $total,
            'with_description' => $withDesc,
            'empty' => $empty,
            'jobs' => $jobs,
            'applied' => (int)($jobs['applied'] ?? 0),
            'needs_review' => (int)($jobs['needs_review'] ?? 0),
            'generated' => (int)($jobs['generated'] ?? 0),
            'failed' => (int)($jobs['failed'] ?? 0),
            'miche_updates' => (int)($jobs['miche_update'] ?? 0),
            'last_activity' => $last,
            'stages' => self::STAGES,
            'statuses' => self::STATUS_LABELS,
            'official_domains' => count(ProductSourceResearchService::DEFAULT_DOMAINS),
        ];
    }

    public function listProducts(array $filters = []): array
    {
        $db = Database::instance();
        $q = trim((string)($filters['q'] ?? ''));
        $filter = (string)($filters['filter'] ?? 'empty');
        $status = trim((string)($filters['status'] ?? ''));
        $vendor = trim((string)($filters['vendor'] ?? ''));
        $limit = max(10, min(200, (int)($filters['limit'] ?? 50)));
        $offset = max(0, (int)($filters['offset'] ?? 0));
        $where = ["p.site_id = @site_id", "p.status = 'active'"];
        $params = [];
        if ($q !== '') {
            $where[] = '(p.name LIKE :q1 OR p.slug LIKE :q2 OR p.vendor LIKE :q3 OR p.catalog_handle LIKE :q4)';
            $needle = '%' . $q . '%';
            $params['q1'] = $needle;
            $params['q2'] = $needle;
            $params['q3'] = $needle;
            $params['q4'] = $needle;
        }
        if ($vendor !== '') {
            $where[] = 'p.vendor = :vendor';
            $params['vendor'] = $vendor;
        }
        if ($filter === 'empty') $where[] = "(p.description IS NULL OR p.description = '' OR CHAR_LENGTH(TRIM(p.description)) < 20)";
        if ($status !== '') {
            if ($status === 'no_job') $where[] = 'j.id IS NULL';
            else { $where[] = 'j.status = :jstatus'; $params['jstatus'] = $status; }
        }
        $whereSql = implode(' AND ', $where);
        $base = "FROM store_products p LEFT JOIN product_content_jobs j ON j.site_id = p.site_id AND (j.product_id = p.id OR j.handle = COALESCE(NULLIF(p.catalog_handle,''), p.slug)) WHERE $whereSql";
        $total = 0;
        try {
            $st = $db->prepare("SELECT COUNT(*) $base");
            $st->execute($params);
            $total = (int)$st->fetchColumn();
        } catch (\Throwable $e) {
        }
        $rows = [];
        try {
            $sql = "SELECT p.id, p.name, p.slug, p.vendor, p.product_type, p.catalog_handle, p.description, p.short_description, p.seo_title, p.seo_description,
                j.id job_id, j.status job_status, j.stage job_stage, j.confidence job_confidence, j.generated_score, j.source_url, j.source_domain,
                j.miche_recommendation, j.video_status, j.youtube_url, j.applied_at, j.updated_at job_updated_at
                $base ORDER BY (j.id IS NOT NULL) DESC, (CHAR_LENGTH(TRIM(COALESCE(p.description,''))) < 20) DESC, p.name ASC LIMIT $limit OFFSET $offset";
            $st = $db->prepare($sql);
            $st->execute($params);
            $rows = $st->fetchAll();
        } catch (\Throwable $e) {
        }
        $svc = new ProductContentService();
        $items = [];
        foreach ($rows as $r) {
            $analysis = $svc->analyze(['body_html' => (string)($r['description'] ?? ''), 'title' => (string)$r['name']]);
            $items[] = [
                'id' => (int)$r['id'],
                'name' => (string)$r['name'],
                'slug' => (string)$r['slug'],
                'vendor' => (string)($r['vendor'] ?? ''),
                'product_type' => (string)($r['product_type'] ?? ''),
                'catalog_handle' => (string)($r['catalog_handle'] ?? ''),
                'description_chars' => (int)$analysis['chars'],
                'score' => (int)$analysis['score'],
                'issues' => $analysis['issues'],
                'issue_keys' => $analysis['issue_keys'],
                'job_id' => (int)($r['job_id'] ?? 0),
                'job_status' => (string)($r['job_status'] ?? ''),
                'job_status_label' => self::STATUS_LABELS[(string)($r['job_status'] ?? '')] ?? 'Sin iniciar',
                'job_stage' => (string)($r['job_stage'] ?? ''),
                'confidence' => (int)($r['job_confidence'] ?? 0),
                'generated_score' => (int)($r['generated_score'] ?? 0),
                'source_url' => (string)($r['source_url'] ?? ''),
                'source_domain' => (string)($r['source_domain'] ?? ''),
                'miche_recommendation' => (string)($r['miche_recommendation'] ?? ''),
                'video_status' => (string)($r['video_status'] ?? 'none'),
                'youtube_url' => (string)($r['youtube_url'] ?? ''),
                'applied_at' => (string)($r['applied_at'] ?? ''),
                'job_updated_at' => (string)($r['job_updated_at'] ?? ''),
                'live_url' => $this->liveUrl($r),
            ];
        }
        return ['ok' => true, 'items' => $items, 'total' => $total, 'limit' => $limit, 'offset' => $offset, 'filter' => $filter];
    }

    public function product(int $id): ?array
    {
        try {
            $st = Database::instance()->prepare('SELECT * FROM store_products WHERE id = ? AND site_id = @site_id LIMIT 1');
            $st->execute([$id]);
            $r = $st->fetch();
            if (!$r) return null;
            $r['tags'] = json_decode((string)($r['tags'] ?? ''), true) ?: [];
            $r['images'] = json_decode((string)($r['images'] ?? ''), true) ?: [];
            $r['title'] = (string)($r['name'] ?? '');
            $r['body_html'] = (string)($r['description'] ?? '');
            return $r;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function job(int $id): ?array
    {
        try {
            $st = Database::instance()->prepare('SELECT * FROM product_content_jobs WHERE id = ? AND site_id = @site_id LIMIT 1');
            $st->execute([$id]);
            $r = $st->fetch();
            return $r ? $this->hydrateJob($r) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function jobForProduct(int $productId): ?array
    {
        try {
            $st = Database::instance()->prepare('SELECT * FROM product_content_jobs WHERE site_id = @site_id AND product_id = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$productId]);
            $r = $st->fetch();
            return $r ? $this->hydrateJob($r) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function research(int $productId, string $manualUrl, string $by = 'admin'): array
    {
        $product = $this->product($productId);
        if (!$product) return ['ok' => false, 'message' => 'Producto no encontrado.'];
        $jobId = $this->ensureJob($product, $by);
        $this->updateJob($jobId, ['status' => 'researching', 'stage' => 'sources', 'error' => null]);
        $this->log($jobId, $productId, (string)$product['slug'], 'research', 'ok', 'Buscando en dominios oficiales: ' . implode(', ', $this->research->domainsForProduct($product)), $by);
        try {
            $candidates = $this->research->research($product, trim($manualUrl));
        } catch (\Throwable $e) {
            $candidates = [];
            $this->updateJob($jobId, ['status' => 'failed', 'stage' => 'sources', 'error' => 'Error de investigación: ' . $e->getMessage()]);
            $this->log($jobId, $productId, (string)$product['slug'], 'research', 'error', $e->getMessage(), $by);
            return ['ok' => false, 'message' => 'No se pudo investigar: ' . $e->getMessage(), 'job' => $this->job($jobId)];
        }
        if (!$candidates) {
            $this->updateJob($jobId, ['status' => 'failed', 'stage' => 'sources', 'error' => 'No se encontraron fuentes oficiales. Puedes pegar una URL oficial manualmente.']);
            $this->log($jobId, $productId, (string)$product['slug'], 'research', 'empty', 'Sin candidatos oficiales.', $by);
            return ['ok' => false, 'message' => 'No se encontraron fuentes oficiales. Pega una URL oficial manualmente.', 'job' => $this->job($jobId)];
        }
        $best = null;
        foreach ($candidates as $c) if (($c['status'] ?? '') === 'verified') { $best = $c; break; }
        if (!$best) {
            foreach ($candidates as $c) if (($c['status'] ?? '') === 'needs_review' && (int)($c['confidence'] ?? 0) >= 55) { $best = $c; break; }
        }
        if (!$best) $best = $candidates[0];
        $status = ((string)($best['status'] ?? '') === 'verified') ? 'needs_review' : (((int)($best['confidence'] ?? 0) >= 55) ? 'needs_review' : 'failed');
        $error = $status === 'failed' ? 'Ninguna fuente alcanzó el umbral de coincidencia exacta. Revisa los candidatos o usa una URL oficial manual.' : null;
        $source = [
            'source_url' => (string)($best['url'] ?? ''),
            'source_domain' => (string)($best['domain'] ?? ''),
            'source_title' => (string)($best['title'] ?? ''),
            'source_text' => (string)($best['description_text'] ?? ''),
            'source_html' => (string)($best['description_html'] ?? ''),
            'source_specs' => json_encode($best['specs'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'source_features' => json_encode($best['features'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'source_videos' => json_encode($best['videos'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'candidates' => json_encode($candidates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'evidence' => json_encode($best['evidence'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'confidence' => (int)($best['confidence'] ?? 0),
            'status' => $status,
            'stage' => 'source_review',
            'error' => $error,
        ];
        $this->updateJob($jobId, $source);
        $this->log($jobId, $productId, (string)$product['slug'], 'research', $status === 'failed' ? 'warning' : 'ok', 'Mejor candidato: ' . ($best['url'] ?? '—') . ' · confianza ' . (int)($best['confidence'] ?? 0) . '% · ' . ($best['status'] ?? ''), $by);
        return ['ok' => $status !== 'failed', 'message' => $status === 'failed' ? $error : 'Fuente encontrada. Confirma que es exactamente el mismo producto antes de generar.', 'job' => $this->job($jobId), 'candidates' => $candidates];
    }

    public function confirmSource(int $jobId, int $index, bool $confirm, string $by = 'admin', string $manualUrl = ''): array
    {
        $job = $this->job($jobId);
        if (!$job) return ['ok' => false, 'message' => 'Trabajo no encontrado.'];
        if ($manualUrl !== '') {
            return $this->research((int)($job['product_id'] ?? 0), $manualUrl, $by);
        }
        $candidates = (array)($job['candidates'] ?? []);
        $candidate = $candidates[$index] ?? null;
        if (!$candidate) return ['ok' => false, 'message' => 'Candidato no encontrado.'];
        if (($candidate['status'] ?? '') === 'rejected') return ['ok' => false, 'message' => 'Este candidato fue rechazado por las reglas de identidad. No se puede confirmar.'];
        if (!$confirm) return ['ok' => false, 'message' => 'Debes confirmar que es exactamente el mismo producto.'];
        $this->updateJob($jobId, [
            'source_url' => (string)($candidate['url'] ?? ''),
            'source_domain' => (string)($candidate['domain'] ?? ''),
            'source_title' => (string)($candidate['title'] ?? ''),
            'source_text' => (string)($candidate['description_text'] ?? ''),
            'source_html' => (string)($candidate['description_html'] ?? ''),
            'source_specs' => json_encode($candidate['specs'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'source_features' => json_encode($candidate['features'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'source_videos' => json_encode($candidate['videos'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'evidence' => json_encode($candidate['evidence'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'confidence' => (int)($candidate['confidence'] ?? 0),
            'status' => 'verified',
            'stage' => 'source_confirmed',
            'error' => null,
        ]);
        $this->log($jobId, (int)($job['product_id'] ?? 0), (string)$job['handle'], 'source_confirmed', 'ok', 'Fuente confirmada manualmente: ' . ($candidate['url'] ?? ''), $by);
        return ['ok' => true, 'message' => 'Fuente confirmada. Ya puedes generar la descripción con TIA.', 'job' => $this->job($jobId)];
    }

    public function generate(int $jobId, string $by = 'admin'): array
    {
        $job = $this->job($jobId);
        if (!$job) return ['ok' => false, 'message' => 'Trabajo no encontrado.'];
        if (!in_array((string)$job['status'], ['verified', 'generated'], true)) {
            return ['ok' => false, 'message' => 'Primero confirma una fuente oficial para este producto.'];
        }
        $product = $this->product((int)($job['product_id'] ?? 0));
        if (!$product) return ['ok' => false, 'message' => 'Producto no encontrado.'];
        $specs = (array)($job['source_specs'] ?? []);
        $features = (array)($job['source_features'] ?? []);
        $sourceText = (string)($job['source_text'] ?? '');
        if (mb_strlen($sourceText) < 80 && !$specs) return ['ok' => false, 'message' => 'La fuente no tiene suficiente contenido para generar una descripción verificable.'];
        $result = $this->aiGenerate($product, $job, $specs, $features, $sourceText);
        if (empty($result['ok'])) {
            $result = $this->fallbackGenerate($product, $job, $specs, $features, $sourceText);
            $result['warning'] = ($result['warning'] ?? '') . ' Se usó el generador determinista porque TIA no respondió.';
        }
        $structured = $result['structured'] ?? [];
        $semantic = $this->semanticHtml($structured, $product, (string)($job['youtube_id'] ?? ''));
        $preview = $this->preview($semantic, $product);
        $this->updateJob($jobId, [
            'generated_json' => json_encode($structured, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'generated_html' => $semantic,
            'generated_preview' => $preview['html'],
            'generated_score' => (int)($preview['score'] ?? 0),
            'ai_model' => (string)($result['model'] ?? ''),
            'ai_provider' => (string)($result['provider'] ?? ''),
            'tokens_used' => (int)($result['tokens'] ?? 0),
            'status' => 'generated',
            'stage' => 'generated',
            'error' => null,
        ]);
        $this->log($jobId, (int)($job['product_id'] ?? 0), (string)$job['handle'], 'generate', 'ok', 'Descripción generada. Score editorial: ' . (int)($preview['score'] ?? 0) . '/100.', $by);
        return ['ok' => true, 'message' => 'Descripción generada con TIA. Revísala y aplícala.', 'job' => $this->job($jobId), 'preview' => $preview, 'warning' => $result['warning'] ?? null];
    }

    public function apply(int $jobId, string $by = 'admin', bool $overwriteSeo = false): array
    {
        $job = $this->job($jobId);
        if (!$job) return ['ok' => false, 'message' => 'Trabajo no encontrado.'];
        $html = (string)($job['generated_html'] ?? '');
        if ($html === '') return ['ok' => false, 'message' => 'Primero genera la descripción.'];
        $product = $this->product((int)($job['product_id'] ?? 0));
        if (!$product) return ['ok' => false, 'message' => 'Producto no encontrado.'];
        $json = (array)($job['generated_json'] ?? []);
        $short = trim((string)($json['summary'] ?? ''));
        if ($short === '') $short = mb_substr(trim(strip_tags($html)), 0, 500);
        $short = mb_substr($short, 0, 500);
        $this->saveRevision($job, $product, $html, $short, 'lab', $by);
        try {
            $db = Database::instance();
            $seoTitle = $overwriteSeo ? mb_substr((string)($json['seo_title'] ?? ''), 0, 255) : '';
            $seoDesc = $overwriteSeo ? mb_substr((string)($json['seo_description'] ?? ''), 0, 500) : '';
            $db->prepare("UPDATE store_products SET description = ?, short_description = ?,
                seo_title = CASE WHEN ? <> '' THEN ? ELSE seo_title END,
                seo_description = CASE WHEN ? <> '' THEN ? ELSE seo_description END
                WHERE id = ? AND site_id = @site_id")
                ->execute([$html, $short, $seoTitle, $seoTitle, $seoDesc, $seoDesc, (int)$product['id']]);
            $this->writeCatalogBody((string)($product['catalog_handle'] ?: $product['slug']), $html);
            try { (new CatalogReconciliationService())->clearIndexCaches(); } catch (\Throwable $e) {}
            $live = $this->liveUrl($product);
            $this->updateJob($jobId, [
                'previous_description' => (string)($product['description'] ?? ''),
                'previous_short' => (string)($product['short_description'] ?? ''),
                'status' => 'applied',
                'stage' => 'applied',
                'applied_by' => mb_substr($by, 0, 190),
                'applied_at' => date('Y-m-d H:i:s'),
                'live_url' => $live,
                'error' => null,
            ]);
            $this->log($jobId, (int)$product['id'], (string)$product['slug'], 'apply', 'ok', 'Descripción aplicada y publicada.', $by);
            return ['ok' => true, 'message' => 'Descripción aplicada. Ya está visible en el sitio.', 'job' => $this->job($jobId), 'live_url' => $live];
        } catch (\Throwable $e) {
            $this->log($jobId, (int)$product['id'], (string)$product['slug'], 'apply', 'error', $e->getMessage(), $by);
            return ['ok' => false, 'message' => 'No se pudo aplicar: ' . $e->getMessage()];
        }
    }

    public function rollback(int $jobId, string $by = 'admin'): array
    {
        $job = $this->job($jobId);
        if (!$job) return ['ok' => false, 'message' => 'Trabajo no encontrado.'];
        $db = Database::instance();
        try {
            $st = $db->prepare('SELECT * FROM product_content_revisions WHERE site_id = @site_id AND job_id = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$jobId]);
            $rev = $st->fetch();
            if (!$rev) return ['ok' => false, 'message' => 'No hay una revisión anterior para revertir.'];
            $product = $this->product((int)($job['product_id'] ?? 0));
            if (!$product) return ['ok' => false, 'message' => 'Producto no encontrado.'];
            $db->prepare('UPDATE store_products SET description = ?, short_description = ? WHERE id = ? AND site_id = @site_id')
                ->execute([(string)($rev['previous_description'] ?? ''), (string)($rev['previous_short'] ?? ''), (int)$product['id']]);
            $this->writeCatalogBody((string)($product['catalog_handle'] ?: $product['slug']), (string)($rev['previous_description'] ?? ''));
            $this->updateJob($jobId, ['status' => 'rolled_back', 'stage' => 'review', 'applied_at' => null, 'live_url' => $this->liveUrl($product)]);
            $this->log($jobId, (int)$product['id'], (string)$product['slug'], 'rollback', 'ok', 'Se restauró la descripción anterior.', $by);
            return ['ok' => true, 'message' => 'Descripción anterior restaurada.', 'job' => $this->job($jobId)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo revertir: ' . $e->getMessage()];
        }
    }

    public function compareMiche(int $jobId): array
    {
        $job = $this->job($jobId);
        if (!$job) return ['ok' => false, 'message' => 'Trabajo no encontrado.'];
        $product = $this->product((int)($job['product_id'] ?? 0));
        if (!$product) return ['ok' => false, 'message' => 'Producto no encontrado.'];
        $miche = $this->micheProduct($product);
        if (empty($miche['ok'])) {
            $this->log($jobId, (int)$product['id'], (string)$product['slug'], 'miche_compare', 'error', (string)($miche['message'] ?? ''), 'admin');
            return ['ok' => false, 'message' => $miche['message'] ?? 'No se pudo consultar Miche.'];
        }
        $ours = $this->quality((string)($product['description'] ?? ''));
        $theirs = $this->quality((string)($miche['body_html'] ?? ''));
        $recommendation = $this->micheRecommendation($ours, $theirs);
        $data = $miche + ['ours' => $ours, 'miche_quality' => $theirs, 'recommendation' => $recommendation];
        $this->updateJob($jobId, [
            'miche_data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'miche_score' => (int)($theirs['score'] ?? 0),
            'miche_recommendation' => (string)($recommendation['action'] ?? 'keep'),
        ]);
        $this->log($jobId, (int)$product['id'], (string)$product['slug'], 'miche_compare', 'ok', 'Miche: ' . (int)($theirs['score'] ?? 0) . '/100 · nuestra: ' . (int)($ours['score'] ?? 0) . '/100 · ' . (string)($recommendation['action'] ?? 'keep'), 'admin');
        return ['ok' => true, 'message' => $recommendation['label'], 'data' => $data, 'job' => $this->job($jobId)];
    }

    public function applyMiche(int $jobId, string $by = 'admin'): array
    {
        $job = $this->job($jobId);
        if (!$job) return ['ok' => false, 'message' => 'Trabajo no encontrado.'];
        $data = (array)($job['miche_data'] ?? []);
        if (($data['recommendation']['action'] ?? '') !== 'replace') {
            return ['ok' => false, 'message' => 'La descripción de Miche no es mejor que la nuestra. Se conserva la actual.'];
        }
        $html = $this->cleanMicheHtml((string)($data['body_html'] ?? ''));
        if (mb_strlen(trim(strip_tags($html))) < 80) return ['ok' => false, 'message' => 'La descripción de Miche está vacía o es insuficiente.'];
        $product = $this->product((int)($job['product_id'] ?? 0));
        if (!$product) return ['ok' => false, 'message' => 'Producto no encontrado.'];
        $short = mb_substr(trim(strip_tags($html)), 0, 500);
        $this->saveRevision($job, $product, $html, $short, 'miche', $by);
        try {
            $db = Database::instance();
            $db->prepare('UPDATE store_products SET description = ?, short_description = ? WHERE id = ? AND site_id = @site_id')
                ->execute([$html, $short, (int)$product['id']]);
            $this->writeCatalogBody((string)($product['catalog_handle'] ?: $product['slug']), $html);
            $this->updateJob($jobId, ['status' => 'applied', 'stage' => 'applied', 'applied_by' => mb_substr($by, 0, 190), 'applied_at' => date('Y-m-d H:i:s'), 'live_url' => $this->liveUrl($product)]);
            $this->log($jobId, (int)$product['id'], (string)$product['slug'], 'miche_apply', 'ok', 'Se reemplazó por la versión de Miche por ser más completa.', $by);
            return ['ok' => true, 'message' => 'Descripción de Miche aplicada.', 'job' => $this->job($jobId), 'live_url' => $this->liveUrl($product)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo aplicar Miche: ' . $e->getMessage()];
        }
    }

    public function scanMicheUpdates(int $limit = 250, string $by = 'admin'): array
    {
        $limit = max(50, min(250, $limit));
        $state = $this->micheState();
        $page = max(1, (int)($state['next_page'] ?? 1));
        $url = 'https://www.miche.com.co/collections/all/products.json?limit=' . $limit . '&page=' . $page;
        $res = $this->httpGet($url, 25);
        $source = 'live';
        $hasMore = false;
        if ((int)$res['code'] >= 400 || trim((string)$res['body']) === '') {
            $all = $this->catalogSnapshotProducts();
            if (!$all) {
                return ['ok' => false, 'message' => 'Miche no respondió (' . (int)$res['code'] . ') y no hay catálogo sincronizado disponible. Intenta más tarde.'];
            }
            $source = 'catalog_snapshot';
            $products = array_slice($all, ($page - 1) * $limit, $limit);
            $hasMore = count($all) > $page * $limit;
        } else {
            $data = json_decode((string)$res['body'], true);
            $products = is_array($data) ? (array)($data['products'] ?? []) : [];
            $hasMore = count($products) >= $limit;
        }
        if (!$products) {
            $state['next_page'] = 1;
            $this->saveMicheState($state);
            return ['ok' => true, 'items' => [], 'source' => $source, 'message' => $source === 'catalog_snapshot' ? 'El catálogo sincronizado no tiene más productos por revisar.' : 'No hay más productos en Miche o la página está vacía.'];
        }
        $db = Database::instance();
        $items = [];
        foreach ($products as $mp) {
            $handle = (string)($mp['handle'] ?? '');
            if ($handle === '') continue;
            $updated = (string)($mp['updated_at'] ?? '');
            $body = (string)($mp['body_html'] ?? '');
            $prev = (string)($state['updated'][$handle] ?? '');
            if ($body === '' || ($updated !== '' && $prev !== '' && $updated <= $prev)) continue;
            $local = $this->findLocalByHandle($handle);
            if (!$local) { $state['updated'][$handle] = $updated; continue; }
            $jobId = $this->ensureJob($local, $by);
            $currentJob = $this->job($jobId);
            $currentStatus = (string)($currentJob['status'] ?? '');
            $ours = $this->quality((string)($local['description'] ?? ''));
            $theirs = $this->quality($body);
            $rec = $this->micheRecommendation($ours, $theirs);
            $micheData = ['ok' => true, 'handle' => $handle, 'title' => (string)($mp['title'] ?? ''), 'body_html' => $body, 'updated_at' => $updated, 'url' => 'https://www.miche.com.co/products/' . $handle, 'ours' => $ours, 'miche_quality' => $theirs, 'recommendation' => $rec];
            $this->updateJob($jobId, [
                'miche_data' => json_encode($micheData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'miche_score' => (int)($theirs['score'] ?? 0),
                'miche_recommendation' => (string)($rec['action'] ?? 'keep'),
                'status' => $currentStatus === 'applied' ? 'applied' : 'miche_update',
                'stage' => 'miche_review',
            ]);
            $this->log($jobId, (int)$local['id'], (string)$local['slug'], 'miche_scan', 'ok', 'Actualización detectada en Miche (' . $updated . '). Recomendación: ' . ($rec['action'] ?? 'keep'), $by);
            $items[] = ['job_id' => $jobId, 'product_id' => (int)$local['id'], 'name' => (string)$local['name'], 'handle' => $handle, 'updated_at' => $updated, 'miche_score' => $theirs['score'], 'ours_score' => $ours['score'], 'recommendation' => $rec['action'] ?? 'keep', 'label' => $rec['label'] ?? '', 'live_url' => $this->liveUrl($local)];
            $state['updated'][$handle] = $updated;
        }
        $state['next_page'] = $hasMore ? $page + 1 : 1;
        $state['last_scan'] = date('c');
        $state['last_source'] = $source;
        $this->saveMicheState($state);
        $intro = $source === 'catalog_snapshot' ? 'Miche en vivo no respondió; se revisó el catálogo sincronizado. ' : '';
        return ['ok' => true, 'items' => $items, 'scanned' => count($products), 'page' => $page, 'next_page' => $state['next_page'], 'source' => $source, 'message' => $intro . count($items) . ' producto(s) con actualización de contenido en Miche.'];
    }

    public function prepareVideo(int $jobId, int $index, bool $rightsConfirmed, string $by = 'admin'): array
    {
        $job = $this->job($jobId);
        if (!$job) return ['ok' => false, 'message' => 'Trabajo no encontrado.'];
        if (!$rightsConfirmed) return ['ok' => false, 'message' => 'Debes confirmar que SONIKA tiene derechos para reutilizar el video.'];
        $videos = (array)($job['source_videos'] ?? []);
        $video = $videos[$index] ?? null;
        if (!$video) return ['ok' => false, 'message' => 'Video no encontrado.'];
        $svc = new ProductContentVideoService();
        $result = $svc->prepare($job, $video, $rightsConfirmed, $by);
        if (!empty($result['ok'])) {
            $this->updateJob($jobId, [
                'video_status' => (string)($result['status'] ?? 'sandbox'),
                'video_source_url' => (string)($video['url'] ?? ''),
                'video_file' => (string)($result['file'] ?? ''),
                'rights_confirmed' => 1,
            ]);
            $this->log($jobId, (int)($job['product_id'] ?? 0), (string)$job['handle'], 'video_prepare', 'ok', (string)($result['message'] ?? ''), $by);
        }
        return $result;
    }

    public function uploadYoutube(int $jobId, bool $rightsConfirmed, string $by = 'admin'): array
    {
        $job = $this->job($jobId);
        if (!$job) return ['ok' => false, 'message' => 'Trabajo no encontrado.'];
        if (!$rightsConfirmed) return ['ok' => false, 'message' => 'Debes confirmar los derechos antes de subir el video.'];
        $svc = new ProductContentVideoService();
        $result = $svc->uploadToYoutube($job, $rightsConfirmed, $by);
        if (!empty($result['ok'])) {
            $this->updateJob($jobId, [
                'video_status' => 'youtube',
                'youtube_id' => (string)($result['youtube_id'] ?? ''),
                'youtube_url' => (string)($result['youtube_url'] ?? ''),
                'rights_confirmed' => 1,
            ]);
            $this->log($jobId, (int)($job['product_id'] ?? 0), (string)$job['handle'], 'video_upload', 'ok', (string)($result['youtube_url'] ?? ''), $by);
        }
        return $result;
    }

    public function exportRows(array $filters = []): array
    {
        $filters['limit'] = 200;
        $rows = [];
        $offset = 0;
        do {
            $filters['offset'] = $offset;
            $page = $this->listProducts($filters);
            $items = (array)($page['items'] ?? []);
            foreach ($items as $item) $rows[] = $this->exportRow($item);
            $offset += 200;
            $total = (int)($page['total'] ?? 0);
        } while ($items && $offset < $total && $offset < 5000);
        return $rows;
    }

    public function previewHtml(int $jobId): array
    {
        $job = $this->job($jobId);
        if (!$job) return ['ok' => false, 'message' => 'Trabajo no encontrado.'];
        $product = $this->product((int)($job['product_id'] ?? 0));
        if (!$product) return ['ok' => false, 'message' => 'Producto no encontrado.'];
        return $this->preview((string)($job['generated_html'] ?? ''), $product);
    }

    // ─────────────────────────────────────────────
    // Generación
    // ─────────────────────────────────────────────

    private function aiGenerate(array $product, array $job, array $specs, array $features, string $sourceText): array
    {
        $sourceText = mb_substr($sourceText, 0, 12000);
        $specLines = [];
        foreach ($specs as $s) {
            if (!is_array($s)) continue;
            $specLines[] = '- ' . (string)($s['label'] ?? '') . ': ' . (string)($s['value'] ?? '');
        }
        $prompt = "PRODUCTO SONIKA\n"
            . "Nombre: " . (string)($product['name'] ?? '') . "\n"
            . "Marca: " . (string)($product['vendor'] ?? '') . "\n"
            . "Tipo: " . (string)($product['product_type'] ?? '') . "\n"
            . "SKU: " . (string)($product['sku'] ?? '') . "\n"
            . "URL oficial verificada: " . (string)($job['source_url'] ?? '') . "\n"
            . "Título oficial: " . (string)($job['source_title'] ?? '') . "\n\n"
            . "CONTENIDO OFICIAL VERIFICADO (única fuente de verdad):\n" . $sourceText . "\n\n"
            . "FICHA TÉCNICA EXTRAÍDA:\n" . (implode("\n", $specLines) ?: '(sin ficha)') . "\n\n"
            . "CARACTERÍSTICAS EXTRAÍDAS:\n- " . implode("\n- ", array_slice($features, 0, 12)) . "\n\n"
            . "Devuelve SOLO JSON válido con esta forma:\n"
            . "{\"summary\":\"2-4 frases\",\"benefits\":[\"4-6 beneficios\"],\"specs\":[{\"label\":\"...\",\"value\":\"...\"}],\"includes\":[\"qué incluye\"],\"details\":[{\"title\":\"...\",\"paragraphs\":[\"...\"]}],\"seo_title\":\"<=60 caracteres\",\"seo_description\":\"<=155 caracteres\",\"video_query\":\"búsqueda exacta del producto para YouTube\",\"warnings\":[\"datos faltantes o dudas\"]}";
        $system = 'Eres TIA, editora de contenido técnico de SONIKA (sonika.com.co), tienda colombiana de instrumentos musicales, audio e iluminación. '
            . 'Escribe en español de Colombia, claro y comercial, sin exageraciones. Usa SOLO los hechos del contenido oficial verificado. '
            . 'No inventes especificaciones, medidas, materiales, compatibilidades, precios, stock, descuentos, garantías, envíos ni promesas. '
            . 'Si un dato no está en la fuente, omítelo. No copies párrafos completos: parafrasea y resume. '
            . 'La ficha técnica debe ser concreta y útil para decidir la compra. Devuelve SOLO JSON, sin markdown.';
        try {
            $router = new AiRouter();
            $r = $router->route([
                'system_id' => 'wontia', 'module' => 'catalog', 'function' => 'product_content_lab',
                'system_prompt' => $system,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.2, 'max_tokens' => 3200,
            ]);
            if (!empty($r['ok']) && !empty($r['content'])) {
                $parsed = $this->parseJson((string)$r['content']);
                if ($parsed) {
                    $valid = $this->validateStructured($parsed);
                    if ($valid['ok']) {
                        return ['ok' => true, 'structured' => $valid['structured'], 'model' => (string)($r['model'] ?? ''), 'provider' => (string)($r['provider'] ?? ''), 'tokens' => (int)($r['usage']['total_tokens'] ?? 0)];
                    }
                }
            }
        } catch (\Throwable $e) {
        }
        $key = (string)Config::get('DEEPSEEK_API_KEY', '');
        if ($key !== '') {
            try {
                $body = json_encode([
                    'model' => 'deepseek-chat',
                    'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0.2, 'max_tokens' => 3200,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
                    CURLOPT_POSTFIELDS => $body,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 90,
                ]);
                $resp = curl_exec($ch);
                $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($http === 200) {
                    $d = json_decode((string)$resp, true);
                    $content = (string)($d['choices'][0]['message']['content'] ?? '');
                    $parsed = $this->parseJson($content);
                    if ($parsed) {
                        $valid = $this->validateStructured($parsed);
                        if ($valid['ok']) {
                            return ['ok' => true, 'structured' => $valid['structured'], 'model' => 'deepseek-chat', 'provider' => 'deepseek', 'tokens' => (int)($d['usage']['total_tokens'] ?? 0)];
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        return ['ok' => false, 'message' => 'TIA no devolvió un JSON válido.'];
    }

    private function fallbackGenerate(array $product, array $job, array $specs, array $features, string $sourceText): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($sourceText)) ?: [];
        $summary = '';
        foreach (array_slice($sentences, 0, 4) as $s) {
            $s = trim($s);
            if ($s === '') continue;
            if (mb_strlen($summary . ' ' . $s) > 520) break;
            $summary = $summary === '' ? $s : $summary . ' ' . $s;
        }
        if ($summary === '') $summary = 'Conoce ' . (string)($product['name'] ?? 'este producto') . ' y sus características principales.';
        $benefits = [];
        foreach (array_slice($features, 0, 6) as $f) $benefits[] = mb_substr($f, 0, 180);
        if (count($benefits) < 3) {
            foreach (array_slice($specs, 0, 4) as $s) {
                if (!is_array($s)) continue;
                $benefits[] = (string)($s['label'] ?? '') . ': ' . (string)($s['value'] ?? '');
            }
        }
        $benefits = array_values(array_slice(array_filter($benefits), 0, 6));
        $details = [];
        $paragraphs = preg_split('/\n{2,}/u', trim($sourceText)) ?: [];
        foreach (array_slice($paragraphs, 0, 3) as $i => $p) {
            $p = trim($p);
            if (mb_strlen($p) < 40) continue;
            $details[] = ['title' => $i === 0 ? 'Acerca de ' . (string)($product['name'] ?? 'este producto') : 'Información adicional', 'paragraphs' => [mb_substr($p, 0, 900)]];
        }
        $structured = [
            'summary' => $summary,
            'benefits' => $benefits,
            'specs' => array_values(array_slice(array_map(static function ($s) {
                return is_array($s) ? ['label' => (string)($s['label'] ?? ''), 'value' => (string)($s['value'] ?? '')] : null;
            }, $specs), 0, 20)),
            'includes' => [],
            'details' => $details,
            'seo_title' => mb_substr((string)($product['name'] ?? ''), 0, 60),
            'seo_description' => mb_substr(strip_tags($summary), 0, 155),
            'video_query' => (string)($product['name'] ?? ''),
            'warnings' => ['Generado sin IA: revisa especialmente la ficha técnica.'],
        ];
        $valid = $this->validateStructured($structured);
        return ['ok' => true, 'structured' => $valid['structured'], 'model' => 'deterministic', 'provider' => 'sonika', 'tokens' => 0, 'warning' => 'Generación determinista.'];
    }

    private function validateStructured(array $d): array
    {
        $clean = [
            'summary' => trim((string)($d['summary'] ?? '')),
            'benefits' => [],
            'specs' => [],
            'includes' => [],
            'details' => [],
            'seo_title' => mb_substr(trim((string)($d['seo_title'] ?? '')), 0, 60),
            'seo_description' => mb_substr(trim((string)($d['seo_description'] ?? '')), 0, 155),
            'video_query' => trim((string)($d['video_query'] ?? '')),
            'warnings' => array_values(array_filter(array_map('strval', (array)($d['warnings'] ?? [])))),
        ];
        foreach ((array)($d['benefits'] ?? []) as $b) {
            $b = trim(strip_tags((string)$b));
            if ($b !== '' && mb_strlen($b) <= 180) $clean['benefits'][] = $b;
        }
        foreach ((array)($d['specs'] ?? []) as $s) {
            if (!is_array($s)) continue;
            $label = trim(strip_tags((string)($s['label'] ?? '')));
            $value = trim(strip_tags((string)($s['value'] ?? '')));
            if ($label !== '' && $value !== '' && mb_strlen($label) <= 80 && mb_strlen($value) <= 300) $clean['specs'][] = ['label' => $label, 'value' => $value];
        }
        foreach ((array)($d['includes'] ?? []) as $i) {
            $i = trim(strip_tags((string)$i));
            if ($i !== '' && mb_strlen($i) <= 200) $clean['includes'][] = $i;
        }
        foreach ((array)($d['details'] ?? []) as $det) {
            if (!is_array($det)) continue;
            $title = trim(strip_tags((string)($det['title'] ?? '')));
            $paras = array_values(array_filter(array_map(static fn($p) => trim(strip_tags((string)$p)), (array)($det['paragraphs'] ?? [])), static fn($p) => $p !== ''));
            if ($title !== '' && $paras) $clean['details'][] = ['title' => mb_substr($title, 0, 120), 'paragraphs' => array_slice($paras, 0, 4)];
        }
        $clean['benefits'] = array_slice(array_values(array_unique($clean['benefits'])), 0, 6);
        $clean['specs'] = array_slice($clean['specs'], 0, 20);
        $clean['includes'] = array_slice(array_values(array_unique($clean['includes'])), 0, 8);
        $clean['details'] = array_slice($clean['details'], 0, 4);
        if (mb_strlen($clean['summary']) < 80 || count($clean['benefits']) < 3 || count($clean['specs']) < 3) {
            return ['ok' => false, 'structured' => $clean, 'message' => 'La estructura generada está incompleta.'];
        }
        return ['ok' => true, 'structured' => $clean];
    }

    private function semanticHtml(array $d, array $product, string $youtubeId = ''): string
    {
        $esc = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $out = '<!-- sonika-content-lab:v1 -->';
        $title = trim((string)($product['name'] ?? ''));
        $out .= '<h2>Resumen</h2><p>' . $esc((string)($d['summary'] ?? '')) . '</p>';
        if (!empty($d['benefits'])) {
            $out .= '<h2>Beneficios principales</h2><ul>';
            foreach ($d['benefits'] as $b) $out .= '<li>' . $esc($b) . '</li>';
            $out .= '</ul>';
        }
        if (!empty($d['specs'])) {
            $out .= '<h2>Ficha técnica</h2><table><tbody>';
            foreach ($d['specs'] as $s) $out .= '<tr><th>' . $esc((string)($s['label'] ?? '')) . '</th><td>' . $esc((string)($s['value'] ?? '')) . '</td></tr>';
            $out .= '</tbody></table>';
        }
        if (!empty($d['includes'])) {
            $out .= '<h2>Qué incluye</h2><ul>';
            foreach ($d['includes'] as $i) $out .= '<li>' . $esc($i) . '</li>';
            $out .= '</ul>';
        }
        if (!empty($d['details'])) {
            $out .= '<h2>Detalles del producto</h2>';
            foreach ($d['details'] as $det) {
                $out .= '<h3>' . $esc((string)($det['title'] ?? 'Detalles')) . '</h3>';
                foreach ((array)($det['paragraphs'] ?? []) as $p) $out .= '<p>' . $esc((string)$p) . '</p>';
            }
        }
        if ($youtubeId !== '' && preg_match('/^[A-Za-z0-9_-]{6,}$/', $youtubeId)) {
            $out .= '<h2>Conócelo en video</h2><iframe src="https://www.youtube-nocookie.com/embed/' . $esc($youtubeId) . '?rel=0&modestbranding=1&playsinline=1" title="' . $esc($title) . ' — video oficial" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
        }
        return $out;
    }

    private function preview(string $semantic, array $product): array
    {
        $svc = new ProductContentService();
        $fake = $product;
        $fake['body_html'] = $semantic;
        $fake['title'] = (string)($product['name'] ?? $product['title'] ?? '');
        $html = $svc->render($fake);
        $analysis = $svc->analyze($fake);
        return ['html' => $html, 'score' => (int)($analysis['score'] ?? 0), 'analysis' => $analysis];
    }

    // ─────────────────────────────────────────────
    // Miche / calidad
    // ─────────────────────────────────────────────

    private function micheProduct(array $product): array
    {
        $handle = trim((string)($product['catalog_handle'] ?? ''));
        if ($handle === '') $handle = trim((string)($product['slug'] ?? ''));
        if ($handle === '') return ['ok' => false, 'message' => 'El producto no tiene handle de catálogo.'];
        $cache = $this->cachePath('miche_' . md5($handle) . '.json');
        if (is_file($cache) && time() - (int)filemtime($cache) < 21600) {
            $cached = json_decode((string)@file_get_contents($cache), true);
            if (is_array($cached)) return $cached;
        }
        $url = 'https://www.miche.com.co/products/' . rawurlencode($handle) . '.json';
        $res = $this->httpGet($url, 20);
        if ((int)$res['code'] === 429) {
            usleep(2500000);
            $res = $this->httpGet($url, 20);
        }
        if ((int)$res['code'] >= 400) {
            $snapshot = $this->micheSnapshot($handle);
            if ($snapshot) {
                $snapshot['note'] = 'Miche en vivo no respondió (' . (int)$res['code'] . '). Se usa el último catálogo sincronizado.';
                return $snapshot;
            }
            return ['ok' => false, 'message' => 'Miche no devolvió el producto (' . (int)$res['code'] . ').'];
        }
        $data = json_decode((string)$res['body'], true);
        $p = is_array($data) ? (array)($data['product'] ?? []) : [];
        if (!$p) {
            $snapshot = $this->micheSnapshot($handle);
            return $snapshot ?: ['ok' => false, 'message' => 'Miche no devolvió un producto válido.'];
        }
        $out = [
            'ok' => true,
            'source' => 'live',
            'handle' => (string)($p['handle'] ?? $handle),
            'title' => (string)($p['title'] ?? ''),
            'body_html' => (string)($p['body_html'] ?? ''),
            'updated_at' => (string)($p['updated_at'] ?? ''),
            'url' => 'https://www.miche.com.co/products/' . rawurlencode((string)($p['handle'] ?? $handle)),
        ];
        @file_put_contents($cache, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $out;
    }

    private function micheSnapshot(string $handle): ?array
    {
        foreach ($this->catalogPaths() as $path) {
            if (!is_file($path)) continue;
            $data = json_decode((string)@file_get_contents($path), true);
            foreach ((array)($data['products'] ?? []) as $p) {
                if (!is_array($p) || (string)($p['handle'] ?? '') !== $handle) continue;
                return [
                    'ok' => true,
                    'source' => 'catalog_snapshot',
                    'handle' => $handle,
                    'title' => (string)($p['title'] ?? ''),
                    'body_html' => (string)($p['body_html'] ?? ''),
                    'updated_at' => (string)($p['updated_at'] ?? ''),
                    'url' => 'https://www.miche.com.co/products/' . rawurlencode($handle),
                    'note' => 'Datos del último catálogo sincronizado de Miche.',
                ];
            }
        }
        return null;
    }

    private function catalogSnapshotProducts(): array
    {
        foreach ($this->catalogPaths() as $path) {
            if (!is_file($path)) continue;
            $data = json_decode((string)@file_get_contents($path), true);
            if (is_array($data) && !empty($data['products'])) return (array)$data['products'];
        }
        return [];
    }

    private function quality(string $html): array
    {
        $svc = new ProductContentService();
        $a = $svc->analyze(['body_html' => $html]);
        $chars = (int)($a['chars'] ?? 0);
        $score = (int)($a['score'] ?? 0);
        $hasStructure = !empty($a['has_table']) || !empty($a['has_list']) || !empty($a['has_heading']);
        if ($chars > 250 && $hasStructure) $score = min(100, $score + 5);
        return ['score' => $score, 'chars' => $chars, 'words' => (int)($a['words'] ?? 0), 'has_specs' => !empty($a['has_specs']), 'has_list' => !empty($a['has_list']), 'has_video' => !empty($a['has_video']), 'has_image' => !empty($a['has_image']), 'inline_styles' => (int)($a['inline_styles'] ?? 0), 'font_tags' => (int)($a['font_tags'] ?? 0), 'issues' => $a['issues'] ?? []];
    }

    private function micheRecommendation(array $ours, array $theirs): array
    {
        $oursScore = (int)($ours['score'] ?? 0);
        $theirsScore = (int)($theirs['score'] ?? 0);
        $oursChars = (int)($ours['chars'] ?? 0);
        $theirsChars = (int)($theirs['chars'] ?? 0);
        $better = ($theirsScore >= $oursScore + 8 && $theirsChars >= $oursChars + 150 && ($theirs['has_specs'] || $theirs['has_list']) && (int)$theirs['inline_styles'] <= 2 && $oursScore < 88)
            || ($theirsScore >= 80 && $oursScore < 60 && $theirsChars >= 300);
        if ($better) {
            return ['action' => 'replace', 'label' => 'Miche tiene una descripción más completa. Se recomienda reemplazar.', 'delta' => $theirsScore - $oursScore];
        }
        return ['action' => 'keep', 'label' => 'La descripción nuestra es igual o mejor. Se conserva la actual.', 'delta' => $theirsScore - $oursScore];
    }

    private function cleanMicheHtml(string $html): string
    {
        $html = preg_replace('~<a\b[^>]*href\s*=\s*("([^"]*miche\.com\.co[^"]*)"|\'([^\']*miche\.com\.co[^\']*)\')[^>]*>(.*?)</a>~is', '$4', $html) ?? $html;
        $html = preg_replace('~(?:https?://)?(?:www\.)?miche\.com\.co/?~i', '', $html) ?? $html;
        $html = preg_replace('~<(script|style|noscript|form|input|button|select|textarea)\b.*?</\1>~is', '', $html) ?? $html;
        return trim($html);
    }

    // ─────────────────────────────────────────────
    // Persistencia
    // ─────────────────────────────────────────────

    private function ensureJob(array $product, string $by): int
    {
        $db = Database::instance();
        $handle = (string)($product['catalog_handle'] ?: $product['slug']);
        $st = $db->prepare('SELECT id FROM product_content_jobs WHERE site_id = @site_id AND handle = ? LIMIT 1');
        $st->execute([$handle]);
        $id = (int)$st->fetchColumn();
        if ($id > 0) {
            $db->prepare('UPDATE product_content_jobs SET product_id = ?, title = ?, vendor = ?, product_type = ?, updated_at = NOW() WHERE id = ? AND site_id = @site_id')
                ->execute([(int)$product['id'], mb_substr((string)($product['name'] ?? ''), 0, 500), mb_substr((string)($product['vendor'] ?? ''), 0, 190), mb_substr((string)($product['product_type'] ?? ''), 0, 190), $id]);
            return $id;
        }
        $db->prepare('INSERT INTO product_content_jobs (site_id, product_id, handle, title, vendor, product_type, status, stage, created_by, created_at, updated_at)
            VALUES (@site_id, ?, ?, ?, ?, ?, "new", "pending", ?, NOW(), NOW())')
            ->execute([(int)$product['id'], $handle, mb_substr((string)($product['name'] ?? ''), 0, 500), mb_substr((string)($product['vendor'] ?? ''), 0, 190), mb_substr((string)($product['product_type'] ?? ''), 0, 190), mb_substr($by, 0, 190)]);
        return (int)$db->lastInsertId();
    }

    private function updateJob(int $jobId, array $fields): void
    {
        if (!$fields) return;
        $allowed = ['status','stage','confidence','source_url','source_domain','source_title','source_text','source_html','source_specs','source_features','source_videos','candidates','evidence','generated_json','generated_html','generated_preview','generated_score','previous_description','previous_short','miche_data','miche_score','miche_recommendation','video_status','video_source_url','video_file','youtube_id','youtube_url','rights_confirmed','ai_model','ai_provider','tokens_used','error','applied_by','applied_at','live_url'];
        $sets = [];
        $params = [];
        foreach ($fields as $k => $v) {
            if (!in_array($k, $allowed, true)) continue;
            $sets[] = "`$k` = :$k";
            $params[$k] = $v;
        }
        if (!$sets) return;
        $sets[] = 'updated_at = NOW()';
        $params['id'] = $jobId;
        try {
            Database::instance()->prepare('UPDATE product_content_jobs SET ' . implode(', ', $sets) . ' WHERE id = :id AND site_id = @site_id')->execute($params);
        } catch (\Throwable $e) {
        }
    }

    private function saveRevision(array $job, array $product, string $newHtml, string $newShort, string $source, string $by): void
    {
        try {
            Database::instance()->prepare('INSERT INTO product_content_revisions (site_id, job_id, product_id, handle, previous_description, previous_short, new_description, new_short, source, created_by, created_at)
                VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())')
                ->execute([(int)$job['id'], (int)$product['id'], (string)($product['catalog_handle'] ?: $product['slug']), (string)($product['description'] ?? ''), mb_substr((string)($product['short_description'] ?? ''), 0, 500), $newHtml, $newShort, $source, mb_substr($by, 0, 190)]);
        } catch (\Throwable $e) {
        }
    }

    private function log(int $jobId, int $productId, string $handle, string $action, string $status, string $detail, string $actor): void
    {
        try {
            Database::instance()->prepare('INSERT INTO product_content_activity (site_id, job_id, product_id, handle, action, status, detail, actor, created_at) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, NOW())')
                ->execute([$jobId, $productId, mb_substr($handle, 0, 255), mb_substr($action, 0, 64), mb_substr($status, 0, 32), mb_substr($detail, 0, 2000), mb_substr($actor, 0, 190)]);
        } catch (\Throwable $e) {
        }
    }

    private function hydrateJob(array $r): array
    {
        foreach (['candidates','evidence','generated_json','source_specs','source_features','source_videos','miche_data'] as $k) {
            $v = $r[$k] ?? null;
            if (is_string($v) && $v !== '') {
                $d = json_decode($v, true);
                $r[$k] = is_array($d) ? $d : [];
            } elseif (!is_array($v)) $r[$k] = [];
        }
        $r['status_label'] = self::STATUS_LABELS[(string)($r['status'] ?? '')] ?? (string)($r['status'] ?? '');
        $r['stages'] = self::STAGES;
        return $r;
    }

    private function exportRow(array $item): array
    {
        $action = $this->actionFor($item);
        return [
            'producto' => $item['name'],
            'handle' => $item['slug'],
            'marca' => $item['vendor'],
            'tipo' => $item['product_type'],
            'estado_contenido' => $item['description_chars'] < 20 ? 'Sin descripción' : (($item['issue_keys'] ?? []) ? implode(', ', (array)$item['issues']) : 'Con descripción'),
            'score_actual' => $item['score'],
            'alerta' => implode(' | ', (array)($item['issues'] ?? [])),
            'estado_lab' => $item['job_status_label'],
            'confianza_fuente' => $item['confidence'],
            'fuente_oficial' => $item['source_url'],
            'recomendacion' => $action['label'],
            'accion' => $action['action'],
            'score_generado' => $item['generated_score'],
            'video' => $item['video_status'] === 'youtube' ? $item['youtube_url'] : $item['video_status'],
            'url_producto' => $item['live_url'],
        ];
    }

    private function actionFor(array $item): array
    {
        if (($item['job_status'] ?? '') === 'applied') return ['action' => 'Verificar en vivo', 'label' => 'Aplicada. Revisar resultado publicado.'];
        if (($item['job_status'] ?? '') === 'generated') return ['action' => 'Revisar y aplicar', 'label' => 'Descripción generada. Revisar y publicar.'];
        if (($item['job_status'] ?? '') === 'needs_review') return ['action' => 'Confirmar fuente exacta', 'label' => 'Hay candidato oficial; confirmar identidad antes de generar.'];
        if (($item['job_status'] ?? '') === 'miche_update') return ['action' => 'Comparar con Miche', 'label' => 'Miche actualizó el producto. Comparar y decidir.'];
        if ((int)($item['description_chars'] ?? 0) < 20) return ['action' => 'Investigar y generar descripción', 'label' => 'Producto sin descripción. Iniciar Content Fix Lab.'];
        if (in_array('no_video', (array)($item['issue_keys'] ?? []), true)) return ['action' => 'Buscar video oficial', 'label' => 'Falta video; buscar en la ficha oficial o YouTube.'];
        if (in_array('no_specs', (array)($item['issue_keys'] ?? []), true)) return ['action' => 'Añadir ficha técnica', 'label' => 'Falta ficha técnica; completar desde fuente oficial.'];
        return ['action' => 'Enriquecer descripción', 'label' => 'Revisar estructura y contenido.'];
    }

    private function liveUrl(array $product): string
    {
        $base = $this->publicBaseUrl();
        $slug = (string)($product['slug'] ?? $product['catalog_handle'] ?? '');
        return $slug !== '' ? $base . '/producto/' . rawurlencode($slug) : $base;
    }

    private function publicBaseUrl(): string
    {
        try {
            $row = Database::instance()->query('SELECT domain, name FROM sites WHERE id = @site_id LIMIT 1')->fetch();
            $domain = trim((string)($row['domain'] ?? ''));
            if ($domain === '') {
                $name = trim((string)($row['name'] ?? ''));
                if (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $name)) $domain = $name;
            }
            if ($domain !== '') return 'https://' . preg_replace('~^https?://~i', '', rtrim($domain, '/'));
        } catch (\Throwable $e) {
        }
        $app = rtrim((string)Config::get('APP_URL', 'https://sonika.com.co'), '/');
        return $app !== '' ? $app : 'https://sonika.com.co';
    }

    private function findLocalByHandle(string $handle): ?array
    {
        try {
            $st = Database::instance()->prepare("SELECT * FROM store_products WHERE site_id = @site_id AND (catalog_handle = ? OR slug = ?) LIMIT 1");
            $st->execute([$handle, $handle]);
            $r = $st->fetch();
            if (!$r) return null;
            $r['tags'] = json_decode((string)($r['tags'] ?? ''), true) ?: [];
            $r['images'] = json_decode((string)($r['images'] ?? ''), true) ?: [];
            $r['title'] = (string)($r['name'] ?? '');
            $r['body_html'] = (string)($r['description'] ?? '');
            return $r;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function writeCatalogBody(string $handle, string $body): void
    {
        foreach ($this->catalogPaths() as $path) {
            if (!is_file($path)) continue;
            $raw = (string)@file_get_contents($path);
            $data = json_decode($raw, true);
            if (!is_array($data) || !isset($data['products']) || !is_array($data['products'])) continue;
            $changed = false;
            foreach ($data['products'] as &$p) {
                if (!is_array($p)) continue;
                if ((string)($p['handle'] ?? '') === $handle) {
                    $p['body_html'] = $body;
                    $changed = true;
                    break;
                }
            }
            unset($p);
            if (!$changed) continue;
            @copy($path, $path . '.bak.' . date('YmdHis'));
            $tmp = $path . '.tmp';
            if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false) @rename($tmp, $path);
        }
    }

    private function catalogPaths(): array
    {
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        return [
            $root . '/public/assets/uploads/_catalog/catalog.json',
            $root . '/templates/themes/sonika/catalog.json',
        ];
    }

    private function cachePath(string $name): string
    {
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        $dir = $root . '/public/assets/uploads/_content_lab/cache';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        return $dir . '/' . $name;
    }

    private function micheStatePath(): string
    {
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 2);
        $dir = $root . '/public/assets/uploads/_content_lab';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        return $dir . '/miche_content_state.json';
    }

    private function micheState(): array
    {
        $f = $this->micheStatePath();
        if (!is_file($f)) return ['updated' => [], 'next_page' => 1];
        $d = json_decode((string)@file_get_contents($f), true);
        return is_array($d) ? $d + ['updated' => [], 'next_page' => 1] : ['updated' => [], 'next_page' => 1];
    }

    private function saveMicheState(array $state): void
    {
        $f = $this->micheStatePath();
        @file_put_contents($f . '.tmp', json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        @rename($f . '.tmp', $f);
    }

    private function parseJson(string $content): ?array
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?|```$/m', '', $content) ?: $content;
        $a = strpos($content, '{');
        $b = strrpos($content, '}');
        if ($a === false || $b === false || $b <= $a) return null;
        $d = json_decode(substr($content, $a, $b - $a + 1), true);
        return is_array($d) ? $d : null;
    }

    private function httpGet(string $url, int $timeout = 15): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 7,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124 Safari/537.36',
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => ['Accept-Language: es-CO,es;q=0.9,en;q=0.8'],
        ]);
        $body = (string)curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['body' => $body, 'code' => $code, 'error' => $err];
    }
}
