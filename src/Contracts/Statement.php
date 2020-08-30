<?php
/**
 * This file is part of the Makise-Co Database Package
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Database\Contracts;

use MakiseCo\Connection\TransientResource;

interface Statement extends TransientResource
{
    /**
     * @param mixed[] $params
     *
     * @return CommandResult|ResultSet
     */
    public function execute(array $params = []);

    /**
     * @return string The SQL string used to prepare the statement.
     */
    public function getQuery(): string;
}
