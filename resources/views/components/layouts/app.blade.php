@props(['title' => null, 'heading' => null, 'description' => null])

@php
    $user = auth()->user();
    $nav = array_filter([
        ['route' => 'app.overview', 'label' => 'Overview', 'icon' => 'dashboard'],
        ['route' => 'app.requests.mine', 'label' => 'My requests', 'icon' => 'inbox'],
        $user->can('operations') ? ['route' => 'app.requests.all', 'label' => 'All requests', 'icon' => 'list'] : null,
        $user->can('operations') ? ['route' => 'app.review', 'label' => 'Review queue', 'icon' => 'review'] : null,
        $user->can('admin') ? ['route' => 'app.admin', 'label' => 'Admin', 'icon' => 'shield'] : null,
    ]);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => $title ?? $heading])
</head>
<body class="min-h-dvh" x-data="{ drawer: false }" @keydown.escape.window="drawer = false">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 btn btn-secondary">Skip to content</a>

    <script type="application/json" id="opsflow-user">@json(['id' => $user->id, 'name' => $user->name, 'role' => $user->role->value])</script>

    {{-- Desktop sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 border-r border-line bg-surface lg:flex lg:flex-col">
        @include('partials.sidebar')
    </aside>

    {{-- Mobile drawer --}}
    <div x-cloak x-show="drawer" class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="Navigation">
        <div class="absolute inset-0 bg-ink/40" @click="drawer = false" x-transition.opacity></div>
        <aside class="relative flex h-full w-72 max-w-[85vw] flex-col border-r border-line bg-surface shadow-(--shadow-pop)" x-transition:enter="transition duration-200" x-transition:enter-start="-translate-x-full" x-transition:leave="transition duration-150" x-transition:leave-end="-translate-x-full">
            @include('partials.sidebar')
        </aside>
    </div>

    <div class="lg:pl-64">
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-line bg-surface/90 px-4 backdrop-blur lg:hidden">
            <button type="button" class="btn-ghost btn -ml-2 px-2" @click="drawer = true" aria-label="Open navigation">
                <x-icon name="menu" class="size-5" />
            </button>
            <a href="{{ route('app.overview') }}" class="flex items-center gap-2 font-semibold">
                @include('partials.logo')
            </a>
        </header>

        <main id="main" class="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            @if ($heading)
                <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="min-w-0">
                        <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ $heading }}</h1>
                        @if ($description)
                            <p class="mt-1 max-w-2xl text-sm text-muted">{{ $description }}</p>
                        @endif
                    </div>
                    @isset($actions)
                        <div class="flex flex-wrap gap-2">{{ $actions }}</div>
                    @endisset
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>

    @include('partials.confirm-dialog')
    @include('partials.toasts')
</body>
</html>
