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
use Illuminate\Support\Facades\Gate;

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

    /** @var \Illuminate\Support\Collection<int, CallRecording>|null */
    protected ?\Illuminate\Support\Collection $recordings = null;

    public function handle(): int
    {
        $this->line('');
        $this->components->info('Playback diagnosis');

        $ok = $this->reportCache();
        $ok = $this->reportPolicy() && $ok;
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

    /**
     * Whether the gate can find the code it is supposed to consult.
     *
     * This is the check that catches a deployment rather than a configuration:
     * Gate denies an ability it cannot resolve, silently and identically to a
     * policy that considered the request and said no. So a server missing the
     * policy file — or holding a copy from before playRecording was written —
     * refuses every play with the permission correctly granted, which is a
     * combination no amount of looking at roles will explain.
     */
    protected function reportPolicy(): bool
    {
        $ok = true;

        if (! class_exists(\App\Policies\CallPolicy::class)) {
            $this->line('  <fg=red>✗</> App\Policies\CallPolicy does not exist on this server.');
            $this->line('      The file was not deployed, or composer\'s autoloader is stale.');
            $this->line('      Upload it and run: composer dump-autoload -o');

            return false;
        }

        $policy = Gate::getPolicyFor(Call::class);

        if ($policy === null) {
            $this->line('  <fg=red>✗</> No policy is registered for App\Models\Call.');
            $this->line('      Gate denies any ability it cannot resolve, so every play is a 403.');

            return false;
        }

        $this->line(sprintf('  <fg=green>✓</> Policy for Call: <options=bold>%s</>', $policy::class));

        // A policy present but missing the method is the same denial, and the
        // likeliest shape of a half-finished upload.
        foreach (['playRecording', 'viewTranscript'] as $method) {
            $exists = method_exists($policy, $method);

            $this->line(sprintf(
                '  %s Policy method %s()%s',
                $exists ? '<fg=green>✓</>' : '<fg=red>✗</>',
                $method,
                $exists ? '' : ' is MISSING — this server has an older copy of the policy',
            ));

            $ok = $exists && $ok;
        }

        return $ok;
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

            // The permission is only half the policy. playRecording is
            // "can(...) AND sharesClinic(...)", and the second half is what the
            // controller actually calls — so run the real gate check against a
            // real recording rather than inferring from the permission alone.
            foreach ($this->recordingsToCheck() as $recording) {
                if ($recording->call === null) {
                    continue;
                }

                $allowed = Gate::forUser($user)->allows('playRecording', $recording->call);

                $this->line(sprintf(
                    '      %s policy playRecording on recording #%d (call %d, clinic %s): %s',
                    $allowed ? '<fg=green>✓</>' : '<fg=red>✗</>',
                    $recording->getKey(),
                    $recording->call->getKey(),
                    $recording->call->clinic_id === null ? 'none' : (string) $recording->call->clinic_id,
                    $allowed ? 'allowed' : 'DENIED — this is the 403',
                ));

                if (! $allowed) {
                    $ok = false;

                    $this->line(sprintf(
                        '          user clinic: %s, super admin: %s',
                        $user->clinic_id === null ? 'none' : (string) $user->clinic_id,
                        $user->hasRole(config('project.roles.super_admin')) ? 'yes' : 'no',
                    ));
                }
            }

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
    /**
     * The recordings both the policy check and the storage report run against.
     *
     * @return \Illuminate\Support\Collection<int, CallRecording>
     */
    protected function recordingsToCheck(): \Illuminate\Support\Collection
    {
        return $this->recordings ??= $this->option('recording')
            ? CallRecording::query()->whereKey($this->option('recording'))->get()
            : CallRecording::query()->latest('id')->limit(5)->get();
    }

    protected function reportRecordings(): void
    {
        $recordings = $this->recordingsToCheck();

        if ($recordings->isEmpty()) {
            $this->line('  <fg=yellow>–</> No recordings to check.');

            return;
        }

        $this->newLine();
        $this->line('  Stored audio:');

        foreach ($recordings as $recording) {
            $exists = $recording->fileExists();

            // A recording whose call has been deleted cannot be played by
            // anybody, and reporting it as MISSING alongside audio that merely
            // failed to download sends the reader after a worker that was never
            // the problem. The two need opposite responses: run the worker, or
            // ignore the row entirely.
            //
            // belongsTo applies the parent's soft-delete scope, so ->call is
            // null for a trashed call as well as an absent one. The foreign key
            // cascades, which makes a truly absent parent close to impossible —
            // so in practice this is always the trash.
            $detached = $recording->call === null;
            $trashed = $detached && Call::withTrashed()->whereKey($recording->call_id)->exists();

            $this->line(sprintf(
                '  %s #%-4d call %-5s disk %-18s %s',
                $exists ? '<fg=green>✓</>' : ($detached ? '<fg=yellow>–</>' : '<fg=red>✗</>'),
                $recording->getKey(),
                (string) $recording->call_id,
                $recording->diskName() ?: '(never downloaded)',
                match (true) {
                    $exists => 'on disk',
                    $trashed => 'its call is in the trash; nothing to play',
                    $detached => 'ORPHAN — its call is gone; nothing to play',
                    default => 'MISSING — the route will answer 404, not 403',
                },
            ));
        }

        $detached = CallRecording::query()->whereDoesntHave('call')->count();

        $this->newLine();
        $this->line(sprintf(
            '  Calls: %d (excluding trashed), recordings: %d%s',
            Call::query()->count(),
            CallRecording::query()->count(),
            $detached > 0 ? sprintf(' — %d belong to deleted calls', $detached) : '',
        ));

        if ($detached > 0) {
            $this->line('      Those are not a playback fault. They are audio for calls');
            $this->line('      that were deleted, and they go when calls:prune runs.');
        }
    }
}
