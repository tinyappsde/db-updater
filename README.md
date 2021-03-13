# Database Updater
This is a simple PHP library for managing and executing database updates.
It allows you to store database updates in a directory or a single config
file and easily execute them in different environments.

Each update needs a unique id. Unlike an incremental versioning this makes it possible
to have independent updates (e.g. from different features and development branches)
that can be automatically executed once the feature is merged or deployed.

## Installation
`composer require tinyapps/db-updater`

The following modes are supported: *Directory*, *JSON Config* and *PHP Config*.
You'll find examples for each in the examples folder.

In JSON/PHP modes an empty config file will be created if no file exists at the
given file path. Alternatively you'll find configs in the examples folder.

## Examples

### Initialization
```php
use TinyApps\DbUpdater\Updater;

$pdo = new PDO(...); // Your PDO instance
$updater = new Updater($db, __DIR__ . '/path/to/updates', Updater::MODE_DIR);
```

### Execute outstanding updates
```php
try {
	$executedUpdates = $updater->executeOutstandingUpdates();
	echo count($executedUpdates) . ' outstanding updates were executed';
} catch (TinyApps\DbUpdater\Exceptions\UpdateFailureException $e) {
	// An update failed
	echo $e->getMessage();
}
```

### Execute a single update
```php
try {
	$updater->executeUpdateWithId('example-update');
	echo 'Update #example-update executed';
} catch (TinyApps\DbUpdater\Exceptions\UpdateFailureException $e) {
	echo $e->getMessage();
}
```

### Programmatically add an update
```php
$updater->saveNewUpdate([
	'CREATE TABLE `test_table` (`id` serial, `test` varchar(255))',
]);
```
