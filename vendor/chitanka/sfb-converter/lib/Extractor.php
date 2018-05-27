<?php namespace Sfblib;

class Extractor {

	public function extractImagesForBookTemplate($text) {
		$nonImageLineCounter = 1;
		$extractedImages = [];
		foreach (explode(SfbConverter::EOL, $text) as $line) {
			if (SfbConverter::lineContainsBlockImage($line)) {
				$extractedImages[] = ":$nonImageLineCounter" . $line;
			} else {
				$nonImageLineCounter++;
			}
		}
		return implode(SfbConverter::EOL, $extractedImages) . SfbConverter::EOL;
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
			$output[] = trim($this->extractImagesForBookTemplate(file_get_contents($textFile)), SfbConverter::EOL);
			$output[] = '>>' . SfbConverter::CMD_DELIM . "{file:-$number}";
		}
		return implode(SfbConverter::EOL, $output) . SfbConverter::EOL;
	}
}
