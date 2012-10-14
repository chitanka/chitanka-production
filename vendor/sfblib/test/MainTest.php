<?php
class MainTest extends TestCase
{
	private $inputFiles = array(
		#'m-in-m.sfb',
		'accent.sfb',
		'ampersand.sfb',
		'annotation-author-dedication.sfb',
		'annotation.sfb',
		'annotation-with-image.sfb',
		'author-date-author.sfb',
		'author-not-last.sfb',
		'author.sfb',
		'bug-body-section-swap.sfb',
		'bug-redundant-stanza.sfb',
		'bug-m-with-author.sfb',
		'cite.sfb',
		'date-alone.sfb',
		'date-letter-begin.sfb',
		'date-multi.sfb',
		'dedication.sfb',
		'dedication-with-subtitle.sfb',
		'deleted.sfb',
		'emphasis.sfb',
		//'emphasis-strong-mishmash.sfb', // will probably never work
		'epigraph.sfb',
		'epigraph-with-separator.sfb',
		'epigraph-dedication.sfb',
		'header.sfb',
		'image-complex.sfb',
		'image-in-blocks.sfb',
		'image.sfb',
		'images-start-section.sfb',
		'image-start-subsection.sfb',
		'index.sfb',
		'infoblock.sfb',
		'letter.sfb',
		'note-in-author.sfb',
		'notes-high-numbers.sfb',
		'notes-mixed.sfb',
		'notes.sfb',
		'notes-with-brackets.sfb',
		'notice.sfb',
		'poem-center.sfb',
		'poem-complex.sfb',
		'poem-epigraph-note-with-poem.sfb',
		'poem-epigraph-poem.sfb',
		'poem-in-epigraph.sfb',
		'poem.sfb',
		'poem-titles.sfb',
		'poem-with-notes.sfb',
		'poem-with-numbers.sfb',
		'poem-with-preformatted.sfb',
		'poem-with-separators.sfb',
		'preformatted.sfb',
		'section-dedication-only.sfb',
		'section-empty.sfb',
		'section-empty-with-note.sfb',
		'separator.sfb',
		'sign.sfb',
		#'sign-with-note.sfb',
		'sign-with-subtitle.sfb',
		'strong.sfb',
		'subtitle.sfb',
		'table-align.sfb',
		'table.sfb',
		'table-span.sfb',
		'table-th2.sfb',
		'table-th.sfb',
		'table-with-img.sfb',
		'title-note-multiline.sfb',
		'title-note-notitle.sfb',
		'titles.sfb',
		'title-with-note.sfb',
		#'all.sfb',
	);

	public function testFb2Converter()
	{
		foreach ($this->getInputFiles() as $file) {
			$fb2File = str_replace('.sfb', '.fb2', $file);
			//$this->clearFb2File($fb2File);
			$this->doTestConverter('SfbToFb2Converter', $file, dirname($file), $fb2File, array($this, 'clearFb2String'));
		}
	}

	public function testHtmlConverter()
	{
		foreach ($this->getInputFiles() as $file) {
			$htmlFile = str_replace('.sfb', '.html', $file);
			$this->doTestConverter('SfbToHtmlConverter', $file, 'img', $htmlFile);
		}
	}

	private function getInputFiles()
	{
		return array_map(function($file){
			return dirname(__FILE__).'/converter/'.$file;
		}, $this->inputFiles);
	}

	private function doTestConverter($converter, $inFile, $imgDir, $outFile, $callback = null)
	{
		$converterClass = 'Sfblib_' . $converter;
		$conv = new $converterClass($inFile, $imgDir);
		$conv->setObjectCount(1);
		$conv->rmPattern(' —')->rmRegExpPattern('/^— /');
		$conv->convert();
		$testOutput = $conv->getContent();
		if ( is_callable($callback) ) {
			$testOutput = call_user_func($callback, $testOutput);
		}
		// remove double new lines
		$testOutput = preg_replace('/\n\n+/', "\n", $testOutput);

		// save output if wanted
		$outDir = dirname($outFile) . '/output';
		if (file_exists($outDir)) {
			file_put_contents($outDir .'/'. basename($outFile), $testOutput);
		}

		$this->assertEquals(file_get_contents($outFile), $testOutput, "$converter: $inFile");
	}


	private function clearFb2File($file)
	{
		if ( ! file_exists($file) ) {
			return;
		}
		$contents = file_get_contents($file);
		if ( $this->shouldClearFb2String($contents) ) {
			file_put_contents($file, strtr($contents, array(
				"\xEF\xBB\xBF" => '', // BOM
				"\r"    => '',
				'fn_'  => 'note_',
			)));
			$this->removeElementFromFile($file, 'id');
			$this->removeElementFromFile($file, 'program-used');
			$this->removeElementFromFile($file, 'date');
			$this->removeElementFromFile($file, 'stylesheet');
		}
	}


	private function clearFb2String($string)
	{
		if ( $this->shouldClearFb2String($string) ) {
			$string = $this->removeElementFromString($string, 'id');
			$string = $this->removeElementFromString($string, 'program-used');
			$string = $this->removeElementFromString($string, 'date');
			$string = $this->removeElementFromString($string, 'stylesheet');
		}
		return $string;
	}


	private function shouldClearFb2String($string)
	{
		return strpos($string, '<id>') !== false;
	}


	private function removeElementFromFile($file, $elm)
	{
		$contents = file_get_contents($file);
		if ( strpos($contents, "<$elm>") !== false ) {
			file_put_contents($file, $this->removeElementFromString($contents, $elm));
		}
	}


	private function removeElementFromString($string, $elm)
	{
		$start = strpos ( $string, "<$elm" );
		if ( $start === false ) {
			return $string;
		}

		$end = strpos ( $string, "</$elm>", $start ) + strlen("</$elm>");
		return substr_replace ( $string, '', $start, $end - $start );
	}
}
