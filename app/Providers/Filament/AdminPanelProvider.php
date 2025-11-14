<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
// use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Pages\Auth\Login;

use Resma\FilamentAwinTheme\FilamentAwinTheme;
use Andreia\FilamentNordTheme\FilamentNordThemePlugin;

// use Filament\View\PanelsRenderHook;
// use Illuminate\Contracts\View\View;

use App\Livewire\Topbar\UserInfo;
use Filament\Topbar\TopbarItem;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Vite;

use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Enums\Width;
use Filament\Actions\Action;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Profile;

use Filament\Navigation\MenuItem;
use Filament\View\PanelsRenderHook;

use Illuminate\Support\Facades\Auth;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            // ->domain('admin.skin-genious-crm-and-api.test')
            ->maxContentWidth(Width::Full)
            ->spa()
            // ->unsavedChangesAlerts()
            ->databaseTransactions()
            // ->topNavigation()
            ->id('admin')
            ->path('')
            ->login(Login::class)
            // ->login()
            // ->profile(Profile::class)
            // ->dashboard(Dashboard::class)
            ->sidebarCollapsibleOnDesktop()
            // ->font('Poppins')
            // ->brandName('Filament Demo')
            // ->brandLogo(asset('images/skin_care_logo.jpg'))
            // ->brandLogoHeight('6rem')
            ->userMenuItems([
                // 'profile' => fn (Action $action) => $action->label('Edit profile')->icon('heroicon-o-user'),
                // 'logout' => fn (Action $action) => $action->label('Log out'),
                'profile' => MenuItem::make()
                    ->label('My Profile')
                    ->icon('heroicon-o-user-circle')
                    ->url(fn (): string => route('filament.admin.pages.profile')),
            ])
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
                // Dashboard::class,
                // Profile::class
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // AccountWidget::class,
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
            ->databaseNotifications()
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                function (): string {
                    $user = Auth::user();

                    if (! $user) return '';

                    // Adjust this according to how you store roles
                    $role = $user->roles()->pluck('name')->first();

                    $name = e($user->name);
                    $roleText = ucwords(str_replace('_', ' ', e($role ?? '')));
                    $clinic_name = !$user->hasRole('super_admin') ? $user->clinic->name : null;

                    return <<<HTML
                        <div class="cb-topbar-center">
                            <div>
                                {$name}
                                <span style="font-size: 13px; color: #6b7280; font-weight: 400;">
                                    ({$roleText})
                                </span>
                                <br>
                                <span style="font-size: 13px; color: #6b7280; font-weight: 400;">$clinic_name</span>
                            </div>
                        </div>
                        <style>
                            .cb-topbar-center {
                                text-align:center;
                                position: absolute;
                                left: 50%;
                                top: 50%;
                                transform: translate(-50%, -50%);
                                font-size: 16px;
                                font-weight: 600;
                                color: #111827;
                                pointer-events: none;
                            }
                            @media (max-width: 1024px) {
                                .cb-topbar-center {
                                    display: none;
                                }
                            }
                        </style>
                    HTML;
                }
            )
            ->navigationGroups([
                // NavigationGroup::make()
                //     ->label('Auth Management')
                //     ->icon('heroicon-o-academic-cap'),
                //     // ->collapsed(),

                NavigationGroup::make()
                    ->label('Therapist Management'),
                    // ->icon('heroicon-o-hand-raised'),
            ]);
    }

    // public function boot(): void
    // {
    //     FilamentAsset::register([
    //         Css::make('custom-styles', Vite::asset('resources/css/custom.css')),
    //         // Js::make('awin-hotfix', resource_path('js/awin-hotfix.js')),
    //     ]);
    // }
}
