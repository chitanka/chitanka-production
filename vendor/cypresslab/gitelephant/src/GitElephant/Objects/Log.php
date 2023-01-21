<?php

/**
 * GitElephant - An abstraction layer for git written in PHP
 * Copyright (C) 2013  Matteo Giachino
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see [http://www.gnu.org/licenses/].
 */

namespace GitElephant\Objects;

use GitElephant\Command\LogCommand;
use GitElephant\Repository;
use GitElephant\Utilities;

/**
 * Git log abstraction object
 *
 * @author Matteo Giachino <matteog@gmail.com>
 * @author Dhaval Patel <tech.dhaval@gmail.com>
 */
class Log implements \ArrayAccess, \Countable, \Iterator
{
    /**
     * @var \GitElephant\Repository
     */
    private $repository;

    /**
     * the commits related to this log
     *
     * @var array
     */
    private $commits = [];

    /**
     * the cursor position
     *
     * @var int
     */
    private $position = 0;

    /**
     * static method to generate standalone log
     *
     * @param \GitElephant\Repository $repository  repo
     * @param array                   $outputLines output lines from command.log
     *
     * @return \GitElephant\Objects\Log
     */
    public static function createFromOutputLines(Repository $repository, array $outputLines): \GitElephant\Objects\Log
    {
        $log = new self($repository);
        $log->parseOutputLines($outputLines);

        return $log;
    }

    /**
     * Class constructor
     *
     * @param Repository  $repository
     * @param string      $ref
     * @param string|null $path
     * @param int         $limit
     * @param int|null    $offset
     * @param bool        $firstParent
     */
    public function __construct(
        Repository $repository,
        $ref = 'HEAD',
        $path = null,
        int $limit = 15,
        int $offset = null,
        bool $firstParent = false
    ) {
        $this->repository = $repository;
        $this->createFromCommand($ref, $path, $limit, $offset, $firstParent);
    }

    /**
     * get the commit properties from command
     *
     * @param string  $ref         treeish reference
     * @param string  $path        path
     * @param int     $limit       limit
     * @param int  $offset      offset
     * @param boolean $firstParent first parent
     *
     * @throws \RuntimeException
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     * @see ShowCommand::commitInfo
     */
    private function createFromCommand(
        $ref,
        $path = null,
        int $limit = null,
        int $offset = null,
        bool $firstParent = false
    ): void {
        $command = LogCommand::getInstance($this->getRepository())
            ->showLog($ref, $path, $limit, $offset, $firstParent);

        $outputLines = $this->getRepository()
            ->getCaller()
            ->execute($command)
            ->getOutputLines(true);

        $this->parseOutputLines($outputLines);
    }

    private function parseOutputLines(array $outputLines): void
    {
        $this->commits = [];
        $commits = Utilities::pregSplitFlatArray($outputLines, '/^commit (\w+)$/');

        foreach ($commits as $commitOutputLines) {
            $this->commits[] = Commit::createFromOutputLines($this->getRepository(), $commitOutputLines);
        }
    }

    /**
     * Get array representation
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->commits;
    }

    /**
     * Get the first commit
     *
     * @return Commit|null
     */
    public function first(): ?\GitElephant\Objects\Commit
    {
        return $this->offsetGet(0);
    }

    /**
     * Get the last commit
     *
     * @return Commit|null
     */
    public function last(): ?\GitElephant\Objects\Commit
    {
        return $this->offsetGet($this->count() - 1);
    }

    /**
     * Get commit at index
     *
     * @param int $index the commit index
     *
     * @return Commit|null
     */
    public function index(int $index): ?\GitElephant\Objects\Commit
    {
        return $this->offsetGet($index);
    }

    /**
     * ArrayAccess interface
     *
     * @param int $offset offset
     *
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->commits[$offset]);
    }

    /**
     * ArrayAccess interface
     *
     * @param int $offset offset
     *
     * @return Commit|null
     */
    public function offsetGet($offset): ?\GitElephant\Objects\Commit
    {
        return isset($this->commits[$offset]) ? $this->commits[$offset] : null;
    }

    /**
     * ArrayAccess interface
     *
     * @param int   $offset offset
     * @param mixed $value  value
     *
     * @return void
     * @throws \RuntimeException
     */
    public function offsetSet($offset, $value): void
    {
        throw new \RuntimeException('Can\'t set elements on logs');
    }

    /**
     * ArrayAccess interface
     *
     * @param int $offset offset
     *
     * @return void
     * @throws \RuntimeException
     */
    public function offsetUnset($offset): void
    {
        throw new \RuntimeException('Can\'t unset elements on logs');
    }

    /**
     * Countable interface
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->commits);
    }

    /**
     * Iterator interface
     *
     * @return Commit|null
     */
    public function current(): ?\GitElephant\Objects\Commit
    {
        return $this->offsetGet($this->position);
    }

    /**
     * Iterator interface
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * Iterator interface
     *
     * @return int
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Iterator interface
     *
     * @return bool
     */
    public function valid(): bool
    {
        return $this->offsetExists($this->position);
    }

    /**
     * Iterator interface
     */
    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * Repository setter
     *
     * @param \GitElephant\Repository $repository the repository variable
     */
    public function setRepository(Repository $repository): void
    {
        $this->repository = $repository;
    }

    /**
     * Repository getter
     *
     * @return \GitElephant\Repository
     */
    public function getRepository(): \GitElephant\Repository
    {
        return $this->repository;
    }
}
