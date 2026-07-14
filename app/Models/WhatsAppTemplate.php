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

    /**
     * Build the components array required by the Send Message API.
     *
     * @param array $bodyVariables Key-value array for named, or sequential array for positional
     * @param array $headerVariables Key-value array for named, or sequential array for positional
     * @param array $buttonUrlVariables Sequential array of string values for dynamic URL button parameters (in order of URL buttons)
     */
    public function buildComponentsForSending(
        array $bodyVariables,
        array $headerVariables = [],
        array $buttonUrlVariables = []
    ): array {
        $components = [];

        // 1. Header Components
        if ($this->header_type === 'text' && str_contains($this->header_content ?? '', '{{')) {
            // Find header placeholders
            preg_match_all('/\{\{([^}]+)\}\}/', $this->header_content, $matches);
            $headerPlaceholders = array_unique($matches[1] ?? []);
            if (!empty($headerPlaceholders)) {
                $parameters = [];
                foreach ($headerPlaceholders as $placeholder) {
                    if ($this->variable_type === 'name') {
                        $val = $headerVariables[$placeholder] ?? '';
                        $parameters[] = [
                            'type' => 'text',
                            'parameter_name' => $placeholder,
                            'text' => (string) $val,
                        ];
                    } else {
                        $idx = (int)$placeholder - 1;
                        $val = $headerVariables[$placeholder] ?? $headerVariables[$idx] ?? '';
                        $parameters[] = [
                            'type' => 'text',
                            'text' => (string) $val,
                        ];
                    }
                }
                $components[] = [
                    'type' => 'header',
                    'parameters' => $parameters,
                ];
            }
        } elseif (in_array(strtoupper($this->header_type ?? ''), ['IMAGE', 'DOCUMENT', 'VIDEO'])) {
            // Media header
            $mediaId = $headerVariables['media_id'] ?? $headerVariables[0] ?? null;
            $mediaUrl = $headerVariables['media_url'] ?? null;
            $filename = $headerVariables['filename'] ?? null;

            if ($mediaId || $mediaUrl) {
                $mediaType = strtolower($this->header_type);
                $mediaPayload = [];
                if ($mediaId) {
                    $mediaPayload['id'] = $mediaId;
                } else {
                    $mediaPayload['link'] = $mediaUrl;
                }

                if ($mediaType === 'document' && $filename) {
                    $mediaPayload['filename'] = $filename;
                }

                $components[] = [
                    'type' => 'header',
                    'parameters' => [
                        [
                            'type' => $mediaType,
                            $mediaType => $mediaPayload,
                        ]
                    ],
                ];
            }
        }

        // 2. Body Components
        $bodyPlaceholders = $this->getVariablePlaceholders();
        if (!empty($bodyPlaceholders)) {
            $parameters = [];
            foreach ($bodyPlaceholders as $placeholder) {
                $val = '';
                if ($this->variable_type === 'name') {
                    $val = $bodyVariables[$placeholder] ?? '';
                    $parameters[] = [
                        'type' => 'text',
                        'parameter_name' => $placeholder,
                        'text' => (string) $val,
                    ];
                } else {
                    $idx = (int)$placeholder - 1;
                    $val = $bodyVariables[$placeholder] ?? $bodyVariables[$idx] ?? '';
                    $parameters[] = [
                        'type' => 'text',
                        'text' => (string) $val,
                    ];
                }
            }
            $components[] = [
                'type' => 'body',
                'parameters' => $parameters,
            ];
        }

        // 3. Buttons Component (dynamic URL buttons)
        if (!empty($this->buttons)) {
            $urlButtonIndex = 0;
            $buttonIndex = 0;
            foreach ($this->buttons as $button) {
                $type = $button['type'] ?? 'QUICK_REPLY';
                if ($type === 'URL' && str_contains($button['url'] ?? '', '{{')) {
                    $val = $buttonUrlVariables[$urlButtonIndex] ?? '';
                    $components[] = [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => (string) $buttonIndex,
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => (string) $val,
                            ]
                        ],
                    ];
                    $urlButtonIndex++;
                }
                $buttonIndex++;
            }
        }

        return $components;
    }
}
