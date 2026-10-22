// Page geometry in CSS pixels (96 per inch), matching @page and .doc-sheet in document.css.
const MM = 96 / 25.4;
const PAGE_HEIGHT = 297 * MM;
const PAGE_MARGIN = 15 * MM;
const PAGE_GAP = 8 * MM;
const PAGE_STRIDE = PAGE_HEIGHT + PAGE_GAP;
const CONTENT_HEIGHT = PAGE_HEIGHT - 2 * PAGE_MARGIN;

/**
 * Lays the preview out as A4 pages, following the same rules Chromium applies to the PDF (see the
 * print section of document.css): item rows never split and the items header repeats on each new
 * page, and the closing totals, notes and payment details stay together where they fit.
 *
 * Blocks that would cross a page's bottom margin are pushed to the next page with spacer elements.
 * The spacers live inside Livewire's DOM, so call the returned `undo` before Livewire morphs it.
 *
 * @param {HTMLElement} article  the .doc element, positioned at the top margin of the first page
 * @param {number} scale  the preview zoom, to convert on-screen sizes back to page sizes
 * @returns {{ pages: number, undo: () => void }}
 */
export function paginate(article, scale) {
    const inserted = [];
    const origin = () => article.getBoundingClientRect().top;
    const topOf = (el) => (el.getBoundingClientRect().top - origin()) / scale;
    const heightOf = (el) => el.getBoundingClientRect().height / scale;

    for (const block of blocksOf(article, scale)) {
        const top = topOf(block);
        const page = Math.floor(top / PAGE_STRIDE);
        const isRow = block instanceof HTMLTableRowElement;
        const header = isRow ? block.closest('table')?.tHead?.rows[0] : null;
        const needed = heightOf(block) + (header ? heightOf(header) : 0);

        // Leave blocks that fit, and ones too tall for any page, which have to split anyway.
        if (top + heightOf(block) <= page * PAGE_STRIDE + CONTENT_HEIGHT || needed > CONTENT_HEIGHT) {
            continue;
        }

        const nextPage = (page + 1) * PAGE_STRIDE;
        const spacer = isRow ? spacerRow(header) : document.createElement('div');
        const gap = isRow ? spacer.cells[0] : spacer;
        const target = nextPage + (header ? heightOf(header) : 0);

        spacer.setAttribute('aria-hidden', 'true');
        block.before(spacer);
        inserted.push(spacer);

        if (header) {
            const repeated = header.cloneNode(true);
            repeated.setAttribute('aria-hidden', 'true');
            block.before(repeated);
            inserted.push(repeated);
        }

        // Size the gap, then correct it once for margins that collapsed around the spacer.
        const height = nextPage - top;
        gap.style.height = `${height}px`;
        gap.style.height = `${Math.max(0, height + target - topOf(block))}px`;
    }

    const bottom = topOf(article) + heightOf(article);

    return {
        pages: Math.max(1, Math.floor(bottom / PAGE_STRIDE) + 1),
        undo: () => inserted.forEach((el) => el.remove()),
    };
}

/**
 * The blocks that must not be split, in document order: each item row, and the closing section
 * (or, when it is too tall for one page, each of its parts).
 */
function* blocksOf(article, scale) {
    // Snapshot the live collections, as the paginator inserts spacers into them as it goes.
    for (const child of [...article.children]) {
        if (child.matches('.doc-items')) {
            yield* [...(child.tBodies[0]?.rows ?? [])];
        } else if (child.matches('.doc-end') && child.getBoundingClientRect().height / scale > CONTENT_HEIGHT) {
            yield* [...child.children];
        } else {
            yield child;
        }
    }
}

function spacerRow(header) {
    const row = document.createElement('tr');
    const cell = row.insertCell();
    cell.colSpan = header?.cells.length ?? 1;
    cell.style.padding = '0';
    cell.style.border = '0';

    return row;
}
