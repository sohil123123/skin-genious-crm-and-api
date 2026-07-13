<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Observers\InvoiceObserver;
use App\Observers\InvoicePaymentObserver;
use App\Filament\ReportWidgets\CollectionChart;
use App\Filament\ReportWidgets\CollectionDistributionChart;

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
        \Livewire\Livewire::component('app.filament.report-widgets.collection-chart', CollectionChart::class);
        \Livewire\Livewire::component('app.filament.report-widgets.collection-distribution-chart', CollectionDistributionChart::class);

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
    }
}
