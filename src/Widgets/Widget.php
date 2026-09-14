<?php
namespace App\Widgets;

abstract class Widget
{
    abstract public function render(array $config = []): string;

    public static function meta(): array
    {
        $name = (new \ReflectionClass(static::class))->getShortName();
        $id = strtolower(preg_replace('/Widget$/', '', $name));
        return ['id' => $id, 'name' => ucfirst($id), 'icon' => 'cube', 'category' => 'general', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [];
    }

    public static function defaultConfig(): array
    {
        $schema = static::configSchema();
        $defaults = [];
        foreach ($schema as $field) {
            $defaults[$field['key']] = $field['default'] ?? '';
        }
        return $defaults;
    }

    public static function editContract(): array
    {
        $repeaters = [];
        $editable = [];
        foreach (static::configSchema() as $field) {
            if (($field['type'] ?? '') === 'repeater') $repeaters[$field['key']] = $field['fields'] ?? [];
            if (!empty($field['inline'])) $editable[] = $field['key'];
        }
        return ['editable' => $editable, 'repeaters' => $repeaters, 'sources' => [], 'dynamic' => false];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:var(--w-surface);border:1px solid var(--w-border);border-radius:8px;padding:20px;text-align:center;color:var(--w-muted);font-size:12px">' . htmlspecialchars(static::meta()['name']) . '</div>';
    }

    protected function mergeConfig(array $config): array
    {
        return array_merge(static::defaultConfig(), $config);
    }

    protected function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    protected function safeJson($value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value)) return json_decode($value, true) ?: [];
        return [];
    }

    protected function rich($value): string
    {
        return \App\Services\LiveEditorService::sanitizeRichHtml((string)$value);
    }
}
