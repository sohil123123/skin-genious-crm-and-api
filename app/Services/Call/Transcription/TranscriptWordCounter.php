<?php

declare(strict_types=1);

namespace App\Services\Call\Transcription;

/**
 * Counts the words in a transcript, in any script.
 *
 * Exists because PHP's str_word_count() recognises Latin letters and nothing
 * else. Handed a Hindi, Gujarati or Urdu transcript it returns 0, and every
 * length rule built on it quietly becomes a rule about the alphabet rather than
 * about the length.
 *
 * That has now caused the same bug twice in this codebase. The first time the
 * transcript panel reported "0 words" for a real conversation. The second time
 * the analysis threshold silently refused to read Hindi calls: a fifteen-word
 * English transcript scored 29 against a floor of 15 and passed, while the same
 * fifteen words in Devanagari scored 14 and were discarded as too short. For a
 * clinic whose calls are mostly Hindi, that turned the feature off.
 *
 * A comment on one class did not stop the second occurrence, so the rule lives
 * here as code that both callers use.
 */
final class TranscriptWordCounter
{
    /**
     * Whitespace-separated tokens, which is what a word is in every script this
     * CRM sees, rather than a run of A-Z.
     */
    public static function count(?string $text): int
    {
        $words = preg_split('/\s+/u', trim(strip_tags((string) $text)), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? 0 : count($words);
    }
}
