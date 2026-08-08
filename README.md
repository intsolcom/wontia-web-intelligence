# Wontia Web Intelligence

**Standalone website & landing page intelligence engine.**

Page builder, blog, SEO, analytics, AI content, multi-tenant.
One Docker container. Deploy anywhere in <2 minutes.

## Quick Start

```bash
cp .env.example .env
# Edit .env with your DB credentials and JWT_SECRET
docker compose up -d
```

Open `http://localhost:4003/install` to run the setup wizard.

## Tech Stack
- PHP 8.3-FPM + Nginx (Alpine)
- MariaDB 10.11 / MySQL 8.0
- Vanilla JS Admin SPA
- REST API v1 (JSON)
- Docker + Dokploy ready

## Deploy via Dokploy
```
1. Add service → Public Git Repository
2. URL: https://github.com/intsolcom/wontia-web-intelligence
3. Branch: main
4. Deploy
```

## License
MIT — Intsolcom 2026
