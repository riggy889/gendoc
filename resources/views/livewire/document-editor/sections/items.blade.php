@php
    $itemCount = count($document['items']);
@endphp

<x-editor.section id="items" title="Line items">
    @if ($itemCount === 0)
        <p class="text-sm text-slate-600">No items yet.</p>
    @endif

    <ol wire:sort="sortItem" class="space-y-3" aria-label="Line items">
        @foreach ($document['items'] as $index => $item)
            @php
                $id = $item['id'];
                $position = $index + 1;
                $discountType = $item['discount']['type'] ?? '';
                $line = $totals->lines[$id] ?? null;
            @endphp
            <li wire:key="item-{{ $id }}" wire:sort:item="{{ $id }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-xs">
                <div class="mb-3 flex items-center gap-1">
                    <button type="button" wire:sort:handle class="icon-btn cursor-grab" aria-label="Drag to reorder item {{ $position }}">
                        <x-icon name="grip" />
                    </button>
                    <h3 class="mr-auto text-sm font-semibold text-slate-800">Item {{ $position }}</h3>
                    <button type="button" class="icon-btn" wire:click="moveItem('{{ $id }}', -1)" @disabled($index === 0) aria-label="Move item {{ $position }} up">
                        <x-icon name="arrow-up" />
                    </button>
                    <button type="button" class="icon-btn" wire:click="moveItem('{{ $id }}', 1)" @disabled($index === $itemCount - 1) aria-label="Move item {{ $position }} down">
                        <x-icon name="arrow-down" />
                    </button>
                    <button type="button" class="icon-btn" wire:click="duplicateItem('{{ $id }}')" aria-label="Duplicate item {{ $position }}">
                        <x-icon name="copy" />
                    </button>
                    <button type="button" class="icon-btn hover:text-red-700" wire:click="removeItem('{{ $id }}')" aria-label="Remove item {{ $position }}">
                        <x-icon name="trash" />
                    </button>
                </div>

                <div class="grid gap-3 sm:grid-cols-6">
                    <x-form.textarea label="Description" model="document.items.{{ $index }}.description" rows="2" class="sm:col-span-6" />
                    <x-form.input label="Quantity" model="document.items.{{ $index }}.quantity" inputmode="decimal" class="sm:col-span-2" />
                    <x-form.input label="Unit price" model="prices.{{ $id }}" inputmode="decimal" :prefix="$currencySymbol" class="sm:col-span-2"
                        :error="$errors->has('prices.'.$id) ? 'prices.'.$id : 'document.items.'.$index.'.unit_price_minor'" />
                    <x-form.select label="Tax" model="document.items.{{ $index }}.tax_rate_id" class="sm:col-span-2">
                        @foreach ($document['tax_rates'] as $rate)
                            <option value="{{ $rate['id'] }}">{{ $rate['name'] }} {{ $rate['rate_percent'] }}%</option>
                        @endforeach
                        <option value="none">No tax (0%)</option>
                    </x-form.select>

                    <div class="sm:col-span-3">
                        <label for="item-discount-type-{{ $id }}" class="label">Discount</label>
                        <select id="item-discount-type-{{ $id }}" class="input" wire:change="setItemDiscountType('{{ $id }}', $event.target.value)">
                            <option value="" @selected($discountType === '')>None</option>
                            <option value="percent" @selected($discountType === 'percent')>Percentage</option>
                            <option value="fixed" @selected($discountType === 'fixed')>Fixed amount</option>
                        </select>
                    </div>
                    @if ($discountType === 'percent')
                        <x-form.input label="Discount %" model="document.items.{{ $index }}.discount.value" inputmode="decimal" prefix="%"
                            error="document.items.{{ $index }}.discount" class="sm:col-span-3" />
                    @elseif ($discountType === 'fixed')
                        <x-form.input label="Discount amount" model="lineDiscountAmounts.{{ $id }}" inputmode="decimal" :prefix="$currencySymbol"
                            :error="$errors->has('lineDiscountAmounts.'.$id) ? 'lineDiscountAmounts.'.$id : 'document.items.'.$index.'.discount'" class="sm:col-span-3" />
                    @endif
                </div>

                @if ($line)
                    <p class="mt-3 text-right text-sm text-slate-600">
                        Line total: <span class="font-semibold text-slate-900">{{ \App\Documents\Support\Format::money($line->net) }}</span>
                    </p>
                @endif
            </li>
        @endforeach
    </ol>

    <button type="button" class="btn w-full" wire:click="addItem" @disabled($itemCount >= \App\Documents\Validation\DocumentRules::MAX_ITEMS)>
        <x-icon name="plus" />
        Add item
    </button>
    @error('document.items')
        <p class="field-error">{{ $message }}</p>
    @enderror
</x-editor.section>
