<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

use App\Filament\Widgets\AiMorningSummary;
use App\Filament\Widgets\AiActionQueue;
use App\Filament\Widgets\AiCapacityGaps;
use App\Filament\Widgets\LeadActionQueue;
use App\Filament\Widgets\LeadMorningSummary;
use App\Models\Lead;
use App\Models\LeadActionLog;
use Livewire\Attributes\Url;

class AiDashboard extends Page
{
    public const TAB_PATIENTS = 'clients';

    public const TAB_LEADS = 'leads';

    /**
     * Persisted in the query string so a staff member working the lead queue
     * stays on it across a refresh or a returned-to bookmark.
     */
    #[Url(as: 'tab', keep: true)]
    public string $activeTab = self::TAB_PATIENTS;

    public function setTab(string $tab): void
    {
        $this->activeTab = in_array($tab, [self::TAB_PATIENTS, self::TAB_LEADS], true)
            ? $tab
            : self::TAB_PATIENTS;
    }

    public function isLeadsTab(): bool
    {
        return $this->activeTab === self::TAB_LEADS;
    }

    /**
     * Badge counts shown on the tabs themselves, so the unopened tab still
     * tells you whether it is worth opening.
     *
     * @return array<string, int>
     */
    public function getTabCounts(): array
    {
        $user = auth()->user();
        $clinicId = ($user && ! $user->hasRole('super_admin') && $user->clinic_id) ? $user->clinic_id : null;

        return [
            self::TAB_PATIENTS => \App\Models\AiActionLog::query()
                ->forToday()->active()->pending()
                ->when($clinicId, fn ($q) => $q->forClinic($clinicId))
                ->count(),

            self::TAB_LEADS => LeadActionLog::query()
                ->forToday()->active()->pending()
                ->when($clinicId, fn ($q) => $q->forClinic($clinicId))
                ->count(),
        ];
    }
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Best Action Dashboard';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Next Best Action';

    protected static ?string $slug = 'ai-dashboard';

    protected string $view = 'filament.pages.ai-dashboard';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->hasRole('clinic_manager')
            || $user->hasRole('clinic_head');
    }

    /**
     * Only the active tab's widgets are returned, so the hidden tab issues no
     * queries at all rather than being rendered and visually hidden.
     */
    protected function getHeaderWidgets(): array
    {
        return $this->isLeadsTab()
            ? [LeadMorningSummary::class]
            : [AiMorningSummary::class];
    }

    /**
     * Middle widgets rendered between header and footer.
     */
    public function getMiddleWidgets(): array
    {
        return $this->isLeadsTab()
            ? [LeadActionQueue::class]
            : [AiActionQueue::class];
    }

    public function getMiddleWidgetsColumns(): int|array
    {
        return 1;
    }

    // public function getFooterWidgets(): array
    // {
    //     return [
    //         AiCapacityGaps::class,
    //     ];
    // }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    // public function getFooterWidgetsColumns(): int|array
    // {
    //     return 1;
    // }
}
