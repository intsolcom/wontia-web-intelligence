<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

class PqrfService
{
    public const TYPES = [
        'PETICION' => 'Petición',
        'QUEJA' => 'Queja',
        'RECLAMO' => 'Reclamo',
        'SUGERENCIA' => 'Sugerencia',
        'FELICITACION' => 'Felicitación',
    ];

    public const STATUSES = [
        'RECEIVED' => 'Recibido',
        'IN_REVIEW' => 'En revisión',
        'INFO_REQUESTED' => 'Información solicitada',
        'IN_PROGRESS' => 'En gestión',
        'RESPONDED' => 'Respondido',
        'CLOSED' => 'Cerrado',
    ];

    public const PRIORITIES = [
        'LOW' => 'Baja',
        'NORMAL' => 'Normal',
        'HIGH' => 'Alta',
        'URGENT' => 'Urgente',
    ];

    public const CATEGORIES = [
        'PRODUCTO' => 'Producto',
        'PEDIDO_ENVIO' => 'Pedido o envío',
        'PAGO' => 'Pago',
        'DEVOLUCION_GARANTIA' => 'Devolución o garantía',
        'CUENTA_DATOS' => 'Cuenta o datos personales',
        'SITIO_WEB' => 'Sitio web',
        'OTRO' => 'Otro',
    ];

    public const ROLES = ['superadmin', 'admin', 'editor'];
    public const MAX_FILES = 5;
    public const MAX_FILE_BYTES = 5242880;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
    ];

    private static bool $ensured = false;
    private static array $settingsCache = [];

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true, 'cached' => true];
        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS pqrf_cases (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL,
            case_number VARCHAR(32) NOT NULL,
            customer_id INT NULL,
            type VARCHAR(20) NOT NULL,
            category VARCHAR(60) NULL,
            subject VARCHAR(180) NOT NULL,
            description TEXT NOT NULL,
            order_ref VARCHAR(60) NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'RECEIVED',
            priority VARCHAR(20) NOT NULL DEFAULT 'NORMAL',
            assignee_id INT NULL,
            tracking_token_hash CHAR(64) NOT NULL,
            tracking_token_expires_at DATETIME NULL,
            idempotency_key VARCHAR(64) NULL,
            contact_name VARCHAR(120) NOT NULL,
            contact_email VARCHAR(190) NOT NULL,
            contact_phone VARCHAR(40) NULL,
            auth_accepted TINYINT(1) NOT NULL DEFAULT 0,
            due_at DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_pqrf_number (case_number),
            KEY idx_pqrf_site_status (site_id, status),
            KEY idx_pqrf_customer (customer_id),
            KEY idx_pqrf_due (due_at),
            KEY idx_pqrf_token (site_id, tracking_token_hash),
            UNIQUE KEY uq_pqrf_idem (site_id, idempotency_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS pqrf_case_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            case_id BIGINT UNSIGNED NOT NULL,
            actor_type VARCHAR(20) NOT NULL DEFAULT 'SYSTEM',
            actor_id INT NULL,
            event VARCHAR(40) NOT NULL,
            from_status VARCHAR(30) NULL,
            to_status VARCHAR(30) NULL,
            note TEXT NULL,
            visible_to_customer TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_pqrf_ev_case (case_id, created_at),
            CONSTRAINT fk_pqrf_ev_case FOREIGN KEY (case_id) REFERENCES pqrf_cases (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS pqrf_case_attachments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            case_id BIGINT UNSIGNED NOT NULL,
            original_name VARCHAR(190) NOT NULL,
            storage_path VARCHAR(255) NOT NULL,
            mime VARCHAR(100) NOT NULL,
            bytes INT UNSIGNED NOT NULL,
            sha256 CHAR(64) NULL,
            visibility VARCHAR(10) NOT NULL DEFAULT 'private',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_pqrf_att_case (case_id),
            CONSTRAINT fk_pqrf_att_case FOREIGN KEY (case_id) REFERENCES pqrf_cases (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

        $this->addColumnIfMissing('pqrf_cases', 'tracking_token_expires_at', "ALTER TABLE pqrf_cases ADD COLUMN tracking_token_expires_at DATETIME NULL AFTER tracking_token_hash");
        $this->addColumnIfMissing('pqrf_cases', 'idempotency_key', "ALTER TABLE pqrf_cases ADD COLUMN idempotency_key VARCHAR(64) NULL AFTER tracking_token_expires_at");
        $this->addIndexIfMissing('pqrf_cases', 'idx_pqrf_token', "ALTER TABLE pqrf_cases ADD KEY idx_pqrf_token (site_id, tracking_token_hash)");
        $this->addIndexIfMissing('pqrf_cases', 'uq_pqrf_idem', "ALTER TABLE pqrf_cases ADD UNIQUE KEY uq_pqrf_idem (site_id, idempotency_key)");

        self::$ensured = true;
        return ['ok' => true, 'cached' => false];
    }

    public function settings(): array
    {
        if (self::$settingsCache) return self::$settingsCache;
        $out = [
            'contact_email' => 'clientes@sonika.com.co',
            'admin_email' => 'clientes@sonika.com.co',
            'whatsapp' => '3045650333',
            'phone' => '3045650333',
            'token_days' => 180,
            'due_hours' => 0,
            'public_url' => '',
            'email_enabled' => true,
            'reminders' => true,
            'max_files' => self::MAX_FILES,
            'max_mb' => 5,
        ];
        try {
            $keys = ['pqrf_contact_email', 'pqrf_admin_email', 'pqrf_whatsapp', 'pqrf_contact_phone', 'pqrf_token_days', 'pqrf_due_hours', 'pqrf_public_url', 'pqrf_email_enabled', 'pqrf_reminders', 'pqrf_max_files', 'pqrf_max_mb', 'store_whatsapp'];
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $st = Database::instance()->prepare("SELECT `key`, `value` FROM settings WHERE site_id = @site_id AND `key` IN ($placeholders)");
            $st->execute($keys);
            $map = [];
            foreach ($st->fetchAll() as $row) $map[(string)$row['key']] = (string)$row['value'];

            $contact = trim((string)($map['pqrf_contact_email'] ?? ''));
            if ($contact !== '' && filter_var($contact, FILTER_VALIDATE_EMAIL)) $out['contact_email'] = $contact;
            $admin = trim((string)($map['pqrf_admin_email'] ?? ''));
            if ($admin !== '' && filter_var($admin, FILTER_VALIDATE_EMAIL)) $out['admin_email'] = $admin;
            elseif ($contact !== '' && filter_var($contact, FILTER_VALIDATE_EMAIL)) $out['admin_email'] = $contact;

            $wa = preg_replace('/\D+/', '', (string)($map['pqrf_whatsapp'] ?? ''));
            if ($wa === '') $wa = preg_replace('/\D+/', '', (string)($map['store_whatsapp'] ?? ''));
            if ($wa !== '') $out['whatsapp'] = substr($wa, 0, 20);
            $ph = trim((string)($map['pqrf_contact_phone'] ?? ''));
            if ($ph !== '') $out['phone'] = mb_substr($ph, 0, 40);

            $days = (int)($map['pqrf_token_days'] ?? 180);
            $out['token_days'] = max(1, min(3650, $days));
            $due = (int)($map['pqrf_due_hours'] ?? 0);
            $out['due_hours'] = max(0, min(8760, $due));
            $pub = trim((string)($map['pqrf_public_url'] ?? ''));
            if ($pub !== '' && preg_match('~^https?://[^\s]+$~i', $pub)) $out['public_url'] = rtrim($pub, '/');
            $out['email_enabled'] = (string)($map['pqrf_email_enabled'] ?? '1') !== '0';
            $out['reminders'] = (string)($map['pqrf_reminders'] ?? '1') !== '0';
            $out['max_files'] = max(1, min(self::MAX_FILES, (int)($map['pqrf_max_files'] ?? self::MAX_FILES)));
            $out['max_mb'] = max(1, min(5, (int)($map['pqrf_max_mb'] ?? 5)));
        } catch (\Throwable $e) {
        }
        self::$settingsCache = $out;
        return $out;
    }

    public function channels(): array
    {
        $s = $this->settings();
        $wa = (string)$s['whatsapp'];
        $waUrl = $wa !== '' ? 'https://wa.me/' . (strlen($wa) === 10 ? '57' . $wa : $wa) : '';
        return [
            'contact_email' => $s['contact_email'],
            'whatsapp' => $wa,
            'whatsapp_url' => $waUrl,
            'phone' => $s['phone'],
            'login_url' => '/cuenta',
            'policies' => [
                'privacy' => '/politica-de-privacidad',
                'terms' => '/terminos-y-condiciones',
                'returns' => '/politica-de-devoluciones',
            ],
        ];
    }

    public function create(int $customerId, array $data, array $files = [], string $ip = '', string $userAgent = '', string $idempotencyKey = ''): array
    {
        $this->ensureTables();
        $customer = $this->customer($customerId);
        if (!$customer) return ['ok' => false, 'message' => 'No encontramos tu cuenta de cliente.', 'errors' => []];

        $errors = [];
        $type = strtoupper(trim((string)($data['type'] ?? '')));
        if (!isset(self::TYPES[$type])) $errors['type'] = 'Selecciona el tipo de solicitud.';

        $category = strtoupper(trim((string)($data['category'] ?? '')));
        if ($category === '' || !isset(self::CATEGORIES[$category])) $errors['category'] = 'Selecciona el motivo de tu solicitud.';

        $subjectRaw = $this->cleanText($data['subject'] ?? '', 10000);
        $subject = mb_substr($subjectRaw, 0, 180);
        if (mb_strlen($subjectRaw) < 5) $errors['subject'] = 'Escribe un asunto de al menos 5 caracteres.';
        elseif (mb_strlen($subjectRaw) > 180) $errors['subject'] = 'El asunto no puede superar 180 caracteres.';

        $descriptionRaw = $this->cleanText($data['description'] ?? '', 20000);
        $description = mb_substr($descriptionRaw, 0, 5000);
        if (mb_strlen($descriptionRaw) < 20) $errors['description'] = 'Cuéntanos con más detalle (mínimo 20 caracteres).';
        elseif (mb_strlen($descriptionRaw) > 5000) $errors['description'] = 'La descripción no puede superar 5000 caracteres.';

        $contactNameRaw = $this->cleanText($data['contact_name'] ?? '', 1000);
        $contactName = mb_substr($contactNameRaw, 0, 120);
        if ($contactName === '') $contactName = trim((string)($customer['name'] ?? ''));
        if (mb_strlen($contactName) < 2) $errors['contact_name'] = 'Escribe tu nombre.';
        elseif (mb_strlen($contactNameRaw) > 120) $errors['contact_name'] = 'El nombre no puede superar 120 caracteres.';

        $contactEmail = trim(strtolower((string)($customer['email'] ?? '')));
        if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) $errors['contact_email'] = 'Tu cuenta no tiene un correo válido.';

        $contactPhone = $this->cleanPhone($data['contact_phone'] ?? '');
        if ($contactPhone !== '' && mb_strlen($contactPhone) < 7) $errors['contact_phone'] = 'Escribe un teléfono válido o déjalo vacío.';

        $orderRef = trim((string)($data['order_ref'] ?? ''));
        if ($orderRef !== '') {
            if (!preg_match('/^[A-Za-z0-9#._\-\/ ]{3,60}$/', $orderRef)) {
                $errors['order_ref'] = 'El número de pedido no tiene un formato válido.';
            } elseif (!$this->ownsOrder($customerId, $contactEmail, $orderRef)) {
                $errors['order_ref'] = 'No encontramos ese pedido en tu cuenta.';
            }
        }

        if (!$this->isAccepted($data['auth_accepted'] ?? null)) {
            $errors['auth_accepted'] = 'Debes autorizar el tratamiento de tus datos para radicar la solicitud.';
        }

        if ($errors) return ['ok' => false, 'message' => 'Revisa los campos marcados.', 'errors' => $errors];

        $fileInfo = $this->validateFiles($files);
        if (!empty($fileInfo['errors'])) return ['ok' => false, 'message' => 'Revisa los archivos adjuntos.', 'errors' => $fileInfo['errors']];

        $idem = $this->normalizeIdempotencyKey($idempotencyKey, $customerId);
        $existing = $this->findDuplicate($customerId, $type, $subject, $description, $idem);
        if ($existing) {
            $this->addEvent((int)$existing['id'], 'SYSTEM', null, 'DUPLICATE_RETRY', null, null, 'Intento repetido dentro de la ventana de idempotencia.', false);
            return [
                'ok' => true,
                'duplicate' => true,
                'case' => [
                    'id' => (int)$existing['id'],
                    'case_number' => (string)$existing['case_number'],
                    'status' => (string)$existing['status'],
                    'created_at' => $this->iso((string)$existing['created_at']),
                ],
                'tracking_url' => null,
                'message' => 'Ya registramos esta solicitud. Revisa el correo de confirmación para continuar el seguimiento.',
            ];
        }

        $db = Database::instance();
        $lockName = 'pqrfseq:' . gmdate('Y');
        $lock = $db->prepare('SELECT GET_LOCK(?, 5)');
        $lock->execute([$lockName]);
        if ((int)$lock->fetchColumn() !== 1) {
            return ['ok' => false, 'message' => 'El sistema está recibiendo muchas solicitudes. Intenta de nuevo en unos segundos.', 'errors' => []];
        }

        $stored = [];
        try {
            $caseNumber = $this->nextCaseNumber();
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $s = $this->settings();
            $dueExpr = $s['due_hours'] > 0 ? 'DATE_ADD(NOW(), INTERVAL ' . (int)$s['due_hours'] . ' HOUR)' : 'NULL';
            $expiresExpr = 'DATE_ADD(NOW(), INTERVAL ' . (int)$s['token_days'] . ' DAY)';

            $db->beginTransaction();
            $idemDb = $idem !== '' ? $idem : null;
            $sql = "INSERT INTO pqrf_cases
                (site_id, case_number, customer_id, type, category, subject, description, order_ref, status, priority,
                 tracking_token_hash, tracking_token_expires_at, idempotency_key, contact_name, contact_email, contact_phone,
                 auth_accepted, due_at)
                VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, 'RECEIVED', 'NORMAL', ?, $expiresExpr, ?, ?, ?, ?, 1, $dueExpr)";
            $st = $db->prepare($sql);
            $st->execute([
                $caseNumber,
                $customerId,
                $type,
                $category,
                $subject,
                $description,
                $orderRef !== '' ? $orderRef : null,
                $tokenHash,
                $idemDb,
                $contactName,
                $contactEmail,
                $contactPhone !== '' ? $contactPhone : null,
            ]);
            $caseId = (int)$db->lastInsertId();

            $this->addEvent($caseId, 'CUSTOMER', $customerId, 'CASE_CREATED', null, 'RECEIVED', 'Solicitud radicada por el titular.', true);
            $this->addEvent($caseId, 'CUSTOMER', $customerId, 'AUTH_ACCEPTED', null, null, 'Autorización de tratamiento de datos aceptada. Políticas: privacidad, términos y devoluciones.', true);

            foreach ($fileInfo['files'] as $file) {
                $storedFile = $this->storeFile($caseId, $file);
                $stored[] = $storedFile['path'];
                $att = $db->prepare('INSERT INTO pqrf_case_attachments (case_id, original_name, storage_path, mime, bytes, sha256, visibility) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $att->execute([$caseId, $storedFile['original_name'], $storedFile['relative'], $storedFile['mime'], $storedFile['bytes'], $storedFile['sha256'], 'private']);
            }
            if (!empty($fileInfo['files'])) {
                $this->addEvent($caseId, 'SYSTEM', null, 'ATTACHMENTS_RECEIVED', null, null, count($fileInfo['files']) . ' archivo(s) adjunto(s).', true);
            }

            $db->commit();
        } catch (\Throwable $e) {
            error_log('PQRF create failed: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            if ($db->inTransaction()) $db->rollBack();
            foreach ($stored as $relative) {
                $path = ROOT_DIR . '/public' . $relative;
                if (is_file($path)) @unlink($path);
            }
            $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
            return ['ok' => false, 'message' => 'No pudimos radicar la solicitud. Intenta de nuevo.', 'errors' => []];
        }
        $db->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);

        $case = null;
        try { $case = $this->findForAdmin($caseId); } catch (\Throwable $e) {}
        $trackingUrl = $this->trackingUrl($token);
        $emailSent = false;
        if ($case) {
            try { $emailSent = $this->sendCreationEmails($case, $trackingUrl); } catch (\Throwable $e) { $emailSent = false; }
        }

        return [
            'ok' => true,
            'duplicate' => false,
            'case' => [
                'id' => $caseId,
                'case_number' => $caseNumber,
                'status' => 'RECEIVED',
                'type' => $type,
                'created_at' => $this->iso((string)($case['created_at'] ?? '')),
            ],
            'tracking_url' => $trackingUrl,
            'email_sent' => $emailSent,
            'message' => 'Solicitud radicada correctamente.',
        ];
    }

    public function track(string $token): array
    {
        $token = trim($token);
        if (!$this->validTokenFormat($token)) return ['ok' => false, 'reason' => 'not_found'];
        $row = $this->findByToken($token, true);
        if (!$row) return ['ok' => false, 'reason' => 'not_found'];
        if (!empty($row['token_expired'])) return ['ok' => false, 'reason' => 'expired'];

        $events = $this->eventsForCustomer((int)$row['id']);
        $attachments = [];
        foreach ($this->attachmentsFor((int)$row['id']) as $att) {
            $attachments[] = [
                'id' => (int)$att['id'],
                'name' => (string)$att['original_name'],
                'mime' => (string)$att['mime'],
                'bytes' => (int)$att['bytes'],
                'url' => $this->publicBaseUrl() . '/api/v1/public/pqrf/attachment?id=' . (int)$att['id'] . '&token=' . urlencode($token),
            ];
        }

        return [
            'ok' => true,
            'data' => [
                'case_number' => (string)$row['case_number'],
                'type' => (string)$row['type'],
                'type_label' => $this->typeLabel((string)$row['type']),
                'category' => (string)($row['category'] ?? ''),
                'category_label' => $this->categoryLabel((string)($row['category'] ?? '')),
                'subject' => (string)$row['subject'],
                'status' => (string)$row['status'],
                'status_label' => $this->statusLabel((string)$row['status']),
                'created_at' => $this->iso((string)$row['created_at']),
                'updated_at' => $this->iso((string)$row['updated_at']),
                'can_reply' => true,
                'events' => $events,
                'attachments' => $attachments,
                'channels' => $this->channels(),
            ],
        ];
    }

    public function addCustomerMessage(string $token, string $message, string $ip = '', string $userAgent = ''): array
    {
        $token = trim($token);
        if (!$this->validTokenFormat($token)) return ['ok' => false, 'reason' => 'not_found'];
        $row = $this->findByToken($token, true);
        if (!$row) return ['ok' => false, 'reason' => 'not_found'];
        if (!empty($row['token_expired'])) return ['ok' => false, 'reason' => 'expired'];

        $messageRaw = $this->cleanText($message, 10000);
        $message = mb_substr($messageRaw, 0, 3000);
        if (mb_strlen($messageRaw) < 5) return ['ok' => false, 'reason' => 'validation', 'message' => 'Escribe un mensaje de al menos 5 caracteres.'];
        if (mb_strlen($messageRaw) > 3000) return ['ok' => false, 'reason' => 'validation', 'message' => 'El mensaje no puede superar 3000 caracteres.'];

        $customerId = !empty($row['customer_id']) ? (int)$row['customer_id'] : null;
        $this->addEvent((int)$row['id'], 'CUSTOMER', $customerId, 'CUSTOMER_MESSAGE', null, null, $message, true);
        Database::instance()->prepare('UPDATE pqrf_cases SET updated_at = NOW() WHERE id = ? AND site_id = @site_id')->execute([(int)$row['id']]);

        $this->sendAdminAlert($row, 'Nuevo mensaje en el radicado ' . (string)$row['case_number'], $message, 'message');
        return ['ok' => true, 'message' => 'Mensaje enviado. El equipo de SONIKA lo revisará.'];
    }

    public function listForAdmin(array $f = []): array
    {
        $this->ensureTables();
        $page = max(1, (int)($f['page'] ?? 1));
        $perPage = max(10, min(100, (int)($f['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;
        [$where, $params] = $this->adminFilters($f);
        $db = Database::instance();
        $count = $db->prepare("SELECT COUNT(*) FROM pqrf_cases c WHERE $where");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $sql = "SELECT c.*, u.username AS assignee_name FROM pqrf_cases c LEFT JOIN users u ON u.id = c.assignee_id AND u.site_id = c.site_id WHERE $where ORDER BY c.id DESC LIMIT $perPage OFFSET $offset";
        $st = $db->prepare($sql);
        $st->execute($params);
        return [
            'items' => $st->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public function stats(): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT COUNT(*) AS total,
            SUM(CASE WHEN status = 'RECEIVED' THEN 1 ELSE 0 END) AS received,
            SUM(CASE WHEN status = 'IN_REVIEW' THEN 1 ELSE 0 END) AS in_review,
            SUM(CASE WHEN status = 'INFO_REQUESTED' THEN 1 ELSE 0 END) AS info_requested,
            SUM(CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE 0 END) AS in_progress,
            SUM(CASE WHEN status = 'RESPONDED' THEN 1 ELSE 0 END) AS responded,
            SUM(CASE WHEN status = 'CLOSED' THEN 1 ELSE 0 END) AS closed,
            SUM(CASE WHEN assignee_id IS NULL THEN 1 ELSE 0 END) AS unassigned,
            SUM(CASE WHEN due_at IS NOT NULL AND due_at < NOW() AND status NOT IN ('RESPONDED','CLOSED') THEN 1 ELSE 0 END) AS overdue
            FROM pqrf_cases WHERE site_id = @site_id");
        $st->execute();
        $row = $st->fetch() ?: [];
        foreach ($row as $k => $v) $row[$k] = (int)$v;
        return $row;
    }

    public function findForAdmin(int $id): ?array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare('SELECT c.*, u.username AS assignee_name FROM pqrf_cases c LEFT JOIN users u ON u.id = c.assignee_id AND u.site_id = c.site_id WHERE c.id = ? AND c.site_id = @site_id LIMIT 1');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function eventsForAdmin(int $caseId): array
    {
        $st = Database::instance()->prepare('SELECT e.* FROM pqrf_case_events e JOIN pqrf_cases c ON c.id = e.case_id AND c.site_id = @site_id WHERE e.case_id = ? ORDER BY e.id ASC');
        $st->execute([$caseId]);
        return $st->fetchAll();
    }

    public function attachmentsFor(int $caseId): array
    {
        $st = Database::instance()->prepare('SELECT a.id, a.original_name, a.mime, a.bytes, a.sha256, a.created_at FROM pqrf_case_attachments a JOIN pqrf_cases c ON c.id = a.case_id AND c.site_id = @site_id WHERE a.case_id = ? ORDER BY a.id ASC');
        $st->execute([$caseId]);
        return $st->fetchAll();
    }

    public function assignees(): array
    {
        $st = Database::instance()->prepare("SELECT id, username, email, role FROM users WHERE site_id = @site_id AND is_active = 1 AND role IN ('superadmin','admin','editor') ORDER BY username ASC");
        $st->execute();
        return $st->fetchAll();
    }

    public function assign(int $caseId, ?int $assigneeId, int $actorId): array
    {
        $case = $this->findForAdmin($caseId);
        if (!$case) return ['ok' => false, 'message' => 'Radicado no encontrado.'];
        $name = '';
        if ($assigneeId !== null && $assigneeId > 0) {
            $st = Database::instance()->prepare("SELECT username FROM users WHERE id = ? AND site_id = @site_id AND is_active = 1 AND role IN ('superadmin','admin','editor') LIMIT 1");
            $st->execute([$assigneeId]);
            $name = (string)$st->fetchColumn();
            if ($name === '') return ['ok' => false, 'message' => 'El responsable seleccionado no es válido.'];
        } else {
            $assigneeId = null;
        }
        Database::instance()->prepare('UPDATE pqrf_cases SET assignee_id = ?, updated_at = NOW() WHERE id = ? AND site_id = @site_id')->execute([$assigneeId, $caseId]);
        $this->addEvent($caseId, 'ADMIN', $actorId, 'ASSIGNED', null, null, $assigneeId ? ('Asignado a ' . $name) : 'Sin responsable asignado.', false);
        return ['ok' => true, 'message' => $assigneeId ? ('Asignado a ' . $name . '.') : 'Responsable removido.'];
    }

    public function setStatus(int $caseId, string $status, int $actorId, string $priority = '', string $publicReply = '', string $internalNote = ''): array
    {
        $case = $this->findForAdmin($caseId);
        if (!$case) return ['ok' => false, 'message' => 'Radicado no encontrado.'];
        $status = strtoupper(trim($status));
        if (!isset(self::STATUSES[$status])) return ['ok' => false, 'message' => 'Estado inválido.'];
        $priority = strtoupper(trim($priority));
        if ($priority !== '' && !isset(self::PRIORITIES[$priority])) return ['ok' => false, 'message' => 'Prioridad inválida.'];
        $publicReply = $this->cleanText($publicReply, 3000);
        $internalNote = $this->cleanText($internalNote, 3000);

        $from = (string)$case['status'];
        $fields = ['status = ?', 'updated_at = NOW()'];
        $params = [$status];
        if ($priority !== '') { $fields[] = 'priority = ?'; $params[] = $priority; }
        $params[] = $caseId;
        Database::instance()->prepare('UPDATE pqrf_cases SET ' . implode(', ', $fields) . ' WHERE id = ? AND site_id = @site_id')->execute($params);

        if ($from !== $status) {
            $this->addEvent($caseId, 'ADMIN', $actorId, 'STATUS_CHANGED', $from, $status, 'Estado actualizado a ' . $this->statusLabel($status) . '.', true);
        }
        if ($publicReply !== '') {
            $this->addEvent($caseId, 'ADMIN', $actorId, 'ADMIN_RESPONSE', null, null, $publicReply, true);
        }
        if ($internalNote !== '') {
            $this->addEvent($caseId, 'ADMIN', $actorId, 'INTERNAL_NOTE', null, null, $internalNote, false);
        }
        return ['ok' => true, 'message' => 'Gestión guardada.'];
    }

    public function addNote(int $caseId, string $note, bool $visible, int $actorId): array
    {
        $case = $this->findForAdmin($caseId);
        if (!$case) return ['ok' => false, 'message' => 'Radicado no encontrado.'];
        $note = $this->cleanText($note, 3000);
        if (mb_strlen($note) < 3) return ['ok' => false, 'message' => 'Escribe una nota.'];
        $this->addEvent($caseId, 'ADMIN', $actorId, $visible ? 'ADMIN_RESPONSE' : 'INTERNAL_NOTE', null, null, $note, $visible);
        return ['ok' => true, 'message' => $visible ? 'Respuesta agregada.' : 'Nota interna agregada.'];
    }

    public function attachmentForAdmin(int $caseId, int $attachmentId): ?array
    {
        $st = Database::instance()->prepare('SELECT a.* FROM pqrf_case_attachments a JOIN pqrf_cases c ON c.id = a.case_id AND c.site_id = @site_id WHERE a.id = ? AND a.case_id = ? LIMIT 1');
        $st->execute([$attachmentId, $caseId]);
        $row = $st->fetch();
        if (!$row) return null;
        $path = ROOT_DIR . '/public' . (string)$row['storage_path'];
        if (!is_file($path)) return null;
        $row['full_path'] = $path;
        return $row;
    }

    public function attachmentForCustomer(string $token, int $attachmentId): ?array
    {
        $token = trim($token);
        if (!$this->validTokenFormat($token)) return null;
        $case = $this->findByToken($token, false);
        if (!$case) return null;
        $st = Database::instance()->prepare('SELECT * FROM pqrf_case_attachments WHERE id = ? AND case_id = ? LIMIT 1');
        $st->execute([$attachmentId, (int)$case['id']]);
        $row = $st->fetch();
        if (!$row) return null;
        $path = ROOT_DIR . '/public' . (string)$row['storage_path'];
        if (!is_file($path)) return null;
        $row['full_path'] = $path;
        return $row;
    }

    public function runMaintenance(): array
    {
        $this->ensureTables();
        $s = $this->settings();
        $out = ['due_reminders' => 0];
        if ($s['due_hours'] <= 0 || !$s['reminders'] || !$s['email_enabled']) return $out;
        $st = Database::instance()->prepare("SELECT c.* FROM pqrf_cases c
            WHERE c.site_id = @site_id AND c.due_at IS NOT NULL
              AND c.status NOT IN ('RESPONDED','CLOSED')
              AND c.due_at <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
              AND NOT EXISTS (SELECT 1 FROM pqrf_case_events e WHERE e.case_id = c.id AND e.event = 'REMINDER_SENT' AND e.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))
            ORDER BY c.due_at ASC LIMIT 20");
        $st->execute();
        foreach ($st->fetchAll() as $case) {
            if ($this->sendAdminAlert($case, 'Recordatorio de plazo: ' . (string)$case['case_number'], 'El radicado tiene un plazo configurado próximo o vencido. Revisa la bandeja.', 'reminder')) {
                $this->addEvent((int)$case['id'], 'SYSTEM', null, 'REMINDER_SENT', null, null, 'Recordatorio interno enviado.', false);
                $out['due_reminders']++;
            }
        }
        return $out;
    }

    public function statusLabel(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }

    public function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    public function categoryLabel(string $category): string
    {
        return self::CATEGORIES[$category] ?? $category;
    }

    public function priorityLabel(string $priority): string
    {
        return self::PRIORITIES[$priority] ?? $priority;
    }

    public function eventLabel(array $event): string
    {
        $map = [
            'CASE_CREATED' => 'Solicitud radicada',
            'CUSTOMER_MESSAGE' => 'Mensaje del titular',
            'AUTH_ACCEPTED' => 'Autorización de tratamiento aceptada',
            'ATTACHMENTS_RECEIVED' => 'Archivos adjuntos recibidos',
            'STATUS_CHANGED' => 'Actualización de estado',
            'ADMIN_RESPONSE' => 'Respuesta de SONIKA',
            'INTERNAL_NOTE' => 'Nota interna',
            'ASSIGNED' => 'Asignación de responsable',
            'DUPLICATE_RETRY' => 'Intento repetido',
            'EMAIL_CONFIRMATION_SENT' => 'Correo de confirmación enviado',
            'EMAIL_CONFIRMATION_FAILED' => 'Correo de confirmación fallido',
            'EMAIL_CONFIRMATION_SKIPPED' => 'Correo de confirmación omitido',
            'EMAIL_ADMIN_SENT' => 'Alerta interna enviada',
            'EMAIL_ADMIN_FAILED' => 'Alerta interna fallida',
            'EMAIL_ADMIN_SKIPPED' => 'Alerta interna omitida',
            'REMINDER_SENT' => 'Recordatorio interno enviado',
        ];
        return $map[(string)($event['event'] ?? '')] ?? (string)($event['event'] ?? '');
    }

    public function trackingUrl(string $token): string
    {
        return $this->publicBaseUrl() . '/pqrf/seguimiento?token=' . urlencode($token);
    }

    public function publicBaseUrl(): string
    {
        $s = $this->settings();
        if ($s['public_url'] !== '') return $s['public_url'];
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '' && preg_match('/^[a-z0-9.-]+(?::\d+)?$/i', $host) && !str_starts_with($host, '127.') && !str_starts_with($host, 'localhost')) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            return ($https ? 'https' : 'http') . '://' . $host;
        }
        return rtrim((string)Config::get('APP_URL', 'https://sonika.com.co'), '/');
    }

    public function iso(?string $date): ?string
    {
        if ($date === null || $date === '') return null;
        $ts = strtotime($date . ' UTC');
        return $ts ? gmdate('c', $ts) : null;
    }

    private function customer(int $id): ?array
    {
        $st = Database::instance()->prepare('SELECT id, name, email, phone FROM store_customers WHERE id = ? AND site_id = @site_id LIMIT 1');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    private function ownsOrder(int $customerId, string $email, string $orderRef): bool
    {
        try {
            $st = Database::instance()->prepare('SELECT id FROM store_orders WHERE site_id = @site_id AND (customer_id = ? OR customer_email = ?) AND (order_number = ? OR uuid = ?) LIMIT 1');
            $st->execute([$customerId, $email, $orderRef, $orderRef]);
            return (bool)$st->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function findDuplicate(int $customerId, string $type, string $subject, string $description, string $idem): ?array
    {
        $db = Database::instance();
        if ($idem !== '') {
            $st = $db->prepare('SELECT id, case_number, status, created_at FROM pqrf_cases WHERE site_id = @site_id AND customer_id = ? AND idempotency_key = ? LIMIT 1');
            $st->execute([$customerId, $idem]);
            $row = $st->fetch();
            if ($row) return $row;
        }
        $st = $db->prepare('SELECT id, case_number, status, created_at FROM pqrf_cases WHERE site_id = @site_id AND customer_id = ? AND type = ? AND subject = ? AND description = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) ORDER BY id DESC LIMIT 1');
        $st->execute([$customerId, $type, $subject, $description]);
        return $st->fetch() ?: null;
    }

    private function nextCaseNumber(): string
    {
        $year = gmdate('Y');
        $st = Database::instance()->prepare('SELECT case_number FROM pqrf_cases WHERE case_number LIKE ? ORDER BY id DESC LIMIT 1');
        $st->execute(['SON-PQRF-' . $year . '-%']);
        $last = (string)$st->fetchColumn();
        $seq = 1;
        if ($last !== '') {
            $n = (int)substr($last, -6);
            if ($n > 0) $seq = $n + 1;
        }
        return sprintf('SON-PQRF-%s-%06d', $year, $seq);
    }

    private function findByToken(string $token, bool $includeExpired): ?array
    {
        $hash = hash('sha256', $token);
        $st = Database::instance()->prepare('SELECT *, (tracking_token_expires_at IS NOT NULL AND tracking_token_expires_at <= NOW()) AS token_expired FROM pqrf_cases WHERE site_id = @site_id AND tracking_token_hash = ? LIMIT 1');
        $st->execute([$hash]);
        $row = $st->fetch();
        if (!$row) return null;
        if (!$includeExpired && !empty($row['token_expired'])) return null;
        return $row;
    }

    private function eventsForCustomer(int $caseId): array
    {
        $st = Database::instance()->prepare('SELECT event, actor_type, note, to_status, created_at FROM pqrf_case_events WHERE case_id = ? AND visible_to_customer = 1 ORDER BY id ASC');
        $st->execute([$caseId]);
        $out = [];
        foreach ($st->fetchAll() as $e) {
            $actor = (string)$e['actor_type'];
            $out[] = [
                'event' => (string)$e['event'],
                'label' => $this->eventLabel($e),
                'actor' => $actor === 'CUSTOMER' ? 'Titular' : ($actor === 'ADMIN' ? 'SONIKA' : 'Sistema'),
                'note' => (string)($e['note'] ?? ''),
                'to_status' => (string)($e['to_status'] ?? ''),
                'to_status_label' => $e['to_status'] ? $this->statusLabel((string)$e['to_status']) : '',
                'created_at' => $this->iso((string)$e['created_at']),
            ];
        }
        return $out;
    }

    private function addEvent(int $caseId, string $actorType, ?int $actorId, string $event, ?string $from, ?string $to, string $note, bool $visible): void
    {
        $st = Database::instance()->prepare('INSERT INTO pqrf_case_events (case_id, actor_type, actor_id, event, from_status, to_status, note, visible_to_customer) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([$caseId, $actorType, $actorId, $event, $from, $to, $note, $visible ? 1 : 0]);
    }

    private function validateFiles(array $files): array
    {
        $out = ['files' => [], 'errors' => []];
        $normalized = $this->normalizeFiles($files);
        $s = $this->settings();
        $maxFiles = $s['max_files'];
        $maxBytes = $s['max_mb'] * 1024 * 1024;
        if (count($normalized) > $maxFiles) {
            $out['errors']['attachments'] = 'Puedes adjuntar máximo ' . $maxFiles . ' archivos.';
            return $out;
        }
        foreach ($normalized as $f) {
            $name = $this->safeFileName((string)($f['name'] ?? ''));
            $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_OK) {
                $out['errors']['attachments'] = 'No pudimos recibir ' . ($name !== '' ? $name : 'un archivo') . '. Intenta de nuevo.';
                return $out;
            }
            $tmp = (string)($f['tmp_name'] ?? '');
            if ($tmp === '' || !is_file($tmp)) {
                $out['errors']['attachments'] = 'No pudimos recibir ' . ($name !== '' ? $name : 'un archivo') . '.';
                return $out;
            }
            $size = (int)@filesize($tmp);
            if ($size <= 0) {
                $out['errors']['attachments'] = 'El archivo ' . $name . ' está vacío.';
                return $out;
            }
            if ($size > $maxBytes) {
                $out['errors']['attachments'] = 'El archivo ' . $name . ' supera el límite de ' . $s['max_mb'] . ' MB.';
                return $out;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $mime = '';
            try {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = (string)$finfo->file($tmp);
            } catch (\Throwable $e) {
                $mime = '';
            }
            if ($mime === '' || !isset(self::MIME_EXTENSIONS[$mime]) || !in_array($ext, self::MIME_EXTENSIONS[$mime], true)) {
                $out['errors']['attachments'] = 'El archivo ' . $name . ' no es un JPG, PNG, WebP o PDF válido.';
                return $out;
            }
            if (str_starts_with($mime, 'image/')) {
                $info = @getimagesize($tmp);
                if (!$info) {
                    $out['errors']['attachments'] = 'El archivo ' . $name . ' no es una imagen válida.';
                    return $out;
                }
            } elseif ($mime === 'application/pdf') {
                $fh = @fopen($tmp, 'rb');
                $head = $fh ? (string)fread($fh, 5) : '';
                if ($fh) fclose($fh);
                if ($head !== '%PDF-') {
                    $out['errors']['attachments'] = 'El archivo ' . $name . ' no es un PDF válido.';
                    return $out;
                }
            }
            $out['files'][] = ['name' => $name, 'tmp' => $tmp, 'ext' => $ext, 'mime' => $mime, 'size' => $size];
        }
        return $out;
    }

    private function normalizeFiles(array $files): array
    {
        if (!isset($files['name'])) return [];
        if (is_array($files['name'])) {
            $out = [];
            foreach ($files['name'] as $i => $name) {
                $err = (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                if ($err === UPLOAD_ERR_NO_FILE) continue;
                $out[] = [
                    'name' => (string)$name,
                    'type' => (string)($files['type'][$i] ?? ''),
                    'tmp_name' => (string)($files['tmp_name'][$i] ?? ''),
                    'error' => $err,
                    'size' => (int)($files['size'][$i] ?? 0),
                ];
            }
            return $out;
        }
        if ((int)($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return [];
        return [$files];
    }

    private function storeFile(int $caseId, array $file): array
    {
        $siteId = (int)Config::get('SITE_ID', '1');
        $relativeDir = '/assets/uploads/_private/pqrf/' . $siteId . '/' . $caseId;
        $dir = ROOT_DIR . '/public' . $relativeDir;
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear el directorio privado.');
        }
        $storedName = bin2hex(random_bytes(16)) . '.' . $file['ext'];
        $dest = $dir . '/' . $storedName;
        $tmp = (string)$file['tmp'];
        if (is_uploaded_file($tmp)) {
            if (!@move_uploaded_file($tmp, $dest)) throw new \RuntimeException('No se pudo guardar el archivo.');
        } elseif (PHP_SAPI === 'cli' && is_file($tmp)) {
            if (!@rename($tmp, $dest)) throw new \RuntimeException('No se pudo guardar el archivo.');
        } else {
            throw new \RuntimeException('Archivo no válido.');
        }
        @chmod($dest, 0640);
        return [
            'original_name' => $file['name'],
            'relative' => $relativeDir . '/' . $storedName,
            'mime' => $file['mime'],
            'bytes' => (int)filesize($dest),
            'sha256' => hash_file('sha256', $dest),
            'path' => $relativeDir . '/' . $storedName,
        ];
    }

    private function sendCreationEmails(array $case, string $trackingUrl): bool
    {
        $s = $this->settings();
        $caseId = (int)$case['id'];
        if (!$s['email_enabled']) {
            $this->addEvent($caseId, 'SYSTEM', null, 'EMAIL_CONFIRMATION_SKIPPED', null, null, 'Envío desactivado por configuración.', false);
            $this->addEvent($caseId, 'SYSTEM', null, 'EMAIL_ADMIN_SKIPPED', null, null, 'Envío desactivado por configuración.', false);
            return false;
        }
        $email = new EmailService();
        $mail = $this->confirmationEmail($case, $trackingUrl, $s);
        $sent = false;
        try {
            $sent = $email->sendMultipart((string)$case['contact_email'], $mail['subject'], $mail['text'], $mail['html'], ['reply_to' => $s['contact_email']]);
        } catch (\Throwable $e) {
            $sent = false;
        }
        $this->addEvent($caseId, 'SYSTEM', null, $sent ? 'EMAIL_CONFIRMATION_SENT' : 'EMAIL_CONFIRMATION_FAILED', null, null, $sent ? 'Confirmación enviada al titular.' : 'No se pudo enviar la confirmación al titular.', false);

        $adminMail = $this->adminEmail($case, $s);
        $adminSent = false;
        try {
            $adminSent = $email->sendMultipart($s['admin_email'], $adminMail['subject'], $adminMail['text'], $adminMail['html'], ['reply_to' => (string)$case['contact_email']]);
        } catch (\Throwable $e) {
            $adminSent = false;
        }
        $this->addEvent($caseId, 'SYSTEM', null, $adminSent ? 'EMAIL_ADMIN_SENT' : 'EMAIL_ADMIN_FAILED', null, null, $adminSent ? 'Alerta interna enviada.' : 'No se pudo enviar la alerta interna.', false);
        return $sent;
    }

    private function sendAdminAlert(array $case, string $subject, string $message, string $kind): bool
    {
        $s = $this->settings();
        if (!$s['email_enabled']) return false;
        $caseNumber = (string)($case['case_number'] ?? '');
        $caseId = (int)($case['id'] ?? 0);
        $url = $this->publicBaseUrl() . '/admin-pqrf.php?id=' . $caseId;
        $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:560px;margin:auto;background:#0b0b0b;color:#fff;border-radius:14px;padding:28px">'
            . '<div style="color:#F20D16;font-size:12px;letter-spacing:.12em;font-weight:800;text-transform:uppercase;margin-bottom:10px">SONIKA · PQRS</div>'
            . '<h2 style="margin:0 0 10px;font-size:20px;color:#fff">' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h2>'
            . '<p style="color:#d4d4d4;font-size:14px;line-height:1.6">' . nl2br($safe) . '</p>'
            . '<p style="margin:22px 0 0"><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#F20D16;color:#fff;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:9px">Abrir en el panel</a></p>'
            . '<p style="color:#8a8a8a;font-size:12px;margin:18px 0 0">Radicado ' . htmlspecialchars($caseNumber, ENT_QUOTES, 'UTF-8') . '</p></div>';
        $text = "SONIKA · PQRS\n\n" . $subject . "\n\n" . $message . "\n\nRadicado: " . $caseNumber . "\nPanel: " . $url . "\n";
        try {
            return (new EmailService())->sendMultipart($s['admin_email'], $subject, $text, $html, ['reply_to' => $s['contact_email']]);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function confirmationEmail(array $case, string $trackingUrl, array $s): array
    {
        $number = (string)$case['case_number'];
        $type = $this->typeLabel((string)$case['type']);
        $subject = 'Radicado ' . $number . ' — SONIKA';
        $safeNumber = htmlspecialchars($number, ENT_QUOTES, 'UTF-8');
        $safeType = htmlspecialchars($type, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars((string)$case['subject'], ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars((string)$case['contact_name'], ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($trackingUrl, ENT_QUOTES, 'UTF-8');
        $contact = htmlspecialchars($s['contact_email'], ENT_QUOTES, 'UTF-8');
        $text = "Hola " . (string)$case['contact_name'] . ",\n\n"
            . "Recibimos tu solicitud con radicado " . $number . ".\n\n"
            . "Tipo: " . $type . "\n"
            . "Asunto: " . (string)$case['subject'] . "\n\n"
            . "Puedes seguir el estado y responder aquí:\n" . $trackingUrl . "\n\n"
            . "Este enlace es personal; no lo compartas.\n"
            . "Si no hiciste esta solicitud, escríbenos a " . $s['contact_email'] . ".\n\n"
            . "SONIKA — sonika.com.co\n";
        $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:600px;margin:auto;background:#ffffff;border:1px solid #ececec;border-radius:16px;overflow:hidden">'
            . '<div style="background:#0b0b0b;padding:26px 28px;color:#fff">'
            . '<div style="color:#F20D16;font-size:12px;letter-spacing:.14em;font-weight:800;text-transform:uppercase">SONIKA</div>'
            . '<h1 style="margin:8px 0 0;font-size:24px;line-height:1.2;color:#fff">Recibimos tu solicitud</h1></div>'
            . '<div style="padding:28px;color:#222">'
            . '<p style="margin:0 0 14px;font-size:15px;line-height:1.6">Hola <strong>' . $safeName . '</strong>,</p>'
            . '<p style="margin:0 0 18px;font-size:15px;line-height:1.6">Tu solicitud quedó radicada con el número <strong style="color:#F20D16">' . $safeNumber . '</strong>.</p>'
            . '<table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 22px;font-size:14px">'
            . '<tr><td style="padding:8px 0;color:#777;width:90px">Tipo</td><td style="padding:8px 0;color:#111;font-weight:600">' . $safeType . '</td></tr>'
            . '<tr><td style="padding:8px 0;color:#777">Asunto</td><td style="padding:8px 0;color:#111;font-weight:600">' . $safeSubject . '</td></tr>'
            . '</table>'
            . '<p style="margin:0 0 18px"><a href="' . $safeUrl . '" style="display:inline-block;background:#F20D16;color:#fff;text-decoration:none;font-weight:700;padding:13px 20px;border-radius:10px">Ver estado de mi solicitud</a></p>'
            . '<p style="margin:0 0 10px;color:#777;font-size:12.5px;line-height:1.6">Este enlace es personal; no lo compartas. Si no hiciste esta solicitud, escríbenos a <a href="mailto:' . $contact . '" style="color:#F20D16">' . $contact . '</a>.</p>'
            . '<p style="margin:18px 0 0;color:#999;font-size:12px">SONIKA — sonika.com.co</p>'
            . '</div></div>';
        return ['subject' => $subject, 'text' => $text, 'html' => $html];
    }

    private function adminEmail(array $case, array $s): array
    {
        $number = (string)$case['case_number'];
        $subject = 'Nuevo PQRS ' . $number . ' — ' . $this->typeLabel((string)$case['type']);
        $safeNumber = htmlspecialchars($number, ENT_QUOTES, 'UTF-8');
        $safeType = htmlspecialchars($this->typeLabel((string)$case['type']), ENT_QUOTES, 'UTF-8');
        $safeCategory = htmlspecialchars($this->categoryLabel((string)($case['category'] ?? '')), ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars((string)$case['subject'], ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars((string)$case['contact_name'], ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars((string)$case['contact_email'], ENT_QUOTES, 'UTF-8');
        $safePhone = htmlspecialchars((string)($case['contact_phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $safeDesc = nl2br(htmlspecialchars((string)$case['description'], ENT_QUOTES, 'UTF-8'));
        $safeOrder = htmlspecialchars((string)($case['order_ref'] ?? ''), ENT_QUOTES, 'UTF-8');
        $url = htmlspecialchars($this->publicBaseUrl() . '/admin-pqrf.php?id=' . (int)$case['id'], ENT_QUOTES, 'UTF-8');
        $text = "Nuevo radicado " . $number . "\n\n"
            . "Tipo: " . $this->typeLabel((string)$case['type']) . "\n"
            . "Categoría: " . $this->categoryLabel((string)($case['category'] ?? '')) . "\n"
            . "Asunto: " . (string)$case['subject'] . "\n"
            . "Contacto: " . (string)$case['contact_name'] . " <" . (string)$case['contact_email'] . "> " . (string)($case['contact_phone'] ?? '') . "\n"
            . ($safeOrder !== '' ? "Pedido: " . (string)$case['order_ref'] . "\n" : '')
            . "\n" . (string)$case['description'] . "\n\n"
            . "Panel: " . $this->publicBaseUrl() . '/admin-pqrf.php?id=' . (int)$case['id'] . "\n";
        $html = '<div style="font-family:Inter,Arial,sans-serif;max-width:640px;margin:auto;background:#0b0b0b;color:#fff;border-radius:14px;padding:28px">'
            . '<div style="color:#F20D16;font-size:12px;letter-spacing:.12em;font-weight:800;text-transform:uppercase;margin-bottom:10px">SONIKA · Nueva solicitud</div>'
            . '<h2 style="margin:0 0 6px;font-size:22px;color:#fff">' . $safeNumber . '</h2>'
            . '<p style="margin:0 0 20px;color:#9a9a9a;font-size:13px">' . $safeType . ' · ' . $safeCategory . '</p>'
            . '<p style="margin:0 0 8px;color:#fff;font-size:15px;font-weight:700">' . $safeSubject . '</p>'
            . '<p style="margin:0 0 18px;color:#d4d4d4;font-size:14px;line-height:1.6">' . $safeDesc . '</p>'
            . '<div style="border-top:1px solid #2a2a2a;padding-top:14px;color:#c9c9c9;font-size:13px;line-height:1.7">'
            . '<strong style="color:#fff">' . $safeName . '</strong><br>' . $safeEmail . ($safePhone !== '' ? '<br>' . $safePhone : '') . ($safeOrder !== '' ? '<br>Pedido: ' . $safeOrder : '') . '</div>'
            . '<p style="margin:22px 0 0"><a href="' . $url . '" style="display:inline-block;background:#F20D16;color:#fff;text-decoration:none;font-weight:700;padding:12px 18px;border-radius:9px">Gestionar en el panel</a></p></div>';
        return ['subject' => $subject, 'text' => $text, 'html' => $html];
    }

    private function adminFilters(array $f): array
    {
        $where = ['c.site_id = @site_id'];
        $params = [];
        $status = strtoupper(trim((string)($f['status'] ?? '')));
        if (isset(self::STATUSES[$status])) { $where[] = 'c.status = ?'; $params[] = $status; }
        $type = strtoupper(trim((string)($f['type'] ?? '')));
        if (isset(self::TYPES[$type])) { $where[] = 'c.type = ?'; $params[] = $type; }
        $priority = strtoupper(trim((string)($f['priority'] ?? '')));
        if (isset(self::PRIORITIES[$priority])) { $where[] = 'c.priority = ?'; $params[] = $priority; }
        $assignee = trim((string)($f['assignee'] ?? ''));
        if ($assignee === 'none') $where[] = 'c.assignee_id IS NULL';
        elseif (ctype_digit($assignee) && (int)$assignee > 0) { $where[] = 'c.assignee_id = ?'; $params[] = (int)$assignee; }
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . mb_substr($q, 0, 80) . '%';
            $where[] = '(c.case_number LIKE ? OR c.subject LIKE ? OR c.contact_name LIKE ? OR c.contact_email LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        $due = (string)($f['due'] ?? '');
        if ($due === 'overdue') $where[] = "c.due_at IS NOT NULL AND c.due_at < NOW() AND c.status NOT IN ('RESPONDED','CLOSED')";
        elseif ($due === 'soon') $where[] = "c.due_at IS NOT NULL AND c.due_at <= DATE_ADD(NOW(), INTERVAL 24 HOUR) AND c.status NOT IN ('RESPONDED','CLOSED')";
        return [implode(' AND ', $where), $params];
    }

    private function isAccepted($value): bool
    {
        if (is_bool($value)) return $value;
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'on', 'yes', 'si', 'sí'], true);
    }

    private function validTokenFormat(string $token): bool
    {
        return (bool)preg_match('/^[a-f0-9]{32,128}$/i', $token);
    }

    private function normalizeIdempotencyKey(string $key, int $customerId): string
    {
        $key = trim($key);
        if ($key === '' || !preg_match('/^[A-Za-z0-9._\-]{8,128}$/', $key)) return '';
        return hash('sha256', $customerId . '|' . $key);
    }

    private function cleanText($value, int $max): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", (string)$value);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s) ?? '';
        $s = trim($s);
        return mb_substr($s, 0, $max);
    }

    private function cleanPhone($value): string
    {
        $s = trim((string)$value);
        $s = preg_replace('/[^0-9+()\-\s]/', '', $s) ?? '';
        $s = preg_replace('/\s+/', ' ', $s) ?? '';
        return mb_substr(trim($s), 0, 40);
    }

    private function safeFileName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = preg_replace('/[^\p{L}\p{N}._\- ()\[\]]/u', '', $name) ?? '';
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') $name = 'archivo';
        return mb_substr($name, 0, 190);
    }

    private function addColumnIfMissing(string $table, string $column, string $sql): void
    {
        try {
            $st = Database::instance()->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $st->execute([$table, $column]);
            if ((int)$st->fetchColumn() === 0) Database::instance()->exec($sql);
        } catch (\Throwable $e) {
        }
    }

    private function addIndexIfMissing(string $table, string $index, string $sql): void
    {
        try {
            $st = Database::instance()->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?');
            $st->execute([$table, $index]);
            if ((int)$st->fetchColumn() === 0) Database::instance()->exec($sql);
        } catch (\Throwable $e) {
        }
    }
}
