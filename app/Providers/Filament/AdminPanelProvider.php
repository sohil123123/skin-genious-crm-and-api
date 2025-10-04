<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
// use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Pages\Auth\Login;

use Resma\FilamentAwinTheme\FilamentAwinTheme;
use Andreia\FilamentNordTheme\FilamentNordThemePlugin;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Vite;

use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Enums\Width;
use Filament\Actions\Action;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            // ->domain('admin.skin-genious-crm-and-api.test')
            // ->maxContentWidth(Width::Full)
            ->spa()
            // ->unsavedChangesAlerts()
            ->databaseTransactions()
            // ->topNavigation()
            // ->userMenuItems([
            //     'profile' => fn (Action $action) => $action->label('Edit profile'),
            // ])
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            // ->login()
            ->profile()
            ->sidebarCollapsibleOnDesktop()
            // ->font('Poppins')
            // ->brandName('Filament Demo')
            // ->brandLogo(asset('images/skin_care_logo.jpg'))
            // ->brandLogoHeight('6rem')
            ->colors([
                'dark-danger' => [
                    700 => 'oklch(0.514 0.222 16.935)',
                ],
                // 'primary' => Color::Amber,
                'danger' => Color::Red,
                'gray' => Color::Zinc,
                'info' => Color::Blue,
                'primary' => Color::Indigo,
                'success' => Color::Emerald,
                'warning' => Color::Orange,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                // FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->sidebarFullyCollapsibleOnDesktop()
            ->plugins([
                // FilamentShieldPlugin::make(),
                FilamentShieldPlugin::make()
                    ->gridColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 3
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                        'lg' => 4,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm' => 2,
                    ])
                    // ->gridColumns(['default' => 1, 'sm' => 2, 'lg' => 3])  // Customize checkbox grid
                    // ->checkboxListColumns(['default' => 1, 'sm' => 2, 'lg' => 4])  // For permission lists
                    // ->resourceCheckboxListColumns(['default' => 1, 'sm' => 2])  // Resource-specific
                    // ->sectionColumnSpan(1)  // Adjust section widths
                    // ->simpleResourcePermissionView()  // Simplify permission UI
                    ->navigationLabel('Roles')  // Change nav label
                    ->navigationIcon('heroicon-o-shield-check')  // Custom icon
                    ->navigationSort(2)  // Position in nav
                    ->navigationGroup('Security'),  // Group under a label
                    // ->showGlobalSearch(false)  // Disable search
                    // ->showInTenancy(false),  // Hide in multi-tenant setups

                // FilamentAwinTheme::make()->primaryColor(Color::Emerald),
                FilamentNordThemePlugin::make()
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Auth Management')
                    ->icon('heroicon-o-academic-cap'),
                    // ->collapsed(),
            ]);
    }

    public function boot(): void
    {
        FilamentAsset::register([
            Css::make('custom-styles', Vite::asset('resources/css/custom.css')),
            // Js::make('awin-hotfix', resource_path('js/awin-hotfix.js')),
        ]);
    }
}
