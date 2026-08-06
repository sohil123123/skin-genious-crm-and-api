<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

use App\Filament\Widgets\AiMorningSummary;
use App\Filament\Widgets\AiActionQueue;
use App\Filament\Widgets\AiCapacityGaps;

class AiDashboard extends Page
{
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

    protected function getHeaderWidgets(): array
    {
        return [
            AiMorningSummary::class,
        ];
    }

    /**
     * Middle widgets rendered between header and footer.
     */
    public function getMiddleWidgets(): array
    {
        return [
            AiActionQueue::class,
        ];
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
