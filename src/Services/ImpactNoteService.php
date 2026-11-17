<?php
namespace App\Services;

class ImpactNoteService
{
    private const DEFAULT_PHRASES = [
        'Cada vez que compras donaremos el 10% de las utilidades a Niños Wayuu de La Fundación: Misión Génesis y Niños San Roque de CC- In - Pacto.',
        'Con cada compra, el 10% de las utilidades llega a los niños Wayuu de Misión Génesis y a los niños San Roque de CC- In - Pacto.',
        'Tu compra también transforma vidas: destinamos el 10% de las utilidades a la niñez Wayuu de Misión Génesis y a los niños San Roque de CC- In - Pacto.',
    ];

    public function defaults(): array
    {
        return [
            'enabled' => true,
            'label' => 'Impacto SONIKA',
            'interval' => 9,
            'phrases' => self::DEFAULT_PHRASES,
        ];
    }

    public function config(): array
    {
        $s = (new StoreService())->settings();
        $raw = json_decode((string)($s['store_impact_note'] ?? ''), true);
        return $this->normalize(is_array($raw) ? $raw : []);
    }

    public function normalize(array $raw): array
    {
        $d = $this->defaults();
        $phrases = $this->lines($raw['phrases'] ?? $d['phrases']);
        if (!$phrases) $phrases = $d['phrases'];
        return [
            'enabled' => $this->bool($raw['enabled'] ?? $d['enabled']),
            'label' => $this->clean($raw['label'] ?? $d['label'], 60) ?: $d['label'],
            'interval' => max(4, min(60, (int)($raw['interval'] ?? $d['interval']))),
            'phrases' => $phrases,
        ];
    }

    public function normalizeJson($raw): string
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) $raw = [];
        return json_encode($this->normalize($raw), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function settingsJson(): string
    {
        return json_encode($this->config(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function lines($value): array
    {
        if (is_string($value)) $value = preg_split('/\r\n|\r|\n/', $value) ?: [];
        if (!is_array($value)) return [];
        $out = [];
        foreach ($value as $line) {
            $line = $this->clean($line, 320);
            if ($line === '') continue;
            $out[] = $line;
            if (count($out) >= 8) break;
        }
        return array_values(array_unique($out));
    }

    private function clean($value, int $max): string
    {
        $value = trim(strip_tags((string)$value));
        return mb_substr($value, 0, $max);
    }

    private function bool($value): bool
    {
        if (is_bool($value)) return $value;
        $value = strtolower(trim((string)$value));
        return !in_array($value, ['0', 'false', 'no', 'off', ''], true);
    }
}
