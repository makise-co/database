<?php
/**
 * This file is part of the Makise-Co Database Package
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\SqlCommon\Contracts;

use MakiseCo\SqlCommon\Exception;
use MakiseCo\Connection\TransientResource;

interface Executor extends TransientResource
{
    /**
     * @param string $sql SQL query to execute.
     *
     * @return CommandResult|ResultSet
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function query(string $sql);

    /**
     * @param string $sql SQL query to prepare.
     *
     * @return Statement
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function prepare(string $sql): Statement;

    /**
     * @param string $sql SQL query to prepare and execute.
     * @param mixed[] $params Query parameters.
     *
     * @return CommandResult|ResultSet
     *
     * @throws Exception\FailureException If the operation fails due to unexpected condition.
     * @throws Exception\ConnectionException If the connection to the database is lost.
     * @throws Exception\QueryError If the operation fails due to an error in the query (such as a syntax error).
     */
    public function execute(string $sql, array $params = []);

    /**
     * Closes the executor. No further queries may be performed.
     *
     * @return mixed
     */
    public function close();
}
