## Backup

Backup is a Laravel package that allow the creation and restoration of database backups in an easy way.

> New version 4.* works with laravel 8

[![Latest Stable Version](https://poser.pugx.org/paulvl/backup/v/stable)](https://packagist.org/packages/paulvl/backup)  [![Latest Unstable Version](https://poser.pugx.org/paulvl/backup/v/unstable)](https://packagist.org/packages/paulvl/backup)  [![License](https://poser.pugx.org/paulvl/backup/license)](https://packagist.org/packages/paulvl/backup)  [![Total Downloads](https://poser.pugx.org/paulvl/backup/downloads)](https://packagist.org/packages/paulvl/backup)

## **Quick Installation**

Begin by installing this package through Composer.

You can run:

```
composer require paulvl/backup 4.*
```

Or edit your project's composer.json file to require paulvl/backup.

```
    "require": {
        "paulvl/backup": "4.*"
    }
```

Next, update Composer from the Terminal:

```
composer update
```

Once the package's installation completes, the final step is to add the service provider. Open  `config/app.php`, and add a new item to the providers array:

```
Backup\BackupServiceProvider::class,
```

Finally publish package's configuration file:

```
php artisan vendor:publish --provider="Backup\BackupServiceProvider" --tag="config"
```

Then the file  `config/backup.php`  will be created.

That's it! You're ready to go. Run the artisan command from the Terminal to see the new  `backup`  commands.

```
php artisan
```

## **Mysql commands**

### **mysql-dump**  - Creating a backup

To make a backup of you current aplication's database you have to run:

```
php artisan backup:mysql-dump
```

This will create an  `.sql`  file on your configured  `local-storage.path`  like  `/this/is/my/path/dbname_20150101201505.sql`, this file is named using current datetime. If you want a custom name run:

```
php artisan backup:mysql-dump custom_name
```

This will create an  `.sql`  file on your configured  `local-storage.path`  like  `/this/is/my/path/custom_name.sql`

#### **--connection=mysql**  - Specify database connection name

Specify the database connection name, it uses the primary connection name by default.

```
php artisan backup:mysql-dump --connection=custom-connection
```

#### **--compress**  - Enable file compression
> From versión *4.** compress option use *gzenconde*. To have your previous compressed files working with this versión see [fix-file](#fix-file----fix-backup-file-encoding-mode) command

Enable file compression regardless if is disabled in the configuration file. This option will always overwrite  `--no-compress`  option

```
php artisan backup:mysql-dump --compress
```

#### **--no-compress**  - Disable file compression

Disable file compression regardless if is enabled in the configuration file. This option will be always overwrited by  `--compress`  option

```
php artisan backup:mysql-dump --no-compress
```

### **mysql-restore**  - Restoring database from a file
> From versión *4.** compress option use *gzenconde*. To have your previous compressed files working with this versión see [fix-file](#fix-file----fix-backup-file-encoding-mode) command

To restore a backup to your current aplication's database you have to run:

```
php artisan mysql:restore filename
```

This will display a list of your current backup files stored on your configured  `local-storage.disk`.

#### **--filename | -f**  - Especifiy a backup file name

Especifiy a backup file name

```
php artisan backup:mysql-restore --filename=backup_filename
```

#### **--all-backup-files | -A**  - Display all backup files

Display all available backup files on disk. By default displays files for current connection's database.

```
php artisan backup:mysql-restore --all-backup-files
```

#### **--from-cloud | -C**  - Use cloud disk

Display a list of backup files from cloud disk.

```
php artisan backup:mysql-restore --from-cloud
```

#### **--restore-latest-backup | -L**  - Restore latest backup file

Use latest backup file to restore database.

```
php artisan backup:mysql-restore --restore-latest-backup
```

#### **--yes | -y**  - Confirms restoration action

Confirms database restoration without asking.

```
php artisan backup:mysql-restore --yes
```

### **fix-file**  - Fix backup file encoding mode

> Prior to version *4.** all backup files generated with compression option where compressed using *gzenconde*, by this the generated file was unable to use with any other uncompression software. Thats why we now use *gzencode* instead which is friendly to external software.

To fix the encoding mode of a compressed backup file you have to run:

```
php artisan mysql:fix-file
```

This will display a list of your current compressed backup files stored on your configured  `local-storage.disk`.

#### **--filename | -f**  - Especifiy a backup file name

Especifiy a backup file name

```
php artisan backup:fix-file --filename=backup_filename
```

#### **--from-cloud | -C**  - Use cloud disk

Display a list of backup files from cloud disk.

```
php artisan backup:fix-file --from-cloud
```

#### **--yes | -y**  - Confirms fixing action

Confirms file fixing without asking.

```
php artisan backup:fix-file --yes
```

### **Programing backups**

If you need to perform a backup for example, every day at midnight, at this like to yor schedule function on  `app/Console/Commands/Kernel.php`:

```
protected function schedule(Schedule $schedule)
{
...
    $schedule->command('backup:mysql-dump')->dailyAt('00:00');
...
}
```

## **Contribute and share ;-)**

If you like this little piece of code share it with you friends and feel free to contribute with any improvements.
