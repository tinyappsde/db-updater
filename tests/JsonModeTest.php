<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TinyApps\DbUpdater\Updater;

final class JsonModeTest extends TestCase {
	protected static ?PDO $db = null;

	public static function setUpBeforeClass(): void {
		self::$db = new PDO(
			'mysql:host=' . getenv('DB_HOST') . ';port=' . getEnv('DB_PORT') . ';dbname=' . getenv('DB') . ';charset=utf8mb4',
			getenv('DB_USER'),
			getenv('DB_PASS'),
		);

		self::$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
		self::$db->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
		self::$db->exec('CREATE TABLE `unit_test_table` (`id` int NOT NULL AUTO_INCREMENT, `test` varchar(255) NULL, PRIMARY KEY (id))');
	}

	public function testInitializeUpdater(): Updater {
		$updater = new Updater(self::$db, __DIR__ . '/test-updates.json', Updater::MODE_JSON);
		$this->assertInstanceOf(Updater::class, $updater);

		return $updater;
	}

	/**
	 * @depends testInitializeUpdater
	 */
	public function testNoOutstandingUpdates(Updater $updater): void {
		$this->assertEmpty($updater->outstandingUpdates());
		$this->assertEmpty($updater->executeOutstandingUpdates());
	}

	/**
	 * @depends testInitializeUpdater
	 */
	public function testCreateUpdate(Updater $updater): void {
		$queries = [
			'INSERT INTO `unit_test_table` SET `test` = \'hello world\';',
			'INSERT INTO `unit_test_table` SET `test` = \'hello world 2\';',
		];

		$updater->saveNewUpdate($queries, 'unit-test-update');
		$this->assertEquals(1, count($updater->outstandingUpdates()));

		$updateFileContents = json_decode(file_get_contents(__DIR__ . '/test-updates.json'));
		$this->assertEquals('unit-test-update', $updateFileContents->updates[0]->id);
		$this->assertEquals($queries, $updateFileContents->updates[0]->queries);
	}

	/**
	 * @depends testInitializeUpdater
	 */
	public function testExecuteUpdate(Updater $updater): void {
		$executed = $updater->executeOutstandingUpdates();
		$this->assertEquals(1, count($executed));

		$stmt = self::$db->query('SELECT * FROM `unit_test_table` ORDER BY id LIMIT 1');
		$this->assertEquals('hello world', $stmt->fetchObject()->test);
	}

	/**
	 * @depends testInitializeUpdater
	 */
	public function testNoOutstandingUpdatesAfterExecution(Updater $updater): void {
		$this->assertEmpty($updater->outstandingUpdates());
		$this->assertEmpty($updater->executeOutstandingUpdates());
	}

	public static function tearDownAfterClass(): void {
		self::$db->exec('DROP TABLE `unit_test_table`');
		self::$db->exec('DROP TABLE `database_updates`');
		unlink(__DIR__ . '/test-updates.json');
	}
}
