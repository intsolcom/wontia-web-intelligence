<?php
$__token = strtolower(trim((string)($segments[1] ?? '')));
$__svc = new \App\Services\QuoteService();
$__quote = $__svc->findByPublicToken($__token, false);
$__admin = \App\Core\Session::isLoggedIn() && in_array((string)\App\Core\Session::userRole(), \App\Services\QuoteService::ROLES, true);
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
if (!$__quote || !$__svc->verifySigned($__quote, 'pdf', (string)($_GET['exp'] ?? ''), (string)($_GET['sig'] ?? '')) || (!$__admin && !$__svc->isPublicAvailable($__quote))) {
    http_response_code(404);
    echo 'PDF no disponible.';
    return;
}
try {
    $__file = (new \App\Services\QuotePdfService())->generate($__quote);
    $__svc->recordEvent((int)$__quote['id'], \App\Services\QuoteService::EVENT_PDF, (string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $__name = 'Cotizacion-' . preg_replace('/[^A-Z0-9-]/i', '', (string)$__quote['code']) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $__name . '"');
    header('Content-Length: ' . (string)filesize($__file));
    header('X-Content-Type-Options: nosniff');
    readfile($__file);
} catch (\Throwable $e) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'El PDF no está disponible en este momento.';
}
