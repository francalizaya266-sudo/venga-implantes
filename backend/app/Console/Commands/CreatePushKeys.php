<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use App\Services\Notices\PushKeys;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('avisos:crear-claves {--show : Solo mostrarlas, sin guardarlas en .env} {--force : Cambiar las que ya hay}')]
#[Description('Crea las claves del consultorio para los avisos en el celular y las guarda en .env (una sola vez)')]
class CreatePushKeys extends Command
{
    public function handle(PushKeys $pushKeys): int
    {
        $exists = filled(config('consultorio.push.public_key')) && filled(config('consultorio.push.private_key'));

        if ($exists && ! $this->option('force') && ! $this->option('show')) {
            $this->info('Las claves de los avisos en el celular ya están en .env. No hace falta crearlas de nuevo.');

            return self::SUCCESS;
        }

        $keys = $pushKeys->create();

        if ($this->option('show')) {
            $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
            $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);

            return self::SUCCESS;
        }

        $this->write(['VAPID_PUBLIC_KEY' => $keys['publicKey'], 'VAPID_PRIVATE_KEY' => $keys['privateKey']]);
        config(['consultorio.push.public_key' => $keys['publicKey'], 'consultorio.push.private_key' => $keys['privateKey']]);

        if ($exists) {
            // Con claves nuevas, los celulares que ya los tenían dejan de recibirlos: los vuelven a activar en Mi cuenta.
            $removed = PushSubscription::query()->delete();
            $this->warn("Claves cambiadas. {$removed} celulares tienen que volver a activar los avisos en Mi cuenta.");
        }

        $this->info('Listo: los avisos en el celular ya se pueden activar desde Mi cuenta. Si usas config:cache, vuelve a ejecutarlo.');

        return self::SUCCESS;
    }

    /** @param  array<string, string>  $values */
    private function write(array $values): void
    {
        $path = $this->laravel->environmentFilePath();
        $env = is_file($path) ? (string) file_get_contents($path) : '';

        foreach ($values as $name => $value) {
            $line = $name.'='.$value;
            $env = preg_match("/^{$name}=.*$/m", $env)
                ? (string) preg_replace("/^{$name}=.*$/m", $line, $env)
                : rtrim($env, "\n")."\n".$line."\n";
        }

        file_put_contents($path, $env);
    }
}
