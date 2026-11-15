<?php

namespace App\Providers;

use App\Listeners\TrackDevicePresence;
use App\Models\Device;
use App\Support\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureDeviceApi();
    }

    protected function configureDeviceApi(): void
    {
        RateLimiter::for('device-claim', fn (Request $request) => Limit::perMinute(config('devices.claim_per_minute'))
            ->by('claim:'.$request->ip())
            ->response(fn () => response()->json(['error' => 'too_many_attempts', 'message' => 'Too many requests.'], 429)));

        RateLimiter::for('device-api', function (Request $request) {
            $device = $request->attributes->get('device');

            return Limit::perMinute(config('devices.api_per_minute'))
                ->by($device instanceof Device ? 'device:'.$device->id : 'ip:'.$request->ip());
        });

        Event::subscribe(TrackDevicePresence::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
