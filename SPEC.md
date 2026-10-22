# Build: GenDoc, a Free Quote, Invoice & Credit Note Generator (Laravel)

You are building **GenDoc** (production URL `https://gendoc.owright.dev`), a free, no-login web app that lets anyone create **quotes, invoices and credit notes**, customise them in a right-hand panel, see a **live A4 preview** on the left, and download a pixel-accurate PDF.

Work in the phases at the bottom. **After each phase: run Pint, Larastan and the test suite, summarise what you built, list any assumptions, and wait for my go-ahead.** If something in this spec is ambiguous, ask before guessing.

---

## 1. Tech stack

- **Laravel** (latest stable, 12+), PHP 8.3+ with the `intl` extension
- **Livewire** (latest stable, v3+) and **Alpine.js** for the editor
- **Tailwind CSS v4** via Vite
- **spatie/laravel-pdf v2** for PDF generation, using the **Gotenberg driver** in all environments.
  - Gotenberg runs as its own container (headless Chromium behind an HTTP API), so the PHP image needs no Node.js, Puppeteer or Chromium.
  - Read the Gotenberg URL from `.env` (`GOTENBERG_URL`, e.g. `http://gotenberg:3000`), and keep the driver env-configurable (`LARAVEL_PDF_DRIVER`).
  - Do not hardcode driver-specific calls outside one `PdfRenderer` service class, so the driver can be swapped later.
  - Use `Pdf::fake()` in tests, so tests never call Gotenberg.
- **Docker is used for both development and production.** Section 14 covers the details.
- **brick/money** for all money maths, which gives currency-aware minor units and rounding
- **Pest** for tests, **Laravel Pint** (PSR-12 preset) and **Larastan** (level 6+)
- Fonts self-hosted via `@fontsource/*` npm packages. No Google Fonts CDN.

## 2. Code conventions

- Follow PSR-12 and Laravel conventions throughout. Use **named routes** only, **Form Requests** for validation, and small single-purpose classes.
- Prefer readable code over clever code: early returns, clear names, no god-components.
- Keep all calculation logic in plain PHP classes with no Livewire or HTTP dependencies, so it is fully unit-testable.
- Use typed DTOs (readonly classes) for document data. Do not pass loose arrays between layers.
- No database tables are needed for v1. Nothing about a user's document is stored server-side.

## 3. Core architecture rules

1. **Single source of truth for rendering:** the live preview and the PDF must render the **same Blade view** (`resources/views/documents/render.blade.php`, which includes the chosen template). Never maintain two templates.
2. **Persistence is client-side only:**
   - Drafts are saved to `localStorage` by Alpine, with saves debounced to about 500ms.
   - On page load, Alpine reads the draft and hydrates the Livewire component through a `loadDraft(array $data)` method. This method validates and sanitises the data, and falls back to defaults if it is corrupt.
   - Users can **Export JSON** (download a file) and **Import JSON** (validated with the same rules as the PDF request).
3. **The logo stays client-side during editing:**
   - Store the logo as a data URI in Alpine state and localStorage only. Render it in the preview with `wire:ignore` and `x-bind:src`, so a 2 MB string is not sent on every Livewire round-trip.
   - Send the logo to the server only on PDF generation.
   - Before storing, resize uploaded logos client-side with a canvas to a maximum of 800px on the longest side, re-encoded as PNG or WebP. SVG files are kept as-is but sanitised.
4. **Money is stored as integer minor units** (e.g. pence) and quantity as a decimal string. Never use floats for money.

## 4. Document data model

Each document has the following fields:

- `type`: `quote` | `invoice` | `credit_note`
- `number`: string, auto-suggested and editable (format described below)
- `issue_date`: date
- `due_date`: date. Used on **invoices only**.
- `valid_until`: date. Used on **quotes only**.
- `payment_terms_days`: one of 7, 14, 30 or 60, or `null` for custom. Selecting a value sets `due_date` to `issue_date` plus that many days.
- `original_invoice_number`: string. Used on **credit notes only**, and required for them.
- `reference`: optional free text, such as a PO number
- `currency`: an ISO 4217 code, applied to the whole document. The dropdown lists common currencies first (GBP, EUR, USD, CAD, AUD, …), then all others alphabetically, each shown with its symbol.
- `prices_include_tax`: bool, default `false`
- `from`: the business details
  - `company_name`, `logo` (data URI or null), `address_line_1`, `address_line_2`, `city`, `region`, `postcode`, `country`
  - optional: `email`, `phone`, `website`, `tax_number` (e.g. a VAT number)
- `to`: the customer details. Same address fields as `from`, plus optional `contact_name`, `email` and `tax_number`.
- `tax_rates`: user-defined list of `{ id, name, rate_percent }`. Default: one rate, "VAT 20%". A "No tax" (0%) option is always available.
- `items`: list of `{ id (uuid), description, quantity, unit_price_minor, tax_rate_id, discount: { type: percent|fixed, value } | null }`
- `document_discount`: `{ type: percent|fixed, value } | null`
- `notes`: multiline text
- `payment_details`: multiline, fully customisable free text, shown at the bottom of the document. Placeholder: bank name, account name, sort code, account number, IBAN, payment reference.
- `style`: `{ template: classic|modern|minimal|bold, accent_color: hex, font: <one of 6>, date_format }`
  - `date_format` is one of `d/m/Y` (default), `m/d/Y` or `j M Y`.

Paper size is **A4 only**.

## 5. Calculation rules

Implement these in `App\Documents\DocumentCalculator`, which returns a `DocumentTotals` DTO. Unit-test every rule.

1. **Line gross** = quantity × unit price.
2. **Line discount:**
   - A percentage discount is a percentage of the line gross.
   - A fixed discount is clamped so the line never goes below 0.
3. **Document discount:**
   - Applied after line discounts and before tax.
   - **Allocated proportionally across lines**, so that tax per rate stays correct.
   - A fixed amount is clamped to the discounted subtotal.
   - Put any rounding remainder from the allocation on the largest line, so the totals reconcile exactly.
4. **Tax is calculated per tax-rate group**, on that group's discounted net. Round **once per group**, half-up, to the currency's minor units.
5. **When prices include tax:**
   - Entered prices are treated as gross.
   - Extract net as gross ÷ (1 + rate) per group.
   - The displayed totals must still reconcile: net + tax = gross.
6. **Zero-decimal currencies** (e.g. JPY, KRW) must work correctly. Use brick/money's currency context, not a hardcoded 2 decimal places.
7. **Totals shown on the document:**
   - Subtotal
   - Discounts, shown only if any exist
   - Net
   - One tax line per rate, e.g. "VAT 20%"
   - **Total**
   
   The total is labelled **"Amount due"** on invoices, **"Total"** on quotes and **"Total credit"** on credit notes.
8. **Credit notes show positive amounts** under the "Credit Note" title. Do not use negative numbers.
9. If quantity is 0 or there are no items, the totals are 0. Never divide by zero.

## 6. UI and layout

- **Desktop (≥ lg):** two-column layout.
  - **Left (about 60%):** the live A4 preview.
    - The page is scaled to fit the column width, with a zoom control offering Fit, 75% and 100%.
    - Long documents show multiple A4 pages with visible page breaks.
  - **Right (about 40%):** the customiser panel, which scrolls independently.
- **Mobile (< lg):** a sticky "Edit | Preview" tab toggle.
- **Top bar:**
  - Document type switcher (Quote / Invoice / Credit Note)
  - New document (with confirmation if there are unsaved changes)
  - Import and Export JSON
  - **Download PDF** (primary button)
  - Contextual actions:
    - **"Convert to invoice"** on a quote. This copies everything, sets type to invoice, suggests the next invoice number, sets the issue date to today and the due date from payment terms.
    - **"Create credit note"** on an invoice. This copies the items and pre-fills `original_invoice_number`.
- **Customiser sections** (accordion, one open at a time, state remembered):
  1. **Document:** number, dates, payment terms, reference, currency, original invoice number (credit notes only)
  2. **Your business (From):** company name, logo upload (drag-and-drop, preview, remove, size slider, left/right alignment), address, contact details, tax number
  3. **Customer (To)**
  4. **Line items:**
     - Add, remove and duplicate items; reorder by drag handle, with up/down buttons for keyboard users.
     - Fields per row: description (multiline), quantity, unit price, tax rate select, and an optional line discount.
  5. **Tax & discounts:** manage tax rates, the tax-inclusive toggle, and the document-level discount
  6. **Notes & payment details**
  7. **Style:** template picker (thumbnail cards), accent colour picker with 8 presets plus custom hex, font select (each option previewed in its own font), date format
- **Live updates:** use `wire:model.live.debounce.300ms` for text inputs and immediate updates for selects and toggles. The preview must never flash or reset scroll position.
- **Tip button:**
  - A small, unobtrusive "☕ Support this free tool" button in the page footer.
  - It links to `config('services.tips.url')`, read from the `TIP_URL` env variable, and opens in a new tab with `rel="noopener"`.
  - Hide the button entirely if the URL is not set. It must never appear on the document or the PDF. `TIP_URL` starts empty.
- **Branding:** the display name is **"GenDoc"**, used in the header, the `<title>`, meta tags and Open Graph tags. Add a simple text-based favicon.
- **Analytics (Umami, self-hosted):**
  - Umami runs as a **separate project** on the same server at `analytics.owright.dev`. It is **not** part of this repo.
  - This app only includes Umami's tracking script, configured from env variables (`UMAMI_SCRIPT_URL`, `UMAMI_WEBSITE_ID`). Render nothing if either is unset.
  - Allow the Umami origin in the CSP (`script-src` and `connect-src`).
  - Track two custom events, `pdf_downloaded` and `document_type_changed`, each with the document type as a property. **Never send any document content or user-entered data to analytics.**
  - Umami is cookieless, so no cookie banner is needed.
- **Privacy page** (`/privacy`, named route `privacy`, linked in the footer):
  - Write it in plain English and keep it short.
  - It must explain that:
    - Documents are stored only in the user's browser.
    - Data is sent to the server only to generate a PDF, and is discarded immediately after.
    - Nothing is logged or stored.
    - Analytics are self-hosted, cookieless and anonymous.
    - The only cookie is the essential session cookie.
- **Accessibility:** every input has a label, focus states are visible, the editor is fully keyboard-operable, and colour contrast is AA.

## 7. Templates, fonts and print CSS

- **Four templates** in `resources/views/documents/templates/`: `classic`, `modern`, `minimal` and `bold`.
  - They share partials for the header, addresses, items table, totals, notes and payment details.
  - The accent colour and font are applied through CSS custom properties (`--accent`, `--doc-font`).
- **Six fonts:** Inter, Roboto, Lato, Source Serif 4, Merriweather and Playfair Display.
  - Fonts must work in the PDF without network access. In the PDF render, inline the needed `@font-face` rules with base64 woff2 data, loading only the selected font.
- **A4 print CSS:**
  - `@page { size: A4; margin: 15mm; }`
  - The items table header repeats on each page, and rows do not break internally (`break-inside: avoid`).
  - The totals, notes and payment details blocks stay together where possible.
- **Document labels:**
  - The title is "Quote", "Invoice" or "Credit Note".
  - Date labels: "Valid until" on quotes and "Due date" on invoices.
  - Credit notes show "Credit note for invoice {original_invoice_number}".
- **Empty states:** optional fields that are blank are hidden, not rendered as empty labels.

## 8. Numbering

- Keep a per-type counter in localStorage. The default formats are `QUO-0001`, `INV-0001` and `CN-0001`, and the prefix is editable in the Document section.
- When a new document is created, suggest the next number. The number field is always editable.
- Increment the counter **only after a successful PDF download** of that number.

## 9. PDF endpoint and security

- **Route:** `POST /documents/pdf`, named `documents.pdf`, with the `throttle:pdf` middleware.
  - Define the `pdf` rate limiter in `AppServiceProvider`: 10 requests per minute per IP, with a friendly 429 message shown in the UI.
- **Validation:** `GenerateDocumentPdfRequest` validates the full payload, using the same rule set as JSON import, extracted into a reusable rules class.
  - String max lengths; item count of 200 or fewer; numeric bounds.
  - `currency` must be a valid ISO 4217 code; `accent_color` must match a hex regex; template and font must be in their whitelists.
  - The logo must be a data URI of type PNG, JPEG, WebP or SVG, 2 MB or less once decoded.
- **Controller:** `DocumentPdfController` is a single-action controller.
  - It builds the `DocumentData` DTO, then calls `DocumentCalculator`, then renders `documents.render` through a `PdfRenderer` service.
  - It streams the PDF as a download named `{type}-{number}.pdf`, sanitised for safe use as a filename.
- **Security:**
  - Escape all user text with `{{ }}`. Never use `{!! !!}` on user input.
  - Sanitise SVG logos with `enshrined/svg-sanitize`, and always render logos through `<img src="data:...">`, never inline SVG.
  - Prevent server-side request forgery (SSRF): Gotenberg must not be able to fetch remote URLs.
    - Inline all assets (fonts, CSS and the logo) into the HTML sent to Gotenberg.
    - Attach the Gotenberg container **only** to a Docker network created with `internal: true`, so it has no internet access.
    - Configure Gotenberg's Chromium deny-list flags to refuse non-data URLs.
    - Never publish Gotenberg's port to the host.
  - Set a render timeout of 20 seconds or less.
  - Store nothing on disk and log no document contents.
  - Add security headers: CSP, `X-Content-Type-Options` and `Referrer-Policy`.
- **Error handling:**
  - If PDF generation fails, show a toast in the UI and log the error with no payload.
  - If a JSON import is invalid, show the field errors and keep the existing draft untouched.
  - If localStorage is full or unavailable, warn the user once and keep working in memory.

## 10. Testing

Write Pest tests covering:

- **Calculator:** line discounts, document discount allocation, multiple tax rates, tax-inclusive pricing, zero-decimal currency, the rounding reconciliation rule, credit note labels, and empty documents.
- **Validation:** valid and invalid payloads, an oversized logo, a malicious SVG, a bad currency code, and too many items.
- **PDF endpoint:**
  - A successful download, tested with `Pdf::fake()` and the package's assertions
  - Throttling returns 429
  - The response has the correct filename
- **Livewire component:** adding and removing items updates totals; changing the type swaps the correct date label; converting a quote to an invoice; creating a credit note from an invoice.
- **Template rendering:** each of the 4 templates renders for each of the 3 document types.

## 11. Non-goals for v1

Do **not** build any of the following:

- Accounts or login
- A database for documents
- Emailing documents
- Online payments
- Recurring invoices
- A client address book
- Multiple languages
- Paper sizes other than A4
- Ads or watermarks

## 12. Build phases

1. **Scaffold:** set up Laravel, Livewire, Tailwind, Pest, Pint, Larastan and spatie/laravel-pdf, plus a CI script (`composer test`).
   - Create the development Docker setup from section 14, including the Gotenberg container, so `docker compose up` gives a working app.
   - Write a README with a local setup section.
2. **Domain:** build the DTOs, `DocumentCalculator` and validation rules class, with the full test suite for calculations and validation.
3. **Editor and preview:** build the Livewire component with all customiser sections, the live preview using the `classic` template, the responsive layout and the mobile tabs.
4. **Templates and styling:** build the remaining 3 templates, the accent colour, the 6 fonts, the date formats and the print CSS with page breaks.
5. **PDF:** build the endpoint, Form Request, renderer service, embedded fonts, network blocking, throttling and error toasts.
6. **Persistence and workflows:** add the localStorage drafts, JSON import/export, numbering, quote-to-invoice conversion and create-credit-note action.
7. **Polish:**
   - The tip button and footer
   - An SEO-friendly landing copy above the fold, plus meta tags and Open Graph tags
   - A 404 page
   - The privacy page
   - Security headers
   - A Lighthouse pass, targeting 90+ for performance and accessibility
   - The app needs no database. Use the `file` or `cookie` session driver and the `file` cache driver.
8. **Production Docker and deployment:** build the production setup from section 14, the GitHub Actions workflow, and a `DEPLOYMENT.md`.
   - `DEPLOYMENT.md` is written for someone deploying to their own server for the first time. It must explain each step and the reason for it, not just list commands.
   - It must cover every required environment variable: `APP_KEY`, `APP_URL`, `LARAVEL_PDF_DRIVER`, `GOTENBERG_URL` and `TIP_URL`.

## 13. Definition of done

- I can create each of the 3 document types, see every change reflected in the preview within about 300ms, and download a PDF that visually matches the preview.
- The totals reconcile exactly in every test case.
- Refreshing the page restores my draft, including the logo.
- Pint, Larastan and all tests pass.

## 14. Docker setup

**Development**

- A `compose.yaml` with three services:
  - `app`: PHP-FPM, with the source code bind-mounted
  - `web`: Nginx
  - `gotenberg`: pinned to an exact image version, not `latest`
- Run the Vite dev server either as a `node` service or on the host. Choose one and document it.
- Xdebug is optional and off by default.
- **The developer is on Windows, using Docker Desktop with the WSL2 backend.**
  - The project lives inside the WSL2 Linux filesystem (e.g. `~/code/gendoc`), **not** under `/mnt/c`, for file-watching speed. Claude Code also runs inside WSL2.
  - Add a `.gitattributes` file with `* text=auto eol=lf` and `*.sh text eol=lf`, so shell scripts such as the entrypoint never get Windows (CRLF) line endings. CRLF breaks them in Linux containers.
  - Configure the Vite dev server for Docker on WSL2:
    - `server.host: '0.0.0.0'`
    - Set `hmr.host` so hot reload works from the Windows browser.
    - Enable polling only if file watching proves unreliable.
  - Bind-mounted files must be owned by the host user, not root. Set the container's user and group IDs through build args (`UID` and `GID`).
  - Include a WSL2 section in the README: install WSL2 and Ubuntu, enable WSL integration in Docker Desktop, clone the project inside WSL, and open it with `code .` (VS Code WSL extension).

**Production**

- A **multi-stage `Dockerfile`**:
  1. A Composer stage (`--no-dev`, optimised autoloader)
  2. A Node stage that runs `npm run build`
  3. A slim PHP runtime stage with OPcache enabled
  
  The runtime image contains no dev tools, no Node and no source maps. It runs as a **non-root user**.
- The code is baked into the image, with no bind mounts. Secrets come from a `.env` file on the server and are **never** baked into the image.
- `compose.prod.yaml` with these services:
  - `app` and `web` (or a single-process alternative such as FrankenPHP; document the choice and why)
  - `gotenberg` on an `internal: true` network only
  - A `scheduler` service if any scheduled tasks are needed
- **Container settings:**
  - Every service has a `restart: unless-stopped` policy and a healthcheck.
  - Docker log rotation is configured with the `max-size` and `max-file` options.
- **No container publishes ports.** The app joins an external Docker network shared with a reverse proxy, **Caddy**, which runs as its own compose project on the server, handles HTTPS automatically and routes by domain.
  - Document why: Docker-published ports bypass the `ufw` firewall.
  - Provide an example `Caddyfile` entry for this app.
- **The production URL is `https://gendoc.owright.dev`.**
  - Set `APP_URL` to this URL.
  - Leave `SESSION_DOMAIN` unset (`null`), so cookies stay scoped to this subdomain and are never shared with other `*.owright.dev` projects.
  - Use `gendoc` as the compose project name and in container and network names, so it doesn't clash with other projects on the same server.
  - Configure trusted proxies so Laravel generates correct `https` URLs and sees real client IPs behind Caddy. The PDF rate limiter depends on real client IPs.
- On container start, run `php artisan config:cache`, `route:cache`, `view:cache` and `event:cache` through an entrypoint script.
- **CI/CD with GitHub Actions:**
  - On every push, run tests, Pint and Larastan.
  - On `main`, also build the production image, tag it with the commit SHA and push it to GitHub Container Registry (GHCR).
  - Deploy is a separate manual-approval job that connects over SSH and runs `docker compose pull && docker compose up -d`.
  - Document how to roll back to a previous SHA tag.
