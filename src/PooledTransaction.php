<?php
/**
 * This file is part of the Makise-Co Postgres Client
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Database;

use Closure;
use MakiseCo\Database\Exception\TransactionError;
use MakiseCo\Database\Contracts\Transaction;
use MakiseCo\Database\Contracts\ResultSet;
use MakiseCo\Database\Contracts\CommandResult;

class PooledTransaction implements Transaction
{
    private ?Transaction $transaction;
    private Closure $release;
    private int $refCount = 1;

    public function __construct(Transaction $transaction, Closure $release)
    {
        $this->release = $release;

        if (!$transaction->isActive()) {
            $release();
            $this->transaction = null;
        } else {
            $this->transaction = $transaction;

            $refCount = &$this->refCount;
            $this->release = static function () use (&$refCount, $release) {
                if (--$refCount === 0) {
                    $release();
                }
            };
        }
    }

    public function __destruct()
    {
        if ($this->transaction && $this->transaction->isActive()) {
            $this->close(); // Invokes $this->release callback.
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isAlive(): bool
    {
        return $this->transaction && $this->transaction->isAlive();
    }

    /**
     * {@inheritDoc}
     */
    public function getLastUsedAt(): int
    {
        if ($this->transaction === null) {
            return 0;
        }

        return $this->transaction->getLastUsedAt();
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        if ($this->transaction === null) {
            return;
        }

        try {
            $this->transaction->commit();
            $this->transaction = null;
        } finally {
            ($this->release)();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql)
    {
        if ($this->transaction === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        $result = $this->transaction->query($sql);

        if ($result instanceof ResultSet) {
            $this->refCount++;

            return new PooledResultSet($result, $this->release);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $params = [])
    {
        if ($this->transaction === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        $result = $this->transaction->execute($sql, $params);

        if ($result instanceof ResultSet) {
            $this->refCount++;

            return new PooledResultSet($result, $this->release);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function prepare(string $sql): PooledStatement
    {
        if ($this->transaction === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        $stmt = $this->transaction->prepare($sql);
        $this->refCount++;

        return new PooledStatement($stmt, $this->release);
    }

    /**
     * {@inheritDoc}
     */
    public function getIsolationLevel(): int
    {
        if ($this->transaction === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->transaction->getIsolationLevel();
    }

    /**
     * {@inheritDoc}
     */
    public function isActive(): bool
    {
        return $this->transaction && $this->transaction->isActive();
    }

    /**
     * {@inheritDoc}
     */
    public function commit(): CommandResult
    {
        if ($this->transaction === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        try {
            $result = $this->transaction->commit();
        } finally {
            $this->transaction = null;
            ($this->release)();
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function rollback(): CommandResult
    {
        if ($this->transaction === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        try {
            $result = $this->transaction->rollback();
        } finally {
            $this->transaction = null;
            ($this->release)();
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function createSavepoint(string $identifier): CommandResult
    {
        if ($this->transaction === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->transaction->createSavepoint($identifier);
    }

    /**
     * {@inheritDoc}
     */
    public function rollbackTo(string $identifier): CommandResult
    {
        if ($this->transaction === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->transaction->rollbackTo($identifier);
    }

    /**
     * {@inheritDoc}
     */
    public function releaseSavepoint(string $identifier): CommandResult
    {
        if ($this->transaction === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->transaction->releaseSavepoint($identifier);
    }

    /**
     * {@inheritDoc}
     */
    public function quoteString(string $data): string
    {
        if ($this->transaction === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->transaction->quoteString($data);
    }

    /**
     * {@inheritDoc}
     */
    public function quoteName(string $name): string
    {
        if ($this->transaction === null) {
            throw new TransactionError("The transaction has been committed or rolled back");
        }

        return $this->transaction->quoteName($name);
    }
}
