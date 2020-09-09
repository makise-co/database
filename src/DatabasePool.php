<?php
/**
 * This file is part of the Makise-Co Database Package
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\SqlCommon;

use Closure;
use InvalidArgumentException;
use MakiseCo\Connection\ConnectionConfigInterface;
use MakiseCo\Connection\ConnectionInterface;
use MakiseCo\Connection\ConnectorInterface;
use MakiseCo\Pool\Pool;
use MakiseCo\SqlCommon\Contracts\CommandResult;
use MakiseCo\SqlCommon\Contracts\Link;
use MakiseCo\SqlCommon\Contracts\ResultSet;
use MakiseCo\SqlCommon\Contracts\Statement;
use MakiseCo\SqlCommon\Contracts\Transaction;
use Throwable;

use function array_key_exists;
use function count;
use function key;
use function md5;

abstract class DatabasePool extends Pool implements Link
{
    /**
     * Free statement that has not been used the longest
     * A more graceful way, but took more CPU time
     */
    public const STATEMENT_FREE_POLICY_MIN_USED_AT = 0;

    /**
     * Free first available statement
     * A faster way, but does not respect statements lastUsedAt
     */
    public const STATEMENT_FREE_POLICY_RING = 1;

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
     * Zero value will disable idle statements closing.
     */
    private int $statementMaxIdleTime = 60;

    /**
     * The number of seconds to sleep between runs of the idle statement validation/cleaner timer.
     * Zero value will disable statements checking. It is highly recommended to not disable statements checking
     * to prevent memory leaks.
     */
    private float $validationStatementsInterval = 30.0;

    /**
     * The maximum number of active statements that can be allocated from this pool at the same time
     * Zero value = no limit
     */
    private int $maxStatements = 0;

    /**
     * Statement pools
     * One pool represents one statement
     *
     * @var StatementPool[]|array<string, StatementPool>
     */
    private array $statements = [];

    /**
     * Statements free policy that determines which statement should be freed when statements limit has been reached
     */
    private int $statementLimitFreePolicy = self::STATEMENT_FREE_POLICY_RING;

    public function __construct(ConnectionConfigInterface $connConfig, ?ConnectorInterface $connector = null)
    {
        parent::__construct($connConfig, $connector);

        $this->pop = Closure::fromCallable([$this, 'pop']);
        $this->push = Closure::fromCallable([$this, 'push']);
    }

    abstract protected function createTransaction(Transaction $transaction, Closure $release): Transaction;

    public function close(): void
    {
        parent::close();

        $this->statements = [];
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

        foreach ($this->statements as $stmtPool) {
            $stmtPool->setMaxIdleTime($statementMaxIdleTime);
        }
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

        foreach ($this->statements as $stmtPool) {
            $stmtPool->setValidationInterval($validationStatementsInterval);
        }
    }

    /**
     * Set the maximum number of active statements that can be allocated from this pool at the same time
     * Zero value = no limit
     *
     * @param int $maxStatements
     */
    public function setMaxStatements(int $maxStatements): void
    {
        if ($maxStatements < 0) {
            throw new InvalidArgumentException('maxStatements should be a positive value');
        }

        $this->maxStatements = $maxStatements;
    }

    /**
     * Set the statements free policy that determines which statement
     * should be freed when statements limit has been reached
     *
     * @param int $freePolicy
     */
    public function setStatementLimitFreePolicy(int $freePolicy): void
    {
        if ($freePolicy !== self::STATEMENT_FREE_POLICY_MIN_USED_AT &&
            $freePolicy !== self::STATEMENT_FREE_POLICY_RING) {
            throw new InvalidArgumentException('freePolicy should be a one of STATEMENT_FREE_POLICY_* constants');
        }

        $this->statementLimitFreePolicy = $freePolicy;
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
            $stmt = $connection->prepare($sql);
        } catch (Throwable $e) {
            $this->push($connection);

            throw $e;
        }

        return new PooledStatement($stmt, function () use ($connection) {
            $this->push($connection);
        });

//        $key = md5($sql);
//        if (!array_key_exists($key, $this->statements)) {
//            // statements limit reached
//            if ($this->maxStatements > 0 && count($this->statements) > $this->maxStatements) {
//                $stmtKey = $this->getStatementKeyForFree();
//                unset($this->statements[$stmtKey]);
//            }
//
//            $stmtPool = new StatementPool(
//                $this,
//                $this->pop,
//                $this->push,
//                $this->statementMaxIdleTime,
//                $this->validationStatementsInterval,
//                $sql
//            );
//
//            $this->statements[$key] = $stmtPool;
//        } else {
//            $stmtPool = $this->statements[$key];
//        }
//
//        return $stmtPool;
//        return new PooledStatement($stmtPool, static function () {
//            // nothing to do
//        });
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

        return $this->createTransaction($transaction, function () use ($connection) {
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

    protected function getStatementKeyForFree(): string
    {
        if ($this->statementLimitFreePolicy === self::STATEMENT_FREE_POLICY_MIN_USED_AT) {
            $minUsedAt = 0;
            $minUsedAtKey = null;

            // find statement that wasn't used for a long time
            foreach ($this->statements as $key => $stmt) {
                if ($minUsedAt === 0) {
                    $minUsedAt = $stmt->getLastUsedAt();
                    $minUsedAtKey = $key;

                    continue;
                }

                if (($stmtUsedAt = $stmt->getLastUsedAt()) < $minUsedAt) {
                    $minUsedAt = $stmtUsedAt;
                    $minUsedAtKey = $key;
                }
            }

            // impossible condition, but for type safety default null to empty string
            if (null === $minUsedAtKey) {
                return '';
            }

            return $minUsedAtKey;
        }

        // ring policy or when policy is unknown
        return key($this->statements) ?? '';
    }
}
