<?php
/**
 * This file is part of the Makise-Co Database Package
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\SqlCommon\Contracts;

use MakiseCo\Connection\ConnectionInterface;
use MakiseCo\SqlCommon\Exception;

interface Link extends Executor, ConnectionInterface
{
    /**
     * Starts a transaction on a single connection.
     *
     * @param int $isolation Transaction isolation level.
     *
     * @return Transaction
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     */
    public function beginTransaction(int $isolation = Transaction::ISOLATION_COMMITTED): Transaction;
}
