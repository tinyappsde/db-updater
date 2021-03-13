<?php

use TinyApps\DbUpdater\Updater;

require __DIR__ . '/../vendor/autoload.php';

$db = new PDO('mysql:host=127.0.0.1;dbname=example;charset=utf8mb4', 'example', 'example');

$updater = new Updater($db, __DIR__ . '/db-updates.json', Updater::MODE_JSON);
$updater->saveNewUpdate([
	'CREATE TABLE `test_table` (`id` serial, `test` varchar(255))',
	'INSERT INTO `test_table` SET `test` = \'hello world\''
], null);

$updater->executeOutstandingUpdates(false);
