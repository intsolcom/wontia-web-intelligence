<?php
namespace App\Services;

use App\Core\Database;

/**
 * Directorio Sonika: perfiles, reseñas verificadas y Top 10 Confianza Sonika.
 * Multi-tenant (@site_id). Las reseñas nacen pendientes y solo el admin las publica.
 */
class DirectoryService
{
    public const RANKS = [
        'semilla' => ['label' => 'Semilla', 'min' => 0],
        'pulso' => ['label' => 'Pulso', 'min' => 200],
        'acorde' => ['label' => 'Acorde', 'min' => 500],
        'escenario' => ['label' => 'Escenario', 'min' => 1000],
        'referente' => ['label' => 'Referente', 'min' => 2000],
        'legado' => ['label' => 'Legado', 'min' => 4000],
    ];

    private static bool $ensured = false;

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true];
        $file = dirname(__DIR__, 2) . '/install/directory.sql';
        if (is_file($file)) {
            $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($file));
            $db = Database::instance();
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                if ($statement === '') continue;
                try { $db->exec($statement); } catch (\Throwable $e) {}
            }
        }
        $db = Database::instance();
        foreach ([
            "ALTER TABLE directory_profiles ADD COLUMN IF NOT EXISTS owner_email VARCHAR(190) NULL",
            "ALTER TABLE directory_profiles ADD COLUMN IF NOT EXISTS owner_phone VARCHAR(40) NULL",
            "ALTER TABLE directory_profiles ADD COLUMN IF NOT EXISTS founder TINYINT NOT NULL DEFAULT 0",
            "ALTER TABLE directory_profiles ADD COLUMN IF NOT EXISTS points INT NOT NULL DEFAULT 0",
            "ALTER TABLE directory_profiles ADD COLUMN IF NOT EXISTS rank_key VARCHAR(30) NOT NULL DEFAULT 'semilla'",
            "ALTER TABLE directory_profiles ADD COLUMN IF NOT EXISTS consent TINYINT NOT NULL DEFAULT 0",
            "ALTER TABLE directory_profiles ADD COLUMN IF NOT EXISTS source VARCHAR(30) NOT NULL DEFAULT 'sonika'",
            "ALTER TABLE directory_profiles ADD COLUMN IF NOT EXISTS approved_at DATETIME NULL",
            "ALTER TABLE directory_profiles MODIFY COLUMN status ENUM('pending','draft','active','paused','blocked','rejected') NOT NULL DEFAULT 'pending'",
        ] as $alter) {
            try { $db->exec($alter); } catch (\Throwable $e) {}
        }
        self::$ensured = true;
        return ['ok' => true];
    }

    public function profiles(): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $out = [];
        try {
            $st = $db->prepare("SELECT * FROM directory_profiles WHERE site_id = @site_id AND status = 'active' ORDER BY display_name ASC");
            $st->execute();
            foreach ($st->fetchAll() as $row) $out[(string)$row['slug']] = $this->mapProfile($row, 'directory');
        } catch (\Throwable $e) {}
        try {
            $st = $db->prepare("SELECT p.*, s.image AS store_image FROM affiliate_profiles p LEFT JOIN affiliate_stores s ON s.site_id = p.site_id AND s.affiliate_id = p.id WHERE p.site_id = @site_id AND p.status = 'APPROVED' ORDER BY p.artist_name ASC");
            $st->execute();
            $globalProfiles = false;
            try {
                $gs = $db->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'affiliate_public_profiles_enabled' LIMIT 1");
                $gs->execute();
                $globalProfiles = trim((string)$gs->fetchColumn()) === '1';
            } catch (\Throwable $e) {}
            foreach ($st->fetchAll() as $row) {
                $slug = (string)($row['slug'] ?? '');
                if ($slug === '' || isset($out[$slug])) continue;
                $visible = $globalProfiles && (int)($row['public_profile_enabled'] ?? 0) === 1;
                $code = '';
                try {
                    $ls = $db->prepare('SELECT code FROM affiliate_links WHERE affiliate_id = :a AND site_id = @site_id LIMIT 1');
                    $ls->execute(['a' => (int)($row['id'] ?? 0)]);
                    $code = (string)($ls->fetchColumn() ?: '');
                } catch (\Throwable $e) {}
                $out[$slug] = $this->mapAffiliateProfile($row, $visible, $code);
            }
        } catch (\Throwable $e) {}
        ksort($out);
        return array_values($out);
    }

    public function profile(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') return null;
        foreach ($this->profiles() as $profile) {
            if ((string)$profile['slug'] === $slug) return $profile;
        }
        return null;
    }

    public function createProfile(array $data): array
    {
        $this->ensureTables();
        $name = trim((string)($data['display_name'] ?? ''));
        $email = strtolower(trim((string)($data['owner_email'] ?? '')));
        $category = trim((string)($data['category'] ?? ''));
        $city = trim((string)($data['city'] ?? ''));
        $bio = trim((string)($data['bio'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 190) return ['ok' => false, 'message' => 'Escribe el nombre del perfil.'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'message' => 'Escribe un correo válido.'];
        if ($category === '') return ['ok' => false, 'message' => 'Selecciona una categoría.'];
        if ($city === '') return ['ok' => false, 'message' => 'Escribe tu ciudad.'];
        if (mb_strlen($bio) < 20) return ['ok' => false, 'message' => 'Cuéntanos un poco más sobre tu perfil (mínimo 20 caracteres).'];
        if (empty($data['consent'])) return ['ok' => false, 'message' => 'Debes aceptar las condiciones del directorio.'];

        $db = Database::instance();
        $rate = $db->prepare("SELECT COUNT(*) FROM directory_profiles WHERE site_id = @site_id AND owner_email = ? AND created_at >= (NOW() - INTERVAL 1 DAY)");
        $rate->execute([$email]);
        if ((int)$rate->fetchColumn() >= 3) return ['ok' => false, 'message' => 'Recibimos varios perfiles con este correo. Intenta más tarde.'];

        $slug = $this->slugify($name);
        $base = $slug;
        $i = 2;
        while (true) {
            $check = $db->prepare("SELECT id FROM directory_profiles WHERE site_id = @site_id AND slug = ? LIMIT 1");
            $check->execute([$slug]);
            if (!$check->fetchColumn()) break;
            $slug = $base . '-' . $i++;
        }

        $photo = $this->safeUrl((string)($data['photo'] ?? ''));
        $website = $this->safeUrl((string)($data['website'] ?? ''));
        $st = $db->prepare("INSERT INTO directory_profiles (site_id, slug, display_name, profile_type, category, subcategory, instrument, city, region, bio, photo, website, whatsapp, email, owner_email, owner_phone, consent, source, status) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'public', 'pending')");
        $st->execute([
            $slug,
            mb_substr($name, 0, 190),
            mb_substr(trim((string)($data['profile_type'] ?? 'artist')), 0, 30),
            mb_substr($category, 0, 100),
            mb_substr(trim((string)($data['subcategory'] ?? '')), 0, 100),
            mb_substr(trim((string)($data['instrument'] ?? '')), 0, 100),
            mb_substr($city, 0, 120),
            mb_substr(trim((string)($data['region'] ?? '')), 0, 120),
            $bio,
            $photo,
            $website,
            mb_substr(trim((string)($data['whatsapp'] ?? '')), 0, 40),
            $email,
            $email,
            mb_substr(trim((string)($data['owner_phone'] ?? '')), 0, 40),
        ]);

        return ['ok' => true, 'id' => (int)$db->lastInsertId(), 'slug' => $slug, 'status' => 'pending', 'message' => 'Recibimos tu perfil. Lo revisaremos antes de publicarlo.'];
    }

    public function pendingProfiles(): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT * FROM directory_profiles WHERE site_id = @site_id AND status = 'pending' ORDER BY created_at ASC LIMIT 200");
        $st->execute();
        return $st->fetchAll();
    }

    public function allProfiles(string $status = '', int $limit = 200): array
    {
        $this->ensureTables();
        $limit = max(1, min(500, $limit));
        $where = 'site_id = @site_id';
        $params = [];
        if (in_array($status, ['pending', 'active', 'rejected', 'paused', 'blocked'], true)) {
            $where .= ' AND status = ?';
            $params[] = $status;
        }
        $st = Database::instance()->prepare("SELECT * FROM directory_profiles WHERE $where ORDER BY id DESC LIMIT $limit");
        $st->execute($params);
        return $st->fetchAll();
    }

    public function moderateProfile(int $id, string $status, string $by = ''): array
    {
        $this->ensureTables();
        if (!in_array($status, ['approved', 'rejected'], true)) return ['ok' => false, 'message' => 'Estado inválido.'];
        $db = Database::instance();
        $st = $db->prepare("SELECT * FROM directory_profiles WHERE id = ? AND site_id = @site_id LIMIT 1");
        $st->execute([$id]);
        $profile = $st->fetch();
        if (!$profile) return ['ok' => false, 'message' => 'Perfil no encontrado.'];

        if ($status === 'rejected') {
            $db->prepare("UPDATE directory_profiles SET status = 'rejected' WHERE id = ? AND site_id = @site_id")->execute([$id]);
            return ['ok' => true];
        }

        $founder = 0;
        if ((int)($profile['founder'] ?? 0) === 0) {
            $count = $db->query("SELECT COUNT(*) FROM directory_profiles WHERE site_id = @site_id AND founder = 1 AND status = 'active'")->fetchColumn();
            if ((int)$count < 1000) $founder = 1;
        } else {
            $founder = 1;
        }
        $db->prepare("UPDATE directory_profiles SET status = 'active', verified = 1, founder = ?, approved_at = NOW() WHERE id = ? AND site_id = @site_id")
            ->execute([$founder, $id]);
        $this->awardPoints($id, 'profile_complete', 100, 'profile:' . $id . ':complete', 'Perfil completo aprobado');
        $this->awardPoints($id, 'identity_verified', 100, 'profile:' . $id . ':verified', 'Identidad verificada');
        if ($founder === 1) $this->awardPoints($id, 'founder', 100, 'profile:' . $id . ':founder', 'Perfil fundador');
        return ['ok' => true, 'founder' => $founder === 1];
    }

    public static function rankForPoints(int $points): string
    {
        $rank = 'semilla';
        foreach (self::RANKS as $key => $row) {
            if ($points >= (int)$row['min']) $rank = $key;
        }
        return $rank;
    }

    public function rankLabel(string $key): string
    {
        return (string)(self::RANKS[$key]['label'] ?? 'Semilla');
    }

    private function awardPoints(int $profileId, string $action, int $points, string $sourceKey, string $note = ''): void
    {
        try {
            $db = Database::instance();
            $st = $db->prepare("INSERT IGNORE INTO directory_point_events (site_id, profile_id, action, points, source_key, note) VALUES (@site_id, ?, ?, ?, ?, ?)");
            $st->execute([$profileId, $action, $points, mb_substr($sourceKey, 0, 190), mb_substr($note, 0, 255)]);
            if ($st->rowCount() > 0) $this->recomputeProfile($profileId);
        } catch (\Throwable $e) {
        }
    }

    private function recomputeProfile(int $profileId): void
    {
        try {
            $db = Database::instance();
            $st = $db->prepare("SELECT COALESCE(SUM(points),0) FROM directory_point_events WHERE site_id = @site_id AND profile_id = ?");
            $st->execute([$profileId]);
            $points = (int)$st->fetchColumn();
            $rank = self::rankForPoints($points);
            $db->prepare("UPDATE directory_profiles SET points = ?, rank_key = ? WHERE id = ? AND site_id = @site_id")->execute([$points, $rank, $profileId]);
        } catch (\Throwable $e) {
        }
    }

    private function slugify(string $name): string
    {
        $map = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n'];
        $name = strtr($name, $map);
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        return $slug !== '' ? mb_substr($slug, 0, 180) : 'perfil-' . time();
    }

    private function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (str_starts_with($url, '/') || preg_match('~^https?://~i', $url)) return mb_substr($url, 0, 500);
        return '';
    }

    public function summary(string $ref): array
    {
        $this->ensureTables();
        try {
            $st = Database::instance()->prepare("SELECT COUNT(*) c, AVG(rating) avg, SUM(rating=5) r5, SUM(rating=4) r4, SUM(rating=3) r3, SUM(rating=2) r2, SUM(rating=1) r1, SUM(verified=1) verified_count FROM directory_reviews WHERE site_id = @site_id AND profile_ref = ? AND status = 'approved'");
            $st->execute([$ref]);
            $row = $st->fetch() ?: [];
            $count = (int)($row['c'] ?? 0);
            return [
                'count' => $count,
                'average' => $count ? round((float)$row['avg'], 1) : 0.0,
                'verified_count' => (int)($row['verified_count'] ?? 0),
                'dist' => [5 => (int)($row['r5'] ?? 0), 4 => (int)($row['r4'] ?? 0), 3 => (int)($row['r3'] ?? 0), 2 => (int)($row['r2'] ?? 0), 1 => (int)($row['r1'] ?? 0)],
            ];
        } catch (\Throwable $e) {
            return ['count' => 0, 'average' => 0.0, 'verified_count' => 0, 'dist' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];
        }
    }

    public function reviews(string $ref, int $limit = 20): array
    {
        $this->ensureTables();
        $limit = max(1, min(100, $limit));
        $st = Database::instance()->prepare("SELECT id, reviewer_name, rating, punctuality, quality, professionalism, communication, reliability, title, body, service_date, verified, created_at FROM directory_reviews WHERE site_id = @site_id AND profile_ref = ? AND status = 'approved' ORDER BY created_at DESC LIMIT $limit");
        $st->execute([$ref]);
        return $st->fetchAll();
    }

    public function submitReview(string $ref, array $data, string $ip = ''): array
    {
        $this->ensureTables();
        $profile = $this->profile($ref);
        if (!$profile) return ['ok' => false, 'message' => 'El perfil no existe.'];

        $name = trim((string)($data['reviewer_name'] ?? ''));
        $email = strtolower(trim((string)($data['reviewer_email'] ?? '')));
        $phone = trim((string)($data['reviewer_phone'] ?? ''));
        $rating = (int)($data['rating'] ?? 0);
        $body = trim((string)($data['body'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));
        $serviceDate = trim((string)($data['service_date'] ?? ''));
        $consent = !empty($data['consent']);

        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) return ['ok' => false, 'message' => 'Escribe tu nombre completo.'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'message' => 'Escribe un correo válido.'];
        if ($rating < 1 || $rating > 5) return ['ok' => false, 'message' => 'Selecciona una calificación de 1 a 5 estrellas.'];
        if (mb_strlen($body) < 10) return ['ok' => false, 'message' => 'Cuéntanos un poco más sobre tu experiencia (mínimo 10 caracteres).'];
        if (mb_strlen($body) > 2000) return ['ok' => false, 'message' => 'La reseña no puede superar 2.000 caracteres.'];
        if (!$consent) return ['ok' => false, 'message' => 'Debes aceptar las condiciones para publicar la reseña.'];

        $date = null;
        if ($serviceDate !== '') {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $serviceDate);
            if (!$dt || $dt->format('Y-m-d') !== $serviceDate) return ['ok' => false, 'message' => 'La fecha del servicio no es válida.'];
            $date = $serviceDate;
        }

        $db = Database::instance();
        $dup = $db->prepare("SELECT id FROM directory_reviews WHERE site_id = @site_id AND profile_ref = ? AND reviewer_email = ? LIMIT 1");
        $dup->execute([$ref, $email]);
        if ($dup->fetchColumn()) return ['ok' => false, 'message' => 'Ya registramos una reseña tuya para este perfil.'];

        $ipHash = $ip !== '' ? hash('sha256', $ip) : null;
        if ($ipHash !== null) {
            $rate = $db->prepare("SELECT COUNT(*) FROM directory_reviews WHERE site_id = @site_id AND ip_hash = ? AND created_at >= (NOW() - INTERVAL 1 HOUR)");
            $rate->execute([$ipHash]);
            if ((int)$rate->fetchColumn() >= 6) return ['ok' => false, 'message' => 'Recibimos varias reseñas desde esta conexión. Intenta más tarde.'];
        }

        $dimensions = [];
        foreach (['punctuality', 'quality', 'professionalism', 'communication', 'reliability'] as $key) {
            $value = (int)($data[$key] ?? $rating);
            $dimensions[$key] = max(1, min(5, $value));
        }

        $st = $db->prepare("INSERT INTO directory_reviews (site_id, profile_ref, reviewer_name, reviewer_email, reviewer_phone, rating, punctuality, quality, professionalism, communication, reliability, title, body, service_date, consent, status, ip_hash) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'pending', ?)");
        $st->execute([$ref, mb_substr($name, 0, 160), $email, mb_substr($phone, 0, 40), $rating, $dimensions['punctuality'], $dimensions['quality'], $dimensions['professionalism'], $dimensions['communication'], $dimensions['reliability'], mb_substr($title, 0, 160), $body, $date, $ipHash]);

        return ['ok' => true, 'id' => (int)$db->lastInsertId(), 'message' => 'Recibimos tu reseña. La verificaremos antes de publicarla.'];
    }

    public function moderate(int $id, string $status, string $by = ''): array
    {
        $this->ensureTables();
        if (!in_array($status, ['approved', 'rejected', 'pending'], true)) return ['ok' => false, 'message' => 'Estado inválido.'];
        $by = mb_substr(trim($by), 0, 150);
        $verified = $status === 'approved' ? 1 : 0;
        $db = Database::instance();
        $st = $db->prepare("SELECT profile_ref FROM directory_reviews WHERE id = ? AND site_id = @site_id LIMIT 1");
        $st->execute([$id]);
        $ref = (string)($st->fetchColumn() ?: '');
        $db->prepare("UPDATE directory_reviews SET status = ?, verified = ?, moderated_by = ?, moderated_at = NOW() WHERE id = ? AND site_id = @site_id")
            ->execute([$status, $verified, $by, $id]);
        if ($status === 'approved' && $ref !== '') {
            $pst = $db->prepare("SELECT id FROM directory_profiles WHERE site_id = @site_id AND slug = ? LIMIT 1");
            $pst->execute([$ref]);
            $profileId = (int)$pst->fetchColumn();
            if ($profileId > 0) $this->awardPoints($profileId, 'review_received', 20, 'review:' . $id, 'Reseña verificada recibida');
        }
        return ['ok' => true];
    }

    public function pendingReviews(): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT * FROM directory_reviews WHERE site_id = @site_id AND status = 'pending' ORDER BY created_at ASC LIMIT 200");
        $st->execute();
        return $st->fetchAll();
    }

    public function allReviews(string $status = '', int $limit = 200): array
    {
        $this->ensureTables();
        $limit = max(1, min(500, $limit));
        $where = 'site_id = @site_id';
        $params = [];
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $where .= ' AND status = ?';
            $params[] = $status;
        }
        $st = Database::instance()->prepare("SELECT * FROM directory_reviews WHERE $where ORDER BY created_at DESC LIMIT $limit");
        $st->execute($params);
        return $st->fetchAll();
    }

    public function top(int $limit = 10, string $category = '', string $city = ''): array
    {
        $limit = max(1, min(50, $limit));
        $items = [];
        foreach ($this->profiles() as $profile) {
            if ($category !== '' && stripos((string)($profile['category'] ?? ''), $category) === false) continue;
            if ($city !== '' && stripos((string)($profile['city'] ?? ''), $city) === false) continue;
            $summary = $this->summary((string)$profile['slug']);
            if ((int)$summary['count'] < 3) continue;
            if ((float)$summary['average'] < 4.3) continue;
            if ((int)$summary['verified_count'] < 1) continue;
            $bayes = (((float)$summary['average'] * (int)$summary['count']) + (4.5 * 3)) / ((int)$summary['count'] + 3);
            $volume = min(1, (int)$summary['count'] / 20);
            $verifiedRatio = (int)$summary['count'] > 0 ? min(1, (int)$summary['verified_count'] / (int)$summary['count']) : 0;
            $profile['summary'] = $summary;
            $profile['confidence_score'] = round(($bayes * 0.7) + ($volume * 0.2) + ($verifiedRatio * 0.1), 3);
            $items[] = $profile;
        }
        usort($items, function (array $a, array $b): int {
            return [$b['confidence_score'], $b['summary']['count'], $b['summary']['average']] <=> [$a['confidence_score'], $a['summary']['count'], $a['summary']['average']];
        });
        $items = array_slice($items, 0, $limit);
        foreach ($items as $i => &$item) $item['top_rank'] = $i + 1;
        unset($item);
        return $items;
    }

    public function topRank(string $ref): int
    {
        foreach ($this->top(10) as $item) {
            if ((string)$item['slug'] === $ref) return (int)$item['top_rank'];
        }
        return 0;
    }

    public function toques(string $status = 'open'): array
    {
        $this->ensureTables();
        $statuses = ['open', 'pending', 'closed', 'cancelled'];
        $where = 'site_id = @site_id';
        $params = [];
        if (in_array($status, $statuses, true)) {
            $where .= ' AND status = ?';
            $params[] = $status;
        }
        $st = Database::instance()->prepare("SELECT * FROM directory_toques WHERE $where ORDER BY COALESCE(event_date, '2999-12-31') ASC, id DESC LIMIT 200");
        $st->execute($params);
        return $st->fetchAll();
    }

    public function toque(int $id): ?array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT * FROM directory_toques WHERE id = ? AND site_id = @site_id LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) return null;
        $row['applications'] = $this->toqueApplications($id);
        return $row;
    }

    public function createToque(array $data, string $by = 'admin'): array
    {
        $this->ensureTables();
        $title = trim((string)($data['title'] ?? ''));
        $city = trim((string)($data['city'] ?? ''));
        if (mb_strlen($title) < 4) return ['ok' => false, 'message' => 'Escribe un título para el toque.'];
        if ($city === '') return ['ok' => false, 'message' => 'Indica la ciudad.'];
        $date = null;
        $dateIn = trim((string)($data['event_date'] ?? ''));
        if ($dateIn !== '') {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $dateIn);
            if (!$dt || $dt->format('Y-m-d') !== $dateIn) return ['ok' => false, 'message' => 'La fecha no es válida.'];
            $date = $dateIn;
        }
        $type = (string)($data['toque_type'] ?? 'open');
        if (!in_array($type, ['open', 'curated'], true)) $type = 'open';
        $status = (string)($data['status'] ?? 'pending');
        if (!in_array($status, ['pending', 'open', 'closed', 'cancelled'], true)) $status = 'pending';
        $db = Database::instance();
        $st = $db->prepare("INSERT INTO directory_toques (site_id, title, description, toque_type, city, event_date, instrument, genre, budget, organizer_name, organizer_email, organizer_phone, status, created_by) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $st->execute([
            mb_substr($title, 0, 190),
            trim((string)($data['description'] ?? '')),
            $type,
            mb_substr($city, 0, 120),
            $date,
            mb_substr(trim((string)($data['instrument'] ?? '')), 0, 100),
            mb_substr(trim((string)($data['genre'] ?? '')), 0, 100),
            mb_substr(trim((string)($data['budget'] ?? '')), 0, 80),
            mb_substr(trim((string)($data['organizer_name'] ?? '')), 0, 190),
            mb_substr(strtolower(trim((string)($data['organizer_email'] ?? ''))), 0, 190),
            mb_substr(trim((string)($data['organizer_phone'] ?? '')), 0, 40),
            $status,
            mb_substr($by, 0, 150),
        ]);
        return ['ok' => true, 'id' => (int)$db->lastInsertId()];
    }

    public function moderateToque(int $id, string $status): array
    {
        $this->ensureTables();
        if (!in_array($status, ['pending', 'open', 'closed', 'cancelled'], true)) return ['ok' => false, 'message' => 'Estado inválido.'];
        Database::instance()->prepare("UPDATE directory_toques SET status = ? WHERE id = ? AND site_id = @site_id")->execute([$status, $id]);
        return ['ok' => true];
    }

    public function inviteToToque(int $toqueId, int $profileId, string $by = 'admin'): array
    {
        $this->ensureTables();
        $toque = $this->toque($toqueId);
        $profile = $this->profileById($profileId);
        if (!$toque || !$profile) return ['ok' => false, 'message' => 'Toque o perfil no encontrado.'];
        if (!$profile['verified']) return ['ok' => false, 'message' => 'El perfil debe estar verificado.'];
        if ((string)$toque['toque_type'] === 'curated' && !$this->canBeInvited($profile)) {
            return ['ok' => false, 'message' => 'Este toque curado requiere rango Escenario o superior.'];
        }
        $db = Database::instance();
        $st = $db->prepare("INSERT INTO directory_toque_applications (site_id, toque_id, profile_id, status, created_by) VALUES (@site_id, ?, ?, 'invited', ?) ON DUPLICATE KEY UPDATE status = IF(status IN ('declined','rejected'), 'invited', status), created_by = VALUES(created_by)");
        $st->execute([$toqueId, $profileId, mb_substr($by, 0, 150)]);
        return ['ok' => true, 'message' => 'Invitación registrada.'];
    }

    public function toqueApplications(int $toqueId): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT a.*, p.display_name, p.rank_key, p.points FROM directory_toque_applications a LEFT JOIN directory_profiles p ON p.id = a.profile_id AND p.site_id = a.site_id WHERE a.site_id = @site_id AND a.toque_id = ? ORDER BY a.id ASC");
        $st->execute([$toqueId]);
        return $st->fetchAll();
    }

    public function respondToqueApplication(int $id, string $status): array
    {
        $this->ensureTables();
        if (!in_array($status, ['accepted', 'declined', 'rejected', 'invited'], true)) return ['ok' => false, 'message' => 'Estado inválido.'];
        Database::instance()->prepare("UPDATE directory_toque_applications SET status = ? WHERE id = ? AND site_id = @site_id")->execute([$status, $id]);
        return ['ok' => true];
    }

    public function monyas(string $status = 'active'): array
    {
        $this->ensureTables();
        $where = 'site_id = @site_id';
        $params = [];
        if (in_array($status, ['pending', 'active', 'paused', 'blocked'], true)) {
            $where .= ' AND m.status = ?';
            $params[] = $status;
        }
        $st = Database::instance()->prepare("SELECT m.*, p.display_name AS leader_name FROM directory_monyas m LEFT JOIN directory_profiles p ON p.id = m.leader_profile_id AND p.site_id = m.site_id WHERE m.$where ORDER BY m.id DESC LIMIT 200");
        $st->execute($params);
        return $st->fetchAll();
    }

    public function monya(int $id): ?array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT m.*, p.display_name AS leader_name FROM directory_monyas m LEFT JOIN directory_profiles p ON p.id = m.leader_profile_id AND p.site_id = m.site_id WHERE m.id = ? AND m.site_id = @site_id LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) return null;
        $ms = Database::instance()->prepare("SELECT mm.*, p.display_name, p.slug AS profile_slug, p.rank_key, p.points FROM directory_monya_members mm LEFT JOIN directory_profiles p ON p.id = mm.profile_id AND p.site_id = mm.site_id WHERE mm.site_id = @site_id AND mm.monya_id = ? AND mm.status IN ('invited','active') ORDER BY mm.id ASC");
        $ms->execute([$id]);
        $row['members'] = $ms->fetchAll();
        return $row;
    }

    public function createMonya(array $data, string $by = 'admin'): array
    {
        $this->ensureTables();
        $leaderId = (int)($data['leader_profile_id'] ?? 0);
        $leader = $this->profileById($leaderId);
        if (!$leader) return ['ok' => false, 'message' => 'Selecciona un líder con perfil verificado.'];
        if (!$this->canLeadMonya($leader)) return ['ok' => false, 'message' => 'Para crear una moña se requiere rango Referente o superior.'];
        $name = trim((string)($data['name'] ?? ''));
        if (mb_strlen($name) < 3) return ['ok' => false, 'message' => 'Escribe el nombre de la moña.'];
        $status = (string)($data['status'] ?? 'pending');
        if (!in_array($status, ['pending', 'active', 'paused', 'blocked'], true)) $status = 'pending';
        $slug = $this->slugify($name);
        $db = Database::instance();
        $check = $db->prepare("SELECT id FROM directory_monyas WHERE site_id = @site_id AND slug = ? LIMIT 1");
        $check->execute([$slug]);
        if ($check->fetchColumn()) $slug .= '-' . time();
        $st = $db->prepare("INSERT INTO directory_monyas (site_id, name, slug, description, city, genre, leader_profile_id, status, created_by) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?)");
        $st->execute([
            mb_substr($name, 0, 190), $slug, trim((string)($data['description'] ?? '')),
            mb_substr(trim((string)($data['city'] ?? '')), 0, 120),
            mb_substr(trim((string)($data['genre'] ?? '')), 0, 100),
            $leaderId, $status, mb_substr($by, 0, 150),
        ]);
        $id = (int)$db->lastInsertId();
        $this->addMonyaMember($id, $leaderId, 'Líder', $by);
        $db->prepare("UPDATE directory_monya_members SET status = 'active' WHERE site_id = @site_id AND monya_id = ? AND profile_id = ?")->execute([$id, $leaderId]);
        return ['ok' => true, 'id' => $id, 'slug' => $slug];
    }

    public function moderateMonya(int $id, string $status): array
    {
        $this->ensureTables();
        if (!in_array($status, ['pending', 'active', 'paused', 'blocked'], true)) return ['ok' => false, 'message' => 'Estado inválido.'];
        Database::instance()->prepare("UPDATE directory_monyas SET status = ? WHERE id = ? AND site_id = @site_id")->execute([$status, $id]);
        return ['ok' => true];
    }

    public function addMonyaMember(int $monyaId, int $profileId, string $role = '', string $by = 'admin'): array
    {
        $this->ensureTables();
        $monya = $this->monya($monyaId);
        $profile = $this->profileById($profileId);
        if (!$monya || !$profile) return ['ok' => false, 'message' => 'Moña o perfil no encontrado.'];
        if (!$this->canBeInvited($profile)) return ['ok' => false, 'message' => 'La invitación requiere rango Escenario o superior.'];
        $db = Database::instance();
        $st = $db->prepare("INSERT INTO directory_monya_members (site_id, monya_id, profile_id, role, status) VALUES (@site_id, ?, ?, ?, 'invited') ON DUPLICATE KEY UPDATE role = VALUES(role), status = IF(status = 'removed', 'invited', status)");
        $st->execute([$monyaId, $profileId, mb_substr(trim($role), 0, 80)]);
        return ['ok' => true];
    }

    public function respondMonyaInvite(int $id, string $status): array
    {
        $this->ensureTables();
        if (!in_array($status, ['active', 'declined', 'removed'], true)) return ['ok' => false, 'message' => 'Estado inválido.'];
        Database::instance()->prepare("UPDATE directory_monya_members SET status = ? WHERE id = ? AND site_id = @site_id")->execute([$status, $id]);
        return ['ok' => true];
    }

    public function canLeadMonya(array $profile): bool
    {
        return in_array((string)($profile['rank_key'] ?? 'semilla'), ['referente', 'legado'], true);
    }

    public function canBeInvited(array $profile): bool
    {
        return in_array((string)($profile['rank_key'] ?? 'semilla'), ['escenario', 'referente', 'legado'], true);
    }

    private function profileById(int $id): ?array
    {
        $st = Database::instance()->prepare("SELECT * FROM directory_profiles WHERE id = ? AND site_id = @site_id AND status = 'active' LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ? $this->mapProfile($row, 'directory') : null;
    }

    private function mapProfile(array $row, string $source): array
    {
        return [
            'id' => (int)$row['id'],
            'slug' => (string)$row['slug'],
            'name' => (string)($row['display_name'] ?? $row['name'] ?? ''),
            'category' => (string)($row['category'] ?? ''),
            'subcategory' => (string)($row['subcategory'] ?? ''),
            'instrument' => (string)($row['instrument'] ?? ''),
            'city' => (string)($row['city'] ?? ''),
            'region' => (string)($row['region'] ?? ''),
            'bio' => (string)($row['bio'] ?? ''),
            'photo' => (string)($row['photo'] ?? ''),
            'website' => (string)($row['website'] ?? ''),
            'whatsapp' => (string)($row['whatsapp'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'verified' => (int)($row['verified'] ?? 0) === 1,
            'is_sonika_artist' => (int)($row['is_sonika_artist'] ?? 0) === 1,
            'founder' => (int)($row['founder'] ?? 0) === 1,
            'points' => (int)($row['points'] ?? 0),
            'rank_key' => (string)($row['rank_key'] ?? 'semilla'),
            'profile_type' => (string)($row['profile_type'] ?? 'artist'),
            'source' => $source,
        ];
    }

    private function mapAffiliateProfile(array $row, bool $visible = true, string $code = ''): array
    {
        $name = trim((string)($row['artist_name'] ?? '')) ?: (trim((string)($row['public_name'] ?? '')) ?: 'Partner SONIKA');
        return [
            'id' => (int)($row['id'] ?? 0),
            'slug' => (string)($row['slug'] ?? ''),
            'name' => $name,
            'category' => 'Sonika Partners',
            'subcategory' => '',
            'instrument' => (string)($row['music_instrument'] ?? ''),
            'city' => '',
            'region' => '',
            'bio' => '',
            'photo' => $visible ? (string)($row['store_image'] ?? '') : '',
            'website' => '',
            'whatsapp' => '',
            'email' => (string)($row['email'] ?? ''),
            'verified' => true,
            'is_sonika_artist' => true,
            'founder' => false,
            'points' => 0,
            'rank_key' => 'semilla',
            'profile_type' => 'artist',
            'source' => 'affiliate',
            'profile_visible' => $visible,
            'store_link' => $code !== '' ? '/r/' . rawurlencode($code) : '',
        ];
    }
}
