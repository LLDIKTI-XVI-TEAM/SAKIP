<?php

namespace Tests\Feature\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use App\Services\Authorization\PermissionCatalog;
use App\Services\Authorization\PermissionResolver;
use Database\Seeders\AccessCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermissionResolverBatchTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('cases')]
    public function test_batch_preserves_canonical_decisions(string $case): void
    {
        $this->seed(AccessCatalogSeeder::class);
        $user = User::factory()->create(['status' => $case === 'inactive_user' ? 'nonaktif' : 'aktif']);
        $unit = Unit::create(['nama' => 'Unit Batch', 'status' => $case === 'inactive_unit' ? 'nonaktif' : 'aktif', 'created_by' => $user->id]);
        $role = Role::where('kode', $case === 'role' ? 'perencanaan' : 'pegawai')->sole();
        if ($case !== 'no_role') {
            $user->roles()->attach($role->id, ['id' => Str::uuid(), 'sumber_pemberian' => 'manual', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
        }
        $codes = PermissionCatalog::UNIT_SCOPED;
        $permission = Permission::where('kode', $codes[0])->sole();
        $grantId = (string) Str::uuid();
        DB::table('user_permission_granted')->insert(['id' => $grantId, 'user_id' => $user->id, 'permission_id' => $permission->id, 'unit_id' => $unit->id,
            'alasan' => 'Fixture batch', 'diberikan_oleh' => $user->id, 'created_at' => now()]);
        $denyId = (string) Str::uuid();
        if ($case === 'deny') {
            DB::table('user_permission_denied')->insert(['id' => $denyId, 'user_id' => $user->id, 'permission_id' => $permission->id,
                'unit_id' => null, 'alasan' => 'Fixture deny', 'ditetapkan_oleh' => $user->id, 'created_at' => now()]);
        }
        $scope = match ($case) {
            'missing_scope' => null, 'wrong_scope' => (string) Str::uuid(), 'uppercase_scope' => strtoupper($unit->id), 'malformed_scope' => 'invalid', default => $unit->id
        };
        $results = app(PermissionResolver::class)->decideMany($user, $codes, $scope);
        $this->assertSame($codes, array_keys($results));
        foreach ($codes as $code) {
            $first = $code === $codes[0];
            $reason = match ($case) {
                'inactive_user' => 'inactive_user','no_role' => 'no_role','role' => 'allow','missing_scope', 'malformed_scope' => 'invalid_scope',
                'wrong_scope' => 'no_allow','inactive_unit' => $first ? 'inactive_unit' : 'no_allow','deny' => $first ? 'explicit_deny' : 'no_allow',
                default => $first ? 'allow' : 'no_allow',
            };
            $expected = ['allowed' => $reason === 'allow', 'permission' => $code, 'reason' => $reason,
                'roles' => $case === 'role' ? [$role->id] : [],
                'grants' => $first && in_array($case, ['grant', 'deny', 'role', 'uppercase_scope'], true) ? [$grantId] : [],
                'denies' => $first && $case === 'deny' ? [$denyId] : []];
            $this->assertSame($expected, $results[$code]);
            $this->assertSame($expected, app(PermissionResolver::class)->decide($user, $code, $scope));
        }
    }

    public static function cases(): array
    {
        return array_map(fn ($case) => [$case], ['grant', 'deny', 'role', 'inactive_user', 'no_role', 'missing_scope', 'wrong_scope', 'inactive_unit', 'uppercase_scope', 'malformed_scope']);
    }
}
