<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

class TranslationService
{
    private static bool $tableChecked = false;

    public static function sourceLocale(): string
    {
        return 'en';
    }

    public static function enabled(string $locale): bool
    {
        return $locale !== self::sourceLocale() && (string)Config::get('DEEPSEEK_API_KEY', '') !== '';
    }

    private static function ensureTable(): void
    {
        if (self::$tableChecked) return;
        self::$tableChecked = true;
        try {
            Database::instance()->exec("CREATE TABLE IF NOT EXISTS wwi_translations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                site_id INT NOT NULL,
                locale VARCHAR(5) NOT NULL,
                hash CHAR(32) NOT NULL,
                source TEXT NOT NULL,
                translated LONGTEXT NOT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_tr (site_id, locale, hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {
        }
    }

    private static function skip(string $text): bool
    {
        $t = trim($text);
        if ($t === '' || mb_strlen($t) < 2) return true;
        if (preg_match('#^(https?:|/|mailto:|tel:|\#)#i', $t)) return true;
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $t)) return true;
        if (preg_match('/^[\d\s.,%+\-–—:]+$/', $t)) return true;
        if (preg_match('/^[A-Z0-9&.\s()\x{2014}\-–]+$/u', $t) && preg_match('/[A-Z]{2,}/', $t)) return true;
        if (!preg_match('/[A-Za-z]{2,}/', $t)) return true;
        return false;
    }

    public static function translateConfig(array $config, string $locale): array
    {
        if (!self::enabled($locale)) return $config;
        $texts = [];
        self::collect($config, $texts);
        if (!$texts) return $config;
        $map = self::translateBatch(array_values(array_unique($texts)), $locale);
        self::apply($config, $map);
        return $config;
    }

    public static function collectConfigTexts(array $config): array
    {
        $texts = [];
        self::collect($config, $texts);
        return array_values(array_unique($texts));
    }

    public static function translateText(string $text, string $locale): string
    {
        if (!self::enabled($locale) || self::skip($text)) return $text;
        $map = self::translateBatch([$text], $locale);
        return $map[$text] ?? $text;
    }

    private static function collect($node, array &$out, ?string $key = null): void
    {
        if (is_array($node)) {
            foreach ($node as $k => $v) self::collect($v, $out, is_string($k) ? $k : null);
            return;
        }
        if (!is_string($node)) return;
        if ($key !== null && preg_match('/url|link|href|color|icon|image|img|slug|id$|hash|src|video|map/i', $key)) return;
        if (self::skip($node)) return;
        $out[] = $node;
    }

    private static function apply(&$node, array $map, ?string $key = null): void
    {
        if (is_array($node)) {
            foreach ($node as $k => &$v) self::apply($v, $map, is_string($k) ? $k : null);
            unset($v);
            return;
        }
        if (!is_string($node)) return;
        if ($key !== null && preg_match('/url|link|href|color|icon|image|img|slug|id$|hash|src|video|map/i', $key)) return;
        if (isset($map[$node])) $node = $map[$node];
    }

    public static function translateBatch(array $texts, string $locale): array
    {
        $texts = array_values(array_filter(array_map('trim', $texts), fn($t) => $t !== ''));
        if (!$texts || !self::enabled($locale)) return [];
        self::ensureTable();
        $siteId = (int)Config::get('SITE_ID', '1');
        $db = Database::instance();

        $hashes = [];
        foreach ($texts as $t) $hashes[$t] = md5($t);

        $map = [];
        $missing = [];
        $chunks = array_chunk(array_values(array_unique($texts)), 40);
        foreach ($chunks as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));
            $vals = array_map(fn($t) => $hashes[$t], $chunk);
            $st = $db->prepare("SELECT hash, translated FROM wwi_translations WHERE site_id = ? AND locale = ? AND hash IN ($in)");
            $st->execute(array_merge([$siteId, $locale], $vals));
            $found = [];
            foreach ($st->fetchAll() as $row) $found[$row['hash']] = $row['translated'];
            foreach ($chunk as $t) {
                if (isset($found[$hashes[$t]])) $map[$t] = $found[$hashes[$t]];
                else $missing[] = $t;
            }
        }

        foreach (array_chunk($missing, 25) as $batch) {
            $translated = self::callApi($batch, $locale);
            if (!$translated) continue;
            $ins = $db->prepare("INSERT INTO wwi_translations (site_id, locale, hash, source, translated) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE translated = VALUES(translated)");
            foreach ($batch as $i => $src) {
                if (!isset($translated[$i])) continue;
                $dst = $translated[$i];
                $map[$src] = $dst;
                try {
                    $ins->execute([$siteId, $locale, $hashes[$src], $src, $dst]);
                } catch (\Throwable $e) {
                }
            }
        }
        return $map;
    }

    private static function callApi(array $texts, string $locale): array
    {
        $key = (string)Config::get('DEEPSEEK_API_KEY', '');
        if ($key === '') return [];
        $target = $locale === 'es' ? 'Spanish' : $locale;
        $system = 'You are a professional translator for INTSOLCOM, a corporate technology holding company. '
            . 'Translate every string in the user JSON array from English to ' . $target . '. '
            . 'Return ONLY a valid JSON array of the same length and order, with translations as strings. '
            . 'Rules: preserve HTML tags exactly; keep URLs, emails, phone numbers, colors and placeholders unchanged; '
            . 'never translate brand and product names in capitals (INTSOLCOM, WONTIA, WONTIA AIP, WONTIA FOOD SECURITY, WONTIA IA ANNOTATION SUITE, MACROPONDER, TIA, AIS, BPO, SaaS); '
            . 'keep a professional, concise, friendly B2B tone; do not add explanations or commentary.';

        $payload = [
            'model' => 'deepseek-chat',
            'temperature' => 0,
            'max_tokens' => 6000,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode(array_values($texts), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ],
        ];

        $ch = curl_init('https://api.deepseek.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp === false || $code >= 400) return [];

        $data = json_decode($resp, true);
        $content = trim((string)($data['choices'][0]['message']['content'] ?? ''));
        if ($content === '') return [];
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);
        $arr = json_decode($content, true);
        if (!is_array($arr)) return [];

        $out = [];
        foreach (array_values($texts) as $i => $src) {
            $dst = $arr[$i] ?? null;
            if (!is_string($dst) || trim($dst) === '') continue;
            if (preg_match('/no puedo traducir|lo siento|cannot translate|i cannot/i', $dst)) continue;
            $out[$i] = $dst;
        }
        return $out;
    }
}
