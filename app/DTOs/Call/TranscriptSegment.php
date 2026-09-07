<?php

declare(strict_types=1);

namespace App\DTOs\Call;

use App\Enums\Call\TranscriptSpeakerType;

/**
 * One attributed piece of a transcript, as the provider returned it.
 *
 * speakerType is the CRM's interpretation of the provider's raw speaker label
 * and defaults to Unknown, because deciding that "SPEAKER_00" is the agent is a
 * guess that has to be made explicitly rather than assumed. The provider's own
 * label is kept in $speaker so that guess can be revisited.
 */
final readonly class TranscriptSegment
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $text,
        public ?float $startSeconds = null,
        public ?float $endSeconds = null,
        public ?string $speaker = null,
        public TranscriptSpeakerType $speakerType = TranscriptSpeakerType::Unknown,
        public ?float $confidence = null,
        public array $metadata = [],
    ) {}
}
