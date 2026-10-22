import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { paginate } from './paginator';

// A4 is 210mm wide; at 96 CSS pixels per inch that's ~793.7px.
const A4_WIDTH_PX = (210 / 25.4) * 96;
const PREVIEW_GUTTER_PX = 32;

const LOGO_MAX_SIDE = 800;
const LOGO_MAX_BYTES = 2 * 1024 * 1024;
const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];

const SECTION_KEY = 'gendoc.section';
const DEFAULT_SECTION = 'document'; // rendered open by the server, see sections/document.blade.php
const DRAFT_KEY = 'gendoc.draft';
const NUMBERING_KEY = 'gendoc.numbering';
const SAVE_DELAY_MS = 500;

const IMPORT_MAX_BYTES = 5 * 1024 * 1024;
const EXPORT_VERSION = 1;
const LOGO_DATA_URI = /^data:image\/(png|jpeg|webp|svg\+xml);base64,[A-Za-z0-9+/]+=*$/;

const TOAST_MS = 8000;
const MAX_ERROR_DETAILS = 5;

function readSetting(key, fallback) {
    try {
        return window.localStorage.getItem(key) ?? fallback;
    } catch {
        return fallback;
    }
}

/**
 * @returns {boolean} false if storage is unavailable (private mode, blocked or full)
 */
function writeSetting(key, value) {
    try {
        window.localStorage.setItem(key, value);

        return true;
    } catch {
        return false;
    }
}

function readJson(key) {
    try {
        return JSON.parse(readSetting(key, 'null'));
    } catch {
        return null;
    }
}

/**
 * Client-side state for the editor: mobile tabs, the accordion, preview zoom and pages, the logo,
 * and the draft and numbering saved in localStorage.
 *
 * The logo is held here rather than in Livewire so a large data URI isn't sent to the server on
 * every update. It is only sent when generating a PDF or importing a file.
 */
export default () => {
    // Not reactive data, so kept out of Alpine state: the paginator's undo, and the pending save.
    let undoPagination = () => {};
    let saveTimer = null;

    return {
        tab: 'edit',
        openSection: readSetting(SECTION_KEY, DEFAULT_SECTION),
        zoom: 'fit',
        scale: 1,
        pages: 1,
        logo: null,
        logoError: null,
        draggingLogo: false,
        downloading: false,
        toasts: [],
        restored: false,
        storageWarned: false,

        init() {
            const viewport = this.$refs.previewViewport;

            if (viewport) {
                new ResizeObserver(() => this.updateScale()).observe(viewport);
            }

            this.$watch('zoom', () => this.updateScale());
            this.$watch('tab', () => this.$nextTick(() => {
                this.updateScale();
                this.paginate();
            }));
            this.$watch('logo', () => this.$nextTick(() => this.paginate()));

            // Spacers must be gone before Livewire diffs the preview, then are worked out afresh.
            Livewire.hook('morph', ({ component }) => {
                if (component.el === this.$root) {
                    undoPagination();
                    undoPagination = () => {};
                }
            });
            Livewire.hook('morphed', ({ component }) => {
                if (component.el === this.$root) {
                    this.paginate();
                }
            });

            // Fonts and the logo change the layout once they load.
            document.fonts.addEventListener('loadingdone', () => this.paginate());
            this.$refs.sheet?.addEventListener('load', () => this.paginate(), true);
            this.$nextTick(() => this.paginate());

            // Save the draft shortly after each change, and straight away if the page is closed.
            Livewire.hook('commit', ({ component, succeed }) => {
                if (component.el === this.$root) {
                    succeed(() => this.queueSave());
                }
            });
            this.$watch('logo', () => this.queueSave());
            window.addEventListener('pagehide', () => this.saveNow());

            this.restore();
        },

        /**
         * Load the saved draft and numbering into the editor. Until this finishes nothing is saved,
         * so the blank starting document can't overwrite a stored draft.
         */
        async restore() {
            const draft = readJson(DRAFT_KEY);
            const document = draft?.document && typeof draft.document === 'object' ? draft.document : null;
            const logo = document?.from?.logo;

            this.logo = typeof logo === 'string' && LOGO_DATA_URI.test(logo) ? logo : null;

            try {
                await this.$wire.restore(document ? withoutLogo(document) : null, readJson(NUMBERING_KEY) ?? {}, draft?.dirty === true);
            } finally {
                this.restored = true;
            }
        },

        queueSave() {
            if (!this.restored) {
                return;
            }

            clearTimeout(saveTimer);
            saveTimer = setTimeout(() => this.saveNow(), SAVE_DELAY_MS);
        },

        saveNow() {
            clearTimeout(saveTimer);

            if (!this.restored) {
                return;
            }

            // Numbering first: it's small, so it still saves if a large logo fills the storage.
            const saved = writeSetting(NUMBERING_KEY, JSON.stringify(this.$wire.numbering))
                && writeSetting(DRAFT_KEY, JSON.stringify({ document: this.currentDocument(), dirty: this.$wire.dirty }));

            if (!saved && !this.storageWarned) {
                this.storageWarned = true;
                this.toast("Your browser isn't letting GenDoc save drafts, so your work will be lost if you close this page. Export it to keep a copy.");
            }
        },

        /**
         * A plain copy of the document with the logo added back in.
         */
        currentDocument() {
            // $wire state is a reactive proxy, which structuredClone can't copy.
            const document = JSON.parse(JSON.stringify(this.$wire.document));
            document.from.logo = this.logo;

            return document;
        },

        paginate() {
            const sheet = this.$refs.sheet;
            const article = sheet?.querySelector(':scope > .doc');

            undoPagination();
            undoPagination = () => {};

            // Skip while the preview is hidden (the mobile edit tab); it is laid out when shown.
            if (!article || sheet.getBoundingClientRect().width === 0) {
                return;
            }

            const result = paginate(article, sheet.getBoundingClientRect().width / A4_WIDTH_PX);
            this.pages = result.pages;
            undoPagination = result.undo;
        },

        toggleSection(id) {
            this.openSection = this.openSection === id ? '' : id;
            writeSetting(SECTION_KEY, this.openSection);
        },

        updateScale() {
            if (this.zoom !== 'fit') {
                this.scale = this.zoom;

                return;
            }

            const available = (this.$refs.previewViewport?.clientWidth ?? A4_WIDTH_PX) - PREVIEW_GUTTER_PX;
            this.scale = Math.max(0.2, Math.min(1, available / A4_WIDTH_PX));
        },

        async setLogo(file) {
            this.logoError = null;

            if (!file) {
                return;
            }

            if (!LOGO_TYPES.includes(file.type)) {
                this.logoError = 'Choose a PNG, JPEG, WebP or SVG image.';

                return;
            }

            try {
                const dataUri = file.type === 'image/svg+xml' ? await sanitisedSvg(file) : await resizedRaster(file);

                if (dataUriBytes(dataUri) > LOGO_MAX_BYTES) {
                    this.logoError = 'That logo is still over 2 MB after resizing. Try a smaller image.';

                    return;
                }

                this.logo = dataUri;
            } catch {
                this.logoError = "That file couldn't be read as an image.";
            }
        },

        dropLogo(event) {
            this.draggingLogo = false;
            this.setLogo(event.dataTransfer?.files?.[0]);
        },

        removeLogo() {
            this.logo = null;
            this.logoError = null;
        },

        /**
         * Send the document, with the logo from Alpine state, to the server and save the PDF.
         */
        async downloadPdf(url) {
            if (this.downloading) {
                return;
            }

            this.downloading = true;

            try {
                // Flush any debounced edits so the PDF matches what's on screen.
                await this.$wire.$commit();

                const payload = this.currentDocument();
                const response = await postJson(url, payload);

                if (!response.ok) {
                    this.toast(...(await errorMessage(response, 'Please fix these before downloading:')));

                    return;
                }

                saveFile(await response.blob(), downloadName(response) ?? 'document.pdf');
                await this.$wire.recordDownload(payload.type, payload.number);
                this.track('pdf_downloaded', { type: payload.type });
            } catch {
                this.toast("Couldn't reach the server. Check your connection and try again.");
            } finally {
                this.downloading = false;
            }
        },

        async exportJson() {
            await this.$wire.$commit();

            const document = this.currentDocument();
            const file = { app: 'GenDoc', version: EXPORT_VERSION, document };
            const name = `${document.type.replace('_', '-')}-${document.number}`.replace(/[^A-Za-z0-9._-]+/g, '-');

            saveFile(new Blob([JSON.stringify(file, null, 2)], { type: 'application/json' }), `${name}.json`);
            await this.$wire.markExported();
        },

        /**
         * Replace the document with one from an exported JSON file, after the server has checked it
         * with the same rules as a PDF. An invalid file leaves the current document untouched.
         */
        async importJson(file, url) {
            if (!file) {
                return;
            }

            if (file.size > IMPORT_MAX_BYTES) {
                this.toast("That file is too large to be a GenDoc document.");

                return;
            }

            let data;

            try {
                data = JSON.parse(await file.text());
            } catch {
                this.toast("That file isn't a GenDoc JSON export.");

                return;
            }

            // Accept our export wrapper or a bare document.
            const document = data && typeof data.document === 'object' ? data.document : data;

            try {
                const response = await postJson(url, document);

                if (!response.ok) {
                    this.toast(...(await errorMessage(response, "That file couldn't be imported:")));

                    return;
                }

                const { document: clean } = await response.json();

                if (this.$wire.dirty && !confirm('Replace the current document with the imported one? Your current changes will be lost.')) {
                    return;
                }

                this.logo = clean.from.logo;
                await this.$wire.loadDraft(withoutLogo(clean));
                this.toast('Document imported.', [], 'success');
            } catch {
                this.toast("Couldn't reach the server. Check your connection and try again.");
            }
        },

        /**
         * Record an analytics event, if Umami is set up. Only ever pass the document type here,
         * never anything the user typed.
         */
        track(event, { type }) {
            window.umami?.track(event, { type });
        },

        toast(message, details = [], tone = 'error') {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message, details, tone });
            setTimeout(() => this.dismissToast(id), TOAST_MS + details.length * 2000);
        },

        dismissToast(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    };
};

function withoutLogo(document) {
    return { ...document, from: { ...(document.from ?? {}), logo: null } };
}

function postJson(url, body) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify(body),
    });
}

/**
 * A toast message (and any field errors) for a failed request.
 *
 * @param {string} invalidHeading  the message shown above validation errors
 * @returns {Promise<[string, string[]]>}
 */
async function errorMessage(response, invalidHeading) {
    const body = await response.json().catch(() => ({}));
    const fallback = 'Sorry, something went wrong. Please try again in a moment.';

    switch (response.status) {
        case 422: {
            const errors = Object.values(body.errors ?? {}).flat();
            const extra = errors.length - MAX_ERROR_DETAILS;
            const details = errors.slice(0, MAX_ERROR_DETAILS).concat(extra > 0 ? [`…and ${extra} more`] : []);

            return [invalidHeading, details];
        }
        case 419:
            return ['Your session expired. Refresh the page and try again.', []];
        case 413:
            return ['This document is too large to send. Try a smaller logo.', []];
        case 429:
        case 503:
            return [body.message ?? fallback, []];
        default:
            return [fallback, []];
    }
}

/**
 * The filename from a Content-Disposition header.
 */
function downloadName(response) {
    const header = response.headers.get('Content-Disposition') ?? '';

    return header.match(/filename="?([^";]+)"?/)?.[1] ?? null;
}

function saveFile(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = Object.assign(document.createElement('a'), { href: url, download: filename });

    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

async function sanitisedSvg(file) {
    // Loaded on demand, as only SVG logos need it.
    const { default: DOMPurify } = await import('dompurify');
    const clean = DOMPurify.sanitize(await file.text(), {
        USE_PROFILES: { svg: true, svgFilters: true },
    });

    if (!clean.trim().startsWith('<svg')) {
        throw new Error('Not an SVG');
    }

    // A standalone SVG (as used in <img src>) needs its namespace, which sanitising can drop.
    const svg = clean.trim().replace(/^<svg(?![^>]*\sxmlns=)/, '<svg xmlns="http://www.w3.org/2000/svg"');
    const bytes = new TextEncoder().encode(svg);

    return `data:image/svg+xml;base64,${bytesToBase64(bytes)}`;
}

/**
 * Downscale to at most 800px on the longest side and re-encode, preferring WebP and falling back
 * to PNG where the browser can't encode WebP.
 */
async function resizedRaster(file) {
    const bitmap = await createImageBitmap(file);
    const ratio = Math.min(1, LOGO_MAX_SIDE / Math.max(bitmap.width, bitmap.height));

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * ratio);
    canvas.height = Math.round(bitmap.height * ratio);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    const webp = canvas.toDataURL('image/webp', 0.9);

    return webp.startsWith('data:image/webp') ? webp : canvas.toDataURL('image/png');
}

function bytesToBase64(bytes) {
    let binary = '';
    bytes.forEach((byte) => {
        binary += String.fromCharCode(byte);
    });

    return btoa(binary);
}

function dataUriBytes(dataUri) {
    const base64 = dataUri.slice(dataUri.indexOf(',') + 1);

    return Math.floor((base64.length * 3) / 4);
}
