<?php

namespace App\Console\Commands;

use App\Actions\Auth\BootstrapSuperadmin as Bootstrap;
use App\Models\User;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Process\Process;

class BootstrapSuperadmin extends Command
{
    protected $signature = 'sakip:bootstrap-superadmin {user? : ID akun SAKIP dari halaman menunggu aktivasi} {--operator-reference= : Identitas operator dan referensi otorisasi manusia} {--reason= : Alasan bootstrap} {--confirm-user= : Konfirmasi ID akun untuk eksekusi non-interaktif}';

    protected $description = 'Inisialisasi satu Superadmin dan preset izin sekali saja dengan audit operator.';

    public function handle(Bootstrap $bootstrap): int
    {
        $interactive = $this->input->isInteractive();
        $userId = trim((string) ($this->argument('user') ?: ($interactive ? $this->ask('ID akun SAKIP') : '')));
        if (! Str::isUuid($userId)) {
            $this->error('ID akun SAKIP harus berupa UUID yang valid.');

            return self::FAILURE;
        }
        $user = User::find($userId);
        if (! $user) {
            $this->error('ID akun SAKIP tidak ditemukan.');

            return self::FAILURE;
        }
        $this->displayAccountIdentity($user);
        $operator = trim((string) ($this->option('operator-reference') ?: ($interactive ? $this->ask('Identitas operator dan referensi otorisasi') : '')));
        $reason = trim((string) ($this->option('reason') ?: ($interactive ? $this->ask('Alasan bootstrap') : '')));
        if ($operator === '' || $reason === '') {
            $this->error('Referensi operator dan alasan wajib diisi.');

            return self::FAILURE;
        }
        $confirmation = $this->option('confirm-user');
        if ($confirmation !== null ? $confirmation !== $userId : (! $interactive || ! $this->confirm('Aktifkan akun ini sebagai Super Admin?'))) {
            $this->error('Bootstrap dibatalkan: ID akun belum dikonfirmasi.');

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
            $changed = $bootstrap->handle($userId, $operator, $reason, $runtime);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info($changed ? 'Bootstrap selesai dan tercatat dalam audit.' : 'Bootstrap sudah pernah selesai; tidak ada hak akses yang dipulihkan.');

        return self::SUCCESS;
    }

    protected function displayAccountIdentity(User $user): void
    {
        // Profil SSO tidak boleh mengubah format terminal atau menyamarkan identitas konfirmasi.
        foreach (['ID akun' => $user->id, 'Nama' => $user->nama, 'Email' => $user->email] as $label => $value) {
            $text = preg_replace('/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]/u', ' ', $value) ?? '[identitas tidak valid]';
            $this->line($label.': '.OutputFormatter::escape($text));
        }
    }
}
