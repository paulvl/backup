<?php

namespace Backup\Console\Commands;

use Illuminate\Console\Command;

class MysqlRestore extends Command
{
     /**
    * The name and signature of the console command.
    *
    * @var string
    */
    protected $signature = 'backup:mysql-restore
                            {filename : Mysql backup filename}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore your Mysql database from a file';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $host       = env('DB_HOST');
        $database   = env('DB_DATABASE');
        $username   = env('DB_USERNAME');
        $password   = env('DB_PASSWORD');

        $path       = config('backup.mysql.path');
        $filename = $this->argument('filename');

        $restoreCommand = "mysql -u $username -p$password $database < /home/paulvl/Backups/$filename.sql";
        exec($restoreCommand);
        $this->info('Restore completed!');
    }
}
