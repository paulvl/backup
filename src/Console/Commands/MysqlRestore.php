<?php

namespace Backup\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MysqlRestore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = "backup:mysql-restore
                            {--f|filename= : Especifiy a backup file name}
                            {--A|all-backup-files : Display all available backup files on disk. By default displays files for current connection's database}
                            {--C|from-cloud : Display a list of backup files from cloud disk}
                            {--L|restore-latest-backup : Use latest backup file to restore database}
                            {--y|yes : Confirms database restoration}
                            ";

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore your Mysql database from a file';

    /**
     * The database connection data.
     *
     * @var array
     */
    protected $connection;

    /**
     * The path to mysql dump.
     *
     * @var string
     */
    protected $mysqlPath;

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
     * Determinate if options will display all files.
     *
     * @var bool
     */
    protected $displayAllBackupFiles;

    /**
     * Determinate if latest backup will be restored.
     *
     * @var bool
     */
    protected $restoreLatestBackup;

    /**
     * Confirms restoration without asking.
     *
     * @var bool
     */
    protected $confirmRestoration;

    public function __construct()
    {
        parent::__construct();

        $this->mysqlPath = config('backup.mysql.mysql_path', 'mysql');

        $this->connection = [
            'host'     => config('database.connections.mysql.host'),
            'database' => config('database.connections.mysql.database'),
            'port'     => config('database.connections.mysql.port'),
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
        ];

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
        $this->restoreDatabase();
    }

    protected function handleOptions()
    {
        $this->cloudRestoration = $this->option('from-cloud');
        $this->displayAllBackupFiles = $this->option('all-backup-files');
        $this->restoreLatestBackup = $this->option('restore-latest-backup');
        $this->confirmRestoration = $this->option('yes');

        $this->setFilename();
    }

    protected function handleContinue()
    {
        if (!$this->confirmRestoration) {
            if (!$this->confirm('Are you sure that you want to restore the database? [y|N]')) {
                die();
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
            die();
        }
        if (!$this->backupFileExists()) {
            $this->error("File '{$this->filename}' does not exists!");
            die();
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

    protected function getAbsFilePath($filename, $disk = null, $path = null)
    {
        $path = $this->cleanPath($path);

        return Storage::disk(is_null($disk) ? $this->getDisk() : $disk)->getAdapter()->getPathPrefix().$path.DIRECTORY_SEPARATOR.$filename;
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
        return ends_with($filename, '.sql') || ends_with($filename, '.sql.gz');
    }

    protected function backupFileExists()
    {
        return Storage::disk($this->getDisk())->has($this->getFilePath());
    }

    protected function displayOptions()
    {
        $files = $this->getFiles();
        $filename = null;
        if ($this->restoreLatestBackup) {
            $filename = $files[0];
        } else {
            $filename = $this->choice("Which database backup file do you want to restore from '{$this->getDisk()}' disk?", $files, false);
        }

        return $filename;
    }

    protected function getFiles()
    {
        $files = [];
        $filesOnDisk = array_reverse(Storage::disk($this->getDisk())->files($this->getFilePath()));

        foreach ($filesOnDisk as $file) {
            $file = $this->sanitizeFile($file);
            if (starts_with($file, $this->connection['database']) || $this->displayAllBackupFiles) {
                array_push($files, $file);
            }
        }
        if (count($files) == 0) {
            $this->error('There are no backup files to restore!');
            die();
        }

        return $files;
    }

    protected function restoreDatabase()
    {
        $hostname = escapeshellarg($this->connection['host']);
        $port = $this->connection['port'];
        $database = $this->connection['database'];
        $username = escapeshellarg($this->connection['username']);
        $password = $this->connection['password'];

        $databaseArg = escapeshellarg($database);
        $portArg = !empty($port) ? '-P '.escapeshellarg($port) : '';
        $passwordArg = !empty($password) ? '-p'.escapeshellarg($password) : '';

        $localFilename = $this->filename;

        if ($this->cloudRestoration) {
            if (ends_with($this->filename, '.gz')) {
                $localFilename = str_replace('.sql.gz', '.cloud.sql.gz', $this->filename);
            } else {
                $localFilename = str_replace('.sql', '.cloud.sql', $this->filename);
            }
            Storage::disk($this->localDisk)->put($this->getFilePath($localFilename, $this->localPath), Storage::disk($this->cloudDisk)->get($this->getFilePath()));
        }

        $filename = $localFilename;

        if (ends_with($filename, '.gz')) {
            $fileContent = gzuncompress(Storage::disk($this->localDisk)->get($this->getFilePath($localFilename, $this->localPath)));
            $filename = str_replace('.sql.gz', '.tmp', $localFilename);
            Storage::disk($this->localDisk)->put($this->getFilePath($filename, $this->localPath), $fileContent);
            $isTempFilename = true;
        }

        $restoreCommand = "{$this->mysqlPath} -h {$hostname} {$portArg} -u{$username} {$passwordArg} {$databaseArg} < ".$this->getAbsFilePath($filename, $this->localDisk, $this->localPath);

        exec($restoreCommand, $restoreResult, $result);

        if ($result == 0) {
            $this->info("Database '{$database}' restored successfully from '{$this->getDisk()}' disk and '{$this->filename}' file");
        } else {
            $this->error("Database '{$database}' cannot be restored");
        }

        if (isset($isTempFilename)) {
            Storage::disk($this->localDisk)->delete($this->getFilePath($filename, $this->localPath));
        }
        if ($localFilename != $this->filename) {
            Storage::disk($this->localDisk)->delete($this->getFilePath($localFilename, $this->localPath));
        }
    }
}
