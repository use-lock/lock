<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $realmId = DB::table('realms')->where('slug', config('lock.master_realm'))->value('id');
            $management = DB::table('resources')->where('realm_id', $realmId)->where('identifier', 'api')->first();

            if ($management === null) {
                return;
            }

            if (DB::table('resources')->where('realm_id', $realmId)->where('identifier', 'admin-api')->exists()) {
                throw new RuntimeException('Cannot split API resources: the master realm already has an admin-api resource. Resolve the identifier conflict before retrying the migration.');
            }

            $adminId = (string) Str::uuid();
            $timestamp = now();

            DB::table('resources')->insert([
                'id' => $adminId,
                'realm_id' => $realmId,
                'identifier' => 'admin-api',
                'name' => 'Admin API',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            foreach (['read', 'write'] as $access) {
                $legacyScope = DB::table('resource_scopes')
                    ->where('resource_id', $management->id)
                    ->where('value', 'realms:'.$access)
                    ->first();
                $socialId = (string) Str::uuid();

                DB::table('resource_scopes')->insert([
                    'id' => $socialId,
                    'resource_id' => $management->id,
                    'value' => 'social-providers:'.$access,
                    'description' => $access === 'read'
                        ? 'Read the social identity providers of a realm.'
                        : 'Create, configure and delete social identity providers.',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                if ($legacyScope === null) {
                    continue;
                }

                DB::table('resource_scopes')->where('id', $legacyScope->id)->update([
                    'resource_id' => $adminId,
                    'updated_at' => $timestamp,
                ]);

                $roleIds = DB::table('role_scope')
                    ->join('roles', 'roles.id', '=', 'role_scope.role_id')
                    ->where('roles.realm_id', $realmId)
                    ->where('role_scope.resource_scope_id', $legacyScope->id)
                    ->pluck('role_scope.role_id');

                foreach ($roleIds as $roleId) {
                    DB::table('role_scope')->insert([
                        'role_id' => $roleId,
                        'resource_scope_id' => $socialId,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
            }
        });
    }
};
