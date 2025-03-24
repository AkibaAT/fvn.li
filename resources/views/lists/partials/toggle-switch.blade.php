{{-- Common Toggle Switch Component
   Parameters:
   - action: The form action URL
   - name: The name attribute for the checkbox input
   - value: The value attribute for the checkbox input (typically "1")
   - checked: Boolean indicating if the toggle is checked
   - srText: Screen reader text for accessibility
   - formClass: Optional CSS class for the form element
   - label: Optional label text to display beside the toggle
   - justify: Optional justification for the flex container (default: justify-end)
--}}

<form action="{{ $action }}" method="POST" class="{{ $formClass ?? 'toggle-updates-form' }}">
    @csrf
    @method('PATCH')
    <div class="flex items-center {{ isset($label) ? 'gap-3' : '' }} {{ $justify ?? 'justify-end' }}">
        @if (isset($label))
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $label }}</span>
        @endif
        <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="{{ $name }}" value="1" class="sr-only peer {{ isset($extraClass) ? $extraClass : '' }}" {{ $checked ? 'checked' : '' }}>
            <div class="w-14 h-7 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600 after:shadow-md after:z-10 after:flex after:justify-center after:items-center after:text-gray-400 peer-checked:after:text-blue-600 after:content-['☐'] peer-checked:after:content-['✓']">
                <span class="sr-only">{{ $srText }}</span>
            </div>
        </label>
    </div>
</form>
