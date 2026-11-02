<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Services\AI\AIRequestAnalyzerInterface;
use App\Services\AI\LocalRequestAnalyzer;
use App\Services\AI\OpenAIRequestAnalyzer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // OpenAI when a key is configured; otherwise the deterministic local analyzer.
        $this->app->bind(AIRequestAnalyzerInterface::class, function () {
            $config = config('services.openai');

            return filled($config['key'] ?? null)
                ? new OpenAIRequestAnalyzer($config['key'], $config['model'], $config['base_url'], (int) $config['timeout'])
                : new LocalRequestAnalyzer;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Surface lazy-loading (N+1) and silently-dropped attributes during development and tests.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Role gates for whole areas (pages and cross-request endpoints); per-request rules stay in the policy.
        Gate::define('operations', fn (User $user) => $user->isOperations());
        Gate::define('admin', fn (User $user) => $user->hasRole(Role::Admin));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute(20)->by($request->ip()),
        ]);
    }
}
