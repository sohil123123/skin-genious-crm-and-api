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
     * Build the components array for registering/creating a template in Meta Cloud API.
     */
    public function buildComponentsForApi(): array
    {
        $category = strtoupper($this->category ?? 'UTILITY');

        if ($category === 'AUTHENTICATION') {
            return $this->buildAuthComponentsForApi();
        }

        return $this->buildUtilityOrMarketingComponentsForApi();
    }

    /**
     * Build Meta API registration components specifically for AUTHENTICATION category.
     */
    protected function buildAuthComponentsForApi(): array
    {
        $components = [];

        // 1. Body component (Meta forbids custom text string in BODY for AUTHENTICATION category)
        $body = [
            'type' => 'BODY',
            'add_security_recommendation' => true,
        ];

        // Body variable examples (e.g. [['123456']])
        $placeholders = $this->getVariablePlaceholders();
        if (!empty($placeholders)) {
            $positionalExamples = [];
            foreach ($placeholders as $placeholder) {
                $positionalExamples[] = $this->getSampleValue($placeholder);
            }
            $body['example'] = ['body_text' => [$positionalExamples]];
        } else {
            $body['example'] = ['body_text' => [['123456']]];
        }

        $components[] = $body;

        // 2. Footer component (Meta Cloud API Authentication Expiration)
        $components[] = [
            'type' => 'FOOTER',
            'code_expiration_minutes' => 10,
        ];

        // 3. Buttons component (Mandatory OTP Copy Code button)
        $components[] = [
            'type' => 'BUTTONS',
            'buttons' => [
                [
                    'type' => 'OTP',
                    'otp_type' => 'COPY_CODE',
                    'text' => 'Copy Code',
                ],
            ],
        ];

        return $components;
    }

    /**
     * Build Meta API registration components for UTILITY and MARKETING categories.
     */
    protected function buildUtilityOrMarketingComponentsForApi(): array
    {
        $components = [];

        // 1. Header component
        if ($this->header_type && $this->header_type !== 'none') {
            $header = ['type' => 'HEADER'];

            if ($this->header_type === 'text') {
                $header['format'] = 'TEXT';
                $header['text'] = $this->header_content ?? '';
            }

            $components[] = $header;
        }

        // 2. Body component
        if (!empty($this->body_text)) {
            $body = [
                'type' => 'BODY',
                'text' => $this->body_text,
            ];

            $placeholders = $this->getVariablePlaceholders();
            if (!empty($placeholders)) {
                if ($this->variable_type === 'name') {
                    $namedExamples = [];
                    foreach ($placeholders as $placeholder) {
                        $namedExamples[] = [
                            'param_name' => $placeholder,
                            'example' => $this->getSampleValue($placeholder),
                        ];
                    }
                    $body['example'] = ['body_text_named_params' => $namedExamples];
                } else {
                    $positionalExamples = [];
                    foreach ($placeholders as $placeholder) {
                        $positionalExamples[] = $this->getSampleValue($placeholder);
                    }
                    $body['example'] = ['body_text' => [$positionalExamples]];
                }
            }

            $components[] = $body;
        }

        // 3. Footer component
        if (!empty($this->footer_text)) {
            $components[] = [
                'type' => 'FOOTER',
                'text' => $this->footer_text,
            ];
        }

        // 4. Buttons component
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
     * Build components array required by the Send Message API.
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
        $category = strtoupper($this->category ?? 'UTILITY');

        if ($category === 'AUTHENTICATION') {
            return $this->buildAuthComponentsForSending($bodyVariables, $buttonUrlVariables);
        }

        return $this->buildUtilityOrMarketingComponentsForSending($bodyVariables, $headerVariables, $buttonUrlVariables);
    }

    /**
     * Build sending components specifically for AUTHENTICATION category templates.
     */
    protected function buildAuthComponentsForSending(array $bodyVariables, array $buttonUrlVariables): array
    {
        $components = [];

        // Determine fallback OTP Code value if variables are missing
        $otpValue = $buttonUrlVariables[0] ?? $bodyVariables['code'] ?? $bodyVariables['otp'] ?? $bodyVariables['1'] ?? $bodyVariables[0] ?? '';
        if (empty($otpValue) && !empty($bodyVariables)) {
            $firstVal = reset($bodyVariables);
            $otpValue = is_string($firstVal) || is_numeric($firstVal) ? (string) $firstVal : '';
        }
        if (empty($otpValue)) {
            $otpValue = '123456';
        }

        // 1. Body parameters for AUTHENTICATION template (Meta expects 1 localizable param for OTP code)
        $bodyParameters = [];
        $placeholders = $this->getVariablePlaceholders();

        if (!empty($placeholders)) {
            foreach ($placeholders as $placeholder) {
                $idx = (int) $placeholder - 1;
                $val = $bodyVariables[$placeholder] ?? $bodyVariables[$idx] ?? $otpValue;
                if ((string) $val !== '') {
                    $bodyParameters[] = [
                        'type' => 'text',
                        'text' => (string) $val,
                    ];
                }
            }
        } elseif (!empty($bodyVariables)) {
            foreach ($bodyVariables as $val) {
                if (is_scalar($val) && (string) $val !== '') {
                    $bodyParameters[] = [
                        'type' => 'text',
                        'text' => (string) $val,
                    ];
                }
            }
        }

        // Guarantee at least 1 body parameter for AUTHENTICATION category (to match Meta 1 param requirement)
        if (empty($bodyParameters)) {
            $bodyParameters[] = [
                'type' => 'text',
                'text' => (string) $otpValue,
            ];
        }

        $components[] = [
            'type' => 'body',
            'parameters' => $bodyParameters,
        ];

        // 2. Button parameter for OTP Copy Code
        $components[] = [
            'type' => 'button',
            'sub_type' => 'url',
            'index' => '0',
            'parameters' => [
                [
                    'type' => 'text',
                    'text' => (string) $otpValue,
                ],
            ],
        ];

        return array_values($components);
    }

    /**
     * Build sending components for UTILITY and MARKETING category templates.
     */
    protected function buildUtilityOrMarketingComponentsForSending(
        array $bodyVariables,
        array $headerVariables = [],
        array $buttonUrlVariables = []
    ): array {
        $components = [];

        // 1. Header Components
        if ($this->header_type === 'text' && str_contains($this->header_content ?? '', '{{')) {
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
                        $idx = (int) $placeholder - 1;
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
                if ($this->variable_type === 'name') {
                    $val = $bodyVariables[$placeholder] ?? '';
                    $parameters[] = [
                        'type' => 'text',
                        'parameter_name' => $placeholder,
                        'text' => (string) $val,
                    ];
                } else {
                    $idx = (int) $placeholder - 1;
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
        } elseif (!empty($bodyVariables)) {
            $parameters = [];
            foreach ($bodyVariables as $key => $val) {
                if (is_scalar($val) && (string) $val !== '') {
                    $parameters[] = [
                        'type' => 'text',
                        'text' => (string) $val,
                    ];
                }
            }
            if (!empty($parameters)) {
                $components[] = [
                    'type' => 'body',
                    'parameters' => $parameters,
                ];
            }
        }

        // 3. Dynamic URL Buttons Component
        if (!empty($this->buttons)) {
            $urlButtonIndex = 0;
            $buttonIndex = 0;
            foreach ($this->buttons as $button) {
                $type = $button['type'] ?? 'QUICK_REPLY';
                if ($type === 'URL' && str_contains($button['url'] ?? '', '{{')) {
                    $val = $buttonUrlVariables[$urlButtonIndex] ?? '';
                    if (!empty($val)) {
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
                    }
                    $urlButtonIndex++;
                }
                $buttonIndex++;
            }
        }

        return array_values($components);
    }
}
