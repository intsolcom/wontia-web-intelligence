<?php
require_once __DIR__ . '/lib.php';

$path = trim((string) ($uri ?? ''), '/');
$slug = $path === '' ? '' : explode('/', $path)[0];

$aliases = ['historial' => 'expediente', 'citas' => 'agenda', 'consulta' => 'teleconsulta', 'compras' => 'tienda'];
$slug = $aliases[$slug] ?? $slug;

$modules = medical_module_map();

if ($slug === '' || !isset($modules[$slug]) || !medical_module_enabled($slug)) {
    return false;
}

$meta = [
    'title' => $modules[$slug]['title'],
    'meta_title' => $modules[$slug]['title'] . ' — Wontia Medical',
    'meta_description' => $modules[$slug]['desc'],
    'slug' => $slug,
];

medical_page_start($meta, $slug);

$html = medical_brick($slug, ['module' => $slug, 'meta' => $modules[$slug]]);
if ($html === '') {
    $html = medical_brick('module', ['module' => $slug, 'meta' => $modules[$slug]]);
}
echo $html;

medical_page_end();

return true;
