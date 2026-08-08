# Wontia Web Intelligence — Implementation Prompt
## Copy this entire file into a new chat with the AI agent

---

## CONTEXT

You are building **Wontia Web Intelligence v1.0.0** — a standalone website & landing page intelligence engine (CMS). 

The project already exists:
- **GitHub:** https://github.com/intsolcom/wontia-web-intelligence
- **Local:** `D:\INTSOLCOM\IA DEVELOPMENT\wontia-web-intelligence\`
- **Base structure:** 13 files committed (Dockerfile, nginx.conf, docker-compose.yml, nixpacks.toml, composer.json, .env.example, etc.)

Your task: implement the FULL application — Core classes, Controllers, Services, Middleware, Admin SPA, Page Builder, Blog, SEO, Analytics, AI Content, Install Wizard.

**Reference code** (DO NOT MODIFY, only extract patterns from it):
- `D:\INTSOLCOM\IA DEVELOPMENT\MARCASBPO\Sitio Web\admin\index.php` (8396 lines — Admin SPA with PHP handlers + vanilla JS)
- `D:\INTSOLCOM\IA DEVELOPMENT\MARCASBPO\Sitio Web\includes\config.php` (DB connection)
- `D:\INTSOLCOM\IA DEVELOPMENT\MARCASBPO\Sitio Web\includes\seo_global.php` (SEO engine)
- `D:\INTSOLCOM\IA DEVELOPMENT\MARCASBPO\Sitio Web\includes\cookie_banner.php` (GDPR consent)
- `D:\INTSOLCOM\IA DEVELOPMENT\MARCASBPO\Sitio Web\admin\bpo_content_factory.php` (AI content gen)
- `D:\INTSOLCOM\IA DEVELOPMENT\MARCASBPO\nginx.conf` (Nginx rewrite rules)
- `D:\INTSOLCOM\IA DEVELOPMENT\MARCASBPO\PEDS-WONTIA-WEB-INTELLIGENCE-MASTER-PROMPT.md` (Full architecture spec, 895 lines)

## SERVER (for deployment later)
- **VPS:** Contabo `169.58.12.55`, SSH `root@169.58.12.55`, key `~/.ssh/contabo_vps`
- **Docker network:** `intsolcom`
- **MariaDB container:** `mysql-prod`, root pass `Admin2026!`, DB `wontia`, user `wontia` / `Wontia2026!`
- **Host Nginx:** `/etc/nginx/sites-enabled/wontia` proxies `https://wontia.intsolcom.com` → `127.0.0.1:4003`
- **Dokploy** is available on the VPS for one-click deploys

## ABSOLUTE RULES

1. **NEVER modify files in** `D:\INTSOLCOM\IA DEVELOPMENT\MARCASBPO\Sitio Web\`
2. **All new code goes in** `D:\INTSOLCOM\IA DEVELOPMENT\wontia-web-intelligence\`
3. **Before writing any code:** Read existing files to understand patterns
4. **After each file:** Verify it parses with `php -l`
5. **Before deploying:** Run all pre-deploy checks
6. **Always use prepared statements** (PDO, zero SQL injection)
7. **No frameworks** — vanilla PHP 8.3 + vanilla JS for admin SPA
8. **PSR-4 autoloading** — namespace `App\` maps to `src/`
9. **Commit frequently** with descriptive messages
10. **Volume mount for uploads** — NEVER forget `-v /host/uploads:/app/public/assets/uploads`

## EXECUTION ORDER

### Step 1: Core Foundation

**1a. Install dependencies**
```bash
cd "D:\INTSOLCOM\IA DEVELOPMENT\wontia-web-intelligence"
composer install
```

**1b. Create `src/Core/Config.php`**
- Loads `.env` file into `$_ENV`
- Static getter: `Config::get('DB_HOST')`
- Fallback to defaults
- Strip comments and empty lines from .env

**1c. Create `src/Core/Database.php`**
- PDO singleton with retry logic (3 attempts)
- Constructor: reads `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` from Config
- Sets: `ATTR_ERRMODE => ERRMODE_EXCEPTION`, `ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC`, `ATTR_EMULATE_PREPARES => false`
- Method `instance()` returns the singleton PDO

**1d. Create `src/Core/Request.php`**
- Wraps `$_GET`, `$_POST`, `php://input` (JSON)
- Method `get(string $key, $default = null)` — sanitized GET param
- Method `post(string $key, $default = null)` — sanitized POST param
- Method `json()` — decodes `php://input` as associative array
- Method `method()` — returns HTTP method
- Method `header(string $key)` — returns request header

**1e. Create `src/Core/Response.php`**
- `Response::json($data, $status = 200)` — sets Content-Type, status, echoes JSON
- `Response::html($html, $status = 200)` — sets Content-Type, echoes HTML
- `Response::error($message, $status = 400)` — JSON error response
- `Response::xml($xml)` — sets Content-Type, echoes XML

**1f. Create `src/Core/Router.php`**
- Regex-based router
- `get($pattern, $handler)` / `post($pattern, $handler)` / `put($pattern, $handler)` / `delete($pattern, $handler)`
- `dispatch($method, $uri)` — matches pattern, extracts params, calls handler
- Supports named params: `/posts/{id}` → `['id' => '123']`
- 404 on no match

**1g. Create `src/Core/App.php`**
- Bootstrap: loads Config, sets error handling based on APP_DEBUG
- `boot()` — requires autoloader, starts session if needed
- `dispatch()` — creates Router, defines routes, dispatches request
- Error handler: in production, log errors, show generic message. In debug, show details.

**1h. Update `public/index.php`**
- Already created. Verify it uses `App\Core\App` correctly.

**1i. Update `public/api.php`**
- Front-controller for all `/api/` routes
- Loads Config, Database, Router
- Defines API routes (stubs for now, full implementation later)
- `/api/v1/health` → returns `{"ok":true,"db":true,"cache":true,"version":"1.0.0"}`
- All responses JSON with CORS headers

**1j. Create `install/schema.sql`**

Full database schema:
```sql
CREATE DATABASE IF NOT EXISTS wontia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE wontia;

CREATE TABLE sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255),
    locale VARCHAR(5) DEFAULT 'en',
    theme VARCHAR(50) DEFAULT 'default',
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('superadmin','admin','editor') DEFAULT 'admin',
    last_login DATETIME,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    template VARCHAR(100) DEFAULT 'default',
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords VARCHAR(500),
    og_image VARCHAR(500),
    canonical_url VARCHAR(500),
    no_index TINYINT DEFAULT 0,
    status ENUM('published','draft') DEFAULT 'published',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_slug (site_id, slug),
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    page_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255),
    subtitle TEXT,
    content LONGTEXT,
    image VARCHAR(500),
    config JSON,
    sort_order INT DEFAULT 0,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    filename VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    size INT DEFAULT 0,
    mime VARCHAR(100),
    alt_text VARCHAR(255),
    width INT DEFAULT 0,
    height INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_key (site_id, `key`),
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(500) NOT NULL,
    excerpt TEXT,
    content LONGTEXT,
    cover_image VARCHAR(500),
    cover_alt VARCHAR(255),
    category_id INT,
    author_name VARCHAR(255),
    author_role VARCHAR(255),
    read_time INT DEFAULT 1,
    status ENUM('draft','published') DEFAULT 'draft',
    featured TINYINT DEFAULT 0,
    views INT DEFAULT 0,
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords VARCHAR(500),
    lang VARCHAR(10) DEFAULT 'en',
    published_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY site_slug (site_id, slug),
    FULLTEXT INDEX ft_search (title, excerpt, content),
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE blog_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#BE1341',
    sort_order INT DEFAULT 0,
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE blog_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE blog_post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE blog_newsletter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    lang VARCHAR(5) DEFAULT 'en',
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE analytics_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL DEFAULT 1,
    page_url VARCHAR(500) NOT NULL,
    referrer VARCHAR(500),
    user_agent VARCHAR(500),
    ip_hash VARCHAR(64),
    is_internal TINYINT DEFAULT 0,
    country VARCHAR(5),
    device VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_site_date (site_id, created_at),
    FOREIGN KEY (site_id) REFERENCES sites(id)
) ENGINE=InnoDB;

CREATE TABLE api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    api_key VARCHAR(128) UNIQUE NOT NULL,
    type ENUM('public','admin') DEFAULT 'public',
    permissions JSON,
    rate_limit INT DEFAULT 1000,
    allowed_origins JSON,
    is_active TINYINT DEFAULT 1,
    last_used_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

**1k. Create `install/seed.sql`**
- Insert default site: `INSERT INTO sites (name, domain, locale) VALUES ('My Website', 'localhost', 'en');`
- Insert admin user: `INSERT INTO users (site_id, username, email, password_hash, role) VALUES (1, 'admin', 'admin@example.com', '$2y$12$...', 'superadmin');` (password: `admin`)
- Insert default settings: `site_name`, `site_description`, `ga_measurement_id`, `cookie_consent_enabled`
- Insert demo page + sections (home page with hero, features, cta)

### Step 2: Authentication

**2a. Create `src/Core/Session.php`**
- Wrapper for PHP sessions
- `start()`, `set($key, $val)`, `get($key)`, `has($key)`, `remove($key)`, `destroy()`
- `flash($key, $val)` — one-time messages

**2b. Create `src/Middleware/AuthMiddleware.php`**
- Checks if user is logged in via session
- Redirects to `/admin/login` if not authenticated
- For API: checks JWT in `Authorization: Bearer` header

**2c. Create `src/Controllers/Admin/AuthController.php`**
- `login()` — POST handler: validates username/password against DB (bcrypt verify), sets session, returns JWT
- `logout()` — destroys session
- `me()` — returns current user info
- Password hash: use `password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])`

### Step 3: Admin SPA Shell

**3a. Create `public/admin.php`**
- Admin SPA entry point
- If not logged in: show login form
- If logged in: show admin dashboard shell with sidebar navigation
- Sidebar links: Dashboard, Pages, Blog, Media, SEO, Analytics, Settings, Users
- Content area: `<div id="wontia-app"></div>` loaded by JS

**3b. Create `public/assets/css/admin.css`**
Modern dark admin theme with CSS custom properties:
```css
:root {
    --w-bg: #0f1117;
    --w-surface: #1a1d27;
    --w-border: #2a2d3a;
    --w-text: #e1e4ed;
    --w-muted: #8b8fa3;
    --w-primary: #00B87D;
    --w-accent: #BE1341;
    --w-radius: 8px;
    --w-sidebar-w: 240px;
}
```
- Sidebar fixed left, 240px, dark surface
- Main content area with padding
- Cards with surface bg, border-radius, subtle shadow
- Form inputs styled consistently
- Tables with hover rows
- Buttons: primary (green), danger (red), secondary (gray)
- Toast notifications (top-right)
- Modal overlay
- Responsive: sidebar collapses on mobile

**3c. Create `public/assets/js/admin.js`**
Vanilla JS SPA framework (no React/Vue):

Core modules:
```
wontia = {
    api(url, options)  — fetch wrapper with JWT header, JSON parse, error handling
    router()           — hash-based routing (#dashboard, #pages, #blog, etc.)
    panels {}          — each panel is a function that renders into #wontia-app
    state {}           — global state cache (pages, posts, categories...)
    notify(msg, type)  — toast notification (success/error/info)
    modal(title, body) — modal dialog
    confirm(msg, cb)   — confirmation dialog
}
```

Route mapping:
```
#dashboard   → wontia.panels.dashboard()
#pages       → wontia.panels.pages()
#pages/new   → wontia.panels.pageEditor()
#pages/{id}  → wontia.panels.pageEditor(id)
#blog        → wontia.panels.blogPosts()
#blog/new    → wontia.panels.blogEditor()
#blog/{id}   → wontia.panels.blogEditor(id)
#media       → wontia.panels.media()
#seo         → wontia.panels.seo()
#analytics   → wontia.panels.analytics()
#settings    → wontia.panels.settings()
#users       → wontia.panels.users()
```

On page load:
1. Check if logged in (cookie/session)
2. Show login form or admin shell
3. Initialize router
4. Load current panel based on hash

### Step 4: Admin Controllers & API Endpoints

Create these files in `src/Controllers/Admin/`:

**DashboardController.php**
- `GET /api/v1/admin/dashboard` → returns: total pages, total posts (published/draft), total media, total views, recent activity

**PageController.php**
- `GET /api/v1/admin/pages` → list all pages with section count
- `GET /api/v1/admin/pages/{id}` → single page with all sections
- `POST /api/v1/admin/pages` → create page (title, slug, template, status)
- `PUT /api/v1/admin/pages/{id}` → update page
- `DELETE /api/v1/admin/pages/{id}` → delete page + cascade sections

**SectionController.php**
- `GET /api/v1/admin/pages/{pageId}/sections` → list sections for a page
- `POST /api/v1/admin/pages/{pageId}/sections` → add section
- `PUT /api/v1/admin/sections/{id}` → update section
- `DELETE /api/v1/admin/sections/{id}` → delete section
- `PUT /api/v1/admin/sections/reorder` → update sort_order for list of {id, sort_order}

**MediaController.php**
- `GET /api/v1/admin/media` → list media (paginated), filter by type (image/svg)
- `POST /api/v1/admin/media/upload` → handle `$_FILES['file']`, validate: JPG/PNG/WebP/SVG/GIF/ICO, max 5MB, save to `public/assets/uploads/`, insert into `media` table, return `{url: "...", id: ...}`
- `DELETE /api/v1/admin/media/{id}` → delete file + DB record

**BlogController.php**
- `GET /api/v1/admin/blog/posts` → list posts with category name, paginated
- `GET /api/v1/admin/blog/posts/{id}` → single post with tag_ids
- `POST /api/v1/admin/blog/posts` → create/update post (all fields + tags pivot)
- `DELETE /api/v1/admin/blog/posts/{id}` → delete post
- `PATCH /api/v1/admin/blog/posts/{id}/status` → toggle draft/published, set published_at
- `POST /api/v1/admin/blog/polish` → AI content polish via DeepSeek API (read key from Config)

**BlogCategoryController.php**
- `GET /api/v1/admin/blog/categories` → list all
- `POST /api/v1/admin/blog/categories` → create
- `PUT /api/v1/admin/blog/categories/{id}` → update
- `DELETE /api/v1/admin/blog/categories/{id}` → delete

**BlogTagController.php**
- `GET /api/v1/admin/blog/tags` → list all
- `POST /api/v1/admin/blog/tags` → create
- `DELETE /api/v1/admin/blog/tags/{id}` → delete

**SeoController.php**
- `GET /api/v1/admin/seo` → SEO status: pages with/without meta, duplicate titles, missing descriptions
- `POST /api/v1/admin/seo/audit` → run full audit (broken links, missing alt text, long titles, missing meta)

**AnalyticsController.php**
- `GET /api/v1/admin/analytics` → query params: `?from=YYYY-MM-DD&to=YYYY-MM-DD`. Returns: total views, unique visitors, top pages, referrers, devices, countries, daily chart data
- `PUT /api/v1/admin/analytics/ga4` → save GA4 measurement ID to settings

**SettingsController.php**
- `GET /api/v1/admin/settings` → return all settings as key-value
- `PUT /api/v1/admin/settings` → batch update settings from JSON body

**UserController.php**
- `GET /api/v1/admin/users` → list users (superadmin only)
- `POST /api/v1/admin/users` → create user
- `PUT /api/v1/admin/users/{id}` → update user
- `DELETE /api/v1/admin/users/{id}` → delete user (can't delete self)

### Step 5: Page Builder JS

Create `public/assets/js/page-builder.js`:

- Section type selector (hero, features, cta, testimonials, pricing, stats, contact, faq, custom)
- Drag-and-drop reorder (using HTML5 Drag & Drop API)
- Section editor modal with:
  - Type-specific fields (title, subtitle, content, image, CTA text/URL)
  - Content WYSIWYG editor (contenteditable div with toolbar: H2, H3, bold, italic, link, list, blockquote, code)
  - Config panel for layout/colors
- Live preview toggle
- Save button → API call to save all sections

### Step 6: Blog Engine

Extract blog logic from marcasbpo.com `admin/index.php` (lines 185-251 PHP handlers, lines 3700-3819 panels, lines 7280-7559 JS):

**Blog Editor Panel (in admin.js):**
- Title input with auto-slug generation
- Excerpt textarea
- HTML content editor (contenteditable with toolbar)
- Cover image: drag-and-drop zone + file picker + URL paste
- Cover upload via `MediaController` upload endpoint
- Category selector (dropdown)
- Author name + role fields
- Read time (auto-calculated: word count / 200)
- Featured toggle
- SEO fields: meta title, description, keywords
- Tag checkboxes
- AI Polish button → calls `/api/v1/admin/blog/polish`
- Save as Draft / Publish buttons

**Blog Listing Panel:**
- Stats cards: Total Posts, Published, Drafts, Total Views
- Search input + status filter dropdown
- Paginated table: title, category, status, author, views, date, actions
- Action buttons: edit, toggle status, delete

### Step 7: AI Content Service

Create `src/Services/AiContentService.php`:
- `polishContent(string $html): string` — sends HTML to DeepSeek API for grammar/style improvement
- `generateArticle(string $topic, string $keywords): array` — generates title, excerpt, content, SEO meta
- `suggestTopics(string $niche): array` — generates 10 blog topic ideas
- API call: POST to `https://api.deepseek.com/v1/chat/completions` with Bearer token from `DEEPSEEK_API_KEY` env var
- Model: `deepseek-chat`
- Temperature: 0.7 for creative, 0.3 for polish

### Step 8: Public-Facing Site

**8a. Create `src/Controllers/Public/SiteController.php`**
- `GET /{slug}` → looks up page by slug in DB, loads sections, renders template
- Home page: `/` → slug `home` or first published page
- Uses `templates/themes/default/index.php` for layout

**8b. Create `templates/themes/default/index.php`**
Full HTML page with CSS variables for theming:
- Header with nav (loaded from pages where `template = 'nav'`)
- Main content: render each section using type-specific templates
- Footer
- SEO meta tags (from SeoService)
- Cookie consent banner
- Analytics tracking pixel

**8c. Create `src/Services/SeoService.php`**
- `metaTags(array $page): string` — generates full `<head>` meta tags (title, description, keywords, og:title, og:description, og:image, twitter:card, canonical, robots)
- `jsonLd(string $type, array $data): string` — generates JSON-LD script tag
- `breadcrumbs(array $items): string` — generates BreadcrumbList schema
- Schema types: Organization, WebSite, WebPage, Article, BreadcrumbList, FAQ

**8d. Create `src/Services/CookieConsentService.php`**
- GDPR-compliant banner injected at bottom of every public page
- Cookie categories: necessary (always on), analytics, marketing
- Settings stored in localStorage (`wontia_cookie_consent`)
- Banner CSS: fixed bottom, z-index 999999, dark theme, accept/reject/customize buttons
- Analytics tracking only fires if consent given

**8e. Create `src/Services/AnalyticsService.php`**
- `track(string $url, string $referrer, string $userAgent, bool $isInternal): void`
- Insert into `analytics_views` table
- IP hashed with SHA256 for privacy
- Detect device type from user agent: mobile/tablet/desktop
- Detect country from IP (via Cloudflare `CF-IPCountry` header or API)
- `getStats(int $siteId, string $from, string $to): array` — aggregate stats for dashboard

**8f. Create `public/sitemap.php`**
- Dynamic XML sitemap
- Lists all published pages + blog posts
- Includes: `<loc>`, `<lastmod>`, `<changefreq>`, `<priority>`
- Output: `Content-Type: application/xml`

**8g. Create `public/robots.php`**
- Dynamic robots.txt
- `User-agent: *`
- `Disallow: /admin/`
- `Disallow: /api/`
- `Sitemap: {APP_URL}/sitemap.xml`

### Step 9: Install Wizard

**9a. Create `public/install.php`**
Multi-step setup wizard:
- Step 1: Database configuration (host, port, name, user, pass) → test connection → create tables
- Step 2: Admin account (username, email, password)
- Step 3: Site info (name, domain, locale)
- Step 4: Complete → write `.env` file, redirect to `/admin`

**9b. Create `templates/install/` PHP templates**
- Clean, minimal design
- Progress indicator (steps 1-4)
- Form validation with inline errors
- No external CSS dependencies

### Step 10: Install Schema on VPS

```bash
ssh -o StrictHostKeyChecking=no -i ~/.ssh/contabo_vps root@169.58.12.55 "docker exec mysql-prod mysql -uroot -pAdmin2026! -e \"CREATE DATABASE IF NOT EXISTS wontia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'wontia'@'%' IDENTIFIED BY 'Wontia2026!'; GRANT ALL PRIVILEGES ON wontia.* TO 'wontia'@'%'; FLUSH PRIVILEGES;\""
```

```bash
scp -o StrictHostKeyChecking=no -i ~/.ssh/contabo_vps "D:\INTSOLCOM\IA DEVELOPMENT\wontia-web-intelligence\install\schema.sql" root@169.58.12.55:/tmp/
ssh -o StrictHostKeyChecking=no -i ~/.ssh/contabo_vps root@169.58.12.55 "docker exec -i mysql-prod mysql -uwontia -pWontia2026! wontia < /tmp/schema.sql"
```

### Step 11: Build & Deploy

```bash
# Copy all files to VPS build directory
scp -o StrictHostKeyChecking=no -i ~/.ssh/contabo_vps -r "D:\INTSOLCOM\IA DEVELOPMENT\wontia-web-intelligence\*" root@169.58.12.55:/tmp/wontia-build/app/

# Build Docker image
ssh -o StrictHostKeyChecking=no -i ~/.ssh/contabo_vps root@169.58.12.55 "cd /tmp/wontia-build/app; docker build -t wontia-web-intelligence:latest ."

# Stop old container, start new with persistent volume
ssh -o StrictHostKeyChecking=no -i ~/.ssh/contabo_vps root@169.58.12.55 "docker rm -f wontia-web-intelligence 2>/dev/null; docker run -d --name wontia-web-intelligence --network intsolcom -p 4003:80 -v /var/lib/dokploy/uploads/wontia:/app/public/assets/uploads --restart unless-stopped wontia-web-intelligence:latest"

# Smoke test
ssh -o StrictHostKeyChecking=no -i ~/.ssh/contabo_vps root@169.58.12.55 "sleep 2; curl -s http://localhost:4003/api/v1/health"
```

### Step 12: Configure Nginx on Host

```bash
ssh -o StrictHostKeyChecking=no -i ~/.ssh/contabo_vps root@169.58.12.55 'cat > /etc/nginx/sites-enabled/wontia << '"'"'NGINXEOF'"'"'
server {
    listen 80;
    server_name wontia.intsolcom.com;
    return 301 https://$host$request_uri;
}
server {
    listen 443 ssl http2;
    server_name wontia.intsolcom.com;
    
    ssl_certificate /etc/letsencrypt/live/marcasbpo.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/marcasbpo.com/privkey.pem;
    
    client_max_body_size 10M;
    proxy_read_timeout 600s;
    
    location / {
        proxy_pass http://127.0.0.1:4003;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
NGINXEOF
nginx -t && systemctl reload nginx'
```

## VERIFICATION CHECKLIST

After each phase, verify:
- [ ] `php -l` on every PHP file (no parse errors)
- [ ] `composer dump-autoload` runs without errors
- [ ] All API endpoints return correct JSON
- [ ] Admin login works
- [ ] Page CRUD works
- [ ] Blog CRUD works
- [ ] Image upload works (and survives rebuild via volume)
- [ ] SEO meta tags appear on public pages
- [ ] Cookie consent banner appears
- [ ] Analytics tracking records views
- [ ] Sitemap generates correctly
- [ ] Install wizard completes

## GIT COMMANDS TO USE

```bash
cd "D:\INTSOLCOM\IA DEVELOPMENT\wontia-web-intelligence"
git pull origin main           # Always pull before starting
git add -A
git commit -m "feat: description of what was done"
git push origin main
```

## POWERFUL POWERSHELL NOTE

On this Windows machine:
- NEVER use `&&` — it does not work in PowerShell 5.1
- Use separate commands or `; if ($?) { ... }` for chaining
- Quote paths with spaces: `"C:\path with spaces\file.php"`
- For SSH commands, use single quotes `'...'` around the remote command
- `scp` and `ssh` work with the key file `~/.ssh/contabo_vps`
- `grep` doesn't exist on PowerShell — use `Select-String` or `findstr`

---

## FINAL DELIVERABLE

When complete, the following should work:
1. Visit `https://wontia.intsolcom.com` → public homepage with sections
2. Visit `https://wontia.intsolcom.com/admin` → login → full admin dashboard
3. Create/edit/delete pages with drag-and-drop sections
4. Create/edit/delete blog posts with AI polish
5. Upload images that survive container rebuilds
6. `/api/v1/health` returns `{"ok":true}`
7. Install on ANY domain in <2 minutes via Docker + Dokploy

**START NOW. Begin with Step 1. Read reference files before writing code. Commit after each logical group of files.**
