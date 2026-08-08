# WONTIA WEB INTELLIGENCE — MASTER PROMPT v2.0

## Copy this entire file into a new chat with the AI agent

---

## 1. WHAT WE'RE BUILDING

**Wontia Web Intelligence (WWI)** — a lightweight, BRICK-based CMS embedded in `wontia.com`. The landing page is already served dynamically from a Docker container connected to MariaDB. The admin panel is at `wontia.com/admin.php`.

**Current state:** Live. 8 BRICK widgets render the original landing page. We need to evolve it per the Master Prompt below.

---

## 2. ARCHITECTURE

```
wontia.com
    │
Nginx Host (SSL: letsencrypt)
    │ proxy_pass
127.0.0.1:4003
    │
Docker Container: wontia-web-intelligence
    │ PHP 8.3-FPM + Nginx (Alpine)
    │
MariaDB (docker: mysql-prod, network: intsolcom)
    │ DB: wontia / user: wontia / pass: Wontia2026!
```

---

## 3. VPS & DEPLOYMENT

| Key | Value |
|---|---|
| VPS | `root@169.58.12.55` |
| SSH Key | `~/.ssh/contabo_vps` |
| DB Container | `mysql-prod`, root pass `Admin2026!`, DB `wontia` |
| App Container | `wontia-web-intelligence`, port `4003`, network `intsolcom` |
| Volume Mount | `-v /var/lib/dokploy/uploads/wontia:/app/public/assets/uploads` |
| Nginx Config | `/etc/nginx/sites-enabled/wontia-landing` (proxies to :4003) |

### Deploy commands

```powershell
# COPY files
scp -i $env:USERPROFILE\.ssh\contabo_vps file.php root@169.58.12.55:/tmp/wontia-build/app/path/to/file.php

# BUILD & DEPLOY
ssh -i $env:USERPROFILE\.ssh\contabo_vps root@169.58.12.55 "cd /tmp/wontia-build/app && docker build -t wontia-web-intelligence:latest . 2>&1 | tail -3 && docker rm -f wontia-web-intelligence 2>/dev/null; docker run -d --name wontia-web-intelligence --network intsolcom -p 4003:80 -v /var/lib/dokploy/uploads/wontia:/app/public/assets/uploads --restart unless-stopped wontia-web-intelligence:latest"

# DB operations (use file-based approach to avoid PowerShell escaping hell)
scp -i $env:USERPROFILE\.ssh\contabo_vps sqlfile.sql root@169.58.12.55:/tmp/
ssh -i $env:USERPROFILE\.ssh\contabo_vps root@169.58.12.55 "docker exec -i mysql-prod mysql -uroot -pAdmin2026! wontia < /tmp/sqlfile.sql"

# COPY assets into running container
ssh -i $env:USERPROFILE\.ssh\contabo_vps root@169.58.12.55 "docker cp /var/www/wontia-landing/canales-wontia.jpg wontia-web-intelligence:/app/public/"
```

**CRITICAL: NEVER use `&&` in PowerShell 5.1.** Use `; if ($?) { ... }` or separate commands. Quote paths with spaces. For SQL, always write to a .sql file and pipe it via `docker exec -i`.

---

## 4. LOCAL REPO

| Item | Path |
|---|---|
| **Git Repo** | `D:\INTSOLCOM\IA DEVELOPMENT\WONTIA\Contexto de Wontia\wontia-web-intelligence\` |
| **GitHub** | `https://github.com/intsolcom/wontia-web-intelligence` |
| **Landing reference** | `D:\INTSOLCOM\IA DEVELOPMENT\WONTIA\Contexto de Wontia\Wontia\wontia-landing-index.html` |
| **Landing image** | `D:\INTSOLCOM\IA DEVELOPMENT\WONTIA\imagenes\canales-wontia.jpg` |

---

## 5. PROJECT STRUCTURE

```
/app (Docker container root, same as repo)
├── public/
│   ├── index.php              # Front controller (reads pages/sections from DB)
│   ├── admin.php              # Admin SPA entry (login + shell)
│   ├── api.php                # REST API router
│   ├── sitemap.php            # Dynamic XML sitemap
│   ├── robots.php             # robots.txt
│   ├── install.php            # Setup wizard
│   ├── ice-pricing.js         # ICE pricing widget (copied from VPS static dir)
│   ├── canales-wontia.jpg     # Landing image asset
│   └── assets/
│       ├── css/admin.css      # Admin dark theme
│       ├── js/admin.js        # Admin SPA (vanilla JS, hash router, all panels)
│       └── uploads/           # User uploads (volume mount)
├── src/
│   ├── Core/                  # Config, Database, Request, Response, Router, Session, App
│   ├── Middleware/             # AuthMiddleware (session + JWT)
│   ├── Controllers/Admin/     # Auth, Dashboard, Page, Section, Brick, Media, Blog, BlogCat, BlogTag, SEO, Analytics, Settings, User
│   ├── Services/              # AiContent, SeoService, CookieConsent, AnalyticsService
│   └── Widgets/               # BRICK widget system
│       ├── Widget.php         # Base class (render, meta, configSchema, defaultConfig, safeJson, esc, mergeConfig)
│       ├── WidgetRegistry.php # Auto-discover + render + get + all
│       └── *Widget.php        # Concrete widgets
├── templates/themes/default/index.php  # Theme template (full Wontia design system CSS + BRICK rendering)
├── install/
│   ├── schema.sql             # Full DB schema
│   ├── seed.sql               # Generic seed
│   ├── seed-wontia-php.php    # PHP seeder for wontia.com content
│   └── alter_sections.sql     # Add widget_type column
├── Dockerfile
├── docker-compose.yml
├── nginx.conf
└── .env
```

---

## 6. BRICK WIDGET SYSTEM

Every landing page section is a **BRICK** widget. Each widget is a PHP class extending `Widget`.

### Base Widget contract

```php
abstract class Widget {
    abstract public function render(array $config): string;  // Returns HTML
    public static function meta(): array;       // id, name, icon, category, version
    public static function configSchema(): array;  // Admin-editable fields
    public static function defaultConfig(): array;  // Default values
    public static function adminPreview(): string;  // Thumbnail for Brick Hub
    protected function esc(string $s): string;     // htmlspecialchars
    protected function safeJson($value): array;    // string|array → array
    protected function mergeConfig(array $config): array; // DB config merged with defaults
}
```

### Creating a new widget

1. Create `src/Widgets/MyNewWidget.php` with class `MyNewWidget extends Widget`
2. Implement all abstract methods
3. Deploy files + rebuild Docker container
4. Widget auto-discovered by WidgetRegistry via glob
5. Available in admin Brick Hub panel

### Existing BRICKs

| ID | Class | Type |
|---|---|---|
| `hero` | HeroWidget | Original hero section |
| `features` | FeaturesWidget | 6 feature cards |
| `tia` | TiaWidget | TIA section with blob |
| `aip` | AipWidget | AIP philosophy cards |
| `howitworks` | HowItWorksWidget | 3-step process |
| `pricing` | PricingWidget | ICE pricing embed |
| `cta` | CtaWidget | Final CTA |
| `footer` | FooterWidget | Dual-address footer |
| `hero-evolved` | HeroEvolvedWidget | NEW: Evolved hero positioning |
| `differentiator` | DifferentiatorWidget | NEW: Answers vs Action comparison |
| `tiacommand` | TiaCommandWidget | NEW: TIA Command Center |
| `wontia-business` | WontiaBusinessWidget | NEW: Business vertical |
| `domain-arch` | DomainArchWidget | NEW: Domain architecture |
| `food-security` | FoodSecurityWidget | NEW: Food Security vision |

### DB sections table

```sql
sections (
    id, page_id, type, widget_type, title, subtitle, content, image, config (JSON text),
    sort_order, is_active
)
```

- `widget_type` = the BRICK ID (e.g., 'hero', 'tia', 'hero-evolved')
- `config` = JSON string with widget-specific configuration
- Sections with `widget_type` are rendered via `WidgetRegistry::render()`
- Sections without `widget_type` render legacy (content HTML)

---

## 7. DESIGN SYSTEM (WONTIA)

| Token | Value |
|---|---|
| `--bg` | `#F6F6F3` |
| `--bg-alt` | `#F3F3EF` |
| `--bg-card` | `#FBFBF9` |
| `--text` | `#2F2F2F` |
| `--text-sub` | `#6B6B6B` |
| `--text-muted` | `#9A9A9A` |
| `--primary` | `linear-gradient(135deg, #9B8CDE, #B89EFF)` (lavender) |
| `--primary-solid` | `#7C3AED` |
| `--primary-light` | `#EDE9FE` |
| `--accent-1` | `#DCCFFF` |
| `--accent-2` | `#CFE6FF` (blue) |
| `--accent-3` | `#D9F2E2` (green) |
| `--accent-4` | `#F7E8C8` (amber) |
| `--radius` | `12px-24px` |
| `--shadow` | `0 2px 8px rgba(0,0,0,.03), inset 0 1px 2px rgba(255,255,255,.70)` |
| Font | Inter (Google Fonts), weights 400-800 |

### CSS classes (defined in theme template)

- `.reveal` + `.visible` — fade-in scroll trigger (IntersectionObserver)
- `.btn-primary` — lavender button with shadow and hover translateY
- `.btn-outline` — lavender border, subtle hover bg
- `.badge` — pill tag
- `.card` — white card with subtle border and inner shadow
- `.card-padded` — card with 32px padding
- `.gradient-text` — lavender gradient text
- `.grid-3`, `.grid-2` — responsive grids
- `.hero` — hero section spacing
- `.section` — standard section
- `.img-swap` — 2 images cross-fade on hover
- `.list-item`, `.list-num` — numbered lists
- `.hero-bg` — radial abstract background

---

## 8. ADMIN PANEL

**URL:** `https://wontia.com/admin.php`
**Login:** `admin` / `admin`

Panels: Dashboard, Pages, Sections, Bricks, Blog, Media, SEO, Analytics, Settings, Users.

The admin is a vanilla JS SPA with hash-based routing. Each panel is a function in the `wontia.panels` object. API calls use `wontia.api(url, options)` which auto-attaches JWT auth.

---

## 9. WHAT STILL NEEDS TO BE BUILT

### Remaining widgets (3 of 9 new ones done, 3 left):

| Widget | File | Status |
|---|---|---|
| PlatformArchWidget | `src/Widgets/PlatformArchWidget.php` | NOT CREATED |
| TrustWidget | `src/Widgets/TrustWidget.php` | NOT CREATED |
| FutureVisionWidget | `src/Widgets/FutureVisionWidget.php` | NOT CREATED |

### What each needs:

**PlatformArchWidget** — Visual architecture diagram: WONTIA CORE → TIA CORE → DOMAIN INTELLIGENCE → TOOLS/DATA/SYSTEMS → DECISION ENGINE → ACTION ORCHESTRATION → MEASURABLE OUTCOMES

**TrustWidget** — Human control: RECOMMEND → APPROVE → EXECUTE. Permissions, policies, auditability, transparency. Enterprise-grade trust messaging.

**FutureVisionWidget** — WONTIA → TIA → tree of domains (Business, Food Security, Health, Agriculture, Industry, Logistics, Education, MORE). Message: "The intelligence layer remains consistent. The domain context, tools, workflows and actions change."

### Updates needed:

1. **Navbar** — Update in theme template to reflect new hierarchy (Wontia / Solutions / TIA / Food Security / About)
2. **Theme template** — Add section for the new widgets (they auto-render via WidgetRegistry)
3. **Seed data** — Create `install/seed-wontia-evolved.php` that replaces page sections with evolved order:
   - HeroEvolved
   - Differentiator
   - TiaCommand
   - WontiaBusiness
   - DomainArch
   - FoodSecurity
   - PlatformArch
   - TrustWidget
   - FutureVision
   - Pricing (ICE)
   - CTA
   - Footer
4. **Admin SPA** — Add "Bricks" panel title in router

### IMPORTANT RULES:

- **NEVER overpromise.** Mark clearly: AVAILABLE / IN DEVELOPMENT / FUTURE / CONCEPT / PROTOTYPE
- **Wontia Business:** CURRENT / AVAILABLE
- **Food Security:** IN DEVELOPMENT / NEXT VERTICAL
- **Health, Agriculture, Industry, Logistics, Education:** FUTURE
- **Do NOT call Wontia a CRM.** Position as Applied Intelligence Platform.
- **TIA is the intelligence layer.** Wontia is the platform. Domains are applications.
- **Core message:** UNDERSTAND → DECIDE → ACT
- **ONE PLATFORM. ONE INTELLIGENCE. MULTIPLE DOMAINS.**
- Keep the existing Wontia visual identity (lavender, clean, premium, enterprise)
- Do not remove working features unless they conflict with new positioning
- All widgets must extend Widget base class
- Use `$this->safeJson()` not `json_decode()` for config arrays
- Use `$this->esc()` for user-facing text
- Preserve responsive design, animations, and CSS classes

---

## 10. SEO / POSITIONING

Natural keywords: Applied Intelligence, AI platform, AI agents, AI automation, intelligent workflows, decision intelligence, AI orchestration, business intelligence, domain-specific AI, enterprise AI. Do not keyword-stuff.

---

## 11. BRAND PERSONALITY

INTELLIGENT · CALM · PRECISE · POWERFUL · HUMAN · FUTURE-READY

Avoid: flashy, chaotic, cyberpunk, gimmicky, startup clichés, "AI magic", excessive emojis.

---

## 12. EXECUTION STEPS

1. Read `src/Widgets/Widget.php` to understand base class
2. Read 1-2 existing widgets for patterns (e.g., `DifferentiatorWidget.php`)
3. Create the 3 remaining widgets: `PlatformArchWidget.php`, `TrustWidget.php`, `FutureVisionWidget.php`
4. Update `templates/themes/default/index.php` navbar
5. Create `install/seed-wontia-evolved.php` with new section order
6. SCP all new files to `/tmp/wontia-build/app/` on the VPS
7. Run the seed PHP script inside the container via `docker cp` + `docker exec`
8. Rebuild Docker: `docker build -t wontia-web-intelligence:latest .`
9. Redeploy: `docker rm -f wontia-web-intelligence && docker run -d --name wontia-web-intelligence --network intsolcom -p 4003:80 -v /var/lib/dokploy/uploads/wontia:/app/public/assets/uploads --restart unless-stopped wontia-web-intelligence:latest`
10. Test: `curl -H 'Host: wontia.com' https://localhost/`
11. Commit + push to GitHub: `git add -A && git commit -m "..." && git push origin main`

---

## 13. SUCCESS CRITERIA

- [ ] wontia.com loads and no longer feels like a CRM
- [ ] Hero says "Turn AI into Action" 
- [ ] TIA Command Center section visible
- [ ] Wontia Business positioned as current product
- [ ] Domain Architecture shows all verticals with status badges
- [ ] Food Security section shows Response Engine with CONCEPT label
- [ ] Differentiator section (Answers vs Action) clear
- [ ] Future Vision section shows tree of domains
- [ ] Admin panel at /admin.php still works
- [ ] 12+ BRICKs listed in admin Brick Hub
- [ ] Zero 500 errors
- [ ] Responsive on mobile
