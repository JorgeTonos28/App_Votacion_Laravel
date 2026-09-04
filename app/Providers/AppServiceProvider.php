<?php

namespace App\Providers;

use App\Support\Domain;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $accessResponse = static function (Request $request, array $headers) {
            $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));

            return back()
                ->withInput()
                ->withErrors(["Has realizado varios intentos seguidos. Espera {$seconds} segundos e inténtalo nuevamente."])
                ->withHeaders($headers);
        };

        $voteResponse = static function (Request $request, array $headers) {
            $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));

            return back()
                ->withInput()
                ->withErrors(["Recibimos varios envíos seguidos. Espera {$seconds} segundos antes de volver a enviar tu voto."])
                ->withHeaders($headers);
        };

        RateLimiter::for('public-access', function (Request $request) use ($accessResponse) {
            $device = $request->cookie('innovamente_device')
                ?: Domain::technicalHash($request->ip().'|'.$request->userAgent());

            return [
                Limit::perMinute(12)
                    ->by('device:'.Domain::technicalHash($device))
                    ->response($accessResponse),
                Limit::perMinute(600)
                    ->by('network:'.Domain::technicalHash($request->ip()))
                    ->response($accessResponse),
            ];
        });

        RateLimiter::for('jury-access', function (Request $request) use ($accessResponse) {
            $credential = Domain::normalizeCode($request->string('event_code')).'|'.Domain::normalizeCode($request->string('juror_code'));
            $failed = static fn ($response): bool => $response->getStatusCode() !== 200;

            return [
                Limit::perMinutes(10, 6)
                    ->by('credential:'.Domain::technicalHash($credential))
                    ->after($failed)
                    ->response($accessResponse),
                Limit::perMinute(120)
                    ->by('network:'.Domain::technicalHash($request->ip()))
                    ->after($failed)
                    ->response($accessResponse),
            ];
        });

        RateLimiter::for('public-vote', fn (Request $request) => Limit::perMinute(12)
            ->by('session:'.Domain::technicalHash($request->cookie('innovamente_public') ?: $request->ip()))
            ->response($voteResponse));

        RateLimiter::for('jury-vote', fn (Request $request) => Limit::perMinute(12)
            ->by('session:'.Domain::technicalHash($request->cookie('innovamente_juror') ?: $request->ip()))
            ->response($voteResponse));
    }
}
