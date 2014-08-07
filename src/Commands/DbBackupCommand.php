<?php namespace BigName\BackupManagerLaravel\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use BigName\BackupManager\Databases\DatabaseProvider;
use BigName\BackupManager\Procedures\BackupProcedure;
use BigName\BackupManager\Filesystems\FilesystemProvider;

/**
 * Class ManagerBackupCommand
 * @package BigName\BackupManagerLaravel\Commands
 */
class DbBackupCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'db:backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create database dump and save it on a service';

    /**
     * The required arguments.
     *
     * @var array
     */
    private $required = ['database', 'destination', 'destinationPath', 'compression'];

    /**
     * The missing arguments.
     *
     * @var array
     */
    private $missingArguments;

    /**
     * @var \BigName\BackupManager\Procedures\BackupProcedure
     */
    private $backupProcedure;

    /**
     * @var \BigName\BackupManager\Databases\DatabaseProvider
     */
    private $databases;

    /**
     * @var \BigName\BackupManager\Filesystems\FilesystemProvider
     */
    private $filesystems;

    /**
     * @param BackupProcedure $backupProcedure
     * @param DatabaseProvider $databases
     * @param FilesystemProvider $filesystems
     */
    public function __construct(BackupProcedure $backupProcedure, DatabaseProvider $databases, FilesystemProvider $filesystems)
    {
        parent::__construct();
        $this->backupProcedure = $backupProcedure;
        $this->databases = $databases;
        $this->filesystems = $filesystems;
    }

    /**
     * Execute the console command.
     * @return mixed
     */
    public function fire()
    {
        if ($this->isMissingArguments()) {
            $this->displayMissingArguments();
            $this->promptForMissingArgumentValues();
            $this->validateArguments();
        }

        $this->info('Dumping database and uploading...');
        $this->runBackupProcedure();

        $this->showSuccessMessage();
    }

    private function runBackupProcedure()
    {
        $this->backupProcedure->run(
            $this->option('database'),
            $this->option('destination'),
            $this->option('destinationPath'),
            $this->option('compression')
        );
    }

    private function showSuccessMessage()
    {
        $this->lineBreak();
        $root = $this->filesystems->getConfig($this->option('destination'), 'root');
        $this->info(sprintf('Successfully dumped <comment>%s</comment>, compressed with <comment>%s</comment> and store it to <comment>%s</comment> at <comment>%s</comment>',
            $this->option('database'),
            $this->option('compression'),
            $this->option('destination'),
            $root . $this->option('destinationPath')
        ));
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
        return isset($this->missingArguments);
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
            if ($argument == 'database')
                $this->askDatabase();
            elseif ($argument == 'destination')
                $this->askDestination();
            elseif ($argument == 'destinationPath')
                $this->askDestinationPath();
            elseif ($argument == 'compression')
                $this->askCompression();

            $this->lineBreak();
        }
    }

    private function askDatabase()
    {
        $providers = $this->databases->getAvailableProviders();
        $formatted = implode(', ', $providers);
        $this->info("Available database connections: <comment>{$formatted}</comment>");
        $database = $this->askWithCompletion("From which database connection you want to dump?", $providers);
        $this->input->setOption('database', $database);
    }

    private function askDestination()
    {
        $providers = $this->filesystems->getAvailableProviders();
        $formatted = implode(', ', $providers);
        $this->info("Available storage services: <comment>{$formatted}</comment>");
        $destination = $this->askWithCompletion("To which storage service you want to save?", $providers);
        $this->input->setOption('destination', $destination);
    }

    private function askDestinationPath()
    {
        $root = $this->filesystems->getConfig($this->option('destination'), 'root');
        $path = $this->ask("How do you want to name the backup?<comment> {$root}</comment>");
        $this->input->setOption('destinationPath', $path);
    }

    private function askCompression()
    {
        $types = ['null', 'gzip'];
        $formatted = implode(', ', $types);
        $this->info("Available compression types: <comment>{$formatted}</comment>");
        $compression = $this->askWithCompletion('Which compression type you want to use?', $types);
        $this->input->setOption('compression', $compression);
    }

    /**
     * @return void
     */
    private function validateArguments()
    {
        $root = $this->filesystems->getConfig($this->option('destination'), 'root');
        $this->info('Just to be sure...');
        $this->info(sprintf('Do you want to create a backup of <comment>%s</comment>, store it on <comment>%s</comment> at <comment>%s</comment> and compress it to <comment>%s</comment>?',
            $this->option('database'),
            $this->option('destination'),
            $root . $this->option('destinationPath'),
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
            ['database', null, InputOption::VALUE_OPTIONAL, 'Database configuration name', null],
            ['destination', null, InputOption::VALUE_OPTIONAL, 'Destination configuration name', null],
            ['destinationPath', null, InputOption::VALUE_OPTIONAL, 'File destination path', null],
            ['compression', null, InputOption::VALUE_OPTIONAL, 'Compression type', null],
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
}
