<?php

declare(strict_types=1);

namespace App\DTOs\Call;

/**
 * One phone number, in the three forms this system needs it in at once.
 *
 * The original is kept because it is evidence: when a match goes wrong the
 * first question is always what the provider actually sent, and a normaliser
 * that overwrites its input destroys the only way to answer it.
 *
 * The key is what matching compares on — the last N digits — because the same
 * person's number reaches the CRM as "+919876543210" from one provider,
 * "919876543210" from another and "9876543210" from a form typed years ago.
 */
final readonly class PhoneNumber
{
    public function __construct(
        public ?string $original,
        public ?string $normalized,
        public ?string $key,
        public ?string $countryCode = null,
    ) {}

    public static function empty(): self
    {
        return new self(null, null, null, null);
    }

    public function isUsable(): bool
    {
        return filled($this->key);
    }

    /**
     * The best form available for showing to a person.
     */
    public function display(): ?string
    {
        return $this->normalized ?: $this->original;
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'original' => $this->original,
            'normalized' => $this->normalized,
            'key' => $this->key,
            'country_code' => $this->countryCode,
        ];
    }
}
