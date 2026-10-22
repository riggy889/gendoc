import DOMPurify from 'dompurify';
import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { paginate } from './paginator';

// A4 is 210mm wide; at 96 CSS pixels per inch that's ~793.7px.
const A4_WIDTH_PX = (210 / 25.4) * 96;
const PREVIEW_GUTTER_PX = 32;

const LOGO_MAX_SIDE = 800;
const LOGO_MAX_BYTES = 2 * 1024 * 1024;
const LOGO_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];

const SECTION_KEY = 'gendoc.section';

function readSetting(key, fallback) {
    try {
        return window.localStorage.getItem(key) ?? fallback;
    } catch {
        return fallback;
    }
}

function writeSetting(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Storage unavailable (private mode, blocked or full); the setting just won't be remembered.
    }
}

/**
 * Client-side state for the editor: mobile tabs, the accordion, preview zoom and pages, and the logo.
 *
 * The logo is held here rather than in Livewire so a large data URI isn't sent to the server on
 * every update. It is only sent when generating a PDF.
 */
export default () => {
    // Removes the paginator's spacers; kept out of Alpine state as it isn't reactive data.
    let undoPagination = () => {};

    return {
        tab: 'edit',
        openSection: readSetting(SECTION_KEY, 'document'),
        zoom: 'fit',
        scale: 1,
        pages: 1,
        logo: null,
        logoError: null,
        draggingLogo: false,

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
    };
};

async function sanitisedSvg(file) {
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
