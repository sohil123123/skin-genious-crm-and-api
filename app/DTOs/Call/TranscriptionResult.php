<?php

declare(strict_types=1);

namespace App\DTOs\Call;

use App\Services\Call\Transcription\TranscriptWordCounter;

/**
 * What a speech-to-text service returned, in a shape the CRM understands.
 *
 * Segments are optional because most transcription APIs return a single block
 * of text and only some offer timings or diarisation. A result with no segments
 * is a complete, usable result — not a degraded one — so nothing downstream may
 * require them.
 */
final readonly class TranscriptionResult
{
    /**
     * @param  array<int, TranscriptSegment>  $segments
     * @param  array<string, mixed>  $raw
     * @param  array<int, string>  $warnings  problems worth showing the reader
     */
    public function __construct(
        public string $transcript,
        public ?string $language = null,
        public ?string $languageCode = null,
        public ?float $confidence = null,
        public ?int $durationSeconds = null,
        public ?string $model = null,
        public array $segments = [],
        public array $raw = [],
        public array $warnings = [],
    ) {}

    /**
     * Words in the transcript, in any script.
     *
     * str_word_count() only recognises Latin letters, so a Hindi, Gujarati or
     * Urdu transcript came back as 0 words — which then read on screen as a
     * transcript that had failed, next to a page full of text.
     */
    public function wordCount(): int
    {
        return TranscriptWordCounter::count($this->transcript);
    }

    public function hasSegments(): bool
    {
        return $this->segments !== [];
    }

    /**
     * Whether anything was actually said.
     *
     * A recording of a ring-out transcribes to an empty string or a stray
     * syllable, and storing that as a transcript would put a meaningless row in
     * front of anyone reading the call.
     */
    public function isMeaningful(): bool
    {
        return trim($this->transcript) !== '';
    }
}
