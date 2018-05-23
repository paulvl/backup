<?php

namespace Backup\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MysqlDump extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:mysql-dump
                            {filename? : Mysql backup filename}
                            {--no-compress : Disable file compression regardless if is enabled in the configuration file. This option will be always overwrited by --compress option}
                            {--compress : Enable file compression regardless if is disabled in the configuration file. This option will always overwrite --no-compress option}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump your Mysql database to a file';

    /**
     * The database connection data.
     *
     * @var array
     */
    protected $connection;

    /**
     * The databases connection data.
     *
     * @var array
     */
    protected $connections;
    
    /**
     * Flag if connection != null
     *
     * @var array
     */
    protected $unique_connection;

    /**
     * The databases connection data.
     *
     * @var array
     */
    protected $file_names;

    /**
     * The path to mysql dump.
     *
     * @var string
     */
    protected $mysqldumpPath;

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
     * Determine if backup will be cloud synced.
     *
     * @var bool
     */
    protected $cloudSync;

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
     * The path where backups will be stored.
     *
     * @var array
     */
    protected $keepLocal;

    /**
     * Determine if the file will be compressed.
     *
     * @var array
     */
    protected $isCompressionEnabled = false;

    public function __construct()
    {
        parent::__construct();

        $this->mysqldumpPath = config('backup.mysql.mysqldump_path', 'mysqldump');
        
        $this->file_names = [];

        $this->connections = config('backup.mysql.connections');

        $this->unique_connection = false;

        if (is_null($this->connections) || !is_array($this->connections)) 
        {
            $this->unique_connection = true;

            $this->connection = [
                'host'     => config('database.connections.mysql.host'),
                'database' => config('database.connections.mysql.database'),
                'port'     => config('database.connections.mysql.port'),
                'username' => config('database.connections.mysql.username'),
                'password' => config('database.connections.mysql.password'),
            ];
        }

        $this->localDisk = config('backup.mysql.local-storage.disk', 'local');
        $this->localPath = config('backup.mysql.local-storage.path', null);
        $this->cloudSync = config('backup.mysql.cloud-storage.enabled', false);
        $this->cloudDisk = config('backup.mysql.cloud-storage.disk', null);
        $this->cloudPath = config('backup.mysql.cloud-storage.path', null);
        $this->keepLocal = config('backup.mysql.cloud-storage.keep-local', true);
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->handleOptions();
        
        if ($this->unique_connection)
        {
            $this->dumpDatabase($this->connection);
        }
        else
        {
            $this->dumpDatabases();
        }
    }

    protected function handleOptions()
    {
        $compress = $this->option('compress');
        $noCompress = $this->option('no-compress');

        if ($compress) {
            $this->isCompressionEnabled = true;
        } elseif ($noCompress) {
            $this->isCompressionEnabled = false;
        } else {
            $this->isCompressionEnabled = config('backup.mysql.compress', false);
        }

        if ($this->unique_connection) 
        {
            $this->setFilename($this->connection);
        }
        else
        {
            foreach ($this->connections as $connection) 
            {
                $this->setFilename($connection);
            }
        }
    }

    protected function setFilename($connection)
    {
        $filename = trim($this->argument('filename'));
        if (empty($filename)) {
            $filename = $this->connection['database'].'_'.\Carbon\Carbon::now()->format('YmdHis');
        }
        $filename = explode('.', $filename)[0];

        if (!$this->unique_connection) 
        {
            $filename = $connection['database'].'_'.\Carbon\Carbon::now()->format('YmdHis');
        }

        $filename = $filename.'.sql'.($this->isCompressionEnabled ? '.gz' : '');
        
        $this->file_names[$connection['database']] = $filename;
    }

    protected function getFilePath($connection)
    {
        $localPath = $this->cleanPath($this->localPath);

        return $localPath.DIRECTORY_SEPARATOR.$this->file_names[$connection['database']];
    }

    protected function getFileCloudPath($connection)
    {
        $cloudPath = $this->cleanPath($this->cloudPath);

        return $cloudPath.DIRECTORY_SEPARATOR.$this->file_names[$connection['database']];
    }

    protected function isPathAbsolute($path)
    {
        return starts_with($path, DIRECTORY_SEPARATOR);
    }

    protected function cleanPath($path)
    {
        return ltrim(rtrim($path, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
    }

    protected function storeDumpFile($data, $connection)
    {
        if ($this->keepLocal) {
            Storage::disk($this->localDisk)->put($this->getFilePath($connection), $data);
        }
        $compressionMessage = $this->isCompressionEnabled ? 'and compressed' : '';
        $this->info("Database '{$this->file_names[$connection['database']]}' dumped {$compressionMessage} successfully");
        if ($this->cloudSync) {
            Storage::disk($this->cloudDisk)->put($this->getFileCloudPath($connection), $data);
            $this->info("Database dump '{$this->file_names[$connection['database']]}' synced successfully with '{$this->cloudDisk}' disk");
        }
    }

    protected function dumpDatabase($connection)
    {
        $hostname = escapeshellarg($connection['host']);
        $port = $connection['port'];
        $database = $connection['database'];
        $username = escapeshellarg($connection['username']);
        $password = $connection['password'];

        $databaseArg = escapeshellarg($database);
        $portArg = !empty($port) ? '-P '.escapeshellarg($port) : '';
        $passwordArg = !empty($password) ? '-p'.escapeshellarg($password) : '';

        $dumpCommand = "{$this->mysqldumpPath} -C -h {$hostname} {$portArg} -u{$username} {$passwordArg} --single-transaction --skip-lock-tables --quick {$databaseArg}";

        exec($dumpCommand, $dumpResult, $result);

        if ($result == 0) {
            $dumpResult = implode(PHP_EOL, $dumpResult);
            $dumpResult = $this->isCompressionEnabled ? gzcompress($dumpResult, 9) : $dumpResult;
            $this->storeDumpFile($dumpResult, $connection);
        } else {
            $this->error("Database '{$database}' cannot be dumped");
        }
    }

    protected function dumpDatabases()
    {
        foreach ($this->connections as $connection) 
        {
            $this->dumpDatabase($connection);
        }
    }
}
