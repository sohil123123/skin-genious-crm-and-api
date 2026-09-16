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
use Illuminate\Support\Str;
// use Illuminate\Support\Facades\URL;

use Filament\Facades\Filament;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

use App\Filament\Pages\ActivityLog;

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
                    ->url(fn(): string => route('filament.admin.pages.profile')),
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
                ActivityLog::class
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
                    ->navigationSort(41)  // Position in nav
                    ->navigationGroup('Security'),  // Group under a label
                // ->showGlobalSearch(false)  // Disable search
                // ->showInTenancy(false),  // Hide in multi-tenant setups

                // FilamentAwinTheme::make()->primaryColor(Color::Emerald),
                FilamentNordThemePlugin::make(),

                FilamentFullCalendarPlugin::make()
                    // ->selectable()  // Optional: Allow selecting dates for new events
                    ->editable()    // Optional: Allow dragging/resizing events
                // ->timezone('UTC')  // Optional: Set your app's timezone
                // ->config([
                //     'initialDate' => now()->format('Y-m-d'),  // Always start on today
                //     'initialView' => 'timeGridDay',  // Defaults to today's view
                //     'firstDay' => 1,  // Optional: Start week on Monday
                //     'headerToolbar' => [
                //         'left' => 'prev,next',
                //         'center' => 'title',
                //         'right' => 'today,dayGridWeek,timeGridDay',
                //     ],
                //     'slotMinTime' => '08:00:00',  // Appointments from 8 AM
                //     'slotMaxTime' => '20:00:00',  // To 8 PM
                //     'slotDuration' => '00:15:00',  // 30-min slots
                //     'businessHours' => [
                //         [
                //             'daysOfWeek' => [1, 2, 3, 4, 5],  // Mon-Fri only (1=Mon, 7=Sun)
                //             'startTime' => '08:00',
                //             'endTime' => '12:00',  // Morning shift
                //         ],
                //         [
                //             'daysOfWeek' => [1, 2, 3, 4, 5],
                //             'startTime' => '13:00',  // After lunch
                //             'endTime' => '18:00',    // End at 6 PM
                //         ],
                //     ],
                //     'dayHeaderClassNames' => ['fc-business-hours'],
                //     // 'dayHeaderClassNames' => function ($info) {
                //     //     return $info.date.getDay() === 0 || $info.date.getDay() === 6 ? ['fc-non-business'] : [];
                //     // },
                // ])
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                function (): string {
                    $user = Auth::user();

                    if (!$user)
                        return '';

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
            ->renderHook(
                PanelsRenderHook::BODY_END,
                function (): string {
                    $userId = Auth::id();
                    if (!$userId)
                        return '';

                    return <<<HTML
                        <script>
                            document.addEventListener('DOMContentLoaded', () => {
                                const stopBtn = document.createElement('button');
                                stopBtn.innerHTML = '🛑 Stop Sound';
                                stopBtn.style.cssText = 'display:none; position:fixed; bottom:30px; right:30px; z-index:99999; padding:12px 24px; background-color:#ef4444; color:white; border:none; border-radius:8px; font-weight:bold; font-size:16px; cursor:pointer; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: all 0.2s;';
                                document.body.appendChild(stopBtn);

                                let currentAudio = null;

                                stopBtn.addEventListener('click', () => {
                                    if (currentAudio) {
                                        currentAudio.pause();
                                        currentAudio.currentTime = 0;
                                    }
                                    stopBtn.style.display = 'none';
                                });

                                stopBtn.addEventListener('mouseenter', () => { stopBtn.style.backgroundColor = '#dc2626'; });
                                stopBtn.addEventListener('mouseleave', () => { stopBtn.style.backgroundColor = '#ef4444'; });

                                let initEcho = () => {
                                    if (window.Echo) {
                                        window.Echo.private('App.Models.User.{$userId}')
                                            .notification((notification) => {
                                                if (currentAudio) {
                                                    currentAudio.pause();
                                                    currentAudio.currentTime = 0;
                                                }
                                                currentAudio = new Audio('/audio/doorbell.mp3');
                                                currentAudio.play().then(() => {
                                                    stopBtn.style.display = 'block';
                                                }).catch(e => console.error("Error playing sound:", e));

                                                currentAudio.addEventListener('ended', () => {
                                                    stopBtn.style.display = 'none';
                                                });
                                            });
                                    } else {
                                        setTimeout(initEcho, 200);
                                    }
                                };
                                initEcho();
                            });
                        </script>
                    HTML;
                }
            )
            // The live incoming-call popup, on every panel page — a ringing
            // phone has to reach whichever screen the receptionist is actually
            // looking at, not just the calls list.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn(): string => view('filament.incoming-call-popup')->render(),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn(): string => \Illuminate\Support\Facades\Blade::render(<<<'HTML'
                    @vite('resources/js/app.js')
                    <style>
                        /* .invoice-status-paid {
                            background-color: #f0fdf4 !important;
                        } */
                        .invoice-status-partial, .invoice-status-unpaid {
                            background-color: #fef2f2 !important;
                        }
                        /* .dark .invoice-status-paid {
                            background-color: rgba(6, 78, 59, 0.2) !important;
                        } */
                        .dark .invoice-status-partial, .dark .invoice-status-unpaid {
                            background-color: rgba(127, 29, 29, 0.2) !important;
                        }

                        /*
                         * Calls table: the two card cells.
                         *
                         * The panel registers no viteTheme, so no compiled
                         * Tailwind reaches it — these are the classes the call
                         * view columns are written against
                         * (resources/views/filament/tables/columns/call-card
                         * and call-agent). Colours come from the panel's own
                         * palette variables so they follow the theme and dark
                         * mode without a second set of rules.
                         */
                        .sgc {
                            display: flex;
                            align-items: flex-start;
                            gap: 0.75rem;
                            padding: 0.5rem 0.25rem;
                            width: 30rem;
                            max-width: 100%;
                        }

                        /* Direction and outcome in one glyph: an arrow in is a
                           call we received, and its colour says whether anyone
                           actually spoke to them. */
                        .sgc-glyph {
                            flex: 0 0 auto;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            width: 2.25rem;
                            height: 2.25rem;
                            border-radius: 9999px;
                            margin-top: 0.125rem;
                        }

                        .sgc-glyph svg { width: 1.125rem; height: 1.125rem; }

                        .sgc-glyph--ok { background: var(--success-50); color: var(--success-600); }
                        .sgc-glyph--bad { background: var(--danger-50); color: var(--danger-600); }
                        .sgc-glyph--idle { background: var(--gray-100); color: var(--gray-500); }

                        .dark .sgc-glyph--ok { background: color-mix(in srgb, var(--success-500) 16%, transparent); color: var(--success-400); }
                        .dark .sgc-glyph--bad { background: color-mix(in srgb, var(--danger-500) 16%, transparent); color: var(--danger-400); }
                        .dark .sgc-glyph--idle { background: color-mix(in srgb, var(--gray-500) 16%, transparent); color: var(--gray-400); }

                        .sgc-body { min-width: 0; display: flex; flex-direction: column; gap: 0.25rem; }

                        .sgc-head { display: flex; align-items: center; flex-wrap: wrap; gap: 0.375rem; }

                        .sgc-name {
                            font-weight: 600;
                            font-size: 0.875rem;
                            color: var(--gray-950);
                            overflow: hidden;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                            max-width: 18rem;
                        }

                        .dark .sgc-name { color: var(--gray-50); }

                        .sgc-meta {
                            display: flex;
                            align-items: center;
                            flex-wrap: wrap;
                            gap: 0.4375rem;
                            font-size: 0.75rem;
                            color: var(--gray-600);
                        }

                        .dark .sgc-meta { color: var(--gray-400); }

                        .sgc-meta-item { display: inline-flex; align-items: center; gap: 0.25rem; }
                        .sgc-meta-item svg { width: 0.875rem; height: 0.875rem; opacity: 0.7; }

                        .sgc-sep { color: var(--gray-300); }
                        .dark .sgc-sep { color: var(--gray-600); }

                        .sgc-muted { color: var(--gray-500); }
                        .dark .sgc-muted { color: var(--gray-500); }

                        .sgc-warn { color: var(--warning-600); }
                        .dark .sgc-warn { color: var(--warning-400); }

                        /* Phone numbers line up down the column. */
                        .sgc-num { font-variant-numeric: tabular-nums; }

                        /* Roomier than the meta rows: these hold full-size
                           Filament badges, which carry their own padding. */
                        .sgc-foot { display: flex; align-items: center; flex-wrap: wrap; gap: 0.375rem; margin-top: 0.25rem; }

                        /*
                            A badge that opens something has to look like it
                            does. The button is stripped back to nothing so the
                            badge inside keeps its own shape, and the affordance
                            is carried by the cursor and a lift on hover.
                        */
                        .sgc-badge-button {
                            padding: 0; border: 0; background: none; cursor: pointer;
                            line-height: 0; border-radius: 999px;
                            transition: transform .12s ease, filter .12s ease;
                        }

                        .sgc-badge-button:hover { transform: translateY(-1px); filter: brightness(1.08); }
                        .sgc-badge-button:focus-visible { outline: 2px solid var(--primary-500, #16a34a); outline-offset: 2px; }
                        .sgc-badge-button:disabled { opacity: .5; cursor: progress; }

                        /* Handled by */
                        .sgc-own { display: flex; align-items: flex-start; gap: 0.625rem; padding: 0.5rem 0.25rem; }

                        .sgc-own-avatar {
                            flex: 0 0 auto;
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            width: 2rem;
                            height: 2rem;
                            border-radius: 9999px;
                            font-size: 0.6875rem;
                            font-weight: 700;
                            letter-spacing: 0.02em;
                        }

                        .sgc-own-avatar-primary { background: var(--primary-50); color: var(--primary-600); }
                        .sgc-own-avatar-success { background: var(--success-50); color: var(--success-600); }
                        .sgc-own-avatar-warning { background: var(--warning-50); color: var(--warning-600); }
                        .sgc-own-avatar-danger { background: var(--danger-50); color: var(--danger-600); }
                        .sgc-own-avatar-info { background: var(--info-50); color: var(--info-600); }
                        .sgc-own-avatar-gray { background: var(--gray-100); color: var(--gray-500); }

                        .dark .sgc-own-avatar-primary { background: color-mix(in srgb, var(--primary-500) 16%, transparent); color: var(--primary-400); }
                        .dark .sgc-own-avatar-success { background: color-mix(in srgb, var(--success-500) 16%, transparent); color: var(--success-400); }
                        .dark .sgc-own-avatar-warning { background: color-mix(in srgb, var(--warning-500) 16%, transparent); color: var(--warning-400); }
                        .dark .sgc-own-avatar-danger  { background: color-mix(in srgb, var(--danger-500) 16%, transparent);  color: var(--danger-400); }
                        .dark .sgc-own-avatar-info    { background: color-mix(in srgb, var(--info-500) 16%, transparent);    color: var(--info-400); }
                        .dark .sgc-own-avatar-gray    { background: color-mix(in srgb, var(--gray-500) 16%, transparent);    color: var(--gray-400); }

                        .sgc-own-body { min-width: 0; display: flex; flex-direction: column; gap: 0.125rem; }

                        .sgc-own-name {
                            font-weight: 600;
                            font-size: 0.8125rem;
                            color: var(--gray-950);
                            overflow: hidden;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                            max-width: 12rem;
                        }

                        .dark .sgc-own-name { color: var(--gray-50); }

                        .sgc-own-row {
                            display: inline-flex;
                            align-items: center;
                            gap: 0.25rem;
                            font-size: 0.6875rem;
                            color: var(--gray-600);
                            overflow: hidden;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                            max-width: 12rem;
                        }

                        .dark .sgc-own-row { color: var(--gray-400); }
                        .sgc-own-row svg { width: 0.75rem; height: 0.75rem; opacity: 0.7; flex: 0 0 auto; }

                        /* A call nobody could attribute is tinted, so the rows
                           that need a human are visible without reading a
                           column. */
                        .fi-row-call-unmatched { background-color: rgba(251, 191, 36, 0.07) !important; }
                        .dark .fi-row-call-unmatched { background-color: rgba(180, 83, 9, 0.12) !important; }

                        /* An enquiry that turned into a client. Green, and kept
                           faint on purpose: this is the good outcome and wants
                           to be countable down the page, not to shout over the
                           rows that still need working. The left edge does the
                           work — a tint alone is easy to miss against the
                           zebra striping, and easy to mistake for a hover
                           state. */
                        .fi-row-lead-converted {
                            background-color: rgba(16, 185, 129, 0.06) !important;
                            box-shadow: inset 3px 0 0 0 rgba(16, 185, 129, 0.55);
                        }

                        .dark .fi-row-lead-converted {
                            background-color: rgba(16, 185, 129, 0.10) !important;
                            box-shadow: inset 3px 0 0 0 rgba(52, 211, 153, 0.5);
                        }

                        /* Recording column: one button, one shared player. */
                        .sgc-rec { display: inline-flex; align-items: center; gap: 0.5rem; }

                        .sgc-rec-btn {
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            width: 2rem;
                            height: 2rem;
                            border-radius: 9999px;
                            background: var(--primary-50);
                            color: var(--primary-600);
                            cursor: pointer;
                            flex: 0 0 auto;
                            transition: background-color .15s, transform .1s;
                        }

                        .sgc-rec-btn:hover { background: var(--primary-100); }
                        .sgc-rec-btn:active { transform: scale(0.94); }

                        .dark .sgc-rec-btn { background: color-mix(in srgb, var(--primary-500) 18%, transparent); color: var(--primary-400); }
                        .dark .sgc-rec-btn:hover { background: color-mix(in srgb, var(--primary-500) 28%, transparent); }

                        /* Playing reads as a different control, not the same one
                           with a swapped glyph. */
                        .sgc-rec-btn.is-playing { background: var(--success-50); color: var(--success-600); }
                        .dark .sgc-rec-btn.is-playing { background: color-mix(in srgb, var(--success-500) 18%, transparent); color: var(--success-400); }

                        .sgc-rec-icon { display: inline-flex; }
                        .sgc-rec-icon svg { width: 1rem; height: 1rem; }
                        .sgc-rec-icon[data-sgc-icon="wait"] svg { animation: sgc-spin 1s linear infinite; }

                        @keyframes sgc-spin { to { transform: rotate(360deg); } }

                        .sgc-rec-body { display: flex; flex-direction: column; line-height: 1.25; }

                        .sgc-rec-time {
                            font-size: 0.75rem;
                            font-variant-numeric: tabular-nums;
                            color: var(--gray-700);
                        }

                        .dark .sgc-rec-time { color: var(--gray-300); }

                        .sgc-rec-note { font-size: 0.6875rem; color: var(--gray-500); }

                        .sgc-rec-empty {
                            display: inline-flex;
                            align-items: center;
                            gap: 0.25rem;
                            font-size: 0.75rem;
                            color: var(--gray-500);
                        }

                        .sgc-rec-empty svg { width: 0.875rem; height: 0.875rem; opacity: 0.7; }

                        /* Detail page: one native player per recording. */
                        .sgc-rec-item { margin-bottom: 1rem; }
                        .sgc-rec-item:last-child { margin-bottom: 0; }

                        /*
                            Hidden, not removed. The element is still the thing
                            that plays; only its chrome is ours, so seeking,
                            buffering and the solo behaviour all keep working
                            through the same API.
                        */
                        .sgc-rec-audio { display: none; }

                        .sgc-player {
                            padding: 0.625rem 0.875rem;
                            background: var(--gray-50, #f9fafb);
                            border: 1px solid rgba(17, 24, 39, .08);
                            border-radius: 0.75rem;
                        }

                        /* Button and rail on one line, sharing a centre. */
                        .sgc-player-row { display: flex; align-items: center; gap: 0.75rem; }

                        .sgc-player-toggle {
                            position: relative;
                            flex: 0 0 auto;
                            width: 2.25rem; height: 2.25rem;
                            display: grid; place-items: center;
                            border: 0; border-radius: 999px; cursor: pointer;
                            color: #fff; background: var(--primary-600, #16a34a);
                            transition: transform .12s ease, filter .12s ease;
                        }

                        .sgc-player-toggle:hover { filter: brightness(1.08); transform: scale(1.04); }
                        .sgc-player-toggle:focus-visible { outline: 2px solid var(--primary-500, #16a34a); outline-offset: 2px; }

                        /*
                            Pressed, briefly and physically. A control that
                            starts something several seconds away — a stream has
                            to be fetched before a sound arrives — needs to
                            acknowledge the press at the moment of pressing, or
                            it gets pressed again.
                        */
                        .sgc-player-toggle:active { transform: scale(0.9); filter: brightness(0.95); }

                        /*
                            And a ring while it plays, so a page holding several
                            recordings says which one is audible without anyone
                            reading two small icons to work it out.
                        */
                        .sgc-player.is-playing .sgc-player-toggle::after {
                            content: ''; position: absolute; inset: 0;
                            border-radius: 999px;
                            border: 2px solid var(--primary-600, #16a34a);
                            animation: sgc-player-pulse 1.6s ease-out infinite;
                        }

                        @keyframes sgc-player-pulse {
                            0% { opacity: .6; transform: scale(1); }
                            100% { opacity: 0; transform: scale(1.7); }
                        }

                        .sgc-player-icon { width: 1.05rem; height: 1.05rem; }

                        /* Generous hit area around a 4px bar: the bar is the
                           thing to look at, not the thing to hit. */
                        .sgc-player-rail {
                            position: relative; flex: 1 1 auto; min-width: 0;
                            height: 1.25rem; cursor: pointer;
                            display: flex; align-items: center;
                        }

                        .sgc-player-rail::before {
                            content: ''; position: absolute; inset-inline: 0;
                            height: 4px; border-radius: 999px;
                            background: rgba(17, 24, 39, .12);
                        }

                        .sgc-player-buffer, .sgc-player-fill {
                            position: absolute; inset-inline-start: 0;
                            height: 4px; border-radius: 999px; width: 0;
                        }

                        .sgc-player-buffer { background: rgba(17, 24, 39, .18); }
                        .sgc-player-fill { background: var(--primary-600, #16a34a); }

                        /*
                            Always visible, not hover-only. It marks where the
                            playhead is, which is worth seeing at rest — and on
                            a touch screen there is no hover to reveal it with.
                        */
                        .sgc-player-knob {
                            position: absolute; inset-inline-start: 0;
                            width: 0.75rem; height: 0.75rem; margin-inline-start: -0.375rem;
                            border-radius: 999px; background: var(--primary-600, #16a34a);
                            box-shadow: 0 1px 3px rgba(0, 0, 0, .25);
                            transition: transform .12s ease;
                        }

                        .sgc-player:hover .sgc-player-knob,
                        .sgc-player-rail:focus-visible .sgc-player-knob { transform: scale(1.2); }

                        /*
                            Indented to start where the rail starts — button
                            width plus the gap — so the elapsed time sits under
                            the position it describes rather than under the
                            button.
                        */
                        .sgc-player-times {
                            display: flex; align-items: center; justify-content: space-between;
                            gap: 0.5rem; margin-top: 0.125rem;
                            padding-inline-start: 3rem;
                            font-size: 0.6875rem; color: #6b7280;
                            font-variant-numeric: tabular-nums;
                        }

                        .sgc-player-times .sgc-rec-meta { margin: 0; }

                        .dark .sgc-player { background: rgba(255, 255, 255, .03); border-color: rgba(255, 255, 255, .08); }
                        .dark .sgc-player-rail::before { background: rgba(255, 255, 255, .15); }
                        .dark .sgc-player-buffer { background: rgba(255, 255, 255, .22); }
                        .dark .sgc-player-times { color: #9ca3af; }

                        @media (prefers-reduced-motion: reduce) {
                            .sgc-player-toggle, .sgc-player-knob { transition: none; }
                            .sgc-player:hover .sgc-player-knob { transform: none; }
                            .sgc-player-toggle:active { transform: none; }
                            .sgc-player.is-playing .sgc-player-toggle::after { animation: none; opacity: .5; }
                            .sgc-player-toggle:hover { transform: none; }
                        }

                        .sgc-rec-meta {
                            margin: 0.375rem 0 0;
                            font-size: 0.75rem;
                            color: var(--gray-500);
                        }

                        .sgc-rec-msg { margin: 0; font-size: 0.8125rem; color: var(--gray-500); }

                        .sgc-rec-err {
                            margin: 0.25rem 0 0;
                            font-size: 0.75rem;
                            color: var(--danger-600);
                            word-break: break-word;
                        }

                        .dark .sgc-rec-err { color: var(--danger-400); }

                        @media (max-width: 1024px) {
                            .sgc { width: 100%; }
                            .sgc-name { max-width: 100%; white-space: normal; }
                        }
                    </style>

                    <script>
                        /*
                         * One audio element for the whole panel.
                         *
                         * A player per table row would hold fifty media elements
                         * on a fifty-row page, and starting a second recording
                         * would leave the first one talking over it. A single
                         * shared element makes "only one plays at a time" the
                         * default rather than something to enforce.
                         *
                         * Registered on window rather than in a module so the
                         * inline handlers in the table cell can reach it, and
                         * guarded so SPA navigation cannot build a second one.
                         */
                        window.sgCallAudio = window.sgCallAudio || (() => {
                            const el = new Audio();
                            el.preload = 'none';

                            let button = null;

                            const icon = (name, show) => {
                                const node = button?.querySelector(`[data-sgc-icon="${name}"]`);

                                if (node) {
                                    node.hidden = ! show;
                                }
                            };

                            const clock = (seconds) => {
                                if (! Number.isFinite(seconds)) {
                                    return null;
                                }

                                const whole = Math.floor(seconds);

                                return `${Math.floor(whole / 60)}:${String(whole % 60).padStart(2, '0')}`;
                            };

                            const paint = (state) => {
                                if (! button) {
                                    return;
                                }

                                icon('play', state === 'idle');
                                icon('pause', state === 'playing');
                                icon('wait', state === 'loading');

                                button.classList.toggle('is-playing', state === 'playing');
                                button.setAttribute('title', state === 'playing' ? 'Pause' : 'Play this recording');
                            };

                            // The row keeps its own total length, so restoring it
                            // on stop is just remembering what was there.
                            const time = (text) => {
                                const node = button?.parentElement?.querySelector('[data-sgc-time]');

                                if (node && text !== null) {
                                    node.textContent = text;
                                }
                            };

                            const release = () => {
                                if (! button) {
                                    return;
                                }

                                paint('idle');
                                time(button.dataset.sgcTotal ?? null);
                                button = null;
                            };

                            el.addEventListener('playing', () => paint('playing'));
                            el.addEventListener('waiting', () => paint('loading'));
                            el.addEventListener('pause', () => paint('idle'));
                            el.addEventListener('ended', release);

                            el.addEventListener('error', () => {
                                // A 404 from the stream route means the file is
                                // gone from disk. Say so on the row rather than
                                // leaving a button that silently does nothing.
                                time('Unavailable');
                                paint('idle');
                                button = null;
                            });

                            el.addEventListener('timeupdate', () => {
                                if (button) {
                                    time(clock(el.currentTime));
                                }
                            });

                            const stop = () => {
                                el.pause();
                                el.removeAttribute('src');
                                release();
                            };

                            return {
                                stop,

                                toggle(trigger) {
                                    const src = trigger.dataset.sgcAudio;

                                    if (! src) {
                                        return;
                                    }

                                    // Same row, already playing: pause in place
                                    // rather than restarting from zero.
                                    if (button === trigger && ! el.paused) {
                                        el.pause();
                                        paint('idle');

                                        return;
                                    }

                                    if (button === trigger && el.paused && el.currentSrc) {
                                        el.play();

                                        return;
                                    }

                                    // A different row: whatever was playing stops.
                                    if (button && button !== trigger) {
                                        stop();
                                    }

                                    button = trigger;
                                    button.dataset.sgcTotal = button.parentElement
                                        ?.querySelector('[data-sgc-time]')?.textContent ?? '';

                                    paint('loading');
                                    el.src = src;
                                    el.play().catch(() => {
                                        time('Unavailable');
                                        paint('idle');
                                        button = null;
                                    });
                                },
                            };
                        })();

                        /*
                         * The detail page uses native <audio> elements rather
                         * than the shared one, because reviewing a call means
                         * scrubbing and a play/pause button cannot seek. A call
                         * can carry several recordings, so this keeps them from
                         * talking over each other — the same guarantee the
                         * table's single shared element gets for free.
                         */
                        window.sgCallAudioSolo = window.sgCallAudioSolo || function (playing) {
                            document.querySelectorAll('audio.sgc-rec-audio').forEach((other) => {
                                if (other !== playing) {
                                    other.pause();
                                }
                            });
                        };

                        /*
                         * The detail-page player.
                         *
                         * Delegated from the document rather than bound per
                         * element: the infolist is re-rendered by Livewire on
                         * every action, and handlers attached to the old nodes
                         * would be lost without anybody noticing until a button
                         * stopped responding.
                         */
                        window.sgCallPlayer = window.sgCallPlayer || (() => {
                            const clock = (seconds) => {
                                if (!isFinite(seconds) || seconds < 0) return '--:--';
                                const m = Math.floor(seconds / 60);
                                const s = Math.floor(seconds % 60);
                                return m + ':' + String(s).padStart(2, '0');
                            };

                            const paint = (player) => {
                                const audio = player.querySelector('audio');
                                if (!audio) return;

                                const ratio = audio.duration > 0 ? audio.currentTime / audio.duration : 0;
                                const fill = player.querySelector('[data-sgc-fill]');
                                const knob = player.querySelector('[data-sgc-knob]');
                                const rail = player.querySelector('[data-sgc-rail]');

                                if (fill) fill.style.width = (ratio * 100) + '%';
                                if (knob) knob.style.insetInlineStart = (ratio * 100) + '%';
                                if (rail) rail.setAttribute('aria-valuenow', Math.round(ratio * 100));

                                const current = player.querySelector('[data-sgc-current]');
                                const duration = player.querySelector('[data-sgc-duration]');
                                if (current) current.textContent = clock(audio.currentTime);
                                if (duration) duration.textContent = clock(audio.duration);

                                // Buffered ahead of the playhead, so a slow
                                // connection looks like loading rather than like
                                // a player that has stopped.
                                const buffer = player.querySelector('[data-sgc-buffer]');
                                if (buffer && audio.buffered.length && audio.duration > 0) {
                                    const end = audio.buffered.end(audio.buffered.length - 1);
                                    buffer.style.width = ((end / audio.duration) * 100) + '%';
                                }

                                const playing = !audio.paused && !audio.ended;
                                const play = player.querySelector('[data-sgc-icon="play"]');
                                const pause = player.querySelector('[data-sgc-icon="pause"]');
                                if (play) play.hidden = playing;
                                if (pause) pause.hidden = !playing;

                                const toggle = player.querySelector('[data-sgc-toggle]');
                                if (toggle) toggle.setAttribute('aria-label', playing ? 'Pause recording' : 'Play recording');

                                // Carries the state to CSS. The icon swap alone
                                // is a small target to read across a page, and
                                // a card with several recordings needs to say
                                // which one is the one you can hear.
                                player.classList.toggle('is-playing', playing);
                            };

                            const seek = (player, clientX) => {
                                const audio = player?.querySelector('audio');
                                const rail = player?.querySelector('[data-sgc-rail]');
                                if (!audio || !rail || !(audio.duration > 0)) return;

                                const box = rail.getBoundingClientRect();
                                const ratio = Math.min(1, Math.max(0, (clientX - box.left) / box.width));
                                audio.currentTime = ratio * audio.duration;
                                paint(player);
                            };

                            document.addEventListener('click', (event) => {
                                const toggle = event.target.closest('[data-sgc-toggle]');

                                if (toggle) {
                                    const player = toggle.closest('[data-sgc-player]');
                                    const audio = player?.querySelector('audio');
                                    if (audio) { audio.paused ? audio.play() : audio.pause(); }
                                    return;
                                }

                                const rail = event.target.closest('[data-sgc-rail]');
                                if (rail) seek(rail.closest('[data-sgc-player]'), event.clientX);
                            });

                            // Arrow keys on the rail, because scrubbing a call
                            // with a mouse alone excludes anyone who cannot.
                            document.addEventListener('keydown', (event) => {
                                const rail = event.target.closest ? event.target.closest('[data-sgc-rail]') : null;
                                if (!rail) return;

                                const player = rail.closest('[data-sgc-player]');
                                const audio = player?.querySelector('audio');
                                if (!audio) return;

                                if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
                                    event.preventDefault();
                                    audio.currentTime += event.key === 'ArrowRight' ? 5 : -5;
                                } else if (event.key === ' ' || event.key === 'Enter') {
                                    event.preventDefault();
                                    audio.paused ? audio.play() : audio.pause();
                                }
                            });

                            // Captured, because media events do not bubble.
                            ['timeupdate', 'loadedmetadata', 'play', 'pause', 'ended', 'progress'].forEach((name) => {
                                document.addEventListener(name, (event) => {
                                    const audio = event.target;

                                    if (audio && audio.classList && audio.classList.contains('sgc-rec-audio')) {
                                        const player = audio.closest('[data-sgc-player]');
                                        if (player) paint(player);
                                    }
                                }, true);
                            });

                            return { paint };
                        })();

                        // Filament runs in SPA mode, so leaving the list does not
                        // reload the page — without this the audio would keep
                        // playing over whatever screen you moved to.
                        document.addEventListener('livewire:navigating', () => {
                            window.sgCallAudio?.stop();
                            document.querySelectorAll('audio.sgc-rec-audio').forEach((el) => el.pause());
                        });
                    </script>
                HTML)
            )
            ->navigationGroups([
                NavigationGroup::make('Inventory'),
                NavigationGroup::make('Finance'),
                NavigationGroup::make('Reports'),
                NavigationGroup::make('WhatsApp'),
                NavigationGroup::make('Leads'),
                NavigationGroup::make('Calls'),
                NavigationGroup::make('User Scheduling & Holidays'),
                NavigationGroup::make('Others'),
                NavigationGroup::make('Security'),
            ])
            ->navigationItems([
                NavigationItem::make('Logs')
                    ->url(fn(): string => route('log-viewer.index'))
                    ->icon('heroicon-o-document-text')
                    ->group('Others')
                    ->sort(39)
                    ->visible(fn(): bool => auth()->check() && auth()->user()->hasRole('super_admin'))
            ]);
    }

    public function boot(): void
    {
        // Filament::registerNavigationItems([
        //     NavigationItem::make('Create Appointment')
        //         ->url(function () {

        //             $user = auth()->user();

        //             return URL::temporarySignedRoute(
        //                 'vue.sso',
        //                 now()->addMinutes(5), // ⏱ expires
        //                 [
        //                     'user_id'    => $user->id,
        //                     'clinic_id'  => $user->clinic_id,
        //                     'role'       => $user->getRoleNames()->first(),
        //                 ]
        //             );

        //         }, shouldOpenInNewTab: true)
        //         ->icon('heroicon-o-calendar-days'),
        //         // ->group('Custom Links'),
        // ]);



        // FilamentAsset::register([
        //     Css::make('custom-styles', Vite::asset('resources/css/custom.css')),
        //     // Js::make('awin-hotfix', resource_path('js/awin-hotfix.js')),
        // ]);
    }
}
