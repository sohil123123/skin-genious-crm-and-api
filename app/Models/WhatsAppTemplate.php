<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'meta_template_id',
        'name',
        'category',
        'variable_type',
        'language',
        'components',
        'header_type',
        'header_content',
        'body_text',
        'footer_text',
        'buttons',
        'variable_samples',
        'status',
        'rejected_reason',
        'quality_score',
        'last_synced_at',
    ];

    protected $casts = [
        'components' => 'array',
        'buttons' => 'array',
        'variable_samples' => 'array',
        'last_synced_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED';
    }

    public function isPaused(): bool
    {
        return $this->status === 'PAUSED';
    }

    /*
    |--------------------------------------------------------------------------
    | Variable Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Count the number of unique variables in body_text.
     */
    public function getVariableCount(): int
    {
        return count($this->getVariablePlaceholders());
    }

    /**
     * Extract variable placeholders from body_text in order.
     */
    public function getVariablePlaceholders(): array
    {
        if (empty($this->body_text)) {
            return [];
        }

        $pattern = ($this->variable_type === 'name')
            ? '/\{\{([a-zA-Z_][a-zA-Z0-9_]*)\}\}/'
            : '/\{\{(\d+)\}\}/';

        preg_match_all($pattern, $this->body_text, $matches);

        return array_unique($matches[1]);
    }

    /*
    |--------------------------------------------------------------------------
    */

    /**
     * Get the sample value for a given placeholder.
     * Supports both flat key-value array and Filament repeater format.
     */
    public function getSampleValue(string $placeholder): string
    {
        if (empty($this->variable_samples) || !is_array($this->variable_samples)) {
            return "sample_{$placeholder}";
        }

        // 1. Try to find it in flat key-value format (must not be an array)
        if (array_key_exists($placeholder, $this->variable_samples) && !is_array($this->variable_samples[$placeholder])) {
            return (string) $this->variable_samples[$placeholder];
        }

        // 2. Try to find it in Filament repeater format: [['key' => '1', 'value' => 'value']]
        foreach ($this->variable_samples as $item) {
            if (is_array($item) && isset($item['key']) && (string) $item['key'] === $placeholder) {
                return (string) ($item['value'] ?? '');
            }
        }

        // 3. Fallback
        return "sample_{$placeholder}";
    }

    /*
    |--------------------------------------------------------------------------
    | Component Builder (for Meta API payload)
    |--------------------------------------------------------------------------
    */

    /**
     * Build the components array for the Meta Cloud API.
     */
    public function buildComponentsForApi(): array
    {
        $components = [];

        // Header component
        if ($this->header_type && $this->header_type !== 'none') {
            $header = ['type' => 'HEADER'];

            if ($this->header_type === 'text') {
                $header['format'] = 'TEXT';
                $header['text'] = $this->header_content ?? '';
            }

            $components[] = $header;
        }

        // Body component
        if (!empty($this->body_text)) {
            $body = [
                'type' => 'BODY',
                'text' => $this->body_text,
            ];

            // Add example values for variables
            $placeholders = $this->getVariablePlaceholders();
            if (!empty($placeholders)) {
                $examples = [];
                if ($this->variable_type === 'name') {
                    foreach ($placeholders as $placeholder) {
                        $examples[] = [
                            'param_name' => $placeholder,
                            'example' => $this->getSampleValue($placeholder),
                        ];
                    }
                    $body['example'] = ['body_text_named_params' => $examples];
                } else {
                    foreach ($placeholders as $placeholder) {
                        $examples[] = $this->getSampleValue($placeholder);
                    }
                    $body['example'] = ['body_text' => [$examples]];
                }
            }

            $components[] = $body;
        }

        // Footer component
        if (!empty($this->footer_text)) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $this->footer_text,
            ];
        }

        // Buttons component
        if (!empty($this->buttons)) {
            $buttonItems = [];

            foreach ($this->buttons as $button) {
                $buttonType = $button['type'] ?? 'QUICK_REPLY';
                $btn = [
                    'type' => $buttonType,
                    'text' => $button['text'] ?? '',
                ];

                if ($buttonType === 'URL') {
                    $btn['url'] = $button['url'] ?? '';
                    if (!empty($button['url_example'])) {
                        $btn['example'] = [$button['url_example']];
                    }
                } elseif ($buttonType === 'PHONE_NUMBER') {
                    $btn['phone_number'] = $button['phone_number'] ?? '';
                }

                $buttonItems[] = $btn;
            }

            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => $buttonItems,
            ];
        }

        return $components;
    }
}
