<?php

declare(strict_types=1);

namespace Vajexal\AmpSQLite;

use Amp\Promise;
use Amp\Sql\ConnectionException;
use Amp\Sql\FailureException;
use Amp\Sql\QueryError;
use Amp\Sql\Transaction;
use Amp\Sql\TransactionError;
use InvalidArgumentException;
use function Amp\call;

class SQLiteTransaction implements Transaction
{
    const ISOLATION_DEFERRED  = 0;
    const ISOLATION_IMMEDIATE = 1;
    const ISOLATION_EXCLUSIVE = 2;

    const ISOLATION_MAP = [
        self::ISOLATION_DEFERRED  => 'DEFERRED',
        self::ISOLATION_IMMEDIATE => 'IMMEDIATE',
        self::ISOLATION_EXCLUSIVE => 'EXCLUSIVE',
    ];

    private ?SQLiteConnection $connection;
    private int               $isolation;

    public function __construct(SQLiteConnection $connection, int $isolation)
    {
        $this->connection = $connection;
        $this->isolation  = $isolation;
    }

    public function __destruct()
    {
        if ($this->isAlive()) {
            Promise\rethrow($this->rollback());
        }
    }

    /**
     * @inheritDoc
     * @throws TransactionError
     */
    public function query(string $sql): Promise
    {
        return $this->getAliveConnection()->query($sql);
    }

    /**
     * @inheritDoc
     * @throws TransactionError
     */
    public function prepare(string $sql): Promise
    {
        return $this->getAliveConnection()->prepare($sql);
    }

    /**
     * @inheritDoc
     * @throws TransactionError
     */
    public function execute(string $sql, array $params = []): Promise
    {
        return $this->getAliveConnection()->execute($sql, $params);
    }

    /**
     * @inheritDoc
     * @return Promise<void>
     */
    public function close(): Promise
    {
        return call(function () {
            if (!$this->isAlive()) {
                return;
            }

            yield $this->commit();
        });
    }

    /**
     * @inheritDoc
     */
    public function getIsolationLevel(): int
    {
        return $this->isolation;
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        return $this->connection !== null;
    }

    /**
     * @inheritDoc
     * @throws TransactionError
     * @throws ConnectionException
     * @throws FailureException
     * @throws QueryError
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     */
    public function commit(): Promise
    {
        $promise = $this->getAliveConnection()->execute('COMMIT');

        $this->connection = null;

        return $promise;
    }

    /**
     * @inheritDoc
     * @throws TransactionError
     * @throws ConnectionException
     * @throws FailureException
     * @throws QueryError
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     */
    public function rollback(): Promise
    {
        $promise = $this->getAliveConnection()->execute('ROLLBACK');

        $this->connection = null;

        return $promise;
    }

    /**
     * @inheritDoc
     * @throws TransactionError
     * @throws ConnectionException
     * @throws FailureException
     * @throws QueryError
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     */
    public function createSavepoint(string $identifier): Promise
    {
        $this->validateSavepointIdentifier($identifier);

        return $this->getAliveConnection()->execute("SAVEPOINT {$identifier}");
    }

    /**
     * @inheritDoc
     * @throws TransactionError
     * @throws ConnectionException
     * @throws FailureException
     * @throws QueryError
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     */
    public function rollbackTo(string $identifier): Promise
    {
        $this->validateSavepointIdentifier($identifier);

        return $this->getAliveConnection()->execute("ROLLBACK TO {$identifier}");
    }

    /**
     * @inheritDoc
     * @throws TransactionError
     * @throws ConnectionException
     * @throws FailureException
     * @throws QueryError
     * @psalm-suppress InvalidReturnStatement
     * @psalm-suppress InvalidReturnType
     */
    public function releaseSavepoint(string $identifier): Promise
    {
        $this->validateSavepointIdentifier($identifier);

        return $this->getAliveConnection()->execute("RELEASE {$identifier}");
    }

    /**
     * @param string $identifier
     *
     * @return void
     */
    private function validateSavepointIdentifier(string $identifier): void
    {
        if (!\preg_match('/^[a-zA-Z_]\w*$/', $identifier)) {
            throw new InvalidArgumentException("Invalid savepoint identifier {$identifier}");
        }
    }

    /**
     * @inheritDoc
     */
    public function isAlive(): bool
    {
        /** @psalm-suppress PossiblyNullReference */
        return $this->isActive() && $this->connection->isAlive();
    }

    /**
     * @inheritDoc
     */
    public function getLastUsedAt(): int
    {
        // I don't think we need last used timestamp when transaction is closed
        /** @psalm-suppress PossiblyNullReference */
        return $this->isActive() ? $this->connection->getLastUsedAt() : 0;
    }

    /**
     * @psalm-suppress NullableReturnStatement
     * @psalm-suppress InvalidNullableReturnType
     *
     * @return SQLiteConnection
     */
    private function getAliveConnection(): SQLiteConnection
    {
        if (!$this->isAlive()) {
            throw new TransactionError('Transaction has been closed');
        }

        return $this->connection;
    }
}
