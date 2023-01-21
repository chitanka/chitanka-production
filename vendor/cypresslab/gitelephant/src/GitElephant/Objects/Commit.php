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

use GitElephant\Command\BranchCommand;
use GitElephant\Command\Caller\CallerInterface;
use GitElephant\Command\MainCommand;
use GitElephant\Command\RevListCommand;
use GitElephant\Command\RevParseCommand;
use GitElephant\Command\ShowCommand;
use GitElephant\Objects\Commit\Message;
use GitElephant\Repository;

/**
 * The Commit object represent a commit
 *
 * @author Matteo Giachino <matteog@gmail.com>
 */
class Commit implements TreeishInterface, \Countable
{
    /**
     * @var \GitElephant\Repository
     */
    private $repository;

    /**
     * @var string
     */
    private $ref;

    /**
     * sha
     *
     * @var string
     */
    private $sha;

    /**
     * tree
     *
     * @var string
     */
    private $tree;

    /**
     * the commit parents
     *
     * @var array
     */
    private $parents = [];

    /**
     * the Author instance for author
     *
     * @var  Author
     */
    private $author;

    /**
     * the Author instance for committer
     *
     * @var  Author
     */
    private $committer;

    /**
     * the Message instance
     *
     * @var Commit\Message
     */
    private $message;

    /**
     * the date for author
     *
     * @var \DateTime
     */
    private $datetimeAuthor;

    /**
     * the date for committer
     *
     * @var \Datetime
     */
    private $datetimeCommitter;

    /**
     * Class constructor
     *
     * @param \GitElephant\Repository $repository the repository
     * @param TreeishInterface|string $treeish    a treeish reference
     */
    private function __construct(Repository $repository, $treeish = 'HEAD')
    {
        $this->repository = $repository;
        $this->ref = $treeish;
        $this->parents = [];
    }

    /**
     * factory method to create a commit
     *
     * @param Repository    $repository repository instance
     * @param string        $message    commit message
     * @param bool          $stageAll   automatically stage the dirty working tree. Alternatively call stage() on the repo
     * @param string|Author $author     override the author for this commit
     *
     * @throws \RuntimeException
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Process\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     * @return Commit
     */
    public static function create(
        Repository $repository,
        string $message,
        bool $stageAll = false,
        $author = null
    ): Commit {
        $repository->getCaller()->execute(MainCommand::getInstance($repository)->commit($message, $stageAll, $author));

        return $repository->getCommit();
    }

    /**
     * pick an existing commit
     *
     * @param Repository              $repository repository
     * @param TreeishInterface|string $treeish    treeish
     *
     * @throws \RuntimeException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     * @return Commit
     */
    public static function pick(Repository $repository, $treeish = null): Commit
    {
        $commit = new self($repository, $treeish);
        $commit->createFromCommand();

        return $commit;
    }

    /**
     * static generator to generate a single commit from output of command.show service
     *
     * @param \GitElephant\Repository $repository  repository
     * @param array                   $outputLines output lines
     *
     * @return Commit
     */
    public static function createFromOutputLines(Repository $repository, array $outputLines): Commit
    {
        $commit = new self($repository);
        $commit->parseOutputLines($outputLines);

        return $commit;
    }

    /**
     * get the commit properties from command
     *
     * @see ShowCommand::commitInfo
     */
    public function createFromCommand(): void
    {
        $command = ShowCommand::getInstance($this->getRepository())->showCommit($this->ref);
        $outputLines = $this->getCaller()->execute($command, true, $this->getRepository()->getPath())->getOutputLines();
        $this->parseOutputLines($outputLines);
    }

    /**
     * get the branches this commit is contained in
     *
     * @see BranchCommand::contains
     */
    public function getContainedIn(): array
    {
        $command = BranchCommand::getInstance($this->getRepository())->contains($this->getSha());

        return array_map('trim', (array) $this->getCaller()->execute($command)->getOutputLines(true));
    }

    /**
     * number of commits that lead to this one
     *
     * @throws \RuntimeException
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     * @return int
     */
    public function count(): int
    {
        $command = RevListCommand::getInstance($this->getRepository())->commitPath($this);

        return count($this->getCaller()->execute($command)->getOutputLines(true));
    }

    public function getDiff(): \GitElephant\Objects\Diff\Diff
    {
        return $this->getRepository()->getDiff($this);
    }

    /**
     * parse the output of a git command showing a commit
     *
     * @param Iterable $outputLines output lines
     */
    private function parseOutputLines($outputLines): void
    {
        $message = [];
        foreach ($outputLines as $line) {
            $matches = [];
            if (preg_match('/^commit (\w+)$/', $line, $matches) > 0) {
                $this->sha = $matches[1];
            }

            if (preg_match('/^tree (\w+)$/', $line, $matches) > 0) {
                $this->tree = $matches[1];
            }

            if (preg_match('/^parent (\w+)$/', $line, $matches) > 0) {
                $this->parents[] = $matches[1];
            }

            if (preg_match('/^author (.*) <(.*)> (\d+) (.*)$/', $line, $matches) > 0) {
                $author = new Author();
                $author->setName($matches[1]);
                $author->setEmail($matches[2]);
                $this->author = $author;
                $date = \DateTime::createFromFormat('U O', $matches[3] . ' ' . $matches[4]);
                $date->modify($date->getOffset() . ' seconds');
                $this->datetimeAuthor = $date;
            }

            if (preg_match('/^committer (.*) <(.*)> (\d+) (.*)$/', $line, $matches) > 0) {
                $committer = new Author();
                $committer->setName($matches[1]);
                $committer->setEmail($matches[2]);
                $this->committer = $committer;
                $date = \DateTime::createFromFormat('U O', $matches[3] . ' ' . $matches[4]);
                $date->modify($date->getOffset() . ' seconds');
                $this->datetimeCommitter = $date;
            }

            if (preg_match('/^    (.*)$/', $line, $matches)) {
                $message[] = $matches[1];
            }
        }

        $this->message = new Message($message);
    }

    /**
     * Returns true if the commit is a root commit. Usually the first of the repository
     *
     * @return bool
     */
    public function isRoot(): bool
    {
        return empty($this->parents);
    }

    /**
     * toString magic method
     *
     * @return string the sha
     */
    public function __toString(): string
    {
        return $this->sha;
    }

    /**
     * @return CallerInterface
     */
    private function getCaller(): CallerInterface
    {
        return $this->getRepository()->getCaller();
    }

    /**
     * Repository setter
     *
     * @param \GitElephant\Repository $repository repository variable
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

    /**
     * author getter
     *
     * @return Author
     */
    public function getAuthor(): ?Author
    {
        return $this->author;
    }

    /**
     * committer getter
     *
     * @return Author
     */
    public function getCommitter(): ?Author
    {
        return $this->committer;
    }

    /**
     * message getter
     *
     * @return Message
     */
    public function getMessage(): ?Message
    {
        return $this->message;
    }

    /**
     * parent getter
     *
     * @return array
     */
    public function getParents(): array
    {
        return $this->parents;
    }

    /**
     * sha getter
     *
     * @param bool $short short version
     *
     * @return string
     */
    public function getSha(bool $short = false): ?string
    {
        return $short ? substr($this->sha, 0, 7) : $this->sha;
    }

    /**
     * tree getter
     *
     * @return string
     */
    public function getTree(): ?string
    {
        return $this->tree;
    }

    /**
     * datetimeAuthor getter
     *
     * @return \DateTime
     */
    public function getDatetimeAuthor(): ?\DateTime
    {
        return $this->datetimeAuthor;
    }

    /**
     * datetimeCommitter getter
     *
     * @return \DateTime
     */
    public function getDatetimeCommitter(): ?\DateTime
    {
        return $this->datetimeCommitter;
    }

    /**
     * rev-parse command - often used to return a commit tag.
     *
     * @param array $options the options to apply to rev-parse
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     * @return array
     */
    public function revParse(array $options = []): array
    {
        $c = RevParseCommand::getInstance()->revParse($this, $options);
        $caller = $this->repository->getCaller();
        $caller->execute($c);

        return array_map('trim', $caller->getOutputLines(true));
    }

    /**
     * Is the commit tagged?
     *
     * return true if some tag of repository point to this commit
     * return false otherwise
     *
     * @return bool
     */
    public function tagged(): bool
    {
        $result = false;

        /** @var Tag $tag */
        foreach ($this->repository->getTags() as $tag) {
            if ($tag->getSha() === $this->getSha()) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * Return Tags that point to this commit
     *
     * @return Tag[]
     */
    public function getTags(): array
    {
        $currentCommitTags = [];

        /** @var Tag $tag */
        foreach ($this->repository->getTags() as $tag) {
            if ($tag->getSha() === $this->getSha()) {
                $currentCommitTags[] = $tag;
            }
        }

        return $currentCommitTags;
    }
}
