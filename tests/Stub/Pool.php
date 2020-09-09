<?php
/**
 * This file is part of the Makise-Co SqlCommon Package
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\SqlCommon\Tests\Stub;

use Closure;
use MakiseCo\Connection\ConnectorInterface;
use MakiseCo\SqlCommon\Contracts\Transaction;
use MakiseCo\SqlCommon\DatabasePool;

class Pool extends DatabasePool
{
    protected function createTransaction(Transaction $transaction, Closure $release): Transaction
    {
        throw new \RuntimeException('Not implemented');
    }

    protected function createDefaultConnector(): ConnectorInterface
    {
        return new Connector();
    }
}
