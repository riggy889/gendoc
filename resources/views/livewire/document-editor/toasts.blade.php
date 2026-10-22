{{-- Error and status messages, managed by Alpine (see toast() in resources/js/editor.js). --}}
<div wire:ignore class="pointer-events-none fixed inset-x-0 bottom-0 z-50 flex flex-col items-center gap-2 p-4 sm:items-end" role="alert" aria-live="assertive">
    <template x-for="toast in toasts" :key="toast.id">
        <div class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg border border-red-200 bg-white p-4 shadow-lg" x-transition.opacity>
            <div class="min-w-0 flex-1 text-sm">
                <p class="font-semibold text-slate-900" x-text="toast.message"></p>
                <ul class="mt-1 list-disc space-y-0.5 pl-4 text-slate-700" x-show="toast.details.length">
                    <template x-for="detail in toast.details">
                        <li x-text="detail"></li>
                    </template>
                </ul>
            </div>
            <button type="button" class="icon-btn -m-1" x-on:click="dismissToast(toast.id)">
                <x-icon name="x" />
                <span class="sr-only">Dismiss</span>
            </button>
        </div>
    </template>
</div>
