<div class="flex h-14 items-center justify-between gap-2 border-b border-line px-4">
    <a href="{{ route('app.overview') }}" class="flex items-center gap-2 font-semibold">
        @include('partials.logo')
    </a>
    <button type="button" class="btn-ghost btn px-2 lg:hidden" @click="drawer = false" aria-label="Close navigation">
        <x-icon name="x" class="size-5" />
    </button>
</div>

<div class="p-3">
    <a href="{{ route('app.requests.create') }}" class="btn btn-primary w-full">
        <x-icon name="plus" /> New request
    </a>
</div>

<nav class="flex-1 overflow-y-auto px-3 pb-3" aria-label="Main">
    <ul class="grid gap-0.5">
        @foreach ($nav as $item)
            <li>
                <a href="{{ route($item['route']) }}" class="nav-link" @if (request()->routeIs($item['route'])) aria-current="page" @endif>
                    <x-icon :name="$item['icon']" /> {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>

<div class="border-t border-line p-3">
    <div class="flex items-center gap-3 rounded-(--radius-control) px-2 py-1.5">
        <span class="grid size-8 place-items-center rounded-full bg-subtle text-xs font-semibold text-ink-soft" aria-hidden="true">
            {{ collect(explode(' ', $user->name))->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('') }}
        </span>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium">{{ $user->name }}</p>
            <p class="eyebrow">{{ $user->role->value }}</p>
        </div>
        <button type="button" class="btn-ghost btn px-2" @click="$store.theme.toggle()" :aria-label="$store.theme.dark ? 'Switch to light theme' : 'Switch to dark theme'">
            <x-icon name="moon" class="size-4" x-show="!$store.theme.dark" />
            <x-icon name="sun" class="size-4" x-show="$store.theme.dark" x-cloak />
        </button>
    </div>
    <form method="POST" action="{{ route('logout') }}" class="mt-1">
        @csrf
        <button type="submit" class="nav-link w-full">
            <x-icon name="logout" /> Sign out
        </button>
    </form>
</div>
