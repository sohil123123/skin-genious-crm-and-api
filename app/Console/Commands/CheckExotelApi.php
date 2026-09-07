<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Call\CallProvider;
use App\Models\Call;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Makes one Exotel API request and shows exactly what came back.
 *
 * "Refresh from provider" has three silent endings: credentials missing, Exotel
 * answering 404 for a call id it does not recognise, and a mapper that read the
 * response but found nothing worth merging. All three reach the screen as the
 * same shrug, and none of them says which.
 *
 * So this performs the same request the refresh does and prints the status, the
 * body and the fields that matter — with the token redacted, because this
 * output gets pasted into chat windows.
 */
class CheckExotelApi extends Command
{
    protected $signature = 'calls:check-exotel
        {--call= : A call id, or an Exotel CallSid. Defaults to the newest Exotel call.}';

    protected $description = 'Make one Exotel API request and show the response';

    public function handle(): int
    {
        $this->line('');

        $sid = (string) Setting::getConfigured('exotel_account_sid', config('calls.exotel.account_sid'));
        $key = (string) Setting::getConfigured('exotel_api_key', config('calls.exotel.api_key'));
        $token = (string) Setting::getConfigured('exotel_api_token', config('calls.exotel.api_token'));
        $subdomain = trim((string) Setting::getConfigured('exotel_subdomain', config('calls.exotel.subdomain', 'api.exotel.com')), '/');

        foreach (['Account SID' => $sid, 'API key' => $key, 'API token' => $token, 'Subdomain' => $subdomain] as $label => $value) {
            $this->line(sprintf(
                '  %s %-12s %s',
                filled($value) ? '<fg=green>✓</>' : '<fg=red>✗</>',
                $label . ':',
                // The token is the one secret here, and this output is meant to
                // be shared. Length alone proves it is set without printing it.
                $label === 'API token'
                    ? (filled($value) ? sprintf('set (%d characters)', strlen($value)) : 'MISSING')
                    : ($value ?: 'MISSING'),
            ));
        }

        if (blank($sid) || blank($key) || blank($token)) {
            $this->newLine();
            $this->error('Credentials are incomplete. Calls → Call Settings → Exotel.');

            return self::FAILURE;
        }

        $callSid = $this->resolveCallSid();

        if ($callSid === null) {
            $this->newLine();
            $this->error('No Exotel call to look up. Pass --call= with a call id or a CallSid.');

            return self::FAILURE;
        }

        $url = sprintf('https://%s/v1/Accounts/%s/Calls/%s.json', $subdomain, $sid, urlencode($callSid));

        $this->newLine();
        $this->line('  GET ' . $url);
        $this->newLine();

        try {
            $response = Http::withBasicAuth($key, $token)
                ->acceptJson()
                ->timeout(30)
                ->get($url);
        } catch (\Throwable $exception) {
            $this->error('  Could not reach Exotel: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf('  HTTP %d', $response->status()));
        $this->newLine();

        return $this->explain($response->status(), (array) $response->json(), $response->body());
    }

    /**
     * @param  array<string, mixed>  $json
     */
    protected function explain(int $status, array $json, string $body): int
    {
        if ($status === 401 || $status === 403) {
            $this->error('  Exotel rejected the credentials.');
            $this->line('    The API key and token must be one pair from the same row of');
            $this->line('    Exotel\'s API Credentials table. A key from one row with a token');
            $this->line('    from another authenticates as nobody.');

            return self::FAILURE;
        }

        if ($status === 404) {
            $this->error('  Exotel has no call with that id on this account.');
            $this->line('    Usually the wrong region: an account on api.in.exotel.com answers');
            $this->line('    404 for every call id when asked on api.exotel.com. Check the');
            $this->line('    subdomain against Exotel\'s API Credentials page.');

            return self::FAILURE;
        }

        if ($status >= 400) {
            $this->error('  Exotel returned an error.');
            $this->line('    ' . mb_substr(trim($body), 0, 500));

            return self::FAILURE;
        }

        $call = (array) ($json['Call'] ?? $json);

        if ($call === []) {
            $this->error('  Exotel answered, but with no call in the body.');
            $this->line('    ' . mb_substr(trim($body), 0, 500));

            return self::FAILURE;
        }

        $this->info('  Exotel returned the call.');
        $this->newLine();

        foreach (['Sid', 'Status', 'Direction', 'StartTime', 'EndTime', 'Duration', 'RecordingUrl'] as $field) {
            $value = $call[$field] ?? null;

            $this->line(sprintf(
                '  %s %-13s %s',
                filled($value) ? '<fg=green>✓</>' : '<fg=yellow>–</>',
                $field . ':',
                filled($value) ? (string) $value : 'not present',
            ));
        }

        $this->newLine();

        if (blank($call['RecordingUrl'] ?? null)) {
            $this->warn('  No RecordingUrl on this call, so a refresh cannot attach audio.');
            $this->line('    Exotel finalises recordings a little after a call ends. If this');
            $this->line('    call is minutes old, try again shortly. If it is hours old and');
            $this->line('    the Exotel inbox shows a player, the recording belongs to a leg');
            $this->line('    this call id does not cover.');

            return self::SUCCESS;
        }

        $this->info('  A refresh on this call will attach that recording.');

        return self::SUCCESS;
    }

    protected function resolveCallSid(): ?string
    {
        $given = $this->option('call');

        if (filled($given)) {
            // A number is one of ours; anything else is Exotel's own id.
            return ctype_digit((string) $given)
                ? Call::query()->whereKey($given)->value('provider_call_id')
                : (string) $given;
        }

        return Call::query()
            ->where('provider', CallProvider::Exotel->value)
            ->whereNotNull('provider_call_id')
            ->latest('id')
            ->value('provider_call_id');
    }
}
