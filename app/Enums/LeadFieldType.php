<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * The storage and display type of a dynamic lead custom field.
 *
 * Meta lead form questions are almost always closed sets — every question in
 * the sample exports has six or fewer distinct answers — so detecting Select
 * rather than defaulting to Text is what makes the answers filterable and
 * chartable instead of being dead free text.
 */
enum LeadFieldType: string implements HasColor, HasIcon, HasLabel
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
    case Select = 'select';
    case MultiSelect = 'multiselect';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Textarea => 'Long Text',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Boolean => 'Yes / No',
            self::Select => 'Single Choice',
            self::MultiSelect => 'Multiple Choice',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Text, self::Textarea => 'gray',
            self::Number => 'info',
            self::Date => 'warning',
            self::Boolean => 'success',
            self::Select => 'primary',
            self::MultiSelect => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Text => 'heroicon-o-bars-3-bottom-left',
            self::Textarea => 'heroicon-o-document-text',
            self::Number => 'heroicon-o-hashtag',
            self::Date => 'heroicon-o-calendar',
            self::Boolean => 'heroicon-o-check-circle',
            self::Select => 'heroicon-o-list-bullet',
            self::MultiSelect => 'heroicon-o-queue-list',
        };
    }

    /**
     * Whether values of this type are stored as an array in value_json.
     */
    public function isMultiValue(): bool
    {
        return $this === self::MultiSelect;
    }

    /**
     * Whether this type carries a fixed option list worth exposing as a filter.
     */
    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::MultiSelect], true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case): array => $carry + [$case->value => $case->getLabel()],
            []
        );
    }
}
