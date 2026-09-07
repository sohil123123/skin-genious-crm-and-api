<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Call;
use App\Models\CallRecording;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Says why a recording will not play.
 *
 * The streaming route answers 403 for exactly one reason — the playRecording
 * policy said no — and the policy is two conditions joined by an "and". From a
 * browser the two are indistinguishable, and on a production server there is no
 * comfortable way to poke at them.
 *
 * The awkward part is that granting the permission from the CLI does not always
 * reach the web process. Spatie caches the permission map in the default cache
 * store, and if that store is per-process — array, apc, apcu — then a grant
 * flushed from a terminal leaves php-fpm holding the map from before the grant.
 * The symptom is a command reporting success and a browser still refusing, with
 * nothing in either log. That check is the first thing this prints.
 */
class DiagnoseCallPlayback extends Command
{
    protected $signature = 'calls:diagnose-playback
        {--user= : A specific user id or mobile number to test as.}
        {--recording= : A specific recording id to test against.}';

    protected $description = 'Explain why call recordings will not play';

    /**
     * Stores that are private to one PHP process, so a CLI flush never reaches
     * the web worker.
     *
     * @var list<string>
     */
    protected const PER_PROCESS_STORES = ['array', 'apc', 'apcu', 'octane'];

    public function handle(): int
    {
        $this->line('');
        $this->components->info('Playback diagnosis');

        $ok = $this->reportCache();
        $ok = $this->reportPermissions() && $ok;
        $ok = $this->reportUsers() && $ok;

        $this->reportRecordings();

        $this->newLine();

        if (! $ok) {
            $this->components->error('Something above will stop playback. See the notes against each ✗.');

            return self::FAILURE;
        }

        $this->components->info('Authorisation looks correct. If a browser still gets 403, restart php-fpm.');

        return self::SUCCESS;
    }

    /**
     * The check that explains "I granted it and it still says no".
     */
    protected function reportCache(): bool
    {
        $store = config('permission.cache.store') === 'default'
            ? (string) config('cache.default')
            : (string) config('permission.cache.store');

        $shared = ! in_array($store, self::PER_PROCESS_STORES, true);

        $this->line(sprintf(
            '  %s Permission cache store: <options=bold>%s</>',
            $shared ? '<fg=green>✓</>' : '<fg=red>✗</>',
            $store,
        ));

        if (! $shared) {
            $this->line('      This store is private to each PHP process, so granting a');
            $this->line('      permission from this terminal never reaches php-fpm. Switch');
            $this->line('      CACHE_STORE to file, redis or database, or restart php-fpm');
            $this->line('      after every grant.');
        }

        // Proves the store is reachable and writable from here, which a
        // misconfigured redis or a read-only cache directory is not.
        try {
            Cache::store($store === 'default' ? null : $store)->put('sgc.playback.probe', 1, 5);
            $this->line('  <fg=green>✓</> Cache is writable from the CLI.');
        } catch (\Throwable $exception) {
            $this->line('  <fg=red>✗</> Cache is not writable: ' . $exception->getMessage());

            return false;
        }

        return $shared;
    }

    protected function reportPermissions(): bool
    {
        $guard = (string) config('auth.defaults.guard');
        $this->line(sprintf('  <fg=green>✓</> Default auth guard: <options=bold>%s</>', $guard));

        $ok = true;

        foreach (['PlayRecording:Call', 'ViewTranscript:Call'] as $name) {
            /** @var Permission|null $permission */
            $permission = Permission::query()->where('name', $name)->first();

            if ($permission === null) {
                $this->line(sprintf('  <fg=red>✗</> Permission %s does not exist. Run calls:grant-permissions.', $name));
                $ok = false;

                continue;
            }

            // A permission created under the wrong guard is invisible to can(),
            // which is a silent failure rather than an error.
            $matches = $permission->guard_name === $guard;

            $this->line(sprintf(
                '  %s Permission %s exists (guard: %s)%s',
                $matches ? '<fg=green>✓</>' : '<fg=red>✗</>',
                $name,
                $permission->guard_name,
                $matches ? '' : ' — does not match the app guard, so can() will never see it',
            ));

            $ok = $matches && $ok;
        }

        return $ok;
    }

    protected function reportUsers(): bool
    {
        $users = $this->usersToTest();

        if ($users->isEmpty()) {
            $this->line('  <fg=red>✗</> No user to test. Pass --user= with an id or mobile.');

            return false;
        }

        $ok = true;

        foreach ($users as $user) {
            $can = $user->can('PlayRecording:Call');

            $this->line(sprintf(
                '  %s %s (#%d) can PlayRecording:Call: %s',
                $can ? '<fg=green>✓</>' : '<fg=red>✗</>',
                $user->name,
                $user->getKey(),
                $can ? 'yes' : 'NO',
            ));

            $this->line(sprintf('      roles: %s', $user->getRoleNames()->implode(', ') ?: 'none'));

            if (! $can) {
                $ok = false;

                $holders = Role::query()
                    ->whereHas('permissions', fn ($q) => $q->where('name', 'PlayRecording:Call'))
                    ->pluck('name');

                $this->line(sprintf(
                    '      the permission is held by: %s',
                    $holders->implode(', ') ?: 'no role at all — run calls:grant-permissions',
                ));
            }
        }

        return $ok;
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    protected function usersToTest(): \Illuminate\Support\Collection
    {
        if ($given = $this->option('user')) {
            return User::query()
                ->where('id', $given)
                ->orWhere('mobile', 'like', '%' . $given)
                ->get();
        }

        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', config('project.roles.super_admin')))
            ->limit(5)
            ->get();
    }

    /**
     * Authorisation is only half of it: a policy that says yes still 404s when
     * the audio never made it onto this server's disk, which is the normal
     * state right after a deploy to a machine that has never run the download
     * worker.
     */
    protected function reportRecordings(): void
    {
        $recordings = $this->option('recording')
            ? CallRecording::query()->whereKey($this->option('recording'))->get()
            : CallRecording::query()->latest('id')->limit(5)->get();

        if ($recordings->isEmpty()) {
            $this->line('  <fg=yellow>–</> No recordings to check.');

            return;
        }

        $this->newLine();
        $this->line('  Stored audio:');

        foreach ($recordings as $recording) {
            $exists = $recording->fileExists();

            $this->line(sprintf(
                '  %s #%-4d call %-5s disk %-18s %s',
                $exists ? '<fg=green>✓</>' : '<fg=red>✗</>',
                $recording->getKey(),
                (string) $recording->call_id,
                $recording->diskName(),
                $exists ? 'on disk' : 'MISSING — the route will answer 404, not 403',
            ));
        }

        $this->newLine();
        $this->line(sprintf(
            '  Calls: %d, recordings: %d',
            Call::query()->count(),
            CallRecording::query()->count(),
        ));
    }
}
