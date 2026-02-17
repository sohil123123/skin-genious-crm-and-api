<?php

namespace App\Providers;

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
        \App\Models\Product::observe(\App\Observers\ProductObserver::class);
        \App\Models\StockTransaction::observe(\App\Observers\StockTransactionObserver::class);

        \Livewire\Livewire::component('app.filament.report-widgets.product-purchase-chart', \App\Filament\ReportWidgets\ProductPurchaseChart::class);
        \Livewire\Livewire::component('app.filament.report-widgets.product-sales-chart', \App\Filament\ReportWidgets\ProductSalesChart::class);
        \Livewire\Livewire::component('app.filament.report-widgets.product-purchase-distribution-chart', \App\Filament\ReportWidgets\ProductPurchaseDistributionChart::class);
        \Livewire\Livewire::component('app.filament.report-widgets.product-sales-distribution-chart', \App\Filament\ReportWidgets\ProductSalesDistributionChart::class);
    }
}
