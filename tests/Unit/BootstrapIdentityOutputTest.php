<?php

namespace Tests\Unit;

use App\Console\Commands\BootstrapSuperadmin;
use App\Models\User;
use Illuminate\Console\OutputStyle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class BootstrapIdentityOutputTest extends TestCase
{
    #[DataProvider('identities')]
    public function test_identity_is_literal_single_line_text(string $name, string $email, string $expectedName, string $expectedEmail): void
    {
        $output = new BufferedOutput(32, true);
        $command = new BootstrapSuperadmin;
        $command->setOutput(new OutputStyle(new ArrayInput([]), $output));
        $user = new User;
        $user->forceFill(['id' => '1380daa1-7af3-4884-aa0c-178614d7de78', 'nama' => $name, 'email' => $email]);

        (new ReflectionMethod($command, 'displayAccountIdentity'))->invoke($command, $user);

        $this->assertSame(
            'ID akun: '.$user->id.PHP_EOL.'Nama: '.$expectedName.PHP_EOL.'Email: '.$expectedEmail.PHP_EOL,
            $output->fetch(),
        );
    }

    public static function identities(): array
    {
        return [
            'nama normal Unicode' => ['Nur Aisyah – José', 'calon@example.test', 'Nur Aisyah – José', 'calon@example.test'],
            'markup formatter' => ['Calon <fg=not-a-colour>Admin</>', 'calon@example.test', 'Calon <fg=not-a-colour>Admin</>', 'calon@example.test'],
            'kontrol terminal dan baris baru' => ["Calon\033[2J\033[H\r\nAdmin\t\u{009b}31m\u{202e}Uji", "calon\n@example.test", 'Calon [2J [H  Admin  31m Uji', 'calon @example.test'],
        ];
    }
}
