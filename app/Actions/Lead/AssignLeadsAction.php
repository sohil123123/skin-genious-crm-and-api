<?php

declare(strict_types=1);

namespace App\Actions\Lead;

use App\Models\Lead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk assignment and status changes from the leads table.
 *
 * Written as a single UPDATE rather than a loop of saves because these bulk
 * actions routinely target hundreds of rows selected from a filtered view.
 */
class AssignLeadsAction
{
    /**
     * @param  Collection<int, Lead>  $leads
     * @return int Number of rows affected.
     */
    public function assign(Collection $leads, ?int $userId): int
    {
        return $this->update($leads, ['assigned_to' => $userId]);
    }

    /**
     * @param  Collection<int, Lead>  $leads
     */
    public function changeStatus(Collection $leads, string $status): int
    {
        return $this->update($leads, ['status' => $status]);
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @param  array<string, mixed>  $attributes
     */
    protected function update(Collection $leads, array $attributes): int
    {
        $ids = $leads->pluck('id')->all();

        if ($ids === []) {
            return 0;
        }

        return DB::transaction(fn (): int => Lead::query()
            ->whereIn('id', $ids)
            ->update($attributes + [
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]));
    }
}
