<?php
/**
 * This file is part of the Makise-Co Database Package
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Database;

use Closure;
use MakiseCo\Connection\ConnectionConfigInterface;
use MakiseCo\Connection\ConnectionInterface;
use MakiseCo\Connection\ConnectorInterface;
use MakiseCo\Database\Contracts\CommandResult;
use MakiseCo\Database\Contracts\Link;
use MakiseCo\Database\Contracts\ResultSet;
use MakiseCo\Database\Contracts\Statement;
use MakiseCo\Database\Contracts\Transaction;
use MakiseCo\Pool\Pool;
use Throwable;
use InvalidArgumentException;

abstract class DatabasePool extends Pool implements Link
{
    /**
     * Points to $this->pop()
     */
    private Closure $pop;

    /**
     * Points to $this->push()
     */
    private Closure $push;

    /**
     * The minimum amount of time (seconds) a statement may sit idle in the pool before it is eligible for closing.
     */
    private int $statementMaxIdleTime = 60;

    /**
     * The number of milliseconds to sleep between runs of the idle statement validation/cleaner timer.
     */
    private float $validationStatementsInterval = 5.0;

    public function __construct(ConnectionConfigInterface $connConfig, ?ConnectorInterface $connector = null)
    {
        parent::__construct($connConfig, $connector);

        $this->pop = Closure::fromCallable([$this, 'pop']);
        $this->push = Closure::fromCallable([$this, 'push']);
    }

    public function getStatementMaxIdleTime(): int
    {
        return $this->statementMaxIdleTime;
    }

    public function getValidationStatementsInterval(): float
    {
        return $this->validationStatementsInterval;
    }

    /**
     * Set the minimum amount of time a statement may sit idle in the pool before it is eligible for closing.
     *
     * @param int $statementMaxIdleTime seconds (zero value will disable statement idle checking)
     */
    public function setStatementMaxIdleTime(int $statementMaxIdleTime): void
    {
        $this->statementMaxIdleTime = $statementMaxIdleTime;
    }

    /**
     * Set the number of seconds to sleep between runs of the idle statement validation/cleaner timer.
     * This value should not be set under 1 second.
     * It dictates how often we check for idle statements
     *
     * Zero value will disable statements checking.
     *
     * @param float $validationStatementsInterval seconds with milliseconds precision
     *
     * @throws InvalidArgumentException when $validationStatementsInterval is less than 0
     */
    public function setValidationStatementsInterval(float $validationStatementsInterval): void
    {
        if ($validationStatementsInterval < 0) {
            throw new InvalidArgumentException('validationStatementsInterval should be a positive value');
        }

        $this->validationStatementsInterval = $validationStatementsInterval;
    }

    public function query(string $sql)
    {
        $connection = $this->pop();

        try {
            $result = $connection->query($sql);
        } catch (Throwable $e) {
            $this->push($connection);

            throw $e;
        }

        return $this->processQueryResult($connection, $result);
    }

    public function prepare(string $sql): Statement
    {
        $connection = $this->pop();

        try {
            $statement = $connection->prepare($sql);
        } finally {
            $this->push($connection);
        }

        return new StatementPool(
            $this,
            $this->statementMaxIdleTime,
            $this->validationStatementsInterval,
            $sql,
            $connection,
            $statement,
            $this->pop,
            $this->push
        );
    }

    public function execute(string $sql, array $params = [])
    {
        $connection = $this->pop();

        try {
            $result = $connection->execute($sql, $params);
        } catch (Throwable $e) {
            $this->push($connection);

            throw $e;
        }

        return $this->processQueryResult($connection, $result);
    }

    public function beginTransaction(int $isolation = Transaction::ISOLATION_COMMITTED): Transaction
    {
        $connection = $this->pop();

        try {
            $transaction = $connection->beginTransaction($isolation);
        } catch (Throwable $e) {
            $this->push($connection);

            throw $e;
        }

        return new PooledTransaction($transaction, function () use ($connection) {
            $this->push($connection);
        });
    }

    /**
     * @param ConnectionInterface $connection
     * @param ResultSet|CommandResult $result
     *
     * @return PooledResultSet|ResultSet|CommandResult
     */
    protected function processQueryResult(ConnectionInterface $connection, $result)
    {
        if ($result instanceof ResultSet && $result->isUnbuffered()) {
            return new PooledResultSet($result, function () use ($connection) {
                $this->push($connection);
            });
        }

        $this->push($connection);

        return $result;
    }

    protected function pop(): Link
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        /** @var Link */
        return parent::pop();
    }
}
