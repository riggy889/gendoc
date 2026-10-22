@php
    $presets = ['#2563eb' => 'Blue', '#0f766e' => 'Teal', '#15803d' => 'Green', '#7e22ce' => 'Purple', '#be185d' => 'Pink', '#b91c1c' => 'Red', '#c2410c' => 'Orange', '#0f172a' => 'Slate'];
@endphp

<x-editor.section id="style" title="Style">
    <fieldset>
        <legend class="label">Template</legend>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach (\App\Documents\Enums\Template::cases() as $template)
                <label class="cursor-pointer rounded-lg border-2 p-2 text-center text-sm font-medium has-checked:border-blue-700 has-checked:bg-blue-50 has-focus-visible:ring-2 has-focus-visible:ring-blue-600 border-slate-200">
                    <input type="radio" class="sr-only" name="template" value="{{ $template->value }}" wire:model.live="document.style.template">
                    <span class="mb-2 block aspect-[210/297] rounded border border-slate-200 bg-white p-1.5" aria-hidden="true">
                        <span class="doc-thumb doc-thumb--{{ $template->value }}" style="--accent: {{ $preview->style->accentColor }}"></span>
                    </span>
                    {{ $template->label() }}
                </label>
            @endforeach
        </div>
    </fieldset>

    <fieldset>
        <legend class="label">Accent colour</legend>
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($presets as $hex => $name)
                <label class="relative cursor-pointer">
                    <input type="radio" class="peer sr-only" name="accent" value="{{ $hex }}" wire:model.live="document.style.accent_color">
                    <span class="block size-8 rounded-full ring-offset-2 peer-checked:ring-2 peer-checked:ring-slate-900 peer-focus-visible:ring-2 peer-focus-visible:ring-blue-600" style="background: {{ $hex }}"></span>
                    <span class="sr-only">{{ $name }}</span>
                </label>
            @endforeach
            <label for="field-accent-picker" class="sr-only">Custom colour</label>
            <input id="field-accent-picker" type="color" value="{{ $preview->style->accentColor }}" wire:model.live.debounce.150ms="document.style.accent_color" class="size-8 cursor-pointer rounded border border-slate-300 bg-white p-0.5">
        </div>
        <x-form.input label="Hex code" model="document.style.accent_color" class="mt-3 w-36" />
    </fieldset>

    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.select label="Font" model="document.style.font">
            @foreach (\App\Documents\Enums\Font::cases() as $font)
                <option value="{{ $font->value }}" style="font-family: {{ $font->cssFamily() }}">{{ $font->label() }}</option>
            @endforeach
        </x-form.select>

        <x-form.select label="Date format" model="document.style.date_format">
            @foreach (\App\Documents\Enums\DateFormat::cases() as $format)
                <option value="{{ $format->value }}">{{ $format->example() }}</option>
            @endforeach
        </x-form.select>
    </div>
</x-editor.section>
