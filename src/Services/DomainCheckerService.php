<?php
namespace App\Services;

class DomainCheckerService
{
    public function check(string $rawName): array
    {
        $name = strtolower(trim((string)$rawName));
        $name = preg_replace('/^https?:\/\//', '', $name);
        $name = rtrim($name, '/');

        if ($name === '' || !preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)\.[a-z]{2,24}$/', $name)) {
            return ['name' => $rawName, 'state' => 'INVALID', 'message' => 'Invalid domain name'];
        }

        $db = \App\Core\Database::instance();
        $stmt = $db->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'wwi.domain_check_provider'");
        $stmt->execute();
        $provider = $stmt->fetchColumn();
        if (!$provider) {
            return ['name' => $name, 'state' => 'ERROR', 'message' => 'Domain availability provider is not configured yet'];
        }

        return ['name' => $name, 'state' => 'CHECKING', 'message' => 'Provider configured: ' . $provider . '. Real availability check pending integration.'];
    }
}
