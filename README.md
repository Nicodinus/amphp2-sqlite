# amphp2-sqlite

![License](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)

`nicodinus/amphp2-sqlite` is a non-blocking sqlite client for [amphp:^2.6](https://github.com/amphp/amp) using [parallel](https://amphp.org/parallel/)

## Installation

```bash
composer require nicodinus/amphp2-sqlite
```

### Usage

```php
<?php

declare(strict_types=1);

use Amp\Loop;
use Vajexal\AmpSQLite\SQLiteCommandResult;
use Vajexal\AmpSQLite\SQLiteConnection;
use Vajexal\AmpSQLite\SQLiteResultSet;
use Vajexal\AmpSQLite\SQLiteStatement;
use function Vajexal\AmpSQLite\connect;

require_once 'vendor/autoload.php';

Loop::run(function () {
    /** @var SQLiteConnection $connection */
    $connection = yield connect('database.sqlite');

    yield $connection->execute('drop table if exists users');
    yield $connection->execute('create table users (id integer primary key, name text not null)');
    yield $connection->execute('insert into users (name) values (:name)', [
        ':name' => 'Bob',
    ]);

    /** @var SQLiteResultSet $results */
    $results = yield $connection->query('select * from users');
    while (yield $results->advance()) {
        $row = $results->getCurrent();
        echo "Hello {$row['name']}\n";
    }

    /** @var SQLiteStatement $statement */
    $statement = yield $connection->prepare('update users set name = :name where id = 1');
    /** @var SQLiteCommandResult $result */
    $result = yield $statement->execute([
        ':name' => 'John',
    ]);
    echo "Updated {$result->getAffectedRowCount()} rows\n";
});
```

#### Transactions

```php
<?php

declare(strict_types=1);

use Amp\Loop;
use Vajexal\AmpSQLite\SQLiteConnection;
use Vajexal\AmpSQLite\SQLiteTransaction;
use function Vajexal\AmpSQLite\connect;

require_once 'vendor/autoload.php';

Loop::run(function () {
    /** @var SQLiteConnection $connection */
    $connection = yield connect('database.sqlite');

    yield $connection->execute('drop table if exists users');
    yield $connection->execute('create table users (id integer primary key, name text not null)');

    /** @var SQLiteTransaction $transaction */
    $transaction = yield $connection->beginTransaction();
    yield $transaction->execute('insert into users (name) values (:name)', [
        ':name' => 'Bob',
    ]);

    yield $transaction->createSavepoint('change_name');
    yield $transaction->execute('update users set name = :name where id = 1', [
        ':name' => 'John',
    ]);
    yield $transaction->releaseSavepoint('change_name');

    yield $transaction->commit();
});
```
