# Database Updater
This is a simple PHP library for managing and executing database updates.
This allows you to have store database updates in a directory or a single config
file and easily execute them in different environments.

Each update needs a unique id (string or number). This makes it possible to have independent updates
(e.g. from different features and development branches) that can be automatically executed once
the feature is merged or deployed.

## Installation
`composer require tinyapps/db-updater`

## Example Usage
```php
use TinyApps\DbUpdater\Updater;

$pdo = new PDO(...); // Your PDO instance
$updater = new Updater($db, __DIR__ . '/path/to/updates', Updater::MODE_DIR);

// Execute all outstanding updates
try {
	$executedUpdates = $updater->executeOutstandingUpdates();
	echo count($executedUpdates) . ' outstanding updates were executed';
} catch (TinyApps\DbUpdater\Exceptions\UpdateFailureException $e) {
	// An update failed
	echo $e->getMessage();
}

// Execute a single update
try {
	$updater->executeUpdateWithId('example-update');
	echo 'Update #example-update executed';
} catch (TinyApps\DbUpdater\Exceptions\UpdateFailureException $e) {
	echo $e->getMessage();
}
```

The following modes are supported: *Directory*, *JSON Config* and *PHP Config*.
You'll find examples for each in the examples folder.

In JSON/PHP modes an empty config file will be created if no file exists at the
given file path. Alternatively you'll find configs in the examples folder.

## Documentation
