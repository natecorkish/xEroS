<?php

namespace Xeros;

use Dotenv\Dotenv;

// defaults
declare(ticks=1);
bcscale(0);
date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');
$utf_set = ini_set('default_charset', 'utf-8');

// set path constants
$appDir = dirname(__FILE__) . DIRECTORY_SEPARATOR;
define('APP_DIR', $appDir);

/**
 * Make sure they have installed and executed composer
 */
if (!file_exists(APP_DIR . 'vendor/autoload.php')) {
    echo 'Error: You have to install composer and run `composer install` before you can continue.', PHP_EOL;
    exit(0);
}

// includes
require APP_DIR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

// get the interpreter version
$phpVersion = ((PHP_MAJOR_VERSION * 10000) + (PHP_MINOR_VERSION * 1000) + PHP_RELEASE_VERSION);
if ($phpVersion < 81001) {
    Console::log('Error: The minimum version of PHP must be 8.1');
    exit(1);
}

// setup .env
if (!file_exists(APP_DIR . '.env')) {
    echo 'Error: rename .env_sample to .env, and change the values accordingly', PHP_EOL;
    exit(0);
}

// load the environment
$dotenv = Dotenv::createImmutable(APP_DIR);
$dotenv->load();

// display a header
echo PHP_EOL, Config::getProductName(), " Node v", Config::getVersion(), PHP_EOL;
echo Config::getProductCopyright(), PHP_EOL, PHP_EOL;

// check if migration was run
$db = Database::getInstance();
$tableCount = 0;
$requiredTables = ['accounts', 'blocks', 'key_value_store', 'logs', 'mempool_inputs', 'mempool_outputs', 'mempool_transactions', 'peers', 'queue', 'transaction_inputs', 'transaction_outputs', 'transactions'];
foreach ($requiredTables as $requiredTable) {
    $query = 'SELECT name FROM sqlite_master WHERE type=\'table\' AND name=:table_name';
    $stmt = $db->prepare($query);
    $stmt = DatabaseHelpers::filterBind($stmt, 'table_name', $requiredTable, DatabaseHelpers::TEXT);
    $stmt->execute();
    $tableExists = $stmt->fetchColumn();
    if (in_array($tableExists, $requiredTables)) {
        $tableCount++;
    }
}
if (count($requiredTables) !== $tableCount) {
    Console::log('Error: Before you run ' . Config::getProductName() . ' you must run ./phinx migrate');
    exit(0);
}

// bootstrap from Genesis?
$block = new Block();
if ($block->getCurrentHeight() === 0) {
    $block->addFullBlock($block->genesis(), false);
    $genesis = $block->getByHeight(1);
    $genesis = $block->assembleFullBlock($genesis['block_id']);
    $result = $block->validateFullBlock($genesis);
}

// if there is no state, we're likely needing to sync from the network
$store = new DataStore();
$state = $store->getKey('state', '');
if (empty($state)) {
    $store->add('state', Node::Syncing);
}