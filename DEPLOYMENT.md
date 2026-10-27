# Deploying GenDoc

This guide takes you from an empty Linux server to GenDoc running at <https://gendoc.owright.dev>, with every push to `main` deployable at the press of a button. It assumes you have never deployed a Docker app before, so each step explains *why* as well as *what*.

If you only want the rollback steps, jump to [Rolling back](#rolling-back).

## Contents

1. [How it fits together](#1-how-it-fits-together)
2. [What you need](#2-what-you-need)
3. [Prepare the server](#3-prepare-the-server)
4. [Set up Caddy (once per server)](#4-set-up-caddy-once-per-server)
5. [Set up the GenDoc folder](#5-set-up-the-gendoc-folder)
6. [Environment variables](#6-environment-variables)
7. [Let the server pull images from GitHub](#7-let-the-server-pull-images-from-github)
8. [Set up GitHub Actions](#8-set-up-github-actions)
9. [Your first deploy](#9-your-first-deploy)
10. [Everyday deploys](#10-everyday-deploys)
11. [Rolling back](#rolling-back)
12. [Logs and troubleshooting](#12-logs-and-troubleshooting)

---

## 1. How it fits together

```
 Browser ──HTTPS──▶ Caddy ──▶ web (nginx) ──▶ app (PHP-FPM) ──▶ gotenberg (Chromium)
                   ports 80/443   "caddy" network   "internal" network   "pdf" network
                   (own project)  ─────────────────── GenDoc project ───────────────────
```

- **Caddy** is a reverse proxy that runs as its *own* Docker Compose project. It is the only thing on the server that listens on the internet (ports 80 and 443). It gets and renews HTTPS certificates automatically, then forwards each request to the right app by domain name. One Caddy can serve many projects.
- **GenDoc** is three containers in a Compose project named `gendoc`:
  - `web`: nginx. Serves the CSS/JS/images directly and passes everything else to `app`.
  - `app`: the Laravel app on PHP-FPM. The code is baked into the image.
  - `gotenberg`: headless Chromium that turns HTML into PDFs.
- **None of GenDoc's containers publishes a port.** Caddy reaches `web` over a shared Docker network called `caddy`. `app` and `gotenberg` are on networks marked `internal`, which have no route to the internet at all, so even a bug in the app or in Chromium can't be used to make outbound requests.
- **Images are built by GitHub Actions**, tagged with the commit SHA, and stored in the GitHub Container Registry (GHCR). The server never builds anything; it just pulls a specific SHA and runs it. That makes rolling back as simple as running an older SHA.

> **Why not publish ports from the app containers?** On Linux, Docker writes its own firewall rules for published ports, and those rules take effect *before* `ufw` gets a say. A container started with `ports: ["8080:80"]` is reachable from the whole internet even if `ufw` says port 8080 is closed. Keeping every app on a private network behind Caddy means your firewall rules are the ones that actually apply.

## 2. What you need

- A Linux server (these steps use **Ubuntu 24.04**) with at least 2 GB of RAM. Chromium is the hungriest part.
- SSH access to it as a user with `sudo`.
- The domain `gendoc.owright.dev` (or your own) with a DNS **A record** pointing at the server's public IPv4 address (and an **AAAA** record for IPv6, if the server has one). Caddy can't get a certificate until DNS points at the server, so set this up first; it can take a few minutes to take effect.
- Admin access to the GitHub repository.

## 3. Prepare the server

Log in over SSH, then:

### 3.1 Install Docker

Use Docker's official packages rather than Ubuntu's older `docker.io` package, because the Compose files need a recent Docker Compose (v2.24 or newer).

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

docker compose version   # should print v2.24 or newer
```

### 3.2 Create a deploy user

GitHub Actions will log in over SSH to deploy. Give it its own user rather than your personal account, so you can revoke it independently.

```bash
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG docker deploy
```

> Membership of the `docker` group is effectively root access to the server (anyone who can run containers can mount the host's filesystem). That's why this user gets a dedicated SSH key that only GitHub has, and no password.

### 3.3 Turn on the firewall

Allow SSH first, so you don't lock yourself out, then web traffic:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 443/udp   # HTTP/3
sudo ufw enable
sudo ufw status
```

Port 80 must stay open: Caddy uses it to prove to Let's Encrypt that you own the domain, and to redirect plain HTTP visitors to HTTPS.

## 4. Set up Caddy (once per server)

Skip this section if the server already runs Caddy for other projects; just add GenDoc's site block (step 4.3) and reload it (step 4.4).

### 4.1 Create the shared network

```bash
docker network create caddy
```

Both the Caddy project and the GenDoc project join this network. Creating it outside either project means neither one "owns" it, so you can restart or remove either project without breaking the other.

### 4.2 Create the Caddy project

```bash
sudo mkdir -p /opt/caddy && sudo chown deploy:deploy /opt/caddy
cd /opt/caddy
```

Create `/opt/caddy/compose.yaml`:

```yaml
name: caddy

services:
  caddy:
    image: caddy:2.11.7-alpine
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
      - "443:443/udp"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - data:/data       # certificates: keep these, or you'll hit Let's Encrypt rate limits
      - config:/config
    networks:
      - caddy
    logging:
      driver: json-file
      options:
        max-size: "10m"
        max-file: "3"

networks:
  caddy:
    external: true

volumes:
  data:
  config:
```

Caddy is the one container that publishes ports, deliberately: it is the front door.

### 4.3 Add GenDoc's site

Create `/opt/caddy/Caddyfile` (or add to it) with the block from [`docker/caddy/Caddyfile.example`](docker/caddy/Caddyfile.example):

```caddyfile
gendoc.owright.dev {
	encode zstd gzip
	reverse_proxy gendoc-web:8080
}
```

`gendoc-web` is a network alias that GenDoc's `web` container registers on the `caddy` network. It's more specific than `web`, which other projects might also use. Caddy sends the visitor's real IP in the `X-Forwarded-For` header and ignores any such header the visitor sends, so GenDoc's rate limiter can't be fooled.

### 4.4 Start (or reload) Caddy

```bash
docker compose up -d
# after editing the Caddyfile later:
docker compose exec caddy caddy reload --config /etc/caddy/Caddyfile
```

Until GenDoc is running, visiting the site gives a `502 Bad Gateway`. That's expected.

## 5. Set up the GenDoc folder

```bash
sudo mkdir -p /opt/gendoc && sudo chown deploy:deploy /opt/gendoc
```

This folder holds just two files:

- `compose.yaml`: a copy of [`compose.prod.yaml`](compose.prod.yaml) from the repository. Every deploy uploads the latest copy, so you only need to place it by hand for the very first deploy if you want to start the app before setting up GitHub Actions:
  ```bash
  curl -fsSL https://raw.githubusercontent.com/riggy889/gendoc/main/compose.prod.yaml -o /opt/gendoc/compose.yaml
  ```
- `.env`: the app's settings and secrets (next section). It lives only on the server and is never built into an image, so you can rotate a secret without rebuilding anything.

Docker Compose reads `.env` in two ways: to fill in `${...}` values in `compose.yaml` (such as `IMAGE_TAG`), and, through `env_file: .env`, as environment variables for the `app` container.

## 6. Environment variables

Create `/opt/gendoc/.env` and restrict it to the deploy user, because it contains the app key:

```bash
cd /opt/gendoc
touch .env && chmod 600 .env
nano .env
```

A complete production `.env`:

```dotenv
APP_NAME=GenDoc
APP_KEY=base64:REPLACE_ME
APP_URL=https://gendoc.owright.dev

LARAVEL_PDF_DRIVER=gotenberg
GOTENBERG_URL=http://gotenberg:3000

TIP_URL=

UMAMI_SCRIPT_URL=
UMAMI_WEBSITE_ID=

# The commit SHA to run. Deploys update this line automatically.
IMAGE_TAG=REPLACE_ME
```

### Required

| Variable | What it does | What to set |
|---|---|---|
| `APP_KEY` | Laravel's encryption key. It signs and encrypts the session cookie and CSRF tokens. If it leaks, someone could forge sessions; if it changes, everyone's open tab shows "page expired" once (drafts are safe, they're in the browser). | Generate once with `echo "base64:$(openssl rand -base64 32)"` and keep it the same across deploys. |
| `APP_URL` | The site's public address. Laravel uses it for absolute links such as the canonical and Open Graph URLs. | `https://gendoc.owright.dev` (with `https`, and no trailing slash). |
| `LARAVEL_PDF_DRIVER` | Which PDF engine spatie/laravel-pdf uses. | `gotenberg`. It's also the default if you leave it out. |
| `GOTENBERG_URL` | Where the app finds Gotenberg. `gotenberg` is the service name on the private `pdf` network. | `http://gotenberg:3000`. It's also the default. |
| `TIP_URL` | The link behind the "☕ Support this free tool" footer button. | Your Ko-fi/Buy Me a Coffee/etc. URL, or leave **empty to hide the button**. |
| `IMAGE_TAG` | The full commit SHA whose images to run. | Set automatically by every deploy. For a manual first start, use the SHA of the latest successful build (see [§9](#9-your-first-deploy)). |

### Optional

| Variable | Default | Notes |
|---|---|---|
| `UMAMI_SCRIPT_URL`, `UMAMI_WEBSITE_ID` | empty | Self-hosted Umami analytics, e.g. `https://analytics.owright.dev/script.js` and the website ID from Umami. Both must be set or nothing is rendered. The Content Security Policy allows the script's origin automatically. |
| `APP_NAME` | `GenDoc` | Shown in the header, titles and meta tags. |
| `GENDOC_IMAGE` | `ghcr.io/riggy889/gendoc` | Change only if you build the images under a different GitHub account. |
| `GOTENBERG_TIMEOUT` | `20` | Seconds to wait for a PDF before giving up. Keep it at 20 or less. |

Settings you **don't** need, and why:

- `APP_ENV=production`, `APP_DEBUG=false` and `LOG_CHANNEL=stderr` are set by `compose.yaml`. Logs go to Docker, which rotates them (§12). Never set `APP_DEBUG=true` in production: error pages would show internals.
- `SESSION_DRIVER=file` and `CACHE_STORE=file` are also defaults. GenDoc has no database.
- **Leave `SESSION_DOMAIN` unset.** Then the session cookie only belongs to `gendoc.owright.dev`. Setting it to `.owright.dev` would share the cookie with every other project on that domain.

The config is cached each time the `app` container starts (see `docker/prod/entrypoint.sh`), so after editing `.env` run `docker compose up -d` to recreate `app` with the new values. A plain restart isn't enough when you've changed `.env`, because Compose only re-reads it when the container is recreated.

## 7. Let the server pull images from GitHub

The images live at `ghcr.io/riggy889/gendoc-app` and `ghcr.io/riggy889/gendoc-web`. The server needs permission to download them. Choose one:

- **Make the packages public** (simplest; there are no secrets in the images). After the first successful build, open the repository on GitHub → **Packages** → each package → **Package settings** → **Change visibility** → Public.
- **Or keep them private** and log the server in with a token. On GitHub, create a *classic* personal access token with only the `read:packages` scope, then as the `deploy` user on the server:
  ```bash
  echo "YOUR_TOKEN" | docker login ghcr.io -u YOUR_GITHUB_USERNAME --password-stdin
  ```
  Docker remembers this login in `~deploy/.docker/config.json`.

## 8. Set up GitHub Actions

The workflow [`.github/workflows/ci.yml`](.github/workflows/ci.yml) has three jobs:

1. **Tests, Pint and Larastan**, on every push to any branch.
2. **Build and push images**, only for pushes to `main`, and only if the tests pass. Both images are tagged with the commit SHA (and `latest`) and pushed to GHCR.
3. **Deploy to production**, which waits for a person to approve it, then connects to the server over SSH and runs `docker compose pull && docker compose up -d`.

### 8.1 Create a deploy SSH key

On your own computer (not the server):

```bash
ssh-keygen -t ed25519 -C "github-actions-gendoc" -f gendoc_deploy -N ""
```

This makes `gendoc_deploy` (private) and `gendoc_deploy.pub` (public). Put the public key on the server:

```bash
# on the server
sudo mkdir -p /home/deploy/.ssh
sudo tee -a /home/deploy/.ssh/authorized_keys < gendoc_deploy.pub   # paste the .pub contents
sudo chown -R deploy:deploy /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh && sudo chmod 600 /home/deploy/.ssh/authorized_keys
```

Then record the server's identity, so GitHub can tell it's talking to the real server and not an impostor:

```bash
ssh-keyscan -t ed25519 gendoc.owright.dev
```

Check the fingerprint matches what the server reports (`ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub` on the server) before trusting it.

### 8.2 Add the production environment

In the repository: **Settings → Environments → New environment**, name it `production`, then:

- Tick **Required reviewers** and add yourself. This is the manual approval step: every deploy pauses until you click **Approve**. (Required reviewers are free on public repositories; private repositories need a paid GitHub plan.)
- Optionally, under **Deployment branches**, allow only `main`.
- Add these **environment secrets**:

  | Secret | Value |
  |---|---|
  | `DEPLOY_SSH_KEY` | The whole contents of the private key file `gendoc_deploy`. |
  | `DEPLOY_KNOWN_HOSTS` | The line printed by `ssh-keyscan` above. |

- And these **environment variables** (not secret):

  | Variable | Value |
  |---|---|
  | `DEPLOY_HOST` | `gendoc.owright.dev` (or the server's IP) |
  | `DEPLOY_USER` | `deploy` |
  | `DEPLOY_PATH` | `/opt/gendoc` (the default if unset) |
  | `DEPLOY_PORT` | Only if SSH isn't on port 22 |

Then delete the private key from your computer; GitHub has the only copy it needs.

The build job pushes to GHCR using the token GitHub gives every workflow run, so it needs no extra secrets.

## 9. Your first deploy

1. Push to `main` (or merge a pull request into it).
2. Open the repository's **Actions** tab. When the tests and build finish, the deploy job shows **Waiting for review**. Click **Review deployments → production → Approve and deploy**.
3. The deploy job uploads `compose.yaml`, sets `IMAGE_TAG` in `.env` to the commit SHA, pulls the images and starts the containers. `docker compose up -d --wait` waits for every healthcheck to pass, so if something is wrong the job fails instead of reporting success.
4. If you went the private-package route in §7 and this is the first build, the packages were just created; check the server is logged in to GHCR before approving.

Check it on the server:

```bash
cd /opt/gendoc
docker compose ps            # all three should say "healthy"
curl -I https://gendoc.owright.dev
```

Then open the site, create a document and download a PDF.

## 10. Everyday deploys

Merge to `main`, wait for the build, approve the deploy. The old containers keep serving until the new ones are created; there is a few seconds' gap while `app` restarts and caches its config.

What survives a deploy:

- **Users' documents:** always. They are stored in each visitor's browser, never on the server.
- **Sessions:** kept in the `gendoc_sessions` Docker volume, so open tabs carry on working.

Unused GenDoc images older than 30 days are removed from the server after each deploy. Every SHA ever built stays in GHCR, so you can still roll back to any of them.

## Rolling back

Every build is kept in GHCR under its commit SHA, so rolling back means "run an older SHA".

**From GitHub (easiest):** open **Actions**, find the last good run on `main`, open its **Deploy to production** job and click **Re-run jobs**. It redeploys that run's SHA (and its version of `compose.yaml`), after approval. GitHub only allows re-running runs from the last 30 days; for anything older, use the server steps below.

**From the server:**

1. Find the SHA to go back to: the commit list on GitHub, the run history in the **Actions** tab, or the package's versions in GHCR.
2. Point `.env` at it and restart:
   ```bash
   cd /opt/gendoc
   sed -i 's/^IMAGE_TAG=.*/IMAGE_TAG=<full-commit-sha>/' .env
   docker compose pull
   docker compose up -d --wait
   ```

Rolling back doesn't touch the server's `compose.yaml`. If the bad deploy also changed `compose.prod.yaml`, prefer re-running the old deploy job from GitHub, which restores both.

The next approved deploy from `main` moves `IMAGE_TAG` forward again as normal.

## 12. Logs and troubleshooting

Every container logs to Docker, which keeps at most three 10 MB files per container and deletes the oldest, so logs can't fill the disk.

```bash
cd /opt/gendoc
docker compose ps                    # health of each container
docker compose logs -f app           # Laravel and PHP errors
docker compose logs -f web           # requests
docker compose logs -f gotenberg     # PDF rendering
docker compose exec app php artisan about   # check the app's view of its config
```

The app never logs document contents. A failed PDF is logged as `PDF generation failed` with the error type and message only.

| Symptom | Likely cause |
|---|---|
| `502 Bad Gateway` from Caddy | GenDoc isn't running or isn't healthy (`docker compose ps`), or `web` isn't on the `caddy` network (`docker network inspect caddy` should list `gendoc-web-1`). |
| Caddy can't get a certificate | DNS doesn't point at the server yet, or ports 80/443 are blocked. See `docker compose logs caddy` in `/opt/caddy`. |
| `docker compose pull` says "denied" or "unauthorized" | The server can't read the GHCR packages; see [§7](#7-let-the-server-pull-images-from-github). |
| `required variable IMAGE_TAG is missing a value` | `.env` has no `IMAGE_TAG` line yet. Add one, or let a deploy add it. |
| "This page has expired" in the editor | `APP_KEY` changed, or the sessions volume was removed. Refreshing fixes it, and drafts are kept. |
| PDFs fail with "Sorry, we couldn't create your PDF" | Check `docker compose logs gotenberg`. Gotenberg starts Chromium when it boots and only reports healthy once Chromium is ready, so a restart (`docker compose restart gotenberg`) clears most problems. Lines saying `CONNECT blocked for 'accounts.google.com'` are normal: Chromium trying to phone home and being refused. |
| Changes to `.env` don't take effect | Recreate the container with `docker compose up -d` (a restart keeps the old environment). |

### Security notes

- No GenDoc container publishes a port, and `app` and `gotenberg` have no internet access.
- The `app` container runs as the unprivileged `www-data` user, with the code read-only. Only `storage/` and `bootstrap/cache/` are writable. `web` runs nginx as a non-root user too.
- Secrets exist only in `/opt/gendoc/.env` on the server and in GitHub's encrypted secrets, never in an image or the repository.
- Laravel trusts `X-Forwarded-*` headers only from private Docker network addresses, which only Caddy and nginx can use, because no container publishes a port. If you ever publish GenDoc's ports directly, revisit `trustProxies` in `bootstrap/app.php`.
