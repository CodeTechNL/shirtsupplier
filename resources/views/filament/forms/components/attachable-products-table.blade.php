<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @php($rows = $getRows())

    <div
        x-data="{ selected: $wire.$entangle(@js($getStatePath())) }"
        {{ $getExtraAttributeBag() }}
        class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
    >
        @if (count($rows))
            <div class="max-h-96 overflow-y-auto">
                <table class="w-full text-start text-sm">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="w-10 px-3 py-2.5"></th>
                            <th class="px-3 py-2.5 text-start">Product</th>
                            <th class="px-3 py-2.5 text-start">Identifiers</th>
                            <th class="w-24 px-3 py-2.5 text-start">Visible</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($rows as $row)
                            <tr
                                wire:key="attachable-product-{{ $row['id'] }}"
                                class="cursor-pointer transition-colors hover:bg-gray-50 dark:hover:bg-white/5"
                                :class="selected.includes({{ $row['id'] }}) && 'bg-primary-50 dark:bg-primary-500/10'"
                                @click="
                                    selected.includes({{ $row['id'] }})
                                        ? (selected = selected.filter((id) => id !== {{ $row['id'] }}))
                                        : (selected = [...selected, {{ $row['id'] }}])
                                "
                            >
                                <td class="px-3 py-2.5" @click.stop>
                                    <input
                                        type="checkbox"
                                        value="{{ $row['id'] }}"
                                        x-model.number="selected"
                                        class="size-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600 dark:border-white/20 dark:bg-white/5"
                                    />
                                </td>
                                <td class="px-3 py-2.5 font-medium text-gray-950 dark:text-white">
                                    {{ $row['title'] }}
                                </td>
                                <td class="px-3 py-2.5 text-gray-500 dark:text-gray-400">
                                    {{ $row['codes'] !== '' ? $row['codes'] : '—' }}
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($row['visible'])
                                        <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-500/10 dark:text-green-400 dark:ring-green-500/20">
                                            Visible
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
                                            Hidden
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-between border-t border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                <span>{{ trans_choice('{1} :count result|[2,*] :count results', count($rows), ['count' => count($rows)]) }}</span>
                <span x-show="selected.length"><span x-text="selected.length"></span> selected</span>
            </div>
        @else
            <div class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                Search by title, SKU, EAN or article code to find products to attach.
            </div>
        @endif
    </div>
</x-dynamic-component>
