<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ensures the call feature's permissions exist and are granted.
 *
 * Shield's generator creates the standard CRUD abilities for a resource, but
 * not the two this feature adds by hand — PlayRecording:Call and
 * ViewTranscript:Call. Those are separate on purpose: knowing a patient rang at
 * 3pm is roster information, hearing what they said about their skin condition
 * is not, and the two should not be granted together by default.
 *
 * The failure mode this exists to prevent is quiet. A missing custom permission
 * does not error anywhere — the call list renders, the row appears, the play
 * button draws — and only the request for the audio comes back 403. Which
 * looks exactly like a broken player.
 *
 * Idempotent, so it is safe to run on every deploy alongside shield:generate.
 */
class GrantCallPermissions extends Command
{
    protected $signature = 'calls:grant-permissions
        {--role=* : Roles to grant to. Defaults to the configured super admin.}
        {--dry-run : Report what would change without writing anything.}';

    protected $description = 'Create the call feature permissions and grant them to a role';

    /**
     * Everything the call feature checks for.
     *
     * Listed explicitly rather than derived from the policies: a policy method
     * added without a matching permission should show up here as a deliberate
     * decision, not be silently granted because it exists.
     *
     * viewRawPayload is absent on purpose — it is gated on the super admin role
     * itself, never on a permission, because the raw payload holds every phone
     * number involved unredacted.
     *
     * @var array<int, string>
     */
    protected const PERMISSIONS = [
        // Standard resource abilities, normally produced by shield:generate.
        'ViewAny:Call',
        'View:Call',
        'Create:Call',
        'Update:Call',
        'Delete:Call',
        'DeleteAny:Call',
        'Restore:Call',
        'RestoreAny:Call',
        'ForceDelete:Call',
        'ForceDeleteAny:Call',

        // Added by this feature, and the reason this command exists.
        'PlayRecording:Call',
        'ViewTranscript:Call',

        'ViewAny:CallProviderAgent',
        'View:CallProviderAgent',
        'Create:CallProviderAgent',
        'Update:CallProviderAgent',
        'Delete:CallProviderAgent',
        'DeleteAny:CallProviderAgent',
    ];

    public function handle(): int
    {
        $roleNames = (array) $this->option('role');

        if ($roleNames === []) {
            $roleNames = [(string) config('project.roles.super_admin', 'super_admin')];
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Dry run - nothing will be written.');
        }

        $guard = (string) config('auth.defaults.guard', 'web');

        // ─── Make sure every permission exists ───────────────────────────
        $created = 0;

        foreach (self::PERMISSIONS as $name) {
            $exists = Permission::query()
                ->where('name', $name)
                ->where('guard_name', $guard)
                ->exists();

            if ($exists) {
                continue;
            }

            if (! $dryRun) {
                Permission::create(['name' => $name, 'guard_name' => $guard]);
            }

            $created++;
            $this->line("  created permission: {$name}");
        }

        $this->info(sprintf('%s %d permission(s).', $dryRun ? 'Would create' : 'Created', $created));

        // ─── Grant them ──────────────────────────────────────────────────
        foreach ($roleNames as $roleName) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', $guard)
                ->first();

            if ($role === null) {
                $this->error("Role \"{$roleName}\" does not exist. Skipped.");

                continue;
            }

            $held = $role->permissions()->pluck('name')->all();
            $missing = array_values(array_diff(self::PERMISSIONS, $held));

            if ($missing === []) {
                $this->info("{$roleName}: already holds every call permission.");

                continue;
            }

            foreach ($missing as $name) {
                $this->line("  {$roleName} + {$name}");
            }

            if (! $dryRun) {
                $role->givePermissionTo($missing);
            }

            $this->info(sprintf(
                '%s %d permission(s) to %s.',
                $dryRun ? 'Would grant' : 'Granted',
                count($missing),
                $roleName,
            ));
        }

        if (! $dryRun) {
            // Spatie caches the permission map, and a queue worker or an open
            // session would otherwise keep answering from the old one.
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->comment('Permission cache flushed.');
        }

        return self::SUCCESS;
    }
}
