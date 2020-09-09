<?php
/**
 * This file is part of the Makise-Co SqlCommon Package
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\SqlCommon\Tests;

use MakiseCo\SqlCommon\DatabasePool;
use MakiseCo\SqlCommon\PooledStatement;
use MakiseCo\SqlCommon\Tests\Stub\ConnectionConfig;
use MakiseCo\SqlCommon\Tests\Stub\Pool;
use Swoole\Coroutine;

class StatementPoolTest extends CoroTestCase
{
    private DatabasePool $pool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pool = new Pool(new ConnectionConfig());
    }

    public function testSome(): void
    {
        $this->pool->setMaxActive(4);
        $this->pool->setMinActive(4);
        $this->pool->init();

        Coroutine::sleep(0.010);

        $statementPool = $this->pool->prepare('SELECT 1');
        self::assertInstanceOf(PooledStatement::class, $statementPool);

        // TODO: Write tests
    }

    protected function tearDown(): void
    {
        $this->pool->close();

        parent::tearDown();
    }
}
