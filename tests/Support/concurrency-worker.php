<?php

use App\Actions\Access\AssignRole;
use App\Actions\Access\CreateDeny;
use App\Actions\Access\RevokeDeny;
use App\Actions\Access\SyncRolePermissionPresets;
use App\Actions\Auth\BootstrapSuperadmin;
use App\Actions\Auth\ProvisionKeycloakUser;
use App\Actions\Perencanaan\ChangeIndicatorFormula;
use App\Actions\Perencanaan\ReadIndicatorEditor;
use App\Actions\RencanaAksi\SahkanRencanaAksi;
use App\Actions\Unit\CreateUnitAction;
use App\Actions\Unit\DeleteUnitAction;
use App\Actions\Unit\UpdateUnitAction;
use App\Models\IndikatorKinerja;
use App\Models\User;
use App\Services\Authorization\RoleAssignmentReceipt;
use App\Services\PermissionResolver;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $connection = DB::connection();
    $identity = $connection->selectOne('select current_database() as database, current_user as username, pg_backend_pid() as pid');
    if (! $app->environment('testing') || $app->configurationIsCached()
        || (string) env('SAKIP_TEST_ALLOW_DATABASE_RESET') !== '1'
        || $connection->getDriverName() !== 'pgsql'
        || $connection->getDatabaseName() !== 'sakip_test'
        || $connection->getConfig('username') !== 'sakip_test'
        || $connection->getConfig('read') !== null || $connection->getConfig('write') !== null
        || $identity->database !== 'sakip_test' || $identity->username !== 'sakip_test') {
        throw new RuntimeException('Worker hanya boleh memakai PostgreSQL testing disposable.');
    }
    DB::statement("SET lock_timeout = '15s'");
    DB::statement("SET statement_timeout = '20s'");
    fwrite(STDOUT, 'READY:'.$identity->pid."\n");
    fflush(STDOUT);
    if (trim((string) fgets(STDIN)) !== 'GO') {
        throw new RuntimeException('Barrier worker tidak diberikan.');
    }
    $identity = $argv[2];
    try {
        $assignment = isset($argv[3]) ? json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR) : [];
        if (in_array($argv[1], ['unit-create', 'unit-update', 'unit-delete', 'grant-create', 'grant-revoke', 'jenis-create', 'jenis-update', 'jenis-delete', 'jenis-technical', 'storage-update', 'regulasi-create', 'regulasi-update', 'regulasi-delete', 'regulasi-attachment', 'renstra-create', 'renstra-update', 'renstra-delete', 'renstra-attachment'], true)) {
            $actor = User::findOrFail($assignment['actor_id']);
            $initialDecision = app(PermissionResolver::class)->resolve($actor, $assignment['permission']);
            if (! $initialDecision->allowed) {
                throw new RuntimeException('Fixture harus diizinkan sebelum menunggu lock.');
            }
        }
        $result = match ($argv[1]) {
            'formula-update' => app(ChangeIndicatorFormula::class)->handle(User::findOrFail($assignment['actor_id']), IndikatorKinerja::findOrFail($assignment['indikator_id']), $assignment['data'])['status'],
            'formula-read' => app(ReadIndicatorEditor::class)->handle(User::findOrFail($assignment['actor_id']), $assignment['indikator_id'], false),
            'renstra-create', 'renstra-update', 'renstra-delete', 'renstra-attachment' => performRenstraMutation($argv[1], $assignment),
            'regulasi-create', 'regulasi-update', 'regulasi-delete', 'regulasi-attachment' => performRegulasiMutation($argv[1], $assignment),
            'storage-update' => performStoragePolicyMutation($assignment),
            'jenis-create', 'jenis-update', 'jenis-delete', 'jenis-technical' => performJenisBerkasMutation($argv[1], $assignment),
            'grant-create', 'grant-revoke' => performGrantMutation($argv[1], $assignment),
            'unit-create' => app(CreateUnitAction::class)->handle($actor, $assignment['data'])->id,
            'unit-update' => app(UpdateUnitAction::class)->handle($actor, $assignment['unit_id'], $assignment['data']),
            'unit-delete' => app(DeleteUnitAction::class)->handle($actor, $assignment['unit_id'], 'Alasan penghapusan fixture', $initialDecision),
            'sync-presets' => app(SyncRolePermissionPresets::class)->handle('test-release', 'Fixture konkurensi rilis', 'test-process:'.getmypid()),
            'assign-role' => app(AssignRole::class)->handle(User::findOrFail($assignment['actor_id']), $assignment['target_id'], $assignment['role_id'], $assignment['alasan'], $assignment['expected_assignment'])['status'],
            'receipt-consume' => app(RoleAssignmentReceipt::class)->consume($assignment['actor_id'], $assignment['session_id'], $assignment['reference']) === null ? 'unknown' : 'consumed',
            'create-deny' => app(CreateDeny::class)->handle(User::findOrFail($assignment['actor_id']), $assignment['target_id'], $assignment['permission_id'], $assignment['unit_id'], $assignment['alasan']),
            'revoke-deny' => app(RevokeDeny::class)->handle(User::findOrFail($assignment['actor_id']), $assignment['deny_id'], $assignment['alasan']),
            'provision' => app(ProvisionKeycloakUser::class)->handle(['subject' => $identity, 'nama' => 'Fixture Bersamaan', 'email' => 'concurrent@example.test'])->id,
            'bootstrap' => app(BootstrapSuperadmin::class)->handle($identity, 'Operator Pengujian', 'Fixture konkurensi bootstrap', 'test-process:'.getmypid()),
            'rencana-sahkan' => app(SahkanRencanaAksi::class)->handle(User::findOrFail($assignment['actor_id']), $assignment['rencana_aksi_id'], $assignment['data']),
            default => throw new InvalidArgumentException('Operasi worker tidak dikenal.'),
        };
        $result = match ($argv[1]) {
            'create-deny' => 'created',
            'revoke-deny' => 'revoked',
            'rencana-sahkan' => 'disahkan',
            default => $result,
        };
    } catch (AuthorizationException $exception) {
        if (! in_array($argv[1], ['assign-role', 'formula-update'], true)) {
            throw $exception;
        }
        $result = 'denied';
    } catch (HttpException $exception) {
        if (! in_array($argv[1], ['unit-create', 'unit-update', 'unit-delete'], true) || $exception->getStatusCode() !== 403) {
            throw $exception;
        }
        $result = 'denied';
    } catch (DomainException $exception) {
        if ($argv[1] !== 'bootstrap' || ! ($assignment['expect_ineligible'] ?? false)
            || $exception->getMessage() !== 'Keadaan awal bootstrap tidak sesuai; tidak ada data yang diubah.') {
            throw $exception;
        }
        $result = 'ineligible';
    } catch (ValidationException $exception) {
        $expectedField = match ($argv[1]) {
            'assign-role' => 'expected_assignment',
            'formula-update' => 'konflik',
            'create-deny' => 'permission_id',
            'revoke-deny' => 'deny_id',
            'rencana-sahkan' => 'versi',
            default => null,
        };
        if ($expectedField === null || ! isset($exception->errors()[$expectedField])) {
            throw $exception;
        }
        $result = match ($argv[1]) {
            'create-deny' => 'duplicate',
            'revoke-deny' => 'stale',
            'rencana-sahkan' => 'ditolak',
            default => 'conflict',
        };
    }
    fwrite(STDOUT, 'RESULT:'.json_encode($result, JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    // Jangan mencetak SQL, konfigurasi koneksi, atau kredensial dari exception.
    fwrite(STDERR, get_class($exception)."\n");
    exit(1);
}

/** Request Renstra tetap melewati middleware, FormRequest, controller dan transaksi domain. */
function performRenstraMutation(string $operation, array $assignment): string
{
    Auth::setUser(User::findOrFail($assignment['actor_id']));
    [$method, $path] = match ($operation) {
        'renstra-create' => ['POST', '/renstra'],
        'renstra-update' => ['PUT', '/renstra/'.$assignment['renstra_id']],
        'renstra-delete' => ['DELETE', '/renstra/'.$assignment['renstra_id']],
        'renstra-attachment' => ['DELETE', '/renstra/'.$assignment['renstra_id'].'/berkas/'.$assignment['berkas_id']],
    };
    $request = Request::create($path, $method, $assignment['data']);
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    if ($response->getStatusCode() === 403) {
        return 'denied';
    }
    if ($operation === 'renstra-delete' && $response->getStatusCode() === 302 && $request->session()->has('errors')) {
        return 'validation-denied';
    }
    if (isset($assignment['expected_error_field']) && $response->getStatusCode() === 302 && $request->session()->has('errors')) {
        // Middleware sudah menyimpan session JSON; error bag kini berupa array pesan.
        $errors = $request->session()->get('errors.default.messages');
        if ($errors !== [$assignment['expected_error_field'] => [$assignment['expected_error_message']]]) {
            throw new RuntimeException('Penolakan validasi Renstra tidak sesuai field/pesan yang diharapkan.');
        }

        return 'validation-denied';
    }
    if (isset($assignment['expected_error_field']) && $response->getStatusCode() >= 500) {
        return 'http-'.$response->getStatusCode();
    }
    if ($response->getStatusCode() !== 302 || $request->session()->has('errors') || ! $request->session()->has('success')) {
        throw new RuntimeException('Mutasi Renstra tidak mencapai hasil sukses atau penolakan izin.');
    }

    return 'mutated';
}

/** Jalur request Regulasi mencakup middleware, FormRequest, dan controller yang dipakai aplikasi. */
function performRegulasiMutation(string $operation, array $assignment): string
{
    Auth::setUser(User::findOrFail($assignment['actor_id']));
    [$method, $path] = match ($operation) {
        'regulasi-create' => ['POST', '/regulasi'],
        'regulasi-update' => ['PUT', '/regulasi/'.$assignment['regulasi_id']],
        'regulasi-delete' => ['DELETE', '/regulasi/'.$assignment['regulasi_id']],
        'regulasi-attachment' => ['DELETE', '/regulasi/'.$assignment['regulasi_id'].'/berkas/'.$assignment['berkas_id']],
    };
    $request = Request::create($path, $method, $assignment['data']);
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    if ($response->getStatusCode() === 403) {
        return 'denied';
    }
    if ($response->getStatusCode() !== 302 || $request->session()->has('errors') || ! $request->session()->has('success')) {
        throw new RuntimeException('Mutasi Regulasi tidak mencapai hasil sukses atau penolakan izin.');
    }

    return 'mutated';
}

/** Mutasi storage melewati middleware dan FormRequest yang sama dengan halaman aplikasi. */
function performStoragePolicyMutation(array $assignment): string
{
    Auth::setUser(User::findOrFail($assignment['actor_id']));
    $request = Request::create('/pengaturan/storage', 'PUT', $assignment['data']);
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    if ($response->getStatusCode() === 403) {
        return 'denied';
    }
    if ($response->getStatusCode() !== 302 || $request->session()->has('errors') || ! $request->session()->has('success')) {
        throw new RuntimeException('Mutasi storage tidak mencapai hasil sukses atau penolakan izin.');
    }

    return 'mutated';
}

/** Request nyata mempertahankan middleware, FormRequest, dan controller sebelum/sesudah ekstraksi. */
function performJenisBerkasMutation(string $operation, array $assignment): string
{
    Auth::setUser(User::findOrFail($assignment['actor_id']));
    [$method, $path] = match ($operation) {
        'jenis-create' => ['POST', '/jenis-berkas'],
        'jenis-update' => ['PUT', '/jenis-berkas/'.$assignment['jenis_id']],
        'jenis-delete' => ['DELETE', '/jenis-berkas/'.$assignment['jenis_id']],
        'jenis-technical' => ['PATCH', '/jenis-berkas/'.$assignment['jenis_id'].'/batas-teknis'],
    };
    $request = Request::create($path, $method, $assignment['data']);
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    if ($response->getStatusCode() === 403) {
        return 'denied';
    }
    if ($response->getStatusCode() !== 302 || $request->session()->has('errors') || ! $request->session()->has('success')) {
        throw new RuntimeException('Mutasi Jenis Berkas tidak mencapai hasil sukses atau penolakan izin.');
    }

    return 'mutated';
}

/** Jalur HTTP yang sama membuktikan urutan lock sebelum dan sesudah ekstraksi controller. */
function performGrantMutation(string $operation, array $assignment): string
{
    Auth::setUser(User::findOrFail($assignment['actor_id']));
    $request = Request::create(
        $operation === 'grant-create' ? '/akses/grant' : '/akses/grant/'.$assignment['grant_id'],
        $operation === 'grant-create' ? 'POST' : 'DELETE',
        $assignment['data'],
    );
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return match ($response->getStatusCode()) {
        403 => 'denied',
        302 => $operation === 'grant-create' ? 'created' : 'revoked',
        default => throw new RuntimeException('Status mutasi grant tidak sesuai: '.$response->getStatusCode()),
    };
}
