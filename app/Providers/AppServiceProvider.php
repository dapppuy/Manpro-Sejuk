<?php

namespace App\Providers;

use App\Support\CustomerLocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->configureViews();
    }

    /**
     * Share header data with the customer header.
     */
    protected function configureViews(): void
    {
        View::composer('components.customer.header', function (\Illuminate\View\View $view): void {
            $user = auth()->user();

            $view->with([
                'locationLabel' => app(CustomerLocation::class)->shortLabel($user),
                'hasActiveOrder' => $user?->isCustomer() === true && $user->orders()->active()->exists(),
            ]);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        CarbonImmutable::setLocale(config('app.locale'));

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
