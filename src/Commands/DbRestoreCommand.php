<?php namespace BigName\BackupManagerLaravel\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use BigName\BackupManager\Databases\DatabaseProvider;
use BigName\BackupManager\Procedures\RestoreProcedure;
use BigName\BackupManager\Filesystems\FilesystemProvider;

class DbRestoreCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'db:restore';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore a database backup.';

    /**
     * The required arguments.
     *
     * @var array
     */
    private $required = ['source', 'sourcePath', 'database', 'compression'];

    /**
     * The missing arguments.
     *
     * @var array
     */
    private $missingArguments;

    /**
     * @var RestoreProcedure
     */
    private $restore;

    /**
     * @var FilesystemProvider
     */
    private $filesystems;

    /**
     * @var DatabaseProvider
     */
    private $databases;

    /**
     * @param RestoreProcedure $restore
     * @param FilesystemProvider $filesystems
     * @param DatabaseProvider $databases
     */
    public function __construct(RestoreProcedure $restore, FilesystemProvider $filesystems, DatabaseProvider $databases)
    {
        parent::__construct();
        $this->restore = $restore;
        $this->filesystems = $filesystems;
        $this->databases = $databases;
    }

    /**
     *
     */
    public function fire()
    {
        if ($this->isMissingArguments()) {
            $this->displayMissingArguments();
            $this->promptForMissingArgumentValues();
            $this->validateArguments();
        }

        $this->info('Downloading and importing backup...');
        $this->runRestoreProcedure();

        $this->showSuccessMessage();
    }

    /**
     * @return bool
     */
    private function isMissingArguments()
    {
        foreach ($this->required as $argument) {
            if ( ! $this->option($argument))
                $this->missingArguments[] = $argument;
        }
        return (bool) $this->missingArguments;
    }

    /**
     * @return void
     */
    private function displayMissingArguments()
    {
        $formatted = implode(', ', $this->missingArguments);
        $this->info("These arguments haven't been filled yet: <comment>{$formatted}</comment>");
        $this->info('The following questions will fill these in for you.');
        $this->lineBreak();
    }

    /**
     * @return void
     */
    private function promptForMissingArgumentValues()
    {
        foreach ($this->missingArguments as $argument) {
            if ($argument == 'source')
                $this->askSource();
            elseif ($argument == 'sourcePath')
                $this->askSourcePath();
            elseif ($argument == 'database')
                $this->askDatabase();
            elseif ($argument == 'compression')
                $this->askCompression();

            $this->lineBreak();
        }
    }

    private function askSource()
    {
        $providers = $this->filesystems->getAvailableProviders();
        $formatted = implode(', ', $providers);
        $this->info("Available storage services: <comment>{$formatted}</comment>");
        $source = $this->autocomplete("From which storage service do you want to choose?", $providers);
        $this->input->setOption('source', $source);
    }

    private function askSourcePath()
    {
        // ask path
        $root = $this->filesystems->getConfig($this->option('source'), 'root');
        $path = $this->ask("From which path do you want to select?<comment> {$root}</comment>");
        $this->lineBreak();

        // ask file
        $filesystem = $this->filesystems->get($this->option('source'));
        $contents = $filesystem->listContents($path);

        $files = [];

        foreach ($contents as $file) {
            if ($file['type'] == 'dir') continue;
            $files[] = $file['basename'];
        }

        if (empty($files)) {
            $this->info('No backups were found at this path.');
            return;
        }

        $rows = [];
        foreach ($contents as $file)
            $rows[] = $this->getTableRow($file);

        $this->info('Available database dumps:');
        $this->table(['Name', 'Extension', 'Size', 'Created'], $rows);
        $filename = $this->autocomplete("Which database dump do you want to restore?", $files);
        $this->input->setOption('sourcePath', "{$path}/{$filename}");
    }

    private function askDatabase()
    {
        $providers = $this->databases->getAvailableProviders();
        $formatted = implode(', ', $providers);
        $this->info("Available database connections: <comment>{$formatted}</comment>");
        $database = $this->autocomplete("From which database connection you want to dump?", $providers);
        $this->input->setOption('database', $database);
    }

    private function askCompression()
    {
        $types = ['null', 'gzip'];
        $formatted = implode(', ', $types);
        $this->info("Available compression types: <comment>{$formatted}</comment>");
        $compression = $this->autocomplete('Which compression type you want to use?', $types);
        $this->input->setOption('compression', $compression);
    }

    /**
     * @return void
     */
    private function validateArguments()
    {
        $root = $this->filesystems->getConfig($this->option('source'), 'root');
        $this->info('Just to be sure...');
        $this->info(sprintf('Do you want to restore the backup <comment>%s</comment> from <comment>%s</comment> to database <comment>%s</comment> and decompress it from <comment>%s</comment>?',
            $root . $this->option('sourcePath'),
            $this->option('source'),
            $this->option('database'),
            $this->option('compression')
        ));
        $this->lineBreak();
        $this->confirmToProceed();
    }

    /**
     * Get the console command options.
     *
     * @return void
     */
    private function reAskArguments()
    {
        $this->lineBreak();
        $this->info('Answers have been reset and re-asking questions.');
        $this->lineBreak();
        $this->promptForMissingArgumentValues();
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['source', null, InputOption::VALUE_OPTIONAL, 'Source configuration name', null],
            ['sourcePath', null, InputOption::VALUE_OPTIONAL, 'Source path from service', null],
            ['database', null, InputOption::VALUE_OPTIONAL, 'Database configuration name', null],
            ['compression', null, InputOption::VALUE_OPTIONAL, 'Compression type', null],
        ];
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function getTableRow($file)
    {
        if ($file['type'] == 'dir') {
            return [
                "{$file['basename']}/",
                '',
                '0 B',
                date('D j Y  H:i:s', $file['timestamp'])
            ];
        }
        return [
            $file['basename'],
            $file['extension'],
            $this->formatBytes($file['size']),
            date('D j Y  H:i:s', $file['timestamp'])
        ];
    }

    private function lineBreak()
    {
        $this->line(PHP_EOL);
    }

    private function confirmToProceed()
    {
        $confirmation = $this->confirm('Are these correct? [Y/n]');
        if ( ! $confirmation)
            $this->reAskArguments();
    }

    private function runRestoreProcedure()
    {
        $this->restore->run(
            $this->option('source'),
            $this->option('sourcePath'),
            $this->option('database'),
            $this->option('compression')
        );
    }

    private function showSuccessMessage()
    {
        $this->lineBreak();
        $root = $this->filesystems->getConfig($this->option('source'), 'root');
        $this->info(sprintf('Successfully restored <comment>%s</comment> from <comment>%s</comment> to database <comment>%s</comment>.',
            $root . $this->option('sourcePath'),
            $this->option('source'),
            $this->option('database')
        ));
    }
}
