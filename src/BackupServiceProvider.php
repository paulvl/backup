<?php

namespace Backup;

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
        //Publishes package config file to applications config folder
        $this->publishes([__DIR__.'/config/backup.php' => config_path('backup.php')], 'config');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerMysqlDumpCommand();
        $this->registerMysqlRestoreCommand();
    }

    /**
     * Register the mysql:dump command.
     */
    private function registerMysqlDumpCommand()
    {
        $this->app->singleton('command.backup-mysql.dump', function ($app) {
            return $app['Backup\Console\Commands\MysqlDump'];
        });
        $this->commands('command.backup-mysql.dump');
    }

    /**
     * Register the mysql:restore command.
     */
    private function registerMysqlRestoreCommand()
    {
        $this->app->singleton('command.backup-mysql.restore', function ($app) {
            return $app['Backup\Console\Commands\MysqlRestore'];
        });
        $this->commands('command.backup-mysql.restore');
    }
}
