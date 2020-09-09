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
use MakiseCo\EvPrimitives\Timer;
use MakiseCo\SqlCommon\Contracts\Link;
use MakiseCo\SqlCommon\Contracts\ResultSet;
use MakiseCo\SqlCommon\Contracts\Statement;
use SplObjectStorage;
use Swoole\Coroutine;
use Throwable;

use function time;

class StatementPool implements Statement
{
    private DatabasePool $pool;

    /**
     * Points to $pool->pop();
     */
    private Closure $pop;

    /**
     * Points to $pool->push();
     */
    private Closure $push;

    /**
     * The minimum amount of time (seconds) a statement may sit idle in the pool before it is eligible for closing
     */
    private int $maxIdleTime;

    /**
     * @var SplObjectStorage<Link, Statement>
     */
    private SplObjectStorage $statements;

    private Timer $timer;

    /**
     * SQL to prepare
     */
    private string $query;

    public function __construct(
        DatabasePool $pool,
        Closure $pop,
        Closure $push,
        int $maxIdleTime,
        float $validationInterval,
        string $query
    ) {
        $this->pool = $pool;
        $this->maxIdleTime = $maxIdleTime;
        $this->query = $query;
        $this->pop = $pop;
        $this->push = $push;

        $this->statements = new SplObjectStorage();
        $this->timer = new Timer(
            5000,
            Closure::fromCallable([$this, 'validateStatements']),
            false
        );

        if ($validationInterval > 0.0) {
            $this->timer->setInterval((int)($validationInterval * 1000));
            $this->timer->start();
        }
    }

    public function __destruct()
    {
        if ($this->timer->isStarted()) {
            $this->timer->stop();
        }
    }

    public function setMaxIdleTime(int $maxIdleTime): void
    {
        if ($maxIdleTime < 0) {
            $maxIdleTime = 0;
        }

        $this->maxIdleTime = $maxIdleTime;
    }

    public function setValidationInterval(float $validationInterval): void
    {
        if ($validationInterval < 0.0) {
            if ($this->timer->isStarted()) {
                $this->timer->start();
            }

            return;
        }

        $this->timer->setInterval((int)($validationInterval * 1000));
        if (!$this->timer->isStarted()) {
            $this->timer->start();
        }
    }

    /**
     * @inheritDoc
     */
    public function isAlive(): bool
    {
        return $this->pool->isAlive();
    }

    /**
     * @inheritDoc
     */
    public function getLastUsedAt(): int
    {
        $time = 0;

        foreach ($this->statements as $statement) {
            if (($lastUsedAt = $statement->getLastUsedAt()) > $time) {
                $time = $lastUsedAt;
            }
        }

        return $time;
    }

    /**
     * @inheritDoc
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $params = [])
    {
        /** @var Link $conn */
        $conn = ($this->pop)();

        try {
            $statement = $this->prepare($conn);
        } catch (Throwable $e) {
            ($this->push)($conn);

            throw $e;
        }

        try {
            $result = $statement->execute($params);
        } catch (Throwable $e) {
            ($this->push)($conn);

            throw $e;
        }

        if ($result instanceof ResultSet && $result->isUnbuffered()) {
            return new PooledResultSet($result, function () use ($conn) {
                ($this->push)($conn);
            });
        }

        ($this->push)($conn);

        return $result;
    }

    protected function prepare(Link $conn): Statement
    {
        if (!$this->statements->contains($conn)) {
            return $this->makeStatement($conn);
        }

        /** @var Statement $statement */
        $statement = $this->statements[$conn];

        if (!$statement->isAlive()) {
            $this->statements->detach($conn);

            return $this->makeStatement($conn);
        }

        return $statement;
    }

    protected function makeStatement(Link $conn): Statement
    {
        $statement = $conn->prepare($this->query);

        $this->statements->attach($conn, $statement);

        return $statement;
    }

    protected function validateStatements(): void
    {
        $now = time();

        /**
         * @var Link $conn
         * @var Statement $statement
         */
        foreach ($this->statements as $conn => $statement) {
            if (!$statement->isAlive()) {
                $this->statements->detach($conn);

                continue;
            }

            if ($this->maxIdleTime > 0 && $statement->getLastUsedAt() + $this->maxIdleTime <= $now) {
                Coroutine::create(function () use ($conn) {
                    try {
                        $this->statements->detach($conn);
                    } catch (Throwable $e) {
                        // ignore statement close errors
                    }
                });

                continue;
            }
        }
    }
}
