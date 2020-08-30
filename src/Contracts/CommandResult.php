<?php
/**
 * This file is part of the Makise-Co Database Package
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\SqlCommon\Contracts;

interface CommandResult
{
    /**
     * Returns the number of rows affected by the query.
     *
     * @return int
     */
    public function getAffectedRowCount(): int;
}
