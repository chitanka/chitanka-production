<?php namespace Sfblib;

class Extractor {

	public function extractImagesForBookTemplate($text, string $includedFileLine) {
		$nonImageLineCounter = 1;
		$extractedImages = [];
		$lines = explode(SfbConverter::EOL, rtrim($text, SfbConverter::EOL));
		$lastLineNr = count($lines) - 1;
		$lastImage = null;
		foreach ($lines as $lineNr => $line) {
			if (!SfbConverter::lineContainsBlockImage($line)) {
				$nonImageLineCounter++;
			} elseif ($nonImageLineCounter === 1) {
				$extractedImages[] = $line;
			} elseif ($lineNr < $lastLineNr) {
				$extractedImages[] = ":$nonImageLineCounter" . $line;
			} else {
				$lastImage = $line;
			}
		}
		return implode(SfbConverter::EOL, $extractedImages) . SfbConverter::EOL
			. $includedFileLine . SfbConverter::EOL
			. $lastImage;
	}

	public function extractImagesFromWorkFilesForBookTemplate($workFiles) {
		$getNumberFromWorkFileName = function($file) {
			if (preg_match('/work\.(\d+)\./', $file, $m)) {
				return $m[1];
			}
			return null;
		};
		$output = [];
		foreach ($workFiles as $i => $textFile) {
			$number = $getNumberFromWorkFileName($textFile) ?: $i;
			$output[] = '>'. SfbConverter::CMD_DELIM . "{title:-$number}";
			$output[] = trim($this->extractImagesForBookTemplate(file_get_contents($textFile), '>>' . SfbConverter::CMD_DELIM . "{file:-$number}"), SfbConverter::EOL) . SfbConverter::EOL;
		}
		return implode(SfbConverter::EOL, $output) . SfbConverter::EOL;
	}
}
