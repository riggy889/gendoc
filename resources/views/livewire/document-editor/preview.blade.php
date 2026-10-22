<div class="flex h-full flex-col">
    <div class="flex items-center justify-end gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2">
        <span class="text-sm text-slate-600" id="zoom-label">Zoom</span>
        <div class="segmented" role="group" aria-labelledby="zoom-label">
            <button type="button" :aria-pressed="zoom === 'fit'" x-on:click="zoom = 'fit'">Fit</button>
            <button type="button" :aria-pressed="zoom === 0.75" x-on:click="zoom = 0.75">75%</button>
            <button type="button" :aria-pressed="zoom === 1" x-on:click="zoom = 1">100%</button>
        </div>
    </div>

    <div x-ref="previewViewport" class="min-h-0 flex-1 overflow-auto bg-slate-300/70 p-4" aria-label="Document preview" role="region">
        <div class="mx-auto w-fit" :style="`zoom: ${scale}`">
            <div class="doc-sheet">
                @include('documents.render', ['document' => $preview, 'totals' => $totals, 'isPreview' => true])
            </div>
        </div>
    </div>
</div>
