
use GitElephant\Utilities;
    public const MODE_INDEX = 'index';
    public const MODE_MODE = 'mode';
    public const MODE_NEW_FILE = 'new_file';
    public const MODE_DELETED_FILE = 'deleted_file';
    public const MODE_RENAMED = 'renamed_file';
     * @var int|null
     * @var string|null
     * @var string|null
     * @var int|null
     * @var string|null
    private $chunks = [];
    public function __construct(array $lines)
        $this->chunks = [];

        if ($this->mode === self::MODE_INDEX || $this->mode === self::MODE_NEW_FILE) {
     * @return string
    public function __toString(): string
        return $this->originalPath == null ? "" : $this->originalPath;
     *
    private function findChunks(array $lines): void
    private function findPath(string $line): void
        $matches = [];
            $this->originalPath = $matches[1];
    private function findMode(string $line): void



    private function findSimilarityIndex(string $line): void
        $matches = [];
    public function getChunks(): array
    public function getDestinationPath(): string
    public function getMode(): string
    public function getOriginalPath(): string
    public function hasPathChanged(): bool
        return $this->originalPath !== $this->destinationPath;
    public function getSimilarityIndex(): int
    public function offsetExists($offset): bool
     * @return DiffChunk|null
    public function offsetGet($offset): ?DiffChunk
     * @param int|null   $offset offset
    public function offsetSet($offset, $value): void
    public function offsetUnset($offset): void
    public function count(): int
     * @return DiffChunk|null
    public function current(): ?DiffChunk
    public function next(): void
    public function key(): int
    public function valid(): bool
    public function rewind(): void