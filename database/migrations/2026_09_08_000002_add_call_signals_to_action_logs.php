<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which call, if any, is behind a recommended action.
 *
 * Both action logs already carry a `reason` — free text a staff member reads
 * before picking up the phone — and that stays the explanation. These two
 * columns are the structured basis underneath it: the call the action came
 * from, and the signals that argued for it, with their confidences.
 *
 * Kept separate from `reason` because they answer different questions. `reason`
 * answers "what do I say to this person"; these answer "why did the engine rank
 * them here", which is the question asked when the ranking looks wrong. Without
 * them, an action influenced by a conversation is indistinguishable from one
 * produced by the existing date arithmetic.
 *
 * Both nullable, both additive. Every action generated today continues to be
 * generated unchanged, with these left empty.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    protected array $tables = ['ai_action_logs', 'lead_action_logs'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('related_call_id')
                    ->nullable()
                    ->after('assigned_to')
                    // Nulled rather than cascaded: losing the call should not
                    // silently delete the record that somebody was asked to
                    // ring this person, which may already have been acted on.
                    ->constrained('calls')
                    ->nullOnDelete()
                    ->comment('The call whose analysis contributed to this action, when one did');

                $table->json('call_signals')
                    ->nullable()
                    ->after('related_call_id')
                    ->comment('Signal keys and confidences that influenced the score, for explainability');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('related_call_id');
                $table->dropColumn('call_signals');
            });
        }
    }
};
