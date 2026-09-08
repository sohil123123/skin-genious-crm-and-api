<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audio for a call, as a row of its own rather than a column on calls.
 *
 * A single conversation genuinely produces more than one recording: an Exotel
 * flow that greets, records a voicemail and then connects an agent publishes a
 * separate URL per stage, and a transferred call records each leg. Collapsing
 * that into one call_recording_url would silently keep whichever arrived last.
 *
 * The other reason is lifecycle. A recording URL is knowledge; the audio is an
 * asset with a download that can fail, a checksum, a disk, a retention date and
 * a transcription that runs off it. None of that belongs on the call.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_recordings', function (Blueprint $table) {
            $table->id()->comment('Primary key');

            $table->foreignId('call_id')
                ->constrained('calls')
                ->cascadeOnDelete()
                ->comment('The call this audio belongs to');

            $table->string('provider', 30)->comment('Provider that published this recording');
            $table->string('provider_recording_id', 191)->nullable()->comment('Provider identifier for the recording, where one is given');

            // The provider URL is kept forever even after the file is stored
            // locally: it is the only way to re-download if the local copy is
            // lost, and the only evidence of where the audio came from.
            $table->text('source_url')->nullable()->comment('Recording URL exactly as the provider published it; never discarded');

            // A URL is a poor unique key (they contain expiring tokens), so
            // duplicates are caught on a hash of the stable part instead.
            $table->string('source_url_hash', 64)->nullable()->comment('SHA-256 of the source URL, used to stop the same recording being attached twice');

            $table->string('storage_disk', 60)->nullable()->comment('Laravel filesystem disk the audio was written to');
            $table->text('storage_path')->nullable()->comment('Path on that disk; never a public URL');
            $table->string('original_filename')->nullable()->comment('Filename the provider served, where it gave one');
            $table->string('mime_type', 100)->nullable()->comment('Content type as served, validated before the file is kept');
            $table->string('extension', 12)->nullable()->comment('File extension derived from the MIME type');
            $table->unsignedBigInteger('file_size')->nullable()->comment('Stored file size in bytes');
            $table->unsignedInteger('duration_seconds')->nullable()->comment('Audio length, where the provider reports it');
            $table->string('checksum', 64)->nullable()->comment('SHA-256 of the stored file, so corruption is detectable and a re-download is verifiable');

            $table->string('download_status', 20)->default('pending')->comment('pending, downloading, downloaded, failed, skipped');
            $table->unsignedTinyInteger('download_attempts')->default(0)->comment('How many times a download has been tried; caps the retry loop');
            $table->timestamp('download_started_at')->nullable()->comment('When the most recent download attempt began');
            $table->timestamp('downloaded_at')->nullable()->comment('When the audio was successfully fetched');

            // Separate from download_status because they fail independently: a
            // download can succeed and the write to disk still fail, and the
            // fix differs.
            $table->string('storage_status', 20)->default('remote_only')->comment('remote_only, downloading, stored, failed, purged');

            $table->string('transcription_status', 20)->default('pending')->comment('pending, processing, completed, failed, not_available');
            $table->unsignedTinyInteger('transcription_attempts')->default(0)->comment('How many transcription attempts have been made');
            $table->timestamp('transcription_started_at')->nullable()->comment('When transcription last began');
            $table->timestamp('transcription_completed_at')->nullable()->comment('When transcription last finished');

            $table->text('error_message')->nullable()->comment('Most recent download or storage error');

            $table->json('metadata')->nullable()->comment('Provider recording fields with no column of their own');

            $table->timestamp('purge_after')->nullable()->comment('Retention date; null means keep indefinitely. Nothing is deleted without a configured policy');
            $table->timestamp('purged_at')->nullable()->comment('When the audio was deleted under the retention policy; the row itself survives as an audit trail');

            $table->timestamps();

            // Scoped to the call rather than global: two different calls can
            // legitimately share a provider recording id if a provider reuses
            // ids per account, and merging them would be wrong.
            $table->unique(['call_id', 'source_url_hash'], 'uniq_recording_call_url');
            $table->index(['call_id', 'created_at'], 'idx_recording_call_created');
            $table->index(['provider', 'provider_recording_id'], 'idx_recording_provider_id');
            $table->index('download_status', 'idx_recording_download_status');
            $table->index('storage_status', 'idx_recording_storage_status');
            $table->index('transcription_status', 'idx_recording_transcription_status');
            $table->index('purge_after', 'idx_recording_purge_after');

            $table->comment('Call audio: provider URL, local copy, and the state of getting from one to the other');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_recordings');
    }
};
