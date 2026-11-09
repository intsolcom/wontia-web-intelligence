<?php
namespace App\Services;

use App\Core\Database;

class ArtistContractService
{
    private static bool $ready = false;

    public function ensure(): void
    {
        if (self::$ready) return;
        $db = Database::instance();
        $db->exec("CREATE TABLE IF NOT EXISTS affiliate_contracts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL,
            code VARCHAR(40) NOT NULL DEFAULT 'sonika_partners',
            version VARCHAR(20) NOT NULL,
            title VARCHAR(190) NOT NULL,
            body MEDIUMTEXT NOT NULL,
            effective_at DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'published',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_site_code_ver (site_id, code, version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS affiliate_acceptances (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_id INT NOT NULL,
            affiliate_id INT NOT NULL,
            contract_id INT NOT NULL,
            contract_code VARCHAR(40) NOT NULL,
            contract_version VARCHAR(20) NOT NULL,
            accepted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            ip_hash CHAR(64) DEFAULT NULL,
            user_agent VARCHAR(300) DEFAULT NULL,
            signature_image MEDIUMTEXT NULL,
            signature_hash CHAR(64) DEFAULT NULL,
            contract_hash CHAR(64) DEFAULT NULL,
            signer_name VARCHAR(190) DEFAULT NULL,
            signer_document VARCHAR(60) DEFAULT NULL,
            signed_at DATETIME NULL,
            signed_ip_hash CHAR(64) DEFAULT NULL,
            signed_user_agent VARCHAR(300) DEFAULT NULL,
            UNIQUE KEY uq_site_aff_contract (site_id, affiliate_id, contract_id),
            INDEX idx_aff (site_id, affiliate_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach ([
            'signature_image MEDIUMTEXT NULL',
            'signature_hash CHAR(64) NULL',
            'contract_hash CHAR(64) NULL',
            'signer_name VARCHAR(190) NULL',
            'signer_document VARCHAR(60) NULL',
            'signed_at DATETIME NULL',
            'signed_ip_hash CHAR(64) NULL',
            'signed_user_agent VARCHAR(300) NULL',
        ] as $column) {
            try { $db->exec('ALTER TABLE affiliate_acceptances ADD COLUMN ' . $column); } catch (\Throwable $e) {}
        }
        $st = $db->prepare("SELECT id FROM affiliate_contracts WHERE site_id = @site_id AND code = 'sonika_partners' AND version = '1.1' LIMIT 1");
        $st->execute();
        if (!$st->fetchColumn()) {
            $db->prepare("INSERT INTO affiliate_contracts (site_id, code, version, title, body, effective_at, status, created_at) VALUES (@site_id, 'sonika_partners', '1.1', ?, ?, CURDATE(), 'published', NOW())")
                ->execute(['Contrato de participación en SONIKA Partners — Programa de Distribuidores', $this->body()]);
        }
        self::$ready = true;
    }

    public function current(): ?array
    {
        $this->ensure();
        $st = Database::instance()->query("SELECT * FROM affiliate_contracts WHERE site_id = @site_id AND code = 'sonika_partners' AND status = 'published' ORDER BY id DESC LIMIT 1");
        $row = $st->fetch();
        if ($row) return $row;
        $st = Database::instance()->query("SELECT * FROM affiliate_contracts WHERE site_id = @site_id AND code = 'artist_partners' AND status = 'published' ORDER BY id DESC LIMIT 1");
        return $st->fetch() ?: null;
    }

    public function latestAcceptance(int $affiliateId, int $contractId): ?array
    {
        $this->ensure();
        $st = Database::instance()->prepare("SELECT * FROM affiliate_acceptances WHERE site_id = @site_id AND affiliate_id = ? AND contract_id = ? LIMIT 1");
        $st->execute([$affiliateId, $contractId]);
        return $st->fetch() ?: null;
    }

    public function sign(int $affiliateId, array $in): array
    {
        $this->ensure();
        $contract = $this->current();
        if (!$contract) return ['ok' => false, 'message' => 'El contrato no está disponible.'];
        if (empty($in['accept'])) return ['ok' => false, 'message' => 'Debes aceptar el contrato antes de firmar.'];
        $name = mb_substr(trim(strip_tags((string)($in['name'] ?? ''))), 0, 190);
        $document = mb_substr(trim(strip_tags((string)($in['document'] ?? ''))), 0, 60);
        if ($name === '' || $document === '') return ['ok' => false, 'message' => 'Nombre y documento son obligatorios.'];
        $signature = (string)($in['signature'] ?? '');
        if (!preg_match('~^data:image/png;base64,([A-Za-z0-9+/=\s]+)$~', $signature, $match)) {
            return ['ok' => false, 'message' => 'La firma no tiene un formato válido.'];
        }
        $binary = base64_decode(preg_replace('/\s+/', '', $match[1]), true);
        if ($binary === false || strlen($binary) < 80 || strlen($binary) > 1500000) {
            return ['ok' => false, 'message' => 'La firma está vacía o es demasiado grande.'];
        }
        $image = @imagecreatefromstring($binary);
        if (!$image) return ['ok' => false, 'message' => 'La firma no es una imagen válida.'];
        $width = imagesx($image);
        $height = imagesy($image);
        imagedestroy($image);
        if ($width < 80 || $height < 40) return ['ok' => false, 'message' => 'La firma es demasiado pequeña.'];
        $signatureData = 'data:image/png;base64,' . base64_encode($binary);
        $signatureHash = hash('sha256', $binary);
        $contractHash = hash('sha256', (string)$contract['body']);
        $ipHash = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300);
        $db = Database::instance();
        try {
            $db->prepare("INSERT INTO affiliate_acceptances (site_id, affiliate_id, contract_id, contract_code, contract_version, ip_hash, user_agent, signature_image, signature_hash, contract_hash, signer_name, signer_document, signed_at, signed_ip_hash, signed_user_agent) VALUES (@site_id, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?) ON DUPLICATE KEY UPDATE signature_image = VALUES(signature_image), signature_hash = VALUES(signature_hash), contract_hash = VALUES(contract_hash), signer_name = VALUES(signer_name), signer_document = VALUES(signer_document), signed_at = NOW(), signed_ip_hash = VALUES(signed_ip_hash), signed_user_agent = VALUES(signed_user_agent), accepted_at = NOW()")
                ->execute([
                    $affiliateId, (int)$contract['id'], (string)$contract['code'], (string)$contract['version'],
                    $ipHash, $ua, $signatureData, $signatureHash, $contractHash, $name, $document, $ipHash, $ua,
                ]);
            $db->prepare('UPDATE affiliate_profiles SET contract_version = ? WHERE id = ? AND site_id = @site_id')
                ->execute([(string)$contract['version'], $affiliateId]);
            $this->log($affiliateId, 'contract_signed', ['version' => (string)$contract['version'], 'hash' => $contractHash]);
            return [
                'ok' => true,
                'message' => 'Contrato firmado correctamente.',
                'contract_version' => (string)$contract['version'],
                'contract_hash' => $contractHash,
                'signature_hash' => $signatureHash,
                'signed_at' => date('Y-m-d H:i:s'),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo registrar la firma. Intenta de nuevo.'];
        }
    }

    private function log(int $affiliateId, string $action, array $meta = []): void
    {
        try {
            Database::instance()->prepare("INSERT INTO affiliate_audit (site_id, affiliate_id, actor, action, meta, created_at) VALUES (@site_id, ?, 'partner', ?, ?, NOW())")
                ->execute([$affiliateId, $action, json_encode($meta, JSON_UNESCAPED_UNICODE)]);
        } catch (\Throwable $e) {}
    }

    private function body(): string
    {
        return <<<'TXT'
CONTRATO DE PARTICIPACIÓN EN SONIKA PARTNERS — PROGRAMA DE DISTRIBUIDORES
“COMPARTE, VENDE Y GANA”

Entre INTSOLCOM, LLC, sociedad constituida en los Estados Unidos, e INTSOLCOM S.A.S., sociedad colombiana identificada con NIT 901749025-8 (en adelante, conjuntamente, "Intsolcom"), y la persona natural o jurídica que acepta y firma electrónicamente este documento (en adelante, "el Partner"), se celebra el presente contrato de participación en el programa SONIKA Partners.

1. VINCULACIÓN INDEPENDIENTE
El Partner actúa de manera independiente, voluntaria y autónoma. Este contrato no crea relación laboral, sociedad, agencia, representación, franquicia, distribución física ni asociación entre el Partner e Intsolcom. El Partner no tendrá derecho a salario, prestaciones sociales, indemnizaciones laborales ni beneficios propios de un empleado.

2. OBJETO
El Partner participa en SONIKA Partners para promover la marca y los productos de SONIKA mediante un enlace, catálogo, página, contenido o recomendación personal, recibiendo una comisión únicamente por ventas válidamente atribuidas, conforme a la fórmula, reglas y topes de este contrato.

3. CATEGORÍA INTERNA DEL PARTNER
Intsolcom podrá clasificar internamente al Partner como artista, influencer/creador o persona natural. Esta categoría se usa únicamente para personalizar la experiencia, sugerir herramientas, segmentar comunicaciones, generar reportes internos y activar módulos opcionales. La categoría interna NO modifica la comisión, la atribución, el contrato, los derechos, las obligaciones ni el pago. Ninguna categoría otorga exclusividad, territorio, representación de marca ni derechos adicionales.

4. AUTORIZACIÓN DE CONTENIDO, IMAGEN Y VOZ (OPCIONAL)
Si el Partner decide tener página pública, aparecer en directorios o entregar contenido, autoriza a Intsolcom de forma no exclusiva para usar, reproducir, comunicar, publicar, adaptar, distribuir y promocionar su imagen, voz, nombre artístico o público, semblanza, fotografías, videos, audios, textos y publicaciones entregadas en el marco del programa. Esta autorización es opt-in y no es requisito para participar ni para recibir comisiones.

La autorización no transfiere los derechos morales y se limita a las finalidades comerciales y promocionales de SONIKA. Intsolcom reconocerá la autoría o el nombre del Partner cuando sea razonablemente posible y no distorsionará el contenido de forma que afecte su honor o reputación. El Partner declara tener derechos o autorizaciones sobre el contenido que entrega.

5. ALCANCE, TERRITORIO Y PLAZO
La autorización comprende medios digitales, sitio web, catálogo, redes sociales, publicidad, material formativo, eventos, puntos de venta y piezas promocionales de SONIKA. El territorio es Colombia y los canales digitales con alcance mundial. La autorización rige durante la vinculación y se extiende por cinco (5) años adicionales únicamente para el material ya publicado o entregado.

6. SIN OBLIGACIÓN DE PUBLICACIÓN O PAGO
Intsolcom no está obligada a publicar, mantener, promover o utilizar la imagen, voz o contenidos del Partner. La autorización no genera obligación de remuneración, bonificación, pago en especie, productos, regalías, anticipos ni contraprestación distinta de las comisiones causadas por ventas atribuidas. No existe promesa de ingresos, ventas, alcance, contrataciones, representación artística ni resultados determinados.

7. COMISIONES — PUNTOS SONIKA
La única contraprestación económica del programa será la comisión liquidada sobre ventas válidas atribuidas, calculada con la fórmula Puntos SONIKA:

Puntos = (Ventas netas elegibles del mes / $100.000 COP) + Pedidos válidos del mes + Puntos Premium.

Se toman únicamente las unidades completas de $100.000 COP para el componente de valor. Cada pedido válido suma un (1) punto adicional. Cada pedido que incluya un producto con precio unitario igual o superior a $5.000.000 COP suma veinte (20) puntos premium adicionales; se toma el producto de mayor valor del pedido y el bono se aplica una sola vez por pedido.

La tasa base máxima es del cinco por ciento (5 %). Sobre esa tasa se aplica el Bono de Racha, que puede elevarla hasta el seis por ciento (6 %) o el siete por ciento (7 %) conforme a las reglas siguientes. Ninguna comisión, ajuste, campaña o bono podrá superar el siete por ciento (7 %) de la venta neta elegible.

TABLA DE PUNTOS A COMISIÓN BASE
0 a 9 puntos: 3,0 %
10 a 29 puntos: 3,5 %
30 a 59 puntos: 4,0 %
60 a 99 puntos: 4,5 %
100 puntos o más: 5,0 % (tasa base máxima)

BONO DE RACHA
- 4 o 5 ventas válidas en el mes, con al menos una venta válida en cada una de cuatro semanas distintas: +1,0 punto porcentual sobre la tasa base, con un máximo del 6,0 %.
- 6 o más ventas válidas en el mes, con al menos una venta válida en cada una de cuatro semanas distintas: +2,0 puntos porcentuales sobre la tasa base, con un máximo del 7,0 %.
- Los bonos de racha no son acumulables entre sí: se aplica el mayor que corresponda.
- La racha se calcula sobre pedidos válidos, no sobre unidades. Un pedido con varios productos cuenta como una venta válida.
- Si el partner no cumple la racha, la tasa base máxima sigue siendo 5,0 %.

DEFINICIONES
- Venta neta elegible: valor neto de productos después de descuentos, antes de IVA, impuestos, envío, gift cards, financiación, devoluciones, cancelaciones y contracargos. No incluye pedidos internos, de prueba, fraudulentos, duplicados, anulados, reembolsados o no pagados.
- Pedido válido: pedido pagado, no cancelado, no devuelto, no reversado, no interno, no fragmentado artificialmente, con ticket neto mínimo de $1.000 COP. Pedidos del mismo cliente, misma dirección, mismo medio de pago o creados en una ventana corta podrán agruparse como un solo pedido para el conteo de puntos.
- Outlet o liquidación: 2 % fijo.
- Productos con margen inferior al 15 %: 2 % fijo o excluidos, según la ficha del producto.
- Servicios: máximo 5 %, sin Bono de Racha.

La tasa se recalcula mensualmente de forma retroactiva sobre las ventas y pedidos válidos del mes. Las comisiones se causan únicamente después de la validación del pedido y están sujetas a la retención por devoluciones, cancelaciones, contracargos y fraude.

8. ATRIBUCIÓN
La atribución se realiza por el último clic válido dentro de los treinta (30) días anteriores a la compra, o por el código de cupón del Partner cuando no exista clic. Un pedido solo puede atribuirse a un Partner. No se reconoce comisión por ventas no atribuidas, autocompras, cuentas relacionadas, fraude, pedidos duplicados, anulados, reembolsados o no pagados. La atribución y el ledger de comisiones podrán ser auditados por el Partner en su panel.

9. PAGO, RETENCIÓN E IMPUESTOS
El pago será mensual, previa aprobación, con un mínimo de cien mil pesos colombianos ($100.000 COP) y una retención de quince (15) días para devoluciones y contracargos. El Partner deberá aportar RUT, datos bancarios y la factura o documentación que corresponda. Intsolcom podrá aplicar las retenciones legalmente exigibles. Los pagos reales se activarán después de la validación legal, tributaria y operativa del programa. Hasta entonces, el panel podrá mostrar valores en modo simulación.

10. REVERSOS
Las devoluciones, cancelaciones, contracargos, pedidos fraudulentos o no pagados reversan la comisión correspondiente. Si la comisión ya fue pagada, Intsolcom podrá descontarla de comisiones futuras o solicitar su devolución. El Partner podrá consultar el motivo y el estado en el ledger de su panel.

11. DECLARACIONES Y GARANTÍAS
El Partner declara que: (a) es mayor de edad o cuenta con autorización de su representante legal; (b) es titular de los derechos sobre los contenidos que entrega o publica; (c) cuenta con autorización de las personas, músicos, fotógrafos, marcas y terceros que aparezcan en sus contenidos; (d) sus contenidos no son ilícitos, difamatorios, discriminatorios ni vulneran derechos de terceros; (e) no publicará precios, descuentos, garantías, plazos o condiciones distintas a las informadas oficialmente por SONIKA; (f) no se presentará como empleado, representante exclusivo o vocero de Intsolcom sin autorización escrita; (g) identificará el contenido como publicidad o enlace de afiliado cuando corresponda; y (h) no realizará autocompras, cuentas relacionadas, cookie stuffing, pujas por la marca SONIKA ni prácticas engañosas.

12. DATOS PERSONALES
Intsolcom tratará los datos personales del Partner conforme a la Ley 1581 de 2012, el Decreto 1377 de 2013 y las políticas de privacidad de SONIKA. La finalidad incluye la gestión del programa, verificación, pagos, comunicaciones, publicaciones autorizadas y cumplimiento legal. El Partner podrá ejercer sus derechos de acceso, actualización, rectificación, supresión y revocación mediante los canales de privacidad de SONIKA.

13. TERMINACIÓN
Cualquiera de las partes podrá terminar la participación mediante comunicación escrita. La terminación no afecta comisiones causadas, obligaciones de confidencialidad, indemnidades ni la autorización sobre material ya publicado durante el plazo indicado. Intsolcom podrá suspender la participación por incumplimiento, fraude, uso indebido de marca o riesgo reputacional.

14. FIRMA ELECTRÓNICA
El Partner acepta firmar este contrato mediante firma electrónica simple capturada en el módulo de SONIKA. Se reconocen como válidos el registro de fecha y hora, el hash criptográfico del contrato, el hash de la firma, la dirección IP y el agente de usuario. La firma electrónica se rige por la Ley 527 de 1999 y el Decreto 2364 de 2012. Este mecanismo no constituye firma digital certificada emitida por una entidad de certificación.

15. LEY APLICABLE
Este contrato se rige por las leyes de la República de Colombia. Cualquier controversia se someterá a los jueces competentes de Colombia, sin perjuicio de las normas imperativas de protección al consumidor, datos personales y derechos de autor.

16. ANEXO DE FUENTES LEGALES
Este documento se apoya en la Constitución Política de Colombia (artículos 15, 20 y 44), la Ley 23 de 1982, la Ley 1915 de 2018, la Ley 527 de 1999, el Decreto 2364 de 2012, la Ley 1581 de 2012, el Decreto 1377 de 2013, la Ley 1480 de 2011 y la Ley 1098 de 2006. El Partner declara haber leído el contrato completo antes de firmar.

Al firmar, el Partner confirma que comprende el alcance de la autorización, que no recibe una contraprestación distinta de las comisiones del programa, que la categoría interna no altera sus derechos y que puede consultar el estado de su firma y sus comisiones en su panel.
TXT;
    }

}
