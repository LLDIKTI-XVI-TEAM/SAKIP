<?php

use App\Models\NotifikasiPengiriman;
use App\Models\RencanaAksi;
use App\Services\Notifikasi\AntrekanNotifikasiEksternal;
use App\Services\Notifikasi\PengirimNotifikasiEksternal;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
$identity = $db->selectOne('select current_database() as database, current_user as username');
if (! $app->environment('testing') || $app->configurationIsCached() || env('SAKIP_TEST_ALLOW_DATABASE_RESET') !== '1'
    || $db->getDriverName() !== 'pgsql' || $db->getConfig('username') !== 'sakip_test'
    || $identity->database !== 'sakip_test' || $identity->username !== 'sakip_test') {
    throw new RuntimeException('Worker hanya untuk testing disposable.');
}
config(['notifikasi.kanal.email.aktif' => true]);
$data = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
echo 'READY:'.DB::selectOne('select pg_backend_pid() as pid')->pid.PHP_EOL;
fflush(STDOUT);
fgets(STDIN);
try {
    if ($data['operation'] === 'antre') {
        $row = app(AntrekanNotifikasiEksternal::class)->antre('race:1', RencanaAksi::findOrFail($data['objek']), 'race-periode', [$data['user']], ['email'], 'Pesan sintetis', [])->first();
    } else {
        $row = NotifikasiPengiriman::findOrFail($data['id']);
        app(PengirimNotifikasiEksternal::class)->kirim($row->id, 1, 4);
    }
    echo 'RESULT:'.$row->id.PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Worker notifikasi gagal: '.$exception::class.PHP_EOL);
    exit(1);
}
