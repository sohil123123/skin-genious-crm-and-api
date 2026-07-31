<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Observers\InvoiceObserver;
use App\Observers\InvoicePaymentObserver;

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
        \App\Models\Product::observe(\App\Observers\ProductObserver::class);
        \App\Models\Purchase::observe(\App\Observers\PurchaseObserver::class);
        \App\Models\Expense::observe(\App\Observers\ExpenseObserver::class);
        Invoice::observe(InvoiceObserver::class);
        InvoicePayment::observe(InvoicePaymentObserver::class);
        // \App\Models\StockTransaction::observe(\App\Observers\StockTransactionObserver::class);

        Relation::morphMap([
            'purchase' => \App\Models\Purchase::class,
        ]);

        \Livewire\Livewire::component('app.filament.report-widgets.product-purchase-chart', \App\Filament\ReportWidgets\ProductPurchaseChart::class);
        \Livewire\Livewire::component('app.filament.report-widgets.product-sales-chart', \App\Filament\ReportWidgets\ProductSalesChart::class);
        \Livewire\Livewire::component('app.filament.report-widgets.product-purchase-distribution-chart', \App\Filament\ReportWidgets\ProductPurchaseDistributionChart::class);
        \Livewire\Livewire::component('app.filament.report-widgets.product-sales-distribution-chart', \App\Filament\ReportWidgets\ProductSalesDistributionChart::class);

        \Illuminate\Support\Facades\Gate::define('viewLogViewer', function ($user) {
            return $user->hasRole('super_admin');
        });

        \Illuminate\Support\Facades\Gate::define('deleteLogFile', function ($user) {
            return $user->hasRole('super_admin');
        });

        \Illuminate\Support\Facades\Gate::define('deleteLogFolder', function ($user) {
            return $user->hasRole('super_admin');
        });

        \Illuminate\Support\Facades\Gate::define('downloadLogFile', function ($user) {
            return $user->hasRole('super_admin');
        });

        \Illuminate\Support\Facades\Gate::define('downloadLogFolder', function ($user) {
            return $user->hasRole('super_admin');
        });

        \Illuminate\Support\Facades\View::composer('pdf.pigmentation.*', function ($view) {
            $view->with('uiAssets', [
                'brand_icon' => public_path('images/pigmentation-report/brand_icon.jpg'),
                'ring_8' => public_path('images/pigmentation-report/ring_8.jpg'),
                'icon_glow' => public_path('images/pigmentation-report/icon_glow.jpg'),
                'icon_sun' => public_path('images/pigmentation-report/icon_sun.jpg'),
                'icon_eye' => public_path('images/pigmentation-report/icon_eye.jpg'),
                'icon_pores' => public_path('images/pigmentation-report/icon_pores.jpg'),
                'icon_target' => public_path('images/pigmentation-report/icon_target.jpg'),
                'icon_barrier' => public_path('images/pigmentation-report/icon_barrier.jpg'),
                'icon_camera' => public_path('images/pigmentation-report/icon_camera.jpg'),
                'icon_calendar' => public_path('images/pigmentation-report/icon_calendar.jpg'),
                'icon_user' => public_path('images/pigmentation-report/icon_user.jpg'),
                'icon_check' => public_path('images/pigmentation-report/icon_check.jpg'),
                'icon_stable' => public_path('images/pigmentation-report/icon_stable.jpg'),
                'icon_hydration' => public_path('images/pigmentation-report/icon_hydration.jpg'),
                'icon_renewal' => public_path('images/pigmentation-report/icon_renewal.jpg'),
                'arrow_right' => public_path('images/pigmentation-report/arrow_right.jpg')
            ]);
        });
    }
}
