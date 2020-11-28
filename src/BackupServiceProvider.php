<?php

namespace Backup;

use Backup\Console\Commands\MysqlDump;
use Backup\Console\Commands\MysqlFixFile;
use Backup\Console\Commands\MysqlRestore;
use Illuminate\Support\ServiceProvider;

class BackupServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Publishes package config file to applications config folder
        $this->publishes([__DIR__.'/config/backup.php' => config_path('backup.php')], 'config');

        // Registering commands
        $this->commands([
            MysqlDump::class,
            MysqlRestore::class,
            MysqlFixFile::class,
        ]);
    }
}
