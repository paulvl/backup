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
                            {--t|table= : Specific table that you want to be dumped}
                            {--no-compress : Disable file compression regardless if is enabled in the configuration file. This option will be always overwrited by --compress option}
                            {--compress : Enable file compression regardless if is disabled in the configuration file. This option will always overwrite --no-compress option}
                            {--database= : name of database connection}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dump your entire MySQL database or an individual table to a file';

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
    protected $mysqldumpPath;

    /**
     * Dump file name.
     *
     * @var string
     */
    protected $filename;

    /**
     * Table to be dumped.
     *
     * @var string
     */
    protected $table;

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

        $this->connection = [
            'host'     => config('database.connections.mysql.host'),
            'database' => config('database.connections.mysql.database'),
            'port'     => config('database.connections.mysql.port'),
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
        ];

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
        $this->dumpDatabase();
    }

    protected function handleOptions()
    {
        $compress = $this->option('compress');
        $noCompress = $this->option('no-compress');
        if ($connection = $this->option('database')) {
            $this->connection = [
                'host'     => config("database.connections.{$connection}.host"),
                'database' => config("database.connections.{$connection}.database"),
                'port'     => config("database.connections.{$connection}.port"),
                'username' => config("database.connections.{$connection}.username"),
                'password' => config("database.connections.{$connection}.password"),
            ];
        }

        if ($compress) {
            $this->isCompressionEnabled = true;
        } elseif ($noCompress) {
            $this->isCompressionEnabled = false;
        } else {
            $this->isCompressionEnabled = config('backup.mysql.compress', false);
        }

        $this->setTable();
        $this->setFilename();
    }

    protected function setTable()
    {
        $table = trim($this->option('table'));
        $this->table = (empty($table)) ? null : $table;
    }

    protected function setFilename()
    {
        $filename = trim($this->argument('filename'));
        if (empty($filename)) {
            $filename = $this->connection['database'].'_'.((!empty($this->table)) ? $this->table.'_' : '').\Carbon\Carbon::now()->format('YmdHis');
        }
        $filename = explode('.', $filename)[0];
        $this->filename = $filename.'.sql'.($this->isCompressionEnabled ? '.gz' : '');
    }

    protected function getFilePath()
    {
        $localPath = $this->cleanPath($this->localPath);

        return $localPath.DIRECTORY_SEPARATOR.$this->filename;
    }

    protected function getFileCloudPath()
    {
        $cloudPath = $this->cleanPath($this->cloudPath);

        return $cloudPath.DIRECTORY_SEPARATOR.$this->filename;
    }

    protected function isPathAbsolute($path)
    {
        return starts_with($path, DIRECTORY_SEPARATOR);
    }

    protected function cleanPath($path)
    {
        return ltrim(rtrim($path, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
    }

    protected function storeDumpFile($data)
    {
        if ($this->keepLocal) {
            Storage::disk($this->localDisk)->put($this->getFilePath(), $data);
        }
        $compressionMessage = $this->isCompressionEnabled ? 'and compressed' : '';
        $this->info('Database '.((!empty($this->table)) ? 'table ' : '')."'{$this->connection['database']}'".((!empty($this->table)) ? ".'".$this->table."'" : '')." dumped {$compressionMessage} successfully");
        if ($this->cloudSync) {
            Storage::disk($this->cloudDisk)->put($this->getFileCloudPath(), $data);
            $this->info("Database dump '{$this->filename}' synced successfully with '{$this->cloudDisk}' disk");
        }
    }

    protected function dumpDatabase()
    {
        $hostname = escapeshellarg($this->connection['host']);
        $port = $this->connection['port'];
        $database = $this->connection['database'];
        $username = escapeshellarg($this->connection['username']);
        $password = $this->connection['password'];

        $databaseArg = escapeshellarg($database);
        $tableArg = (empty($this->table)) ? '' : escapeshellarg($this->table);
        $portArg = !empty($port) ? '-P '.escapeshellarg($port) : '';
        $passwordArg = !empty($password) ? '-p'.escapeshellarg($password) : '';

        $dumpCommand = "{$this->mysqldumpPath} -C -h {$hostname} {$portArg} -u{$username} {$passwordArg} --single-transaction --skip-lock-tables --quick {$databaseArg} {$tableArg}";

        exec($dumpCommand, $dumpResult, $result);

        if ($result == 0) {
            $dumpResult = implode(PHP_EOL, $dumpResult);
            $dumpResult = $this->isCompressionEnabled ? gzcompress($dumpResult, 9) : $dumpResult;
            $this->storeDumpFile($dumpResult);
        } else {
            $this->error("Database '{$database}' cannot be dumped");
        }
    }
}
