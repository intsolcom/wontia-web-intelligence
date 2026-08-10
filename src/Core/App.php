<?php
namespace App\Core;

class App
{
    private Router $router;

    public function __construct()
    {
        $this->router = new Router();
    }

    public function boot(): void
    {
        Config::load();
        $debug = Config::get('APP_DEBUG', 'false') === 'true';
        error_reporting($debug ? E_ALL : 0);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        if (!$debug) {
            ini_set('error_log', defined('ROOT_DIR') ? ROOT_DIR . '/cache/error.log' : sys_get_temp_dir() . '/wontia-error.log');
        }
        date_default_timezone_set('UTC');
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function dispatch(): void
    {
        $this->defineRoutes();
        $request = Request::capture();
        $this->router->dispatch($request->method(), $request->uri());
    }

    private function defineRoutes(): void
    {
        $r = $this->router;

        $r->get('/', function () {
            require_once ROOT_DIR . '/templates/themes/' . Config::$defaultTheme . '/index.php';
        });

        $r->get('/admin', function () {
            if (!Session::isLoggedIn()) {
                Response::redirect('/admin.php');
                return;
            }
            require_once ROOT_DIR . '/public/admin.php';
        });

        $r->get('/sitemap.xml', function () {
            header('Content-Type: application/xml; charset=utf-8');
            require_once ROOT_DIR . '/public/sitemap.php';
        });

        $r->get('/robots.txt', function () {
            header('Content-Type: text/plain; charset=utf-8');
            echo "User-agent: *\nDisallow: /admin/\nDisallow: /api/\nSitemap: " . Config::get('APP_URL', 'https://wontia.intsolcom.com') . "/sitemap.xml\n";
        });

        $r->get('/{slug}', function (Request $req, string $slug) {
            $db = Database::instance();
            $page = $db->prepare("SELECT * FROM pages WHERE slug = :slug AND site_id = @site_id AND status = 'published'");
            $page->execute(['slug' => $slug]);
            $p = $page->fetch();
            if (!$p) {
                http_response_code(404);
                echo '<!DOCTYPE html><html><head><title>404</title></head><body style="font-family:sans-serif;text-align:center;padding:100px"><h1>404</h1><p>Page not found</p><a href="/">Go home</a></body></html>';
                return;
            }
            $sections = $db->prepare("SELECT * FROM sections WHERE page_id = :pid AND is_active = 1 ORDER BY sort_order ASC");
            $sections->execute(['pid' => $p['id']]);
            $s = $sections->fetchAll();
            require ROOT_DIR . '/templates/themes/' . Config::$defaultTheme . '/index.php';
        });
    }

    public function handleError(\Throwable $e): void
    {
        $debug = Config::get('APP_DEBUG', 'false') === 'true';
        if ($debug) {
            Response::error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(), 500);
        } else {
            error_log("Wontia Error: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            Response::error('Internal Server Error', 500);
        }
    }
}
