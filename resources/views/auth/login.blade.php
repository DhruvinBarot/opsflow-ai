<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head', ['title' => 'Sign in'])
</head>
<body class="min-h-dvh">
    <main class="grid min-h-dvh lg:grid-cols-2">
        <div class="flex flex-col justify-center px-6 py-12 sm:px-12">
            <div class="mx-auto w-full max-w-sm">
                <a href="{{ route('home') }}" class="mb-10 inline-flex items-center gap-2 font-semibold">@include('partials.logo')</a>

                <h1 class="text-2xl font-semibold tracking-tight">Sign in</h1>
                <p class="mt-1 text-sm text-muted">Use your OpsFlow account to continue.</p>

                <form method="POST" action="{{ url('/login') }}" class="mt-8 grid gap-5" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf

                    @error('email')
                        <div class="flex items-start gap-2 rounded-(--radius-control) border border-danger/30 bg-danger-soft px-3 py-2 text-sm text-danger" role="alert">
                            <x-icon name="alert" class="mt-0.5" /> {{ $message }}
                        </div>
                    @enderror

                    <div>
                        <label for="email" class="field-label">Email</label>
                        <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" required autocomplete="username" autofocus
                               @error('email') aria-invalid="true" @enderror>
                    </div>

                    <div>
                        <label for="password" class="field-label">Password</label>
                        <input id="password" name="password" type="password" class="input" required autocomplete="current-password"
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        @error('password')<p id="password-error" class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-full" :disabled="submitting">
                        <span x-text="submitting ? 'Signing in…' : 'Sign in'">Sign in</span>
                    </button>
                </form>

                @if (app()->isLocal())
                    <div class="mt-8 rounded-(--radius-card) border border-dashed border-line-strong p-4" x-data>
                        <p class="eyebrow">Local demo accounts</p>
                        <p class="mt-1 text-xs text-muted">Seeded for development only. Password: <code class="font-mono">password</code></p>
                        <div class="mt-3 grid gap-1.5">
                            @foreach (['employee' => 'Eli Employee', 'manager' => 'Morgan Manager', 'admin' => 'Avery Admin'] as $role => $name)
                                <button type="button" class="btn btn-secondary btn-sm justify-between"
                                        @click="document.getElementById('email').value = '{{ $role }}@opsflow.test'; document.getElementById('password').value = 'password'; document.getElementById('password').focus()">
                                    <span>{{ $name }}</span><span class="eyebrow">{{ $role }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <aside class="hidden border-l border-line bg-surface lg:flex lg:flex-col lg:justify-center lg:px-16" aria-label="About OpsFlow AI">
            <div class="max-w-md">
                <p class="eyebrow">How a request moves</p>
                <ol class="mt-6 grid gap-5">
                    @foreach ([
                        ['Submit', 'Employees describe the work and submit it.'],
                        ['Analyze', 'A Redis worker runs AI triage in the background.'],
                        ['Decide', 'Managers review with the recommendation as advice.'],
                        ['Deliver', 'Assigned staff complete the work, fully audited.'],
                    ] as $index => [$step, $text])
                        <li class="flex gap-4">
                            <span class="mono-id mt-0.5">{{ sprintf('%02d', $index + 1) }}</span>
                            <span><span class="font-medium">{{ $step }}.</span> <span class="text-muted">{{ $text }}</span></span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </aside>
    </main>
</body>
</html>
