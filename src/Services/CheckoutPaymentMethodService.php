<?php
namespace App\Services;

use App\Core\Database;

/**
 * Controla qué métodos de pago se muestran en el checkout.
 *
 * - Detecta todos los métodos instalados (Mercado Pago, Bre-B, Addi,
 *   Sistecrédito, ePayco y pago asistido).
 * - Permite activar/desactivar, ordenar, renombrar, agregar una etiqueta
 *   y programar una ventana de visibilidad.
 * - Si no hay configuración guardada, muestra automáticamente todos los
 *   métodos disponibles (comportamiento anterior).
 */
class CheckoutPaymentMethodService
{
    public const SETTING_VISIBILITY = 'store_checkout_methods';
    public const SETTING_DEFAULT = 'store_checkout_default_method';

    private const DEFAULT_SORT = [
        'mercadopago' => 10,
        'breb' => 20,
        'addi' => 30,
        'sistecredito' => 40,
        'epayco' => 50,
        'request' => 90,
    ];

    /** Todos los métodos detectados, disponibles o no. */
    public function all(): array
    {
        $out = [];
        $brebSettings = [];
        try { $brebSettings = (new BrebService())->settings(); } catch (\Throwable $e) {}

        // Mercado Pago
        try {
            $mp = new MercadoPagoService();
            $ms = $mp->settings();
            $out[] = $this->method('mercadopago', (string)($ms['label'] ?? 'Mercado Pago'), (string)($ms['description'] ?? 'Paga con tarjeta, PSE o transferencia. Si eliges PSE, selecciona tu banco antes de pagar.'), [
                'source' => 'mercadopago',
                'configured' => $mp->isConfigured(),
                'available' => $mp->isAvailable(),
                'coming_soon' => !$mp->isConfigured(),
                'badge' => '',
                'sort' => self::DEFAULT_SORT['mercadopago'],
            ]);
        } catch (\Throwable $e) {
        }

        // Bre-B
        try {
            $breb = new BrebService();
            $bc = $breb->publicConfig();
            $available = $breb->available();
            $out[] = $this->method('breb', (string)($bc['breb']['label'] ?? 'Bre-B'), 'Escanea el QR y paga desde tu app bancaria.', [
                'source' => 'breb',
                'configured' => (string)($bc['breb']['qr_image'] ?? '') !== '',
                'available' => $available,
                'coming_soon' => !$available,
                'badge' => '',
                'sort' => self::DEFAULT_SORT['breb'],
                'primary' => true,
            ]);
        } catch (\Throwable $e) {
        }

        // Addi
        try {
            $addi = new AddiService();
            $as = $addi->settings();
            $pm = $addi->publicMethod();
            $out[] = $this->method('addi', (string)($as['label'] ?? 'Paga con Addi'), 'Compra ahora y paga a cuotas con Addi.', [
                'source' => 'addi',
                'logo' => (string)($as['logo_url'] ?? ''),
                'configured' => $addi->isConfigured(),
                'available' => $pm !== null,
                'coming_soon' => $pm === null,
                'badge' => $pm !== null ? 'Crédito' : 'Próximamente',
                'sort' => self::DEFAULT_SORT['addi'],
                'redirect' => true,
            ]);
        } catch (\Throwable $e) {
        }

        // Sistecrédito
        try {
            $sc = new SistecreditoService();
            $available = $sc->isAvailable();
            $out[] = $this->method('sistecredito', 'Sistecrédito', 'Compra ahora y paga a cuotas con Sistecrédito.', [
                'source' => 'sistecredito',
                'configured' => $sc->isConfigured(),
                'available' => $available,
                'coming_soon' => !$available,
                'badge' => $available ? '' : 'Próximamente',
                'sort' => self::DEFAULT_SORT['sistecredito'],
            ]);
        } catch (\Throwable $e) {
        }

        // ePayco
        try {
            $epLink = trim((string)($brebSettings['epayco_link'] ?? ''));
            $epEnabled = !empty($brebSettings['epayco_enabled']) && $epLink !== '';
            $out[] = $this->method('epayco', (string)($brebSettings['epayco_label'] ?? 'ePayco'), 'Paga en el entorno seguro de ePayco.', [
                'source' => 'epayco',
                'configured' => $epLink !== '',
                'available' => $epEnabled,
                'coming_soon' => !$epEnabled,
                'badge' => '',
                'sort' => self::DEFAULT_SORT['epayco'],
                'external' => true,
                'link' => $epLink,
            ]);
        } catch (\Throwable $e) {
        }

        // Pago asistido / WhatsApp
        try {
            $requestEnabled = !empty($brebSettings['request_enabled']);
            $out[] = $this->method('request', 'Coordinar pago por WhatsApp', 'Te ayudamos a completar el pago de forma asistida.', [
                'source' => 'request',
                'configured' => true,
                'available' => $requestEnabled,
                'coming_soon' => !$requestEnabled,
                'badge' => 'Asistido',
                'sort' => self::DEFAULT_SORT['request'],
                'fallback' => true,
            ]);
        } catch (\Throwable $e) {
        }

        usort($out, function ($a, $b) {
            return ($a['sort'] <=> $b['sort']) ?: ($a['default_sort'] <=> $b['default_sort']);
        });
        return $out;
    }

    private function method(string $id, string $label, string $description, array $extra = []): array
    {
        $sort = (int)($extra['sort'] ?? (self::DEFAULT_SORT[$id] ?? 99));
        return array_merge([
            'id' => $id,
            'label' => $label,
            'description' => $description,
            'logo' => '',
            'source' => $id,
            'configured' => false,
            'available' => false,
            'coming_soon' => true,
            'badge' => '',
            'sort' => $sort,
            'default_sort' => $sort,
        ], $extra, [
            'id' => $id,
            'label' => $label,
            'description' => $description,
            'sort' => $sort,
            'default_sort' => $sort,
        ]);
    }

    /** @return array<string,array>|null null = modo automático */
    private function decodedConfig(): ?array
    {
        try {
            $settings = (new StoreService())->settings();
            $raw = trim((string)($settings[self::SETTING_VISIBILITY] ?? ''));
        } catch (\Throwable $e) {
            return null;
        }
        if ($raw === '') return null;
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return null;
        $map = [];
        foreach ($decoded as $row) {
            if (is_string($row)) {
                $id = trim($row);
                if ($id !== '') $map[$id] = ['id' => $id, 'enabled' => 1, 'label' => '', 'badge' => '', 'sort' => self::DEFAULT_SORT[$id] ?? 99, 'from' => '', 'to' => ''];
                continue;
            }
            if (!is_array($row)) continue;
            $id = trim((string)($row['id'] ?? ''));
            if ($id === '') continue;
            $map[$id] = [
                'id' => $id,
                'enabled' => !empty($row['enabled']) ? 1 : 0,
                'label' => mb_substr(trim((string)($row['label'] ?? '')), 0, 60),
                'badge' => mb_substr(trim((string)($row['badge'] ?? '')), 0, 30),
                'sort' => max(0, (int)($row['sort'] ?? (self::DEFAULT_SORT[$id] ?? 99))),
                'from' => trim((string)($row['from'] ?? '')),
                'to' => trim((string)($row['to'] ?? '')),
            ];
        }
        return $map;
    }

    /** Métodos visibles ahora, con etiquetas, orden y programación aplicados. */
    public function visible(?array $all = null): array
    {
        $all = $all ?? $this->all();
        $configured = $this->decodedConfig();
        $now = new \DateTime('now', new \DateTimeZone('America/Bogota'));
        $visible = [];
        foreach ($all as $m) {
            $id = (string)$m['id'];
            $cfg = $configured !== null ? ($configured[$id] ?? null) : null;
            if ($configured !== null && $cfg === null) continue;
            if ($cfg !== null && empty($cfg['enabled'])) continue;
            if (empty($m['available'])) continue;
            if ($cfg !== null && !$this->withinSchedule($cfg, $now)) continue;
            $copy = $m;
            if ($cfg !== null) {
                $label = trim((string)($cfg['label'] ?? ''));
                if ($label !== '') $copy['label'] = $label;
                $badge = trim((string)($cfg['badge'] ?? ''));
                if ($badge !== '') $copy['badge'] = $badge;
                $copy['sort'] = (int)($cfg['sort'] ?? $m['sort']);
                $copy['scheduled'] = ((string)($cfg['from'] ?? '') !== '' || (string)($cfg['to'] ?? '') !== '');
                $copy['from'] = (string)($cfg['from'] ?? '');
                $copy['to'] = (string)($cfg['to'] ?? '');
            }
            $copy['enabled'] = 1;
            $copy['visible_now'] = true;
            $visible[] = $copy;
        }

        usort($visible, function ($a, $b) {
            return ($a['sort'] <=> $b['sort']) ?: ($a['default_sort'] <=> $b['default_sort']);
        });

        if (!$visible) {
            // Respaldo: no bloquear la compra si un método dejó de estar disponible.
            $fallback = null;
            foreach ($all as $m) {
                if ((string)$m['id'] === 'request' && !empty($m['available'])) { $fallback = $m; break; }
            }
            if (!$fallback) {
                foreach ($all as $m) {
                    if (!empty($m['available'])) { $fallback = $m; break; }
                }
            }
            if ($fallback) {
                $fallback['enabled'] = 1;
                $fallback['visible_now'] = true;
                $fallback['fallback_active'] = true;
                $visible[] = $fallback;
            }
        }
        return $visible;
    }

    private function withinSchedule(array $cfg, \DateTime $now): bool
    {
        $tz = new \DateTimeZone('America/Bogota');
        $from = trim((string)($cfg['from'] ?? ''));
        $to = trim((string)($cfg['to'] ?? ''));
        try {
            if ($from !== '') {
                $dt = new \DateTime($from, $tz);
                if ($now < $dt) return false;
            }
            if ($to !== '') {
                $dt = new \DateTime($to, $tz);
                if ($now > $dt) return false;
            }
        } catch (\Throwable $e) {
            return true;
        }
        return true;
    }

    public function defaultMethod(?array $visible = null): string
    {
        $visible = $visible ?? $this->visible();
        $ids = array_map(fn($m) => (string)$m['id'], $visible);
        try {
            $settings = (new StoreService())->settings();
            $default = trim((string)($settings[self::SETTING_DEFAULT] ?? ''));
        } catch (\Throwable $e) {
            $default = '';
        }
        if ($default !== '' && in_array($default, $ids, true)) return $default;
        return $ids[0] ?? 'request';
    }

    public function isVisible(string $id): bool
    {
        foreach ($this->visible() as $m) {
            if ((string)$m['id'] === $id) return true;
        }
        return false;
    }

    public function isConfigured(): bool
    {
        return $this->decodedConfig() !== null;
    }

    /** Configuración que consume el checkout (tema y JS). */
    public function checkoutConfig(): array
    {
        $cfg = [];
        try { $cfg = (new BrebService())->publicConfig(); } catch (\Throwable $e) {}
        $all = $this->all();
        $visible = $this->visible($all);
        $cfg['methods'] = array_map(function ($m) {
            $out = $m;
            $out['disabled'] = empty($m['available']);
            $out['coming_soon'] = !empty($m['coming_soon']);
            return $out;
        }, $visible);
        $cfg['default_method'] = $this->defaultMethod($visible);
        $cfg['visible_methods_count'] = count($visible);
        $cfg['all_methods_count'] = count($all);
        $cfg['checkout_methods_configured'] = $this->decodedConfig() !== null;

        try {
            $addi = new AddiService();
            if ($addi->publicMethod()) {
                $as = $addi->settings();
                $cfg['addi'] = [
                    'force_visible' => (bool)$as['force_visible'],
                    'widget_enabled' => (bool)$as['widget_enabled'],
                    'active' => (bool)($as['last_config']['isActiveAlly'] ?? false),
                ];
            }
        } catch (\Throwable $e) {
        }
        try {
            $mp = new MercadoPagoService();
            if ($mp->isAvailable()) $cfg['mercadopago'] = $mp->publicConfig();
        } catch (\Throwable $e) {
        }
        return $cfg;
    }

    /** Configuración para el panel de administración. */
    public function adminConfig(): array
    {
        $all = $this->all();
        $configured = $this->decodedConfig();
        $visible = $this->visible($all);
        $visibleIds = array_map(fn($m) => (string)$m['id'], $visible);
        $methods = [];
        foreach ($all as $m) {
            $id = (string)$m['id'];
            $cfg = $configured !== null ? ($configured[$id] ?? null) : null;
            $enabled = $configured === null ? (!empty($m['available']) ? 1 : 0) : (!empty($cfg['enabled']) ? 1 : 0);
            $methods[] = array_merge($m, [
                'enabled' => $enabled,
                'label_override' => (string)($cfg['label'] ?? ''),
                'badge_override' => (string)($cfg['badge'] ?? ''),
                'has_override' => $cfg !== null,
                'sort' => (int)($cfg['sort'] ?? $m['sort']),
                'from' => (string)($cfg['from'] ?? ''),
                'to' => (string)($cfg['to'] ?? ''),
                'visible_now' => in_array($id, $visibleIds, true),
            ]);
        }
        usort($methods, fn($a, $b) => ($a['sort'] <=> $b['sort']) ?: ($a['default_sort'] <=> $b['default_sort']));
        return [
            'configured' => $configured !== null,
            'default_method' => $this->defaultMethod($visible),
            'methods' => $methods,
            'preview' => $visible,
            'visible_count' => count($visible),
            'all_count' => count($all),
        ];
    }

    public function saveConfig(array $payload): array
    {
        $all = $this->all();
        $byId = [];
        foreach ($all as $m) $byId[(string)$m['id']] = $m;
        $incoming = $payload['methods'] ?? [];
        if (is_string($incoming)) $incoming = json_decode($incoming, true) ?: [];
        if (!is_array($incoming)) $incoming = [];
        $existing = $this->decodedConfig() ?? [];
        $map = [];

        foreach ($byId as $id => $m) {
            $row = null;
            foreach ($incoming as $candidate) {
                if (is_array($candidate) && (string)($candidate['id'] ?? '') === $id) { $row = $candidate; break; }
            }
            if ($row === null && isset($existing[$id])) {
                $map[$id] = $existing[$id];
                continue;
            }
            $base = $existing[$id] ?? [
                'enabled' => !empty($m['available']) ? 1 : 0,
                'label' => '', 'badge' => '', 'sort' => (int)$m['sort'], 'from' => '', 'to' => '',
            ];
            if ($row === null) {
                $map[$id] = $base;
                continue;
            }
            $from = $this->scheduleDate((string)($row['from'] ?? ''), false);
            $to = $this->scheduleDate((string)($row['to'] ?? ''), true);
            if ($from !== '' && $to !== '' && strtotime($from) > strtotime($to)) {
                return ['ok' => false, 'message' => 'La fecha "desde" no puede ser posterior a la fecha "hasta" en ' . $m['label'] . '.'];
            }
            $map[$id] = [
                'id' => $id,
                'enabled' => !empty($row['enabled']) ? 1 : 0,
                'label' => mb_substr(trim((string)($row['label'] ?? '')), 0, 60),
                'badge' => mb_substr(trim((string)($row['badge'] ?? '')), 0, 30),
                'sort' => max(0, (int)($row['sort'] ?? $m['sort'])),
                'from' => $from,
                'to' => $to,
            ];
        }

        $enabled = array_filter($map, fn($row) => !empty($row['enabled']));
        if (!$enabled) {
            return ['ok' => false, 'message' => 'Debes dejar al menos un método de pago habilitado.'];
        }
        $enabledAvailable = array_filter($map, function ($row) use ($byId) {
            $id = (string)($row['id'] ?? '');
            return !empty($row['enabled']) && !empty($byId[$id]['available']);
        });
        if (!$enabledAvailable) {
            return ['ok' => false, 'message' => 'Debes habilitar al menos un método de pago disponible. Los métodos no configurados no pueden usarse en el checkout.'];
        }
        $default = trim((string)($payload['default_method'] ?? ''));
        if ($default === '' || empty($map[$default]['enabled'])) {
            $default = (string)array_key_first($enabled);
        }
        try {
            (new StoreService())->saveSettings([
                self::SETTING_VISIBILITY => json_encode(array_values($map), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                self::SETTING_DEFAULT => $default,
            ]);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar la configuración de pagos.'];
        }
        return ['ok' => true, 'message' => 'Métodos de pago actualizados.', 'config' => $this->adminConfig()];
    }

    private function scheduleDate(string $raw, bool $endOfDay): string
    {
        $raw = trim($raw);
        if ($raw === '') return '';
        $hasTime = (bool)preg_match('/\d{1,2}:\d{2}/', $raw);
        try {
            $tz = new \DateTimeZone('America/Bogota');
            $dt = new \DateTime($raw . ($hasTime ? '' : ' 00:00:00'), $tz);
        } catch (\Throwable $e) {
            return '';
        }
        if ($endOfDay && !$hasTime) $dt->setTime(23, 59, 59);
        return $dt->format('Y-m-d H:i:s');
    }
}
