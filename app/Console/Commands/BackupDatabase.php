<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'database:backup';

    protected $description = 'Create a consistent database backup in the configured private backup directory';

    public function handle(): int
    {
        $connection = DB::connection();
        $config = $connection->getConfig();
        $driver = $config['driver'];
        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            $this->error('Backup supports MySQL, MariaDB and file-based SQLite.');

            return self::FAILURE;
        }
        $directory = (string) config('backup.directory');
        File::ensureDirectoryExists($directory, 0700);
        $path = $directory.DIRECTORY_SEPARATOR.'nagarjuna-'.now()->format('Ymd-His').'-'.Str::random(8).($driver === 'sqlite' ? '.sqlite' : '.sql');
        try {
            if ($driver === 'sqlite') {
                if ($config['database'] === ':memory:' || ! extension_loaded('sqlite3')) {
                    throw new \RuntimeException('A file-based SQLite database and the sqlite3 extension are required.');
                }
                $source = new \SQLite3($config['database'], SQLITE3_OPEN_READONLY);
                $destination = new \SQLite3($path);
                try {
                    if (! $source->backup($destination)) {
                        throw new \RuntimeException('SQLite backup failed.');
                    }
                } finally {
                    $destination->close();
                    $source->close();
                }
            } else {
                $args = [
                    (string) config('backup.mysql_binary'), '--single-transaction', '--skip-lock-tables', '--quick',
                    '--routines', '--triggers', '--hex-blob', '--default-character-set=utf8mb4',
                    '--host='.(string) $config['host'], '--port='.(string) $config['port'],
                    '--user='.(string) $config['username'], '--result-file='.$path,
                ];
                if (! empty($config['unix_socket'])) {
                    $args[] = '--socket='.$config['unix_socket'];
                }
                $args[] = '--';
                $args[] = $config['database'];
                // The password is passed as a child-process environment value,
                // never as a shell argument or logged command string.
                $process = new Process($args, base_path(), ['MYSQL_PWD' => (string) ($config['password'] ?? '')]);
                $process->setTimeout((int) config('backup.timeout'));
                $process->run();
                if (! $process->isSuccessful()) {
                    throw new \RuntimeException('Database dump failed; check binary availability, database privileges and server logs.');
                }
            }
            if (! is_file($path) || filesize($path) === 0) {
                throw new \RuntimeException('Backup is empty.');
            }
            @chmod($path, 0600);
            file_put_contents($path.'.sha256', hash_file('sha256', $path).PHP_EOL);
            @chmod($path.'.sha256', 0600);
            $this->info('Backup created: '.$path);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if (is_file($path)) {
                unlink($path);
            }
            $this->error($exception instanceof \RuntimeException ? $exception->getMessage() : 'Backup could not complete. Verify the private directory and database backup configuration.');

            return self::FAILURE;
        }
    }
}
