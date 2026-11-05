@props(['contact', 'size' => 'size-12'])
@if ($contact->avatarUrl())
    <img src="{{ $contact->avatarUrl() }}" alt="{{ $contact->name }}" {{ $attributes->merge(['class' => "$size shrink-0 rounded-full object-cover"]) }}>
@else
    <div {{ $attributes->merge(['class' => "$size shrink-0 rounded-full grid place-items-center font-semibold text-white select-none"]) }} style="background: {{ $contact->gradient() }}">
        <span class="text-[0.95em]">{{ $contact->initials() }}</span>
    </div>
@endif
