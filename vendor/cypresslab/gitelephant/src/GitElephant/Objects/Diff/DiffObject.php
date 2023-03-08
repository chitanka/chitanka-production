use \GitElephant\Utilities;
    const MODE_INDEX        = 'index';
    const MODE_MODE         = 'mode';
    const MODE_NEW_FILE     = 'new_file';
    const MODE_DELETED_FILE = 'deleted_file';
    const MODE_RENAMED      = 'renamed_file';
     * @var int
     * @var string
     * @var string
     * @var int
     * @var string
    private $chunks;
    public function __construct($lines)
        $this->chunks   = array();
        if ($this->mode == self::MODE_INDEX || $this->mode == self::MODE_NEW_FILE) {
     * @return mixed
    public function __toString()
        return $this->originalPath;
    private function findChunks($lines)
    private function findPath($line)
        $matches = array();
            $this->originalPath    = $matches[1];
    private function findMode($line)
    private function findSimilarityIndex($line)
        $matches = array();
    public function getChunks()
    public function getDestinationPath()
    public function getMode()
    public function getOriginalPath()
    public function hasPathChanged()
        return ($this->originalPath !== $this->destinationPath);
    public function getSimilarityIndex()
    public function offsetExists($offset)
     * @return null
    public function offsetGet($offset)
     * @param int   $offset offset
    public function offsetSet($offset, $value)
    public function offsetUnset($offset)
    public function count()
     * @return mixed
    public function current()
    public function next()
    public function key()
    public function valid()
    public function rewind()