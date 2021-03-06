<?php

namespace TinyApps\DbUpdater;

use PDO;
use PDOException;
use TinyApps\DbUpdater\Exceptions\TableSetupException;

class Setup {
	public static function createConfigTable(PDO $conn, string $tableName = 'database_updates') {
		try {
			if ($conn->exec('
			CREATE TABLE `' . $tableName . '` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`update_id` varchar(255) NOT NULL,
				`execution_date` datetime NOT NULL DEFAULT NOW(),
				PRIMARY KEY (id),
				UNIQUE KEY `update_idx` (`update_id`) USING BTREE
			)') === false) {
				throw new TableSetupException('Unable to create the database updates table. Is it already existing? If not, please try to create it manually.');
			}
		} catch (PDOException $e) {
			throw new TableSetupException('Unable to create the database updates table. Is it already existing? If not, please try to create it manually: ' . $e->getMessage());
		}
	}
}
