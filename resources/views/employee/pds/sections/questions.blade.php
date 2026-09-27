@php
    use App\Support\Pds\PdsFormSchema;

    $answers = collect(PdsFormSchema::QUESTIONS)
        ->mapWithKeys(fn ($q, $key) => [$key => old($key, $values[$key] ?? '')])
        ->all();
@endphp

<div class="space-y-4" x-data="{ a: @js($answers) }">
    @foreach (PdsFormSchema::QUESTIONS as $key => $question)
        @php
            // Question 34 has one details line, shown if either part is YES.
            $showDetails = $key === 'q34b' ? "a.q34a === 'yes' || a.q34b === 'yes'" : "a.{$key} === 'yes'";
        @endphp

        <div class="rounded-lg border border-sand-200 p-4 @error($key) border-red-300 bg-red-50/40 @enderror">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <p class="text-sm text-sand-700 leading-relaxed lg:max-w-3xl">
                    <span class="font-semibold text-sand-900">{{ $question['number'] }}.</span>
                    {{ $question['text'] }}
                </p>

                <div class="flex shrink-0 items-center gap-5" role="radiogroup" aria-label="Question {{ $question['number'] }}">
                    @foreach (PdsFormSchema::YES_NO as $value => $label)
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-sand-700 cursor-pointer">
                            <input type="radio" name="{{ $key }}" value="{{ $value }}" x-model="a.{{ $key }}"
                                   class="h-4 w-4 accent-maroon-700" @disabled($locked)>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            @error($key)
                <span class="error-text">{{ $message }}</span>
            @enderror

            @if ($question['details'])
                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4" x-show="{{ $showDetails }}" x-cloak>
                    @foreach ($question['details'] as $field => $label)
                        @include('employee.pds.partials.field', [
                            'key' => $field,
                            'label' => $label,
                            'type' => $field === 'q35b_date_filed' ? 'date' : 'text',
                            'values' => $values,
                            'locked' => $locked,
                        ])
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
