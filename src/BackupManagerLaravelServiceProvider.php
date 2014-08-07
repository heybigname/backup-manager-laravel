<?php namespace BigName\BackupManagerLaravel;

use BigName\BackupManager\Databases;
use BigName\BackupManager\Filesystems;
use BigName\BackupManager\Compressors;
use Symfony\Component\Process\Process;
use Illuminate\Support\ServiceProvider;
use BigName\BackupManager\Config\Config;
use BigName\BackupManager\Shell\ShellProcessor;

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
        $this->app->bind('BigName\BackupManager\Filesystems\FilesystemProvider', function($app) {
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
        $this->app->bind('BigName\BackupManager\Databases\DatabaseProvider', function($app) {
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
        $this->app->bind('BigName\BackupManager\Compressors\CompressorProvider', function() {
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
        $this->app->bind('BigName\BackupManager\Shell\ShellProcessor', function() {
            return new ShellProcessor(new Process('', null, null, null, null));
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
            'BigName\BackupManager\Shell\ShellProcessor',
        ];
    }

    /**
     * @param  array $connections
     * @return Config
     */
    private function getDatabaseConfig($connections)
    {
        return new Config($this->mapLaravelConnections($connections));
    }

    /**
     * @param  array $connections
     * @return array
     */
    private function mapLaravelConnections(array $connections)
    {
        return array_map(function($connection) {
            if ( ! $this->isSupportedDriver($connection['driver']))
                return;

            if (isset($connection['port']))
                $port = $connection['port'];
            else
                $port = $this->getStandardPortForDriver($connection['driver']);

            return [
                'type' => $connection['driver'],
                'host' => $connection['host'],
                'port' => $port,
                'user' => $connection['username'],
                'pass' => $connection['password'],
                'database' => $connection['database'],
            ];
        }, $connections);
    }

    /**
     * @param  string $driver
     * @return bool
     */
    private function isSupportedDriver($driver)
    {
        return in_array($driver, ['mysql', 'pgsql']);
    }

    /**
     * @param  string $driver
     * @return string
     */
    private function getStandardPortForDriver($driver)
    {
        if ($driver == 'mysql')
            return '3306';
        elseif ($driver == 'pgsql')
            return '5432';
    }
}
