<?php

namespace App\Services\Backup;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * La copia extra en otra carpeta. La copia de cada día se queda en la misma computadora: si la laptop se malogra, se
 * moja o la roban, se pierde con ella. Por eso, apenas se crea, se guarda otra igual en la carpeta que elige el
 * consultorio: la de OneDrive o Google Drive de la computadora, que la suben solas a la nube, o un USB. Ahí quedan solo
 * las últimas. Si no se puede (la carpeta no está, no deja escribir, no hay espacio), se avisa en Hoy y en Copias de
 * seguridad, y se vuelve a intentar cada 15 minutos. Va igual que la original: comprimida y sin cifrar.
 */
final class ExtraCopy
{
    /** La carpeta elegida, con su ruta completa. Vacía: no hay copia extra. */
    public const FOLDER_SETTING = 'backups.extra_folder';

    /** Cuántas copias se guardan en esa carpeta. */
    public const KEEP_SETTING = 'backups.extra_keep';

    /** Una semana de copias: entran de sobra en un USB o en el espacio gratis de Drive y OneDrive. */
    public const KEEP_BY_DEFAULT = 7;

    /** Cómo salió el último intento. Lo anota el sistema, así que no va al historial de cambios. */
    private const LAST_SETTING = 'backups.extra_last';

    /** Cuando la carpeta no aparece: casi siempre un USB desconectado o la unidad de Drive, que las tareas no ven. */
    private const NOT_FOUND = 'No se encontró la carpeta. Si es un USB, conéctalo. Si es de Google Drive, usa la carpeta «Mi unidad» en modo «Reflejar archivos»: las tareas automáticas no ven la unidad G:.';

    public function __construct(private DatabaseBackup $database) {}

    public function folder(): ?string
    {
        $folder = trim((string) Setting::get(self::FOLDER_SETTING));

        return $folder === '' ? null : $folder;
    }

    public function keep(): int
    {
        return max(1, (int) Setting::get(self::KEEP_SETTING, self::KEEP_BY_DEFAULT));
    }

    /**
     * Por qué no sirve una carpeta, en una frase para quien la elige; null si sirve. Tiene que existir, dejar escribir y
     * estar fuera del sistema: dentro de storage/ la copia quedaría junto a la original y dentro de public/ cualquiera
     * podría bajarla desde la web.
     */
    public function problemWith(string $folder): ?string
    {
        $folder = trim($folder);

        if (preg_match('~^([a-zA-Z]:[\\\\/]|\\\\\\\\|/)~', $folder) !== 1) {
            return 'Escribe la ruta completa de la carpeta, por ejemplo C:\Users\consultorio\OneDrive\Copias del consultorio.';
        }

        if (! is_dir($folder)) {
            return self::NOT_FOUND;
        }

        if ($this->isInsideTheSystem($folder)) {
            return 'Esa carpeta es parte del sistema. Elige una fuera de él: la de OneDrive o Google Drive, o un USB.';
        }

        // Se prueba de verdad: en Windows, is_writable() no sabe de las carpetas de red ni de las de solo lectura.
        $probe = rtrim($folder, '\\/').DIRECTORY_SEPARATOR.'.prueba-consultorio-'.bin2hex(random_bytes(4));
        $written = @file_put_contents($probe, 'ok') === 2;
        @unlink($probe);

        return $written ? null : 'No se puede guardar nada en esa carpeta. Elige otra, o revisa que no sea solo de lectura.';
    }

    /**
     * Guarda en la carpeta una copia igual a $name (la de respaldos/) y borra ahí las que pasan de las últimas. Si
     * falla, no corta nada: anota el motivo para avisar y devuelve false.
     */
    public function copy(string $name): bool
    {
        $folder = $this->folder();

        if ($folder === null) {
            return false;
        }

        $file = basename($name);
        $source = Storage::disk('local')->path($name);
        $problem = match (true) {
            ! is_file($source) => 'No se encontró la copia de hoy para guardarla.',
            ! is_dir($folder) => self::NOT_FOUND,
            $this->isInsideTheSystem($folder) => 'Esa carpeta es parte del sistema. Elige una fuera de él: la de OneDrive o Google Drive, o un USB.',
            ($free = @disk_free_space($folder)) !== false && $free < filesize($source) => 'La carpeta no tiene espacio para la copia. Libera espacio o elige otra.',
            default => null,
        };

        if ($problem === null) {
            $target = rtrim($folder, '\\/').DIRECTORY_SEPARATOR.$file;
            // Como la original: se escribe aparte y se renombra al terminar, así nunca queda una a medias con su nombre.
            // Cada intento escribe en su propio archivo: "Probar de nuevo" y el reintento de cada 15 minutos pueden
            // coincidir, y con uno solo para los dos, uno renombraría la copia que el otro todavía está escribiendo.
            $partial = $target.'.'.bin2hex(random_bytes(4)).'.part';

            if (@copy($source, $partial) && @rename($partial, $target)) {
                $this->prune($folder);
                $this->remember($folder, $file, null);

                return true;
            }

            @unlink($partial);
            $problem = 'La copia no se pudo guardar en esa carpeta. Revisa que tenga espacio y que deje guardar archivos.';
        }

        $this->remember($folder, $file, $problem);

        return false;
    }

    /** Guarda la copia más reciente. Null si todavía no hay ninguna. */
    public function copyLatest(): ?bool
    {
        $latest = $this->database->list()[0]['name'] ?? null;

        return $latest === null ? null : $this->copy($latest);
    }

    /**
     * ¿Falta guardar ahí la copia más reciente? Para volver a intentar cuando a la hora de la copia la carpeta no estaba:
     * un USB desconectado, o Drive y OneDrive que todavía no abrían al prender la computadora.
     */
    public function isPending(): bool
    {
        if ($this->folder() === null) {
            return false;
        }

        $latest = $this->database->list()[0]['file'] ?? null;

        return $latest !== null && ($this->last()['saved_file'] ?? null) !== $latest;
    }

    /**
     * El último intento con la carpeta de ahora: si se cambió de carpeta, lo de la anterior ya no cuenta.
     *
     * @return array{at: CarbonImmutable, problem: string|null, saved_file: string|null, saved_at: CarbonImmutable|null}|null
     */
    public function last(): ?array
    {
        $last = Setting::get(self::LAST_SETTING);
        $folder = $this->folder();

        if (! is_array($last) || $folder === null || ($last['folder'] ?? null) !== $folder) {
            return null;
        }

        return [
            'at' => self::moment($last['at']),
            'problem' => $last['problem'] ?? null,
            'saved_file' => $last['saved_file'] ?? null,
            'saved_at' => isset($last['saved_at']) ? self::moment($last['saved_at']) : null,
        ];
    }

    /** Por qué no se guardó la última vez, para avisar en Hoy y en Copias de seguridad; null si se guardó. */
    public function problem(): ?string
    {
        return $this->last()['problem'] ?? null;
    }

    /** La copia más reciente ya está también en la carpeta. */
    public function hasTheLatest(): bool
    {
        $latest = $this->database->list()[0]['file'] ?? null;

        return $latest !== null && ($this->last()['saved_file'] ?? null) === $latest;
    }

    /** Cuándo se guardó ahí la última copia que sí quedó. */
    public function lastSavedAt(): ?CarbonImmutable
    {
        return $this->last()['saved_at'] ?? null;
    }

    private function isInsideTheSystem(string $folder): bool
    {
        $real = realpath($folder);

        if ($real === false) {
            return false;
        }

        $normalized = fn (string $path) => rtrim(str_replace('\\', '/', PHP_OS_FAMILY === 'Windows' ? mb_strtolower($path) : $path), '/').'/';

        foreach ([base_path(), storage_path(), public_path()] as $system) {
            $systemReal = realpath($system);

            if ($systemReal !== false && str_starts_with($normalized($real), $normalized($systemReal))) {
                return true;
            }
        }

        return false;
    }

    /** Deja en la carpeta solo las últimas. Toca únicamente archivos de copia: lo demás de esa carpeta no es suyo. */
    private function prune(string $folder): void
    {
        $copies = collect(scandir($folder) ?: [])
            ->filter(fn (string $file) => DatabaseBackup::isBackupName($file))
            ->sortDesc()
            ->values();

        foreach ($copies->slice($this->keep()) as $old) {
            @unlink(rtrim($folder, '\\/').DIRECTORY_SEPARATOR.$old);
        }
    }

    private function remember(string $folder, string $file, ?string $problem): void
    {
        $before = $this->last();

        Setting::set(self::LAST_SETTING, [
            'folder' => $folder,
            'at' => now()->getTimestamp(),
            'problem' => $problem,
            // La última que sí quedó, para decir "guardada también en…" aunque el intento de después haya fallado.
            'saved_file' => $problem === null ? $file : ($before['saved_file'] ?? null),
            'saved_at' => $problem === null ? now()->getTimestamp() : ($before['saved_at'] ?? null)?->getTimestamp(),
        ]);
    }

    private static function moment(int $timestamp): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestamp($timestamp, config('app.timezone'));
    }
}
