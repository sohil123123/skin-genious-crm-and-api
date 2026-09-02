<?php

declare(strict_types=1);

namespace App\Services\Lead;

use App\DTOs\Lead\ColumnMappingDto;
use App\Models\LeadImport;
use App\Models\LeadMappingTemplate;
use Illuminate\Database\Eloquent\Collection;

/**
 * Saves and recalls column mappings.
 *
 * Meta names every export after the ad and the date range, so the filename is
 * useless for recognising a form. What is stable is the set of questions, so a
 * template is keyed by a signature over its headers: re-export the same form
 * next month and the mapping is offered back automatically.
 */
class MappingTemplateService
{
    /**
     * Find the template most likely to fit an uploaded file.
     *
     * @param  array<int, string>  $headers
     */
    public function suggestFor(array $headers, ?int $clinicId): ?LeadMappingTemplate
    {
        $signature = LeadMappingTemplate::buildSignature($headers);

        $exact = LeadMappingTemplate::query()
            ->matchingSignature($signature)
            ->where(fn ($query) => $query->where('clinic_id', $clinicId)->orWhereNull('clinic_id'))
            ->orderByDesc('usage_count')
            ->first();

        if ($exact !== null) {
            return $exact;
        }

        // No exact signature: fall back to the template with the greatest
        // header overlap, which catches a form that gained or lost one
        // question since the template was saved.
        return $this->candidates($clinicId)
            ->map(fn (LeadMappingTemplate $template): array => [
                'template' => $template,
                'overlap' => $template->headerOverlap($headers),
            ])
            ->filter(fn (array $candidate): bool => $candidate['overlap'] >= 80.0)
            ->sortByDesc('overlap')
            ->first()['template'] ?? null;
    }

    /**
     * Persist the mapping used on an import so it can be reused.
     *
     * @param  array<string, ColumnMappingDto>  $mapping
     */
    public function save(
        string $name,
        array $headers,
        array $mapping,
        LeadImport $import,
        ?string $description = null,
    ): LeadMappingTemplate {
        return LeadMappingTemplate::updateOrCreate(
            [
                'clinic_id' => $import->clinic_id,
                'name' => $name,
            ],
            [
                'description' => $description,
                'signature' => LeadMappingTemplate::buildSignature($headers),
                'header_columns' => $headers,
                'mapping' => $this->serializeMapping($mapping),
                'settings' => $import->settings,
                'duplicate_strategy' => $import->duplicate_strategy?->value ?? 'skip',
                'duplicate_match_fields' => $import->duplicate_match_fields,
                'created_by' => auth()->id(),
            ]
        );
    }

    /**
     * Rebuild a mapping from a saved template, restricted to the headers the
     * current file actually has.
     *
     * A form that gained a question since the template was saved keeps the
     * saved decisions for the columns it recognises and leaves the new column
     * for the auto-mapper, rather than failing outright.
     *
     * @param  array<int, string>  $headers
     * @return array<string, ColumnMappingDto>
     */
    public function applyTo(LeadMappingTemplate $template, array $headers): array
    {
        $saved = $template->mapping ?? [];
        $mapping = [];

        foreach ($headers as $header) {
            if (! isset($saved[$header])) {
                continue;
            }

            $definition = $saved[$header];

            $mapping[$header] = ColumnMappingDto::fromArray(
                is_array($definition) ? $definition : ['csv_column' => $header, 'target' => (string) $definition]
            );
        }

        return $mapping;
    }

    /**
     * @param  array<string, ColumnMappingDto>  $mapping
     * @return array<string, array<string, mixed>>
     */
    public function serializeMapping(array $mapping): array
    {
        $serialized = [];

        foreach ($mapping as $column => $dto) {
            $serialized[$column] = $dto->toArray();
        }

        return $serialized;
    }

    /**
     * @return Collection<int, LeadMappingTemplate>
     */
    protected function candidates(?int $clinicId): Collection
    {
        return LeadMappingTemplate::query()
            ->where(fn ($query) => $query->where('clinic_id', $clinicId)->orWhereNull('clinic_id'))
            ->orderByDesc('usage_count')
            ->limit(50)
            ->get();
    }
}
