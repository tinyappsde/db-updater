<?php

namespace TinyApps\DbUpdater;

use DirectoryIterator;
use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;
use TinyApps\DbUpdater\Exceptions\ConfigReadException;
use TinyApps\DbUpdater\Exceptions\DuplicateIdException;
use TinyApps\DbUpdater\Exceptions\InvalidConfigException;
use TinyApps\DbUpdater\Exceptions\OutdatedConfigException;
use TinyApps\DbUpdater\Exceptions\TableSetupException;
use TinyApps\DbUpdater\Exceptions\UpdateFailureException;

/**
 * @author Dion Purushotham <hello@tinyapps.de>
 */
class Updater {

	public const MODE_DIR = 0;
	public const MODE_JSON = 1;
	public const MODE_PHP = 2;

	protected const PHP_CONFIG_VERSION = '1.0.0';
	protected const JSON_CONFIG_VERSION = '1.0.0';

	protected PDO $conn;
	protected string $path;
	protected int $mode;

	/**
	 * @var Update[]
	 */
	protected array $updates;

	/**
	 * Initialize updater
	 *
	 * @param PDO $conn PDO database instance that is used for checking/executing updates
	 * @param string $path path of the updates or the config file (in single file mode)
	 *
	 * @throws ConfigReadException
	 * @throws OutdatedConfigException
	 * @throws InvalidConfigException
	 * @throws TableSetupException
	 */
	public function __construct(PDO $conn, string $path, int $mode = self::MODE_DIR) {
		$this->conn = $conn;
		$this->path = $path;
		$this->mode = $mode;

		$this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		switch ($mode) {
			case self::MODE_DIR:
				$iterator = new DirectoryIterator($path);
				$this->updates = [];

				foreach ($iterator as $fileInfo) {
					if ($fileInfo->isDir() || $fileInfo->isDot()) continue;

					$this->updates[] = new Update(
						substr(
							$fileInfo->getFilename(),
							0,
							strrpos($fileInfo->getFilename(), '.'),
						),
						array_filter(
							array_map(
								fn ($query) => trim($query),
								explode(';', file_get_contents($fileInfo->getRealPath())),
							),
							fn ($query) => !empty($query),
						),
					);
				}

				usort(
					$this->updates,
					fn (Update $a, Update $b) => $a->getId() <=> $b->getId(),
				);
				break;

			case self::MODE_PHP:
				if (
					!file_exists($path)
					&& !copy(__DIR__ . '/templates/php_config.php', $path)
				) {
					throw new ConfigReadException('No config file found and couldn\'t create new file');
				}

				if (!file_exists($path) || !$config = include $path) {
					throw new ConfigReadException('Couldn\'t find or read database updates config file.');
				}

				if (version_compare(self::PHP_CONFIG_VERSION, $config['config_version']) === 1) {
					throw new OutdatedConfigException('Your database config file is outdated. Please downgrade the Database Updater or update your config file.');
				}

				$this->updates = array_map(
					fn ($update) => Update::fromConfigRow($update),
					$config['updates'],
				);
				break;

			case self::MODE_JSON:
				if (
					!file_exists($path)
					&& !file_put_contents($path, json_encode(['config_version' => '1.0.0', 'updates' => []], JSON_PRETTY_PRINT))
				) {
					throw new ConfigReadException('No config file found and couldn\'t create new file');
				}

				if (!$config = file_get_contents($path)) {
					throw new ConfigReadException('Couldn\'t find or read database updates config file.');
				}

				if (!$config = json_decode($config)) {
					throw new ConfigReadException('Invalid JSON format.');
				}

				if (version_compare(self::JSON_CONFIG_VERSION, $config->config_version) === 1) {
					throw new OutdatedConfigException('Your database config file is outdated. Please downgrade the Database Updater or update your config file.');
				}

				$this->updates = array_map(
					fn ($update) => Update::fromConfigRow($update),
					$config->updates,
				);
				break;

			default:
				throw new InvalidArgumentException('Invalid mode');
		}

		$this->checkForDuplicateIds();
		$this->setup();
	}

	/**
	 * Returns all outstanding updates
	 *
	 * @throws PDOException
	 *
	 * @return Update[]
	 */
	public function outstandingUpdates(): array {
		return array_filter(
			$this->updates,
			fn ($update) => !$this->wasUpdateExecuted($update),
		);
	}

	/**
	 * Executes (and returns successful) outstanding updates
	 *
	 * @param boolean $silent Optionally enable output of which updates were executed or failed
	 *
	 * @throws UpdateFailureException
	 *
	 * @return array
	 */
	public function executeOutstandingUpdates(bool $silent = true): array {
		$executed = [];

		try {
			foreach ($this->outstandingUpdates() as $update) {
				$this->executeUpdate($update);
				$executed[] = $update;

				if (!$silent) {
					echo 'Update #' . $update->getId() . ' has been executed.' . PHP_EOL;
				}
			}
		} catch (UpdateFailureException $e) {
			if (!$silent) {
				echo $e->getMessage();
				return [];
			}

			throw new UpdateFailureException($e);
		}

		if (empty($executed) && !$silent) {
			echo 'No outstanding updates.' . PHP_EOL;
		}

		return $executed;
	}

	/**
	 * Executes a specific update (throws exception on failure if silent)
	 *
	 * @param string $id
	 * @param boolean $silent
	 *
	 * @throws UpdateFailureException
	 *
	 * @return void
	 */
	public function executeUpdateWithId(string $id, bool $silent = true): void {
		foreach ($this->updates as $update) {
			if ($update->getId() === $id) {
				try {
					$this->executeUpdate($update);

					if (!$silent) {
						echo 'Update #' . $update->getId() . ' has been executed.' . PHP_EOL;
					}

					return;
				} catch (UpdateFailureException $e) {
					if (!$silent) {
						echo $e->getMessage();
						return;
					}

					throw new UpdateFailureException($e);
				}
			}
		}

		throw new UpdateFailureException('No update with ID #' . $id . ' was found.');
	}

	/**
	 * Save a new update (according to selected mode)
	 *
	 * @param string[] $queries
	 * @param string|null $id
	 *
	 * @throws DuplicateIdException
	 * @throws InvalidConfigException
	 *
	 * @return void
	 */
	public function saveNewUpdate(array $queries, ?string $id = null) {
		if (empty($id)) {
			$id = Update::randomUniqueId();
		}

		switch ($this->mode) {
			case self::MODE_DIR:
				if (file_exists($this->path . '/' . $id)) {
					throw new DuplicateIdException('File with ID already existing');
				}

				file_put_contents(
					$this->path . '/' . $id . '.sql',
					implode("\n", array_map(
						fn ($query) => strpos($query, ';') ? $query : $query . ';',
						$queries,
					)),
				);
				break;

			case self::MODE_JSON:
				if (!$config = json_decode(file_get_contents($this->path))) {
					throw new InvalidConfigException('JSON config not found, readable or in invalid format.');
				}

				$config->updates[] = (object) [
					'id' => $id,
					'queries' => $queries,
				];

				file_put_contents($this->path, json_encode($config, JSON_PRETTY_PRINT));
				break;

			case self::MODE_PHP:
				$config = file_get_contents($this->path);

				$newConfigContent = substr(
					$config,
					0,
					strpos($config, '// add updates above and keep this line'),
				);

				$newConfigContent .= "[\n\t\t\t'id' => '" . str_replace("'", '\\\'', $id) . "',\n";
				$newConfigContent .= "\t\t\t'queries' => [\n";
				foreach ($queries as $query) {
					$newConfigContent .= "\t\t\t\t'" . str_replace("'", '\\\'', $query) . "',\n";
				}
				$newConfigContent .= "\t\t\t],\n\t\t],\n";
				$newConfigContent .= "\t\t// add updates above and keep this line\n";
				$newConfigContent .= "\t\t// always add a trailing comma to the update array\n\t],\n];\n";

				file_put_contents($this->path, $newConfigContent);
				break;
		}

		$this->updates[] = new Update($id, $queries);
	}

	/**
	 * Executes all queries of an update
	 *
	 * @param Update $update
	 *
	 * @throws UpdateFailureException
	 *
	 * @return void
	 */
	protected function executeUpdate(Update $update): void {
		try {
			$wasInTransaction = $this->conn->inTransaction();

			if (!$wasInTransaction) {
				$this->conn->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
				$this->conn->beginTransaction();
			}

			foreach ($update->getQueries() as $query) {
				$this->conn->exec($query);
			}

			$this->setUpdateAsExecuted($update);

			if (!$wasInTransaction) {
				$this->conn->commit();
			}
		} catch (PDOException $e) {
			if (!$wasInTransaction && $this->conn->inTransaction()) {
				$this->conn->rollBack();
			}

			throw new UpdateFailureException('Couldn\'t execute update with ID ' . $update->getId() . '. PDO Exception occured: ' . $e->getMessage());
		} catch (Exception $e) {
			if (!$wasInTransaction && $this->conn->inTransaction()) {
				$this->conn->rollBack();
			}

			throw new UpdateFailureException('Couldn\'t execute update with ID ' . $update->getId() . '. Exception occured: ' . $e->getMessage());
		}
	}

	/**
	 * Returns true if given update was executed
	 *
	 * @param Update $update
	 *
	 * @throws PDOException
	 *
	 * @return boolean
	 */
	protected function wasUpdateExecuted(Update $update): bool {
		if (!$stmt = $this->conn->prepare('SELECT * FROM `database_updates` WHERE `update_id` = ?')) {
			throw new TableSetupException('Please make sure your config table exists');
		}

		$stmt->execute([$update->getId()]);

		return $stmt->rowCount() === 1;
	}

	/**
	 * Marks the update as executed in the database updates table
	 *
	 * @param Update $update
	 *
	 * @throws TableSetupException
	 * @throws PDOException
	 *
	 * @return void
	 */
	protected function setUpdateAsExecuted(Update $update): void {
		if (!$stmt = $this->conn->prepare('INSERT INTO `database_updates` SET `update_id` = ?, `execution_date` = NOW()')) {
			throw new TableSetupException('Please make sure your config table exists');
		}

		$stmt->execute([$update->getId()]);

		if ($stmt->rowCount() !== 1) {
			throw new TableSetupException('Couldn\'t insert into the database config table.');
		}
	}

	/**
	 * Creates the config table if not existing
	 *
	 * @throws TableSetupException
	 *
	 * @return void
	 */
	protected function setup(): void {
		try {
			$this->conn->query('SELECT 1 FROM `database_updates` LIMIT 1');
		} catch (PDOException $e) {
			Setup::createConfigTable($this->conn);
		}
	}

	/**
	 * Checks for duplicate update ID's
	 *
	 * @throws InvalidConfigException if duplicate ID found
	 *
	 * @return void
	 */
	protected function checkForDuplicateIds(): void {
		$uniqueIds = [];

		foreach ($this->updates as $update) {
			if (isset($uniqueIds[$update->getId()])) {
				throw new InvalidConfigException('Duplicate update ID found. Please make sure to use unique IDs for all updates.');
			}

			$uniqueIds[$update->getId()] = 1;
		}
	}
}
