<?php namespace BigName\BackupManagerLaravel;

use BigName\BackupManager\Databases;
use BigName\BackupManager\Filesystems;
use BigName\BackupManager\Compressors;
use Symfony\Component\Process\Process;
use Illuminate\Support\ServiceProvider;
use BigName\BackupManager\Config\Config;
use BigName\BackupManager\ShellProcessing\ShellProcessor;

class BackupManagerLaravelServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('heybigname/backup-manager-laravel', 'backup-manager-laravel', __DIR__ . '/../../..');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerFilesystemProvider();
        $this->registerDatabaseProvider();
        $this->registerCompressorProvider();
        $this->registerShellProcessor();
        $this->registerArtisanCommands();
    }

    /**
     * Register the filesystem provider.
     *
     * @return void
     */
    private function registerFilesystemProvider()
    {
        $this->app->bind('BigName\BackupManager\Filesystems\FilesystemProvider', function ($app) {
            $provider = new Filesystems\FilesystemProvider(new Config($app['config']['backup-manager::storage']));
            $provider->add(new Filesystems\Awss3Filesystem);
            $provider->add(new Filesystems\DropboxFilesystem);
            $provider->add(new Filesystems\FtpFilesystem);
            $provider->add(new Filesystems\LocalFilesystem);
            $provider->add(new Filesystems\RackspaceFilesystem);
            $provider->add(new Filesystems\SftpFilesystem);
            return $provider;
        });
    }

    /**
     * Register the database provider.
     *
     * @return void
     */
    private function registerDatabaseProvider()
    {
        $this->app->bind('BigName\BackupManager\Databases\DatabaseProvider', function ($app) {
            $provider = new Databases\DatabaseProvider($this->getDatabaseConfig($app['config']['database.connections']));
            $provider->add(new Databases\MysqlDatabase);
            $provider->add(new Databases\PostgresqlDatabase);
            return $provider;
        });
    }

    /**
     * Register the compressor provider.
     *
     * @return void
     */
    private function registerCompressorProvider()
    {
        $this->app->bind('BigName\BackupManager\Compressors\CompressorProvider', function () {
            $provider = new Compressors\CompressorProvider;
            $provider->add(new Compressors\GzipCompressor);
            $provider->add(new Compressors\NullCompressor);
            return $provider;
        });
    }

    /**
     * Register the filesystem provider.
     *
     * @return void
     */
    private function registerShellProcessor()
    {
        $this->app->bind('BigName\BackupManager\ShellProcessing\ShellProcessor', function () {
            return new ShellProcessor(new Process(''));
        });
    }

    /**
     * Register the artisan commands.
     *
     * @return void
     */
    private function registerArtisanCommands()
    {
        $this->commands([
            'BigName\BackupManagerLaravel\Commands\DbBackupCommand',
            'BigName\BackupManagerLaravel\Commands\DbRestoreCommand',
            'BigName\BackupManagerLaravel\Commands\DbListCommand',
        ]);
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'BigName\BackupManager\Filesystems\FilesystemProvider',
            'BigName\BackupManager\Compressors\CompressorProvider',
            'BigName\BackupManager\Databases\DatabaseProvider',
            'BigName\BackupManager\ShellProcessing\ShellProcessor',
        ];
    }

    private function getDatabaseConfig($connections)
    {
        $mapped = array_map(function ($connection) {
            if (!in_array($connection['driver'], ['mysql', 'pgsql'])) {
                return;
            }

            if (isset($connection['port'])) {
                $port = $connection['port'];
            } else {
                if ($connection['driver'] == 'mysql') {
                    $port = '3306';
                } elseif ($connection['driver'] == 'pgsql') {
                    $port = '5432';
                }
            }

            return [
                'type' => $connection['driver'],
                'host' => $connection['host'],
                'port' => $port,
                'user' => $connection['username'],
                'pass' => $connection['password'],
                'database' => $connection['database'],
            ];
        }, $connections);
        return new Config($mapped);
    }
}
