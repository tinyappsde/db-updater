# Database Updater
This is a simple PHP library for managing and executing database updates.
It allows you to store database updates in a directory or a single config
file and easily execute them in different environments.

Each update needs a unique id. Unlike an incremental versioning this makes it possible
to have independent updates (e.g. from different features and development branches)
that can be automatically executed once the feature is merged or deployed.

## Installation
`composer require tinyapps/db-updater`

The following modes are supported: _Directory_, _JSON Config_ and _PHP Config_.
You'll find examples for each in the examples folder.

In JSON/PHP modes an empty config file will be created if no file exists at the
given file path. Alternatively you'll find configs in the examples folder.

## Getting Started
After following the installation instructions above, you’ll need to decide which config mode to use. The directory mode, where each update is stored in a dedicated file in an updates folder, is enabled by default. Alternatively you can use the single config mode with a JSON or PHP file.

### Pick your config mode
* Directory Mode
	* Create an empty folder where you will store the updates in – e.g. `db/updates/` . You’ll need to pass this directory to the updater later.
* JSON/PHP Config Mode
	* When initializing the updater for the first time, the config is automatically generated at your given path. Make sure this path points to a writable folder and no file with that name exists. You’ll need to pass the ` Updater::MODE_JSON` or `Updater::MODE_PHP` as the third argument for the updater’s constructor ( `new Updater(...)`) to enable the mode.

### Execute Updates
* Create a script file that runs outstanding updates. In this example we’ll create a new PHP file here: `scripts/db-updates.php`.
* At first you’ll need a PDO instance for your database connection. In case you don’t have a helper class or similar that returns the PDO instance in your project just yet, please read this documentation: [PHP: PDO::__construct - Manual](https://www.php.net/manual/pdo.construct.php)
* Create a new updater instance with your PDO connection in your script using `$updater = new Updater($pdo, __DIR__ . '/../db/updates');`
	* If using the JSON/PHP mode you’ll need to pass the third argument
* Run outstanding updates (with output) using `$updater->executeOutstandingUpdates(false);`  (see more details in the examples below)

### Save a new update
There are two options for adding an update:
* Programmatically create an update by using  the `saveNewUpdate` method of the updater. (see example below)
* Manually create a file containing the update queries. The file name is used as the ID of the update and is inevitably unique in the directory mode. E.g. create a file `my-first-update.sql`  in your updates folder and put your queries in there (separated by a semicolon `;` ).

## Example Code

### Initialization
```php
use TinyApps\DbUpdater\Updater;

$pdo = new PDO(...); // Your PDO instance
$updater = new Updater($pdo, __DIR__ . '/path/to/updates', Updater::MODE_DIR);
```

### Execute outstanding updates
```php
use TinyApps\DbUpdater\Exceptions\UpdateFailureException;

try {
	$executedUpdates = $updater->executeOutstandingUpdates();
	echo count($executedUpdates) . ' outstanding updates were executed';
} catch (UpdateFailureException $e) {
	// An update failed
	echo $e->getMessage();
}
```

### Execute a single update
```php
use TinyApps\DbUpdater\Exceptions\UpdateFailureException;

try {
	$updater->executeUpdateWithId('example-update');
	echo 'Update #example-update executed';
} catch (UpdateFailureException $e) {
	echo $e->getMessage();
}
```

### Programmatically add an update
The `saveNewUpdate` method optionally takes a second argument containing the ID of the update. If left out, a new unique ID containing the date and a random hash (suggested) is used instead.

```php
$updater->saveNewUpdate([
	'CREATE TABLE ...',
	'ALTER TABLE ...',
]);
```
