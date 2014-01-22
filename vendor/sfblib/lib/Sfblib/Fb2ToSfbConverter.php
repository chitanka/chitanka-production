<?php

class Sfblib_Fb2ToSfbConverter
{
	const EOL = "\n";

	private $data;

	public function __construct($data = null)
	{
		$this->data = $data;
	}

	public function convert($data = null)
	{
		if ($data === null) {
			$data = $this->data;
		}
		if ($data === null) {
			throw new Exception('No data given to FB2 to SFB converter');
		}
		$dataIsFile = strpos($data, '<') === false;
		$fb2 = new SimpleXMLElement($data, null, $dataIsFile);

		$sfb = '';

		// TODO stylesheet

		if ($fb2->description) {
			$sfb .= $this->convertMainTitle($fb2->description->{'title-info'});
			$sfb .= $this->line();
			// TODO description
		}
		if ($fb2->body->image) {
			$sfb .= $this->convertImage($fb2->body->image);
		}

		// skip $fb2->body->title

		if ($fb2->body->epigraph) {
			$sfb .= $this->convertEpigraphs($fb2->body->epigraph);
		}
		if ($fb2->body->section) {
			$sfb .= $this->convertSections($fb2->body->section);
		}

		if ($fb2->body->binary) {
			$this->convertBinaries($fb2->body->binary);
		}

		$sfb = $this->clearSfb($sfb);

		return $sfb;
	}

	private function convertMainTitle(SimpleXMLElement $titleInfo)
	{
		$sfb = '';
		$sfb .= $this->line($this->convertMainAuthor($titleInfo->author), '|');
		$sfb .= $this->line($titleInfo->{'book-title'}, '|');
		return $sfb;
	}

	private function convertMainAuthor(SimpleXMLElement $author)
	{
		return implode(' ', array(
			$author->{'first-name'},
			//$author->{'middle-name'},
			$author->{'last-name'}
		));
		// <nickname> - 0..1 (один, обязателен при отсутствии <first-name> и <last-name>, иначе опционально); 
	}

	// TODO
	private function convertDescription()
	{
//<src-title-info> - 0..1 (один, опционально) с версии 2.1;
//<document-info> - 1 (один, обязателен);
//<publish-info> - 0..1 (один, опционально);
//<custom-info> - 0..n (любое число, опционально);
//<output> - 0..2
	}

	// TODO
	private function convertTitleInfo()
	{
//<genre> - 1..n (любое число, один обязaтелен);
//<author> - 1..n (любое число, один обязaтелен);
//<book-title> - 1 (один, обязателен);
//<annotation> - 0..1 (один, опционально);
//<keywords> - 0..1 (один, опционально);
//<date> - 0..1 (один, опционально);
//<coverpage> - 0..1 (один, опционально);
//<lang> - 1 (один, обязателен);
//<src-lang> - 0..1 (один, опционально);
//<translator> - 0..n (любое число, опционально);
//<sequence> - 0..n (любое число, опционально). <sequence name="Грегор Эйзенхорн" number="2"/>
	}

	// TODO
	private function convertSrcTitleInfo()
	{
//<genre> - 1..n (любое число, один обязaтелен);
//<author> - 1..n (любое число, один обязaтелен);
//<book-title> - 1 (один, обязателен);
//<annotation> - 0..1 (один, опционально);
//<keywords> - 0..1 (один, опционально);
//<date> - 0..1 (один, опционально);
//<coverpage> - 0..1 (один, опционально);
//<lang> - 1 (один, обязателен);
//<src-lang> - 0..1 (один, опционально);
//<translator> - 0..n (любое число, опционально);
//<sequence> - 0..n (любое число, опционально).
	}

	// TODO
	private function convertDocumentInfo()
	{
//<author> - 1..n (любое число, один обязaтелен);
//<program-used> - 0..1 (один, опционально);
//<date> - 1 (один, обязателен);
//<src-url> - 0..n (любое число, опционально); източник
//<src-ocr> - 0..1 (один, опционально);
//<id> - 1 (один, обязателен);
//<version> - 1 (один, обязателен);
//<history> - 0..1 (один, опционально);
//<publisher> - 0..n (любое число, опционально) с версии 2.2.
	}

	// TODO
	private function convertPublishInfo()
	{
//<book-name> - 0..1 (один, опционально) - название;
//<publisher> - 0..1 (один, опционально) - издательство;
//<city> - 0..1 (один, опционально)- место издания;
//<year> - 0..1 (один, опционально) - год издания;
//<isbn> - 0..1 (один, опционально) - ISBN издания;
//<sequence> - 0..n (любое число, опционально) - серия (серии) изданий, в которую входит книга.
	}

	// TODO
	private function convertAnnotation()
	{
//<p>;
//<poem>;
//<cite>;
//<subtitle>;
//<empty-line>;
//<table> (с версии 2.1).
	}

	// TODO
	private function convertCoverpage()
	{
		// <coverpage><image l:href="#cover.jpg"/></coverpage>
	}

	private function convertImage(SimpleXMLElement $image)
	{
		$sfb = '';
		if ($image) {
			$sfb = $this->line(sprintf('{img:%s}', ltrim($image->attributes('l', true)->href, '#')));
		}
		return $sfb;
	}

	private function convertEpigraphs(SimpleXMLElement $epigraphs)
	{
		$sfb = '';
		if ($epigraphs) {
			foreach ($epigraphs as $epigraph) {
				$sfb .= $this->convertEpigraph($epigraph);
			}
		}
		return $sfb;
	}

	private function convertEpigraph(SimpleXMLElement $epigraph)
	{
		$sfb = '';
		$sfb .= $this->command('E>');
		foreach ($epigraph->children() as $elm) {
			switch ($elm->getName()) {
				case 'p':
					$sfb .= $this->convertParagraph($elm); break;
				case 'poem':
					$sfb .= $this->convertPoem($elm); break;
				case 'cite':
					$sfb .= $this->convertCite($elm); break;
				case 'empty-line':
					$sfb .= $this->line(); break;
				case 'text-author':
					$sfb .= $this->convertTextAuthor($elm); break;
			}
		}
		$sfb .= $this->command('E$');
		return $sfb;
	}

	private function convertSections(SimpleXMLElement $sections, $level = 1)
	{
		$sfb = '';
		if ($sections) {
			$sfb .= $this->line();
			foreach ($sections as $section) {
				$sfb .= $this->convertSection($section, $level);
			}
		}
		return $sfb;
	}

	private function convertSection(SimpleXMLElement $section, $level)
	{
		$sfb = '';
		$sfb .= $this->convertTitle($section->title, $level);
		$sfb .= $this->convertEpigraphs($section->epigraph);
		$sfb .= $this->convertImage($section->image);
		//$sfb .= $this->convertAnnotation($section->annotation);
		if ($section->section) {
			$sfb .= $this->convertSections($section->section, $level + 1);
			return $sfb;
		}
		foreach ($section->children() as $elm) {
			switch ($elm->getName()) {
				case 'p':
					$sfb .= $this->convertParagraph($elm); break;
				case 'image':
					$sfb .= $this->convertImage($elm); break;
				case 'poem':
					$sfb .= $this->convertPoem($elm); break;
				case 'subtitle':
					$sfb .= $this->convertSubtitle($elm); break;
				case 'cite':
					$sfb .= $this->convertCite($elm); break;
				case 'empty-line':
					$sfb .= $this->line(); break;
				case 'table':
					$sfb .= $this->convertTable($elm); break;
			}
		}
		return $sfb;
	}

	private $_titleMarkers = array(
		1 => '>',
		2 => '>>',
		3 => '>>>',
		4 => '>>>>',
		5 => '>>>>>',
	);
	private function convertTitle(SimpleXMLElement $title, $level = 1)
	{
		$sfb = '';
		if (!$title) {
			return $sfb;
		}
		$sfb .= $this->line();
		foreach ($title->p as $paragraph) {
			$sfb .= $this->convertParagraph($paragraph, $this->_titleMarkers[$level]);
		}
		$sfb .= $this->line();
		return $sfb;
	}

	private function convertParagraph(SimpleXMLElement $paragraph, $command = '')
	{
		$content = $this->removeElement($paragraph->asXML(), 'p');
		$content = $this->convertInlineElements($content);
		return $this->line($content, $command);
	}

	private function convertPoem(SimpleXMLElement $poem)
	{
		$sfb = '';
		$sfb .= $this->command('P>');
		$currentStanzaCount = 0;
		foreach ($poem->children() as $elm) {
			switch ($elm->getName()) {
				case 'title':
					$sfb .= $this->convertPoemTitle($elm); break;
				case 'epigraph':
					$sfb .= $this->convertEpigraphs($elm); break;
				case 'stanza':
					if ($currentStanzaCount++ > 0) {
						$sfb .= $this->line();
					}
					$sfb .= $this->convertStanza($elm); break;
				case 'text-author':
					$sfb .= $this->convertAuthor($elm); break;
				case 'date':
					$sfb .= $this->convertDate($elm); break;
			}
		}
		$sfb .= $this->command('P$');
		return $sfb;
	}

	private function convertSubtitle(SimpleXMLElement $subtitle)
	{
		$sfb = '';
		$sfb .= $this->line($this->convertInlineElements($subtitle->asXML()), '#');
		return $sfb;
	}

	private function convertCite(SimpleXMLElement $cite)
	{
		$sfb = '';
		$sfb .= $this->command('C>');
		foreach ($cite->children() as $elm) {
			switch ($elm->getName()) {
				case 'p':
					$sfb .= $this->convertParagraph($elm); break;
				case 'subtitle':
					$sfb .= $this->convertSubtitle($elm); break;
				case 'empty-line':
					$sfb .= $this->line(); break;
				case 'poem':
					$sfb .= $this->convertPoem($elm); break;
				case 'table':
					$sfb .= $this->convertTable($elm); break;
				case 'text-author':
					$sfb .= $this->convertTextAuthor($elm); break;
			}
		}
		$sfb .= $this->command('C$');
		return $sfb;
	}

	private function convertTextAuthor(SimpleXMLElement $textAuthor)
	{
		$sfb = '';
		$sfb .= $this->line($this->convertInlineElements($textAuthor->asXML()), '@');
		return $sfb;
	}

	// TODO td, th, tr
	private function convertTable(SimpleXMLElement $table)
	{
		$sfb = '';
		$sfb .= $table->asXML();
		return $sfb;
	}

	private function convertStanza(SimpleXMLElement $stanza)
	{
		$sfb = '';
		foreach ($stanza->children() as $elm) {
			switch ($elm->getName()) {
				case 'title':
					$sfb .= $this->convertSubtitle($elm); break;
				case 'subtitle':
					$sfb .= $this->convertSubtitle($elm); break;
				case 'v':
					$sfb .= $this->convertVerse($elm); break;
			}
		}
		return $sfb;
	}

	private function convertVerse(SimpleXMLElement $verse)
	{
		$content = $this->removeElement($verse->asXML(), 'v');
		$content = $this->convertInlineElements($content);
		return $this->line($content);
	}

	// TODO - a, style, image
	private function convertInlineElements($xml)
	{
		$sfb = strtr($xml, array(
			'<emphasis>' => '{e}', '</emphasis>' => '{/e}',
			'<strong>' => '{s}', '</strong>' => '{/s}',
			'<sup>' => '{sup}', '</sup>' => '{/sup}',
			'<sub>' => '{sub}', '</sub>' => '{/sub}',
			'<code>' => '{pre}', '</code>' => '{/pre}',
			'<strikethrough>' => '{del}', '</strikethrough>' => '{/del}',
		));
		$reStart = '(?<=[\s([„«>])';
		$reEnd = '(?![\w\d])';
		$sfb = ' '.$sfb;
		$sfb = preg_replace("|$reStart{e}(.+){/e}$reEnd|U", '_$1_', $sfb);
		$sfb = preg_replace("|$reStart{s}(.+){/s}$reEnd|U", '__$1__', $sfb);
		$sfb = ltrim($sfb, ' ');
		return $sfb;
	}

	// TODO
	private function convertBinaries(SimpleXMLElement $binaries)
	{

	}

	private function line($content = '', $command = '')
	{
		if (empty($content)) {
			return $command . self::EOL;
		}
		return $command . "\t" . $content . self::EOL;
	}

	private function command($command)
	{
		return $this->line('', $command);
	}

	private function removeElement($xml, $tag)
	{
		return strtr($xml, array("<$tag>" => '', "</$tag>" => ''));
	}

	private function clearSfb($sfb)
	{
		$sfb = preg_replace('/\n\n\n+>/', "\n\n>", $sfb);
		$sfb = trim($sfb, "\n") . "\n";
		return $sfb;
	}
}
