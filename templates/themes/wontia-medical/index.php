<?php
use App\Widgets\WidgetRegistry;

$page = $page ?? ['id' => 0, 'title' => 'Wontia Medical', 'meta_title' => '', 'meta_description' => '', 'slug' => ''];
$sections = $sections ?? [];
$pageMeta = array_merge($page, ['meta_title' => ($page['meta_title'] ?? '') ?: ($page['title'] ?? 'Wontia Medical')]);

require_once __DIR__ . '/lib.php';

medical_page_start($pageMeta, '');

$builderHtml = '';
try {
    $builder = new \App\Services\BuilderService();
    if ($builder->ready() && $builder->hasLayout((int) ($page['id'] ?? 0))) {
        $builderHtml = $builder->renderPage((int) $page['id']);
    }
} catch (\Throwable $e) {
    $builderHtml = '';
}

if ($builderHtml !== '') {
    echo $builderHtml;
} else {
    $rendered = 0;
    foreach ($sections as $section) {
        $widget = $section['widget_type'] ?? '';
        if (!empty($widget) && WidgetRegistry::get($widget)) {
            $config = json_decode($section['config'] ?? '{}', true) ?: [];
            echo WidgetRegistry::render($widget, $config);
            $rendered++;
        } elseif (in_array($section['type'] ?? '', ['custom', 'html'], true) || !empty($section['content'])) {
            echo '<section class="wm-section">' . ($section['content'] ?? '') . '</section>';
            $rendered++;
        }
    }
    if ($rendered === 0) {
        echo medical_brick('hero');
        echo medical_brick('module', ['module' => 'tia']);
    }
}

if ($builderHtml !== '') {
    require ROOT_DIR . '/templates/themes/_shared/builder-only.php';
} else {
    require ROOT_DIR . '/templates/themes/_shared/live-editor.php';
}

medical_page_end();
