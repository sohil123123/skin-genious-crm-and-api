<?php

declare(strict_types=1);

namespace App\Http\Requests\Lead;

/**
 * Rules for editing an existing lead.
 *
 * Identical to creation apart from the ability, since every field on a lead
 * remains editable after import — correcting a salvaged phone number is the
 * whole point of the "needs review" flag.
 */
class UpdateLeadRequest extends StoreLeadRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('Update:Lead') ?? false;
    }
}
