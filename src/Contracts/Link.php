<?php
/**
 * This file is part of the Makise-Co Database Package
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Database\Contracts;

use MakiseCo\Connection\ConnectionInterface;

interface Link extends Executor, ConnectionInterface
{
    /**
     * Starts a transaction on a single connection.
     *
     * @param int $isolation Transaction isolation level.
     *
     * @return Transaction
     */
    public function beginTransaction(int $isolation = Transaction::ISOLATION_COMMITTED): Transaction;
}
