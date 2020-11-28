<?php

namespace Backup\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MysqlFixFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:fix-file
                            {--f|filename= : Fix a specific backup file name}
                            {--C|from-cloud : Display a list of backup files from cloud disk}
                            {--y|yes : Confirms file encoding mode fix}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix backup file compression mode form gzcompress to gzencode';

    /**
     * Dump file name.
     *
     * @var string
     */
    protected $filename;

    /**
     * Local disk where backups will be stored.
     *
     * @var string
     */
    protected $localDisk;

    /**
     * Local path where the backups will be stored.
     *
     * @var array
     */
    protected $localPath;

    /**
     * Determine if backup will be restored from cloud.
     *
     * @var bool
     */
    protected $cloudRestoration;

    /**
     * Cloud disk name.
     *
     * @var string
     */
    protected $cloudDisk;

    /**
     * Cloud path where the backups will be stored.
     *
     * @var array
     */
    protected $cloudPath;

    /**
     * Confirms file encoding fix.
     *
     * @var bool
     */
    protected $confirmFix;

    public function __construct()
    {
        parent::__construct();

        $this->localDisk = config('backup.mysql.local-storage.disk', 'local');
        $this->localPath = config('backup.mysql.local-storage.path', null);
        $this->cloudDisk = config('backup.mysql.cloud-storage.disk', null);
        $this->cloudPath = config('backup.mysql.cloud-storage.path', null);
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->handleOptions();
        $this->handleContinue();
        $this->fixEncoding();
    }

    protected function handleOptions()
    {
        $this->cloudRestoration = $this->option('from-cloud');
        $this->confirmFix = $this->option('yes');
        $this->setFilename();
    }

    protected function handleContinue()
    {
        if (!$this->confirmFix) {
            if (!$this->confirm('Are you sure that you want to fix the file encoding? [y|N]')) {
                exit();
            }
        }
    }

    protected function setFilename()
    {
        $filename = trim($this->option('filename'));
        if (empty($filename)) {
            $filename = $this->displayOptions();
        }
        $this->filename = $filename;
        if (!$this->isFileExtensionValid($this->filename)) {
            $this->error("File '{$this->filename}' is not a valid backup file!");
            exit();
        }
        if (!$this->backupFileExists()) {
            $this->error("File '{$this->filename}' does not exists!");
            exit();
        }
    }

    protected function getDisk()
    {
        return $this->cloudRestoration ? $this->cloudDisk : $this->localDisk;
    }

    protected function getFilePath($filename = null, $path = null)
    {
        $path = $this->cleanPath(is_null($path) ? $this->cloudRestoration ? $this->cloudPath : $this->localPath : $path);

        return $path.DIRECTORY_SEPARATOR.(is_null($filename) ? $this->filename : $filename);
    }

    protected function cleanPath($path)
    {
        return ltrim(rtrim($path, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
    }

    protected function sanitizeFile($file)
    {
        $path = $this->cleanPath($this->cloudRestoration ? $this->cloudPath : $this->localPath);

        return str_replace($path.DIRECTORY_SEPARATOR, '', $file);
    }

    protected function isFileExtensionValid($filename)
    {
        return ends_with($filename, '.sql.gz');
    }

    protected function backupFileExists()
    {
        return Storage::disk($this->getDisk())->has($this->getFilePath());
    }

    protected function displayOptions()
    {
        $files = $this->getFiles();

        return $this->choice("Which database backup file do you want to fix encoding from '{$this->getDisk()}' disk?", $files, false);
    }

    protected function getFiles()
    {
        $files = [];
        $filesOnDisk = array_reverse(Storage::disk($this->getDisk())->files($this->getFilePath()));

        foreach ($filesOnDisk as $file) {
            $file = $this->sanitizeFile($file);
            if (ends_with($file, '.sql.gz')) {
                array_push($files, $file);
            }
        }
        if (count($files) == 0) {
            $this->error('There are no backup files to fix!');
            exit();
        }

        return $files;
    }

    protected function fixEncoding()
    {
        $fileContent = Storage::disk($this->getDisk())->get($this->getFilePath());
        $fixedFileContent = gzencode(gzuncompress($fileContent), 9);
        Storage::disk($this->getDisk())->put($this->getFilePath('fix_'.$this->filename), $fixedFileContent);

        $this->info("File '{$this->filename}' fixed successfully from '{$this->getDisk()}'");
    }
}
