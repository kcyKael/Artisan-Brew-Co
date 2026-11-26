@props(['title', 'description' => null])

<div class="abc-panel p-10 text-center space-y-2">
    <h3 class="font-display text-2xl font-semibold text-[#F4EEE5]">{{ $title }}</h3>
    @if($description)
        <p class="text-sm text-[#BFB4A5] max-w-md mx-auto">{{ $description }}</p>
    @endif
    @if(isset($action))
        <div class="pt-3">{{ $action }}</div>
    @endif
</div>
