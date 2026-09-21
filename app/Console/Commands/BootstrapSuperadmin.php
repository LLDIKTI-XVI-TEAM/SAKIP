<?php

namespace App\Console\Commands;

use App\Actions\Auth\BootstrapSuperadmin as Bootstrap;
use DomainException;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BootstrapSuperadmin extends Command
{
    protected $signature = 'sakip:bootstrap-superadmin {subject : Subject persis dari akun yang sudah login SSO} {--operator-reference= : Identitas operator dan referensi otorisasi manusia} {--reason= : Alasan bootstrap} {--confirm-subject= : Konfirmasi ulang subject yang sama}';

    protected $description = 'Inisialisasi satu Superadmin dan preset izin sekali saja dengan audit operator.';

    public function handle(Bootstrap $bootstrap): int
    {
        $subject = (string) $this->argument('subject');
        if ($subject === '' || $this->option('confirm-subject') !== $subject) {
            $this->error('Konfirmasi subject secara eksplisit dengan --confirm-subject.');

            return self::FAILURE;
        }
        $process = new Process(['whoami']);
        $process->setTimeout(5)->run();
        if (! $process->isSuccessful() || trim($process->getOutput()) === '') {
            $this->error('Identitas akun eksekusi tidak dapat diverifikasi.');

            return self::FAILURE;
        }
        // Akses shell merupakan batas operasional; identitas OS bukan nama target SSO.
        $runtime = trim($process->getOutput()).'@'.gethostname().' pid='.getmypid();
        try {
            $changed = $bootstrap->handle($subject, (string) $this->option('operator-reference'), (string) $this->option('reason'), $runtime);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info($changed ? 'Bootstrap selesai dan tercatat dalam audit.' : 'Bootstrap sudah pernah selesai; tidak ada hak akses yang dipulihkan.');

        return self::SUCCESS;
    }
}
