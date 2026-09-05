<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

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
        View::composer([
            'layouts.admin',
            'admin.debt-ledgers.create',
            'admin.provider-ledgers.create',
            'admin.distributions.create',
        ], function (ViewInstance $view): void {
            $request = request();

            if (! $request->attributes->has('currentDate')) {
                $now = now(config('app.timezone'));
                $selectedDate = $request->session()->get('current_date');

                $currentDate = $now;

                if (is_string($selectedDate)) {
                    try {
                        $currentDate = Carbon::createFromFormat(
                            '!Y-m-d',
                            $selectedDate,
                            config('app.timezone'),
                        )->setTime($now->hour, $now->minute, $now->second, $now->micro);
                    } catch (\Throwable) {
                        // Ignore malformed session values and use the application date instead.
                    }
                }

                $request->attributes->set('currentDate', $currentDate);
            }

            $view->with('currentDate', $request->attributes->get('currentDate'));
        });
    }
}
