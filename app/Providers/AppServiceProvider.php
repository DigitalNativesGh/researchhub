<?php

namespace App\Providers;

use App\Models\Dataset;
use App\Models\Paper;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Setting;
use App\Models\User;
use App\Policies\ProjectDocumentPolicy;
use App\Policies\ProjectPolicy;
use App\Services\Claude\ClaudeGateway;
use App\Services\Claude\ClaudeService;
use App\Services\Claude\CostCalculator;
use App\Services\Claude\FakeGateway;
use App\Services\Claude\PromptRepository;
use App\Services\Claude\SdkGateway;
use App\Services\Payments\Paystack;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ClaudeGateway::class, function ($app) {
            return config('services.claude.fake')
                ? new FakeGateway
                : new SdkGateway(config('services.claude'));
        });

        $this->app->singleton(PromptRepository::class, fn () => new PromptRepository(resource_path('prompts')));
        $this->app->singleton(CostCalculator::class, fn () => new CostCalculator(config('services.claude')));
        $this->app->bind(ClaudeService::class, fn ($app) => new ClaudeService(
            $app->make(ClaudeGateway::class),
            $app->make(PromptRepository::class),
            $app->make(CostCalculator::class),
            config('services.claude'),
        ));

        $this->app->bind(Paystack::class, fn () => new Paystack(config('services.paystack')));
    }

    public function boot(): void
    {
        // `composer dev` / `php artisan dev`: Horizon only serves Redis, so with any other queue driver (for example
        // QUEUE_CONNECTION=database without Redis) run a plain listener for data-file checks and emails instead.
        if (config('queue.default') !== 'redis') {
            DevCommands::except('horizon');
            DevCommands::artisan('queue:listen --queue=default --tries=1 --timeout=0', 'queue');
        }

        // Platform settings saved from the admin dashboard override config/researchhub.php.
        rescue(fn () => Setting::applyToConfig(), report: false);

        Gate::define('admin', fn (User $user) => $user->is_admin);
        Gate::policy(ProjectDocument::class, ProjectDocumentPolicy::class);

        // Everything inside a project is authorised through the project's owner.
        foreach ([Project::class, Paper::class, Dataset::class] as $model) {
            Gate::policy($model, ProjectPolicy::class);
        }

        // Title suggestions call the AI: a few a minute, and an hourly cap per researcher.
        RateLimiter::for('title-suggestions', function (Request $request) {
            $key = $request->user()?->id ?: $request->ip();
            $tooMany = fn () => redirect()->route('title-assistant')->with('error', 'You have asked for a lot of suggestions in a short time. Please wait a little before asking again, or type your own title.');

            return [
                Limit::perMinute(5)->by('min:'.$key)->response($tooMany),
                Limit::perHour((int) config('researchhub.rate_limits.title_suggestions_per_hour', 20))->by('hour:'.$key)->response($tooMany),
            ];
        });

        // "Remember me for 30 days" on the sign-in page.
        Auth::guard('web')->setRememberDuration(60 * 24 * 30);

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
