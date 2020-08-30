<?php
/**
 * This file is part of the Makise-Co Database Package
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\SqlCommon\Contracts;

use stdClass;

interface ResultSet
{
    public const FETCH_ARRAY = 0;
    public const FETCH_ASSOC = 1;
    public const FETCH_OBJECT = 2;

    /**
     * Returns the number of fields (columns) in each row.
     *
     * @return int
     */
    public function getFieldCount(): int;

    /**
     * Iteratively fetch a row
     *
     * @param int $fetchStyle fetch style, one of ResultSet::FETCH_* constants
     * @return array|stdClass|null array numerically indexed for ResultSet::FETCH_ARRAY
     *         or array associatively indexed for ResultSet::FETCH_ASSOC
     *         or object stdClass instance for ResultSet::FETCH_OBJECT
     *         or NULL when iteration ends.
     */
    public function fetch(int $fetchStyle = self::FETCH_ASSOC);

    /**
     * Iteratively fetch a row as associatively indexed array by column name
     *
     * @return array|null associatively indexed array or NULL when iteration ends.
     */
    public function fetchAssoc(): ?array;

    /**
     * Iteratively fetch a row as stdClass instance, where the column names are the property names.
     *
     * @return stdClass|null instance of stdClass or NULL when iteration ends.
     */
    public function fetchObject(): ?stdClass;

    /**
     * Iteratively fetch a row as numerically indexed array, where the index start with 0
     *
     * @return array|null numerically indexed array or NULL when iteration ends.
     */
    public function fetchArray(): ?array;

    /**
     * Iteratively fetch a single column.
     *
     * @param int|string $col The column name or index to fetch.
     * @param mixed $ref The variable where the column value will be stored in.
     * @return bool|null bool success or NULL when iteration ends.
     */
    public function fetchColumn($col, &$ref): ?bool;

    /**
     * Fetch all rows at once.
     *
     * @param int $fetchStyle fetch style, one of ResultSet::FETCH_* constants
     * @return array all fetched rows.
     */
    public function fetchAll(int $fetchStyle = self::FETCH_ASSOC): array;

    /**
     * Unbuffered result sets will free connection only when all results are fetched
     *
     * @return bool
     */
    public function isUnbuffered(): bool;
}
