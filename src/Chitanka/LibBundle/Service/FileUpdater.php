<?php
namespace Chitanka\LibBundle\Service;

class FileUpdater
{
	protected $rootDir;
	protected $updateDir;

	public function __construct($rootDir, $updateDir) {
		$this->rootDir = $rootDir;
		$this->updateDir = $updateDir;
	}

	public function extractArchive(\ZipArchive $zip)
	{
		$extractDir = sys_get_temp_dir().'/chitanka-'.uniqid();
		mkdir($extractDir);
		$zip->extractTo($extractDir);
		$this->onAfterExtract($zip, $extractDir);
		$zip->close();

		$copier = new DirectoryCopier;
		$copier->copy($extractDir, $this->rootDir);

		foreach (file("$extractDir/.deleted", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $filename) {
			unlink("$this->rootDir/$filename");
		}

		copy("$extractDir/.last", "$this->updateDir/.last");
	}

	protected function onAfterExtract(\ZipArchive $zip)
	{
	}

	public function rmdir($path)
	{
		if ( ! file_exists($path)) {
			return false;
		}
		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path), \RecursiveIteratorIterator::CHILD_FIRST);
		foreach ($files as $name => $file) {
			if ($file->isFile()) {
				unlink($file->getRealPath());
			} else if ($file->isLink()) {
				unlink($name);
			} else if ($file->isDir()) {
				rmdir($file->getRealPath());
			}
		}
		rmdir($path);
		return true;
	}
}
