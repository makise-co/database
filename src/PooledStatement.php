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
use MakiseCo\Database\Contracts\ResultSet;
use MakiseCo\Database\Contracts\Statement;
use MakiseCo\Database\Exception\FailureException;

final class PooledStatement implements Statement
{
    private Statement $statement;

    private Closure $release;

    private int $refCount = 1;

    /**
     * PooledStatement constructor.
     *
     * @param Statement $statement Statement object created by pooled connection.
     * @param Closure $release Callable to be invoked when the statement and any associated results are destroyed.
     *
     * @throws FailureException when statement is dead
     */
    public function __construct(Statement $statement, Closure $release)
    {
        if (!$statement->isAlive()) {
            $release();

            throw new FailureException('Statement is dead');
        }

        $this->statement = $statement;
        $refCount = &$this->refCount;

        $this->release = static function () use (&$refCount, $release) {
            if (--$refCount === 0) {
                $release();
            }
        };
    }

    public function __destruct()
    {
        if ($this->release !== null) {
            ($this->release)();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function execute(array $params = [])
    {
        $result = $this->statement->execute($params);

        if ($result instanceof ResultSet) {
            return $this->createResultSet($result, $this->release);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function isAlive(): bool
    {
        return $this->statement->isAlive();
    }

    /**
     * {@inheritDoc}
     */
    public function getQuery(): string
    {
        return $this->statement->getQuery();
    }

    /**
     * {@inheritDoc}
     */
    public function getLastUsedAt(): int
    {
        return $this->statement->getLastUsedAt();
    }

    protected function createResultSet(ResultSet $resultSet, Closure $release): ResultSet
    {
        if ($resultSet->isUnbuffered()) {
            ++$this->refCount;

            return new PooledResultSet($resultSet, $release);
        }

        return $resultSet;
    }
}
