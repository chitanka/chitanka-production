<?php namespace App\Generator;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;

class EpubConverter {

	/** @var ParameterBag */
	private $parameters;
	/** @var string */
	private $cacheDir;

	public function __construct(ParameterBag $parameters, string $cacheDir) {
		$this->parameters = $parameters;
		$this->cacheDir = $cacheDir;
	}

	public function convert(string $epubUrl, string $targetFormat): string {
		$this->assertUrl($epubUrl);
		$this->assertSupportedTargetFormat($targetFormat);
		$this->assertEnabledTargetFormat($targetFormat);

		$commandTemplate = $this->parameters->get("{$targetFormat}_converter_command");
		if (empty($commandTemplate)) {
			throw new InvalidArgumentException("The target format '{$targetFormat}' does not have a shell converter command.");
		}

		$cachedOutputFileStore = new CachedOutputFileStore($this->cacheDir, $epubUrl, $targetFormat);
		$cachedOutputFile = $cachedOutputFileStore->get();
		if ($cachedOutputFile) {
			return $cachedOutputFile;
		}

		$epubFile = $this->downloadEpub($epubUrl);
		$epubFile->saveAt($this->cacheDir);

		$outputFile = $this->convertFile($commandTemplate, $epubFile->path, $targetFormat);
		$cachedOutputFileStore->set($outputFile);
		return $outputFile;
	}

	private function convertFile(string $commandTemplate, string $inputFile, string $outputFormat): string {
		$outputFile = str_replace('.epub', ".$outputFormat", $inputFile);
		$command = strtr($commandTemplate, [
			'INPUT_FILE' => escapeshellarg($inputFile),
			'OUTPUT_FILE' => escapeshellarg($outputFile),
			'OUTPUT_FILE_BASENAME' => escapeshellarg(basename($outputFile)),
		]);
		$binDir = realpath(__DIR__.'/../../bin');
		chdir($binDir);// go to local bin directory to allow execution of locally stored binaries
		$execPath = getenv('PATH');
		$extendPath = $execPath ? 'PATH=.:$PATH' : '';
		$commandWithCustomPath = trim("$extendPath $command");
		shell_exec($commandWithCustomPath);
		return $outputFile;
	}

	private function downloadEpub(string $epubUrl) {
		$stream = fopen($this->sanitizeSource($epubUrl), 'r');
		$headers = stream_get_meta_data($stream)['wrapper_data'];
		$contents = stream_get_contents($stream);
		fclose($stream);

		$epubFile = $this->getFileNameFromHeaders($headers) ?: basename($epubUrl);
		return new DownloadedFile($epubFile, $contents);
	}

	/*
	 * Example headers:
	 *     - Location: /cache/dl/file.epub
	 *     - Content-Disposition: attachment; filename="file.epub"
	 */
	private function getFileNameFromHeaders(array $headers): string {
		foreach (array_reverse($headers) as $header) {
			$parts = explode(':', $header);
			$name = strtolower(trim($parts[0]));
			switch ($name) {
				case 'content-disposition':
					$normalizedValue = strtr($parts[1], [' ' => '', '"' => '', "'" => '']) . ';';
					if (preg_match('#filename=([^;]+)#', $normalizedValue, $matches)) {
						return basename($matches[1]);
					}
					return '';
				case 'location':
					return basename(trim($parts[1]));
			}
		}
		return '';
	}

	private function assertUrl(string $urlToAssert) {
		if ( ! preg_match('#^https?://#', $urlToAssert)) {
			throw new InvalidArgumentException("Not a valid URL: '{$urlToAssert}'");
		}
	}

	private function assertSupportedTargetFormat(string $targetFormat) {
		$key = "{$targetFormat}_download_enabled";
		if ( ! $this->parameters->has($key)) {
			throw new InvalidArgumentException("Unsupported target format: '{$targetFormat}'");
		}
	}

	private function assertEnabledTargetFormat(string $targetFormat) {
		$key = "{$targetFormat}_download_enabled";
		if ( ! $this->parameters->get($key)) {
			throw new InvalidArgumentException("Target format is not enabled: '{$targetFormat}'");
		}
	}

	private function sanitizeSource(string $source): string {
		return preg_replace('#[^a-zA-Z\d:/.,_-]#', '', $source);
	}
}

class DownloadedFile {
	public $name;
	public $contents;
	public $path;

	public function __construct($name, $contents) {
		$this->name = $name;
		$this->contents = $contents;
	}

	public function saveAt(string $directory) {
		$this->path = "$directory/$this->name";
		$fs = new Filesystem();
		$fs->dumpFile($this->path, $this->contents);
	}
}

class CachedOutputFileStore {
	private $store;
	private $fs;

	public function __construct(string $cacheDir, string $sourceUrl, string $outputFormat) {
		$this->store = "$cacheDir/$outputFormat-".md5($sourceUrl).'.file';
		$this->fs = new Filesystem();
	}

	public function get(): ?string {
		if ( ! $this->fs->exists($this->store)) {
			return null;
		}
		$cachedOutputFile = trim(file_get_contents($this->store));
		if ( ! $this->fs->exists($cachedOutputFile)) {
			return null;
		}
		$this->fs->touch($cachedOutputFile);
		return $cachedOutputFile;
	}

	public function set(string $outputFile) {
		$this->fs->dumpFile($this->store, $outputFile);
	}
}
