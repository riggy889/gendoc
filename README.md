# GenDoc

A free, no-login web app for creating quotes, invoices and credit notes, with a live A4 preview and pixel-accurate PDF download.

Production: <https://gendoc.owright.dev>

## Stack

- Laravel 13, PHP 8.4 (with `intl`), Livewire 4, Alpine.js
- Tailwind CSS v4 via Vite
- PDF rendering: `spatie/laravel-pdf` with the Gotenberg driver (headless Chromium in its own container)
- `brick/money` for money maths
- Pest, Pint (`laravel` preset) and Larastan (level 8)

Everything runs in Docker. You do not need PHP, Composer or Node installed on your machine.

## Local setup

Prerequisites: Docker (Docker Desktop with the WSL2 backend on Windows; see [Windows / WSL2](#windows--wsl2) below).

```bash
git clone <repo-url> gendoc && cd gendoc
cp .env.example .env

# If `id -u` / `id -g` are not 1000, set UID and GID in .env to match.
docker compose up -d --build

docker compose exec app composer install
docker compose exec app php artisan key:generate
```

Open <http://localhost:8080>. Edits to Blade, CSS and JS hot-reload in the browser.

### Services

| Service     | What it does                                        | Reachable at                       |
|-------------|-----------------------------------------------------|------------------------------------|
| `app`       | PHP-FPM, source code bind-mounted                   | (internal)                         |
| `web`       | Nginx in front of `app`                             | <http://localhost:8080>            |
| `node`      | Vite dev server (runs `npm install && npm run dev`) | <http://localhost:5173> (assets/HMR) |
| `gotenberg` | Renders HTML to PDF                                 | `http://gotenberg:3000` from `app` only |

**Vite runs in the `node` container**, not on your host, so you don't need Node installed. On the first start it takes a moment to run `npm install`; follow along with `docker compose logs -f node`.

**Gotenberg is isolated.** It sits only on the `pdf` Docker network, which is created with `internal: true`, so it has no internet access, and it publishes no ports. Its Chromium deny-list also refuses every URL except `data:` URIs and its own working files. All fonts, CSS and images are inlined into the HTML before it is sent for rendering.

### Everyday commands

```bash
docker compose exec app composer test       # Pint (check) + Larastan + Pest, the same as CI
docker compose exec app composer lint:fix   # auto-fix code style
docker compose exec app composer analyse    # Larastan only
docker compose exec app composer test:unit  # Pest only
docker compose exec app php artisan <command>
docker compose exec node npm <command>
```

### Ports

To change the published ports, set `APP_PORT` (default `8080`) and `VITE_PORT` (default `5173`) in `.env`. If you change `APP_PORT`, update `APP_URL` to match.

### Xdebug (optional, off by default)

```bash
# in .env
INSTALL_XDEBUG=true
XDEBUG_MODE=debug

docker compose up -d --build app
```

Xdebug connects to `host.docker.internal:9003` and only starts when a trigger (e.g. the `XDEBUG_TRIGGER` cookie or query parameter) is present.

### File watching

If hot reload doesn't pick up changes, set `VITE_USE_POLLING=true` in `.env` and run `docker compose up -d node`. Polling is off by default because it uses more CPU.

## Windows / WSL2

GenDoc is developed on Windows using Docker Desktop with the WSL2 backend. The project must live **inside the WSL2 Linux filesystem** (for example `~/code/gendoc`), **not** under `/mnt/c/...`. Files under `/mnt/c` are slow to access from containers and don't trigger file-change events, so hot reload breaks.

1. **Install WSL2 and Ubuntu.** In an administrator PowerShell, run:
   ```powershell
   wsl --install -d Ubuntu
   ```
   Restart when prompted, then open "Ubuntu" from the Start menu and create your Linux user.
2. **Install Docker Desktop** and, in *Settings → Resources → WSL integration*, enable integration for your Ubuntu distro. `docker` then works from inside the Ubuntu terminal.
3. **Clone the project inside WSL**, from the Ubuntu terminal:
   ```bash
   mkdir -p ~/code && cd ~/code
   git clone <repo-url> gendoc && cd gendoc
   ```
4. **Open it in VS Code** with the [WSL extension](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-wsl) installed:
   ```bash
   code .
   ```
5. Follow [Local setup](#local-setup) above. Browse to <http://localhost:8080> from Windows as normal; Docker Desktop forwards the ports.

Notes:

- `.gitattributes` forces LF line endings. Windows-style CRLF endings break shell scripts inside Linux containers.
- The `app` and `node` containers run as your WSL user's UID/GID (set in `.env`), so files they create (`vendor/`, `node_modules/`, caches) stay owned by you rather than by root.
- If a Windows-installed `php`, `composer` or `node` shows up on your WSL `PATH`, ignore it; use the containers as shown above.
