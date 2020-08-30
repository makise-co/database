<?php
/**
 * This file is part of the Makise-Co Database Package
 * World line: 0.571024a
 *
 * (c) Dmitry K. <coder1994@gmail.com>
 */

declare(strict_types=1);

namespace MakiseCo\Database;

use stdClass;
use Closure;
use MakiseCo\Database\Contracts\ResultSet;

class PooledResultSet implements ResultSet
{
    private ResultSet $resultSet;

    private ?Closure $release;

    /**
     * @param ResultSet $result ResultSet object created by pooled connection or statement.
     * @param Closure $release Callable to be invoked when the result set is destroyed.
     */
    public function __construct(ResultSet $result, Closure $release)
    {
        $this->resultSet = $result;
        $this->release = $release;
    }

    public function __destruct()
    {
        if ($this->release !== null) {
            ($this->release)();
        }
    }

    public function getFieldCount(): int
    {
        return $this->resultSet->getFieldCount();
    }

    /**
     * @inheritDoc
     */
    public function fetch(int $fetchStyle = self::FETCH_ASSOC)
    {
        if ($this->release === null) {
            return null;
        }

        $ex = null;
        $res = null;

        try {
            $res = $this->resultSet->fetch($fetchStyle);
        } catch (\Throwable $e) {
            $ex = $e;
        }

        if ($ex !== null || $res === null) {
            $release = $this->release;
            $this->release = null;
            $release();

            if ($ex !== null) {
                throw $ex;
            }
        }

        return $res;
    }

    /**
     * @inheritDoc
     */
    public function fetchAssoc(): ?array
    {
        /** @var array<string, mixed>|null */
        return $this->fetch(self::FETCH_ASSOC);
    }

    /**
     * @inheritDoc
     */
    public function fetchObject(): ?stdClass
    {
        /** @var stdClass|null */
        return $this->fetch(self::FETCH_OBJECT);
    }

    /**
     * @inheritDoc
     */
    public function fetchArray(): ?array
    {
        /** @var array<int, mixed>|null */
        return $this->fetch(self::FETCH_ARRAY);
    }

    /**
     * @inheritDoc
     */
    public function fetchColumn($col, &$ref): ?bool
    {
        if ($this->release === null) {
            return null;
        }

        $ex = null;
        $res = null;

        try {
            $res = $this->resultSet->fetchColumn($col, $ref);
        } catch (\Throwable $e) {
            $ex = $e;
        }

        if ($ex !== null || $res === null) {
            $release = $this->release;
            $this->release = null;
            $release();

            if ($ex !== null) {
                throw $ex;
            }
        }

        return $res;
    }

    /**
     * @inheritDoc
     */
    public function fetchAll(int $fetchStyle = self::FETCH_ASSOC): array
    {
        $result = [];

        while (null !== ($row = $this->fetch($fetchStyle))) {
            $result[] = $row;
        }

        return $result;
    }
    /**
     * @inheritDoc
     */
    public function isUnbuffered(): bool
    {
        return $this->resultSet->isUnbuffered();
    }
}
