<?php  namespace BigName\BackupManagerLaravel\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use BigName\BackupManager\Filesystems\FilesystemProvider;

class DbListCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'db:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List contents of a backup storage destination.';

    /**
     * @var FilesystemProvider
     */
    private $filesystems;

    /**
     * The required arguments.
     *
     * @var array
     */
    private $required = ['source', 'path'];

    /**
     * The missing arguments.
     *
     * @var array
     */
    private $missingArguments;


    public function __construct(FilesystemProvider $filesystems)
    {
        parent::__construct();
        $this->filesystems = $filesystems;
    }

    /**
     * Execute the console command.
     * @return void
     */
    public function fire()
    {
        if ($this->isMissingArguments()) {
            $this->displayMissingArguments();
            $this->promptForMissingArgumentValues();
            $this->validateArguments();
        }

        $this->showContentsInTable($this->getContentsFromPath());
    }

    private function showContentsInTable(array $contents)
    {
        $rows = [];
        foreach ($contents as $row)
            $rows[] = $this->getTableRow($row);

        $this->table(['Name', 'Extension', 'Size', 'Created'], $rows);
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
        $this->info("These arguments haven't been filled yet: <comment>{$formatted}</comment>.");
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
            elseif ($argument == 'path')
                $this->askPath();

            $this->lineBreak();
        }
    }

    private function askSource()
    {
        $providers = $this->filesystems->getAvailableProviders();
        $formatted = implode(', ', $providers);
        $this->info("Available sources: <comment>{$formatted}</comment>");
        $source = $this->askWithCompletion("From which source do you want to list?", $providers);
        $this->input->setOption('source', $source);
    }

    private function askPath()
    {
        $root = $this->filesystems->getConfig($this->option('source'), 'root');
        $path = $this->ask("From which path?<comment> {$root}</comment>");
        $this->input->setOption('path', $path);
    }

    /**
     * @return void
     */
    private function validateArguments()
    {
        $root = $this->filesystems->getConfig($this->option('source'), 'root');
        $this->info('Just to be sure...');
        $this->info(sprintf('Do you want to list files from <comment>%s</comment> on <comment>%s</comment>?',
            $root . $this->option('path'),
            $this->option('source')
        ));
        $this->lineBreak();
        $this->confirmToProceed();
    }

    private function confirmToProceed()
    {
        $confirmation = $this->confirm('Are these correct? [Y/n]');
        if ( ! $confirmation)
            $this->reAskArguments();
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
            ['path', null, InputOption::VALUE_OPTIONAL, 'Directory path', null],
        ];
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

    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function getContentsFromPath()
    {
        $filesystem = $this->filesystems->get($this->option('source'));
        return $filesystem->listContents($this->option('path'));
    }

    private function lineBreak()
    {
        $this->line(PHP_EOL);
    }
} 
