# QuickKey · Software Shortcut Key Knowledge Base

**[English](README.en.md) | [简体中文](README.md)**

Live demo: **https://xcsy.my-place.us/**

A software keyboard-shortcut lookup site built with **plain PHP + JSON file storage**. No database, no framework, no Composer, no build step — drop it on any PHP-capable shared hosting and it just works.

Covers office, design, development, BIM, GIS and industrial software with **2,000+ shortcuts**, featuring Windows / Mac / Linux platform switching, multi-version lookup, function-based search, one-click copy, local favorites and software comparison.

## Features

- **Shortcut lookup** — browse by software, shortcuts grouped by function
- **Platform / version switching** — Windows / Mac / Linux side by side, per-version data
- **Function search** — search by *what you want to do* ("hide layer", "screenshot"), not by menu name
- **One-click copy** — click any shortcut to copy it
- **Favorites** — local, account-free (localStorage)
- **Software comparison** — compare two apps' shortcuts side by side
- **Trending ranking** — based on real visit statistics (PV/UV)
- **Corrections & submissions** — visitor submissions reviewed in the admin panel, with rate limiting
- **Admin panel** — dashboard (visitor IPs, 14-day bar chart), full CRUD for software & shortcuts, submission management, site notice, dual QR codes (WeChat / QQ)

## Tech Stack

- PHP 8.x (no framework, no database, no Composer)
- JSON file storage (atomic writes + automatic backups)
- Vanilla JavaScript / CSS (no build step)

## Getting Started

### Run locally

```bash
# Option 1: PHP built-in server
cd site
php -S localhost:8080

# Option 2: phpStudy / XAMPP
# Point the web root at the site directory (or the repo root) and you're done.
```

Open `http://localhost:8080`.

### Deploy to shared hosting (cPanel / aaPanel / etc.)

1. Upload all repository files to your web root (e.g. `htdocs`);
2. PHP >= 8.0 with `mbstring`, `json` and `session` extensions enabled;
3. Make the `site/data/` directory (or `data/` in single-directory deployments) **writable** (chmod 755);
4. Apache picks up `data/.htaccess` automatically; for Nginx add:
   ```nginx
   location ^~ /data/ { deny all; return 404; }
   ```
5. Admin panel is at `admin.php`, default credentials `admin / admin123456` — **change the password immediately**.

### Automatic deployment (optional)

`.github/workflows/deploy.yml` syncs the site to your host on every push to `main`. Add four variables in `Settings → Secrets and variables → Actions`:

| Secret | Example |
|---|---|
| `FTP_SERVER` | `sg02.my-plaec.us` |
| `FTP_USERNAME` | `mp_43041338` |
| `FTP_PASSWORD` | your panel/FTP password |
| `FTP_SERVER_DIR` | `/htdocs/` |

Safety: triggers on `main` pushes only (no PR triggers, forks never receive secrets); `lftp mirror -R` **uploads without deleting**; `data/` and `assets/qr/` are excluded so local runs never overwrite live data. If the secrets are missing the job skips itself instead of failing.

## Admin Panel

| Module | Description |
|---|---|
| Dashboard | Today / total page views & unique visitors (10-minute session dedup), visitor IP list & per-IP detail, 14-day bar chart |
| Software management | Full CRUD for software and shortcuts; searchable pending list with one-click adoption |
| Submissions | Review visitor corrections & suggestions (with submitter IP), mark as handled, delete; unread badge in the nav |
| Settings | Site notice, QQ/WeChat numbers with QR image upload (built-in cropper), change password |

The panel ships with brute-force lockout (5 failures → 15 min), CSRF protection and a one-time entry token.

## Data Files

Shortcut data lives in JSON. On first run the app auto-detects the files either next to the site or one level up:

- `softwares.json` — indexed software & shortcuts
- `pending.json` — pending (not yet indexed) software list

Core structure of `softwares.json`:

```json
{
  "app_hotkeys": [
    {
      "app_name": "Photoshop",
      "platform": "Windows",
      "version": "2024",
      "category": "Design",
      "tags": ["image"],
      "aliases": ["PS", "Ps"],
      "doc_url": "https://helpx.adobe.com/photoshop/keyboard-shortcuts.html",
      "hotkeys": [
        { "keys": "Ctrl+J", "desc": "Duplicate layer", "group": "Layers" }
      ]
    }
  ]
}
```

You can edit everything visually in the admin panel, or batch-edit the JSON directly.

## Project Layout

```
├── site/                  # Web root (can be deployed on its own)
│   ├── index.php          # Front end (home/category/detail/search/favorites/compare/feedback/API)
│   ├── admin.php          # Admin panel
│   ├── lib.php            # Data layer & shared functions
│   ├── sitemap.php        # Dynamic sitemap
│   ├── assets/            # CSS/JS/icons (assets/qr/ is the QR upload dir)
│   └── data/              # Runtime data (auto-created, gitignored)
├── 清单.json              # Main shortcut data (dev copy of softwares.json)
├── 待补充清单.json        # Pending software data
├── tools/                 # Helper scripts (lint / favicon / packaging)
└── docs/                  # Development docs
```

## Security Notes

- The `data/` directory holds the password hash, config, stats and submissions. Apache blocks it via `.htaccess`; Nginx users must add the rule shown above;
- Passwords are stored with `password_hash`; all output is HTML-escaped; every write is CSRF-protected;
- Rate limiting: public API 30 req/min, submissions 5 per 10 min, visitor-log anti-abuse 15 req/min;
- This repository contains **no real runtime data** — QR codes, visitor IPs, submissions and password hashes are all excluded via `.gitignore`.

## Contributing

Found a wrong shortcut, missing software, or want to improve the code? Contributions are welcome:

- **No coding needed**: use the in-site [feedback form](https://xcsy.my-place.us/index.php?p=feedback) or open a [shortcut correction issue](https://github.com/mbc27/QuickKey/issues/new?template=shortcut_correction.yml);
- **Developers**: see [CONTRIBUTING.md](CONTRIBUTING.md) for local setup, pre-commit checks, code conventions and the data format;
- Bugs: please use the [bug report template](https://github.com/mbc27/QuickKey/issues/new?template=bug_report.yml).

## CI

GitHub Actions runs on every push/PR:

- PHP syntax check on 8.1 / 8.2 / 8.3
- JSON validity check for all data files
- Smoke test: boots the site with PHP's built-in server and asserts the front page, search, JSON API and admin login page all render

## License

[MIT](LICENSE)
