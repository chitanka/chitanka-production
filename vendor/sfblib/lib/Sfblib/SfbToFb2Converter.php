<?php
/**
* SFB to Fiction Book 2.1 converter
*/
class Sfblib_SfbToFb2Converter extends Sfblib_SfbConverter
{
	protected
		/* block elements */
		$sectionElement        = 'section',
		$epigraphElement       = 'epigraph',
		$dedicationElement     = 'epigraph',
		$citeElement           = 'cite',
		$noticeElement         = '',
		$annotationElement     = 'annotation',
		$infoblockElement      = 'body',
		$poemElement           = 'poem',
		$preformattedElement   = '',
		$tableElement          = 'table',
		$paragraphElement      = 'p',
		$authorElement         = 'text-author',
		//$dateElement           = 'date',
		$dateElement           = 'text-author',
		$emptyLineElement      = 'empty-line',
		$separatorElement      = 'subtitle',
		$subheaderElement      = 'subtitle',
		$poemHeaderElement     = 'title',
		$titleElement          = 'title',
		$footnoteElement       = 'section',
		$footnotesElement      = 'body',

		/* inline elements */
		$imageElement          = 'image',
		$superscriptElement    = 'sup',
		$subscriptElement      = 'sub',
		$emphasisElement       = 'emphasis',
		$strongElement         = 'strong',
		$deletedElement        = 'strikethrough',
		$codeElement           = 'code',

		$rootElement           = 'FictionBook',
		$bodyElement           = 'body',
		$stanzaElement         = 'stanza',
		$verseElement          = 'v',
		$binaryElement         = 'binary',

		$alignments = array(
			self::TABLE_CELL_LEFT   => array('align' => 'left'),
			self::TABLE_CELL_CENTER => array('align' => 'center'),
			self::TABLE_CELL_RIGHT  => array('align' => 'right'),
			self::TABLE_CELL_TOP    => array('valign' => 'top'),
			self::TABLE_CELL_MIDDLE => array('valign' => 'middle'),
			self::TABLE_CELL_BOTTOM => array('valign' => 'bottom'),
		),

		/** save all binary data here */
		$binaryText            = '',

		$genre                 = array('prose_classic'),
		$authors               = array(),
		$title                 = '(няма заглавие)',
		$subtitle              = '',
		$keywords              = '',
		$textDate              = '',
		$lang                  = 'bg',
		$translators           = array(),
		$sequences             = array(),

		$srcAuthors            = array(),
		$srcTitle              = '',
		$srcSubtitle           = '',
		$srcDate               = '',
		$srcLang               = '',
		$srcSequences          = array(),

		$docAuthors            = array('(неизвестен автор)'),
		$programName           = 'Mylib SfbToFb2 Converter',
		$date                  = '',
		$docId                 = 'FILE_ID',
		$docVersion            = '0.0',
		$history               = array();

	private
		$_inStanza             = false,

		// process subheaders as they were titles
		$_subheaderAsTitle     = false,

		/*
			Track the last saved content for every block.
			Used for a dirty hack in isEmptySection()
		*/
		$_lastSaved            = array();


	public function __construct($file, $imgDir = 'img')
	{
		parent::__construct($file, $imgDir);
		#$this->imgDir = dirname(__FILE__) . '/../' . $this->imgDir;
		$this->date = date('Y-m-d H:i:s');
		$this->stylesheetFile = dirname(__FILE__) . '/templates-fb2/stylesheet.css';

		$this->addPattern(' —', ' —'); // nbsp before dashes
		$this->addRegExpPattern('/^— /', '— '); // nbsp after quotation dashes

		$reStart = '(?<=[\s([„>])';
		$reEnd = '(?![\w\d])';
		$e = $this->emphasisElement;
		$s = $this->strongElement;

		$this->addRegExpPattern("/({$reStart}___|^___)([^_]+)__{$reEnd}/U", "_<$s>$2</$s>");
		$this->addRegExpPattern("/({$reStart}___|^___)([^_]+)_{$reEnd}/U", "__<$e>$2</$e>");

		$this->addRegExpPattern("/({$reStart}_|^_)([^_]+)___{$reEnd}/U", "<$e>$2</$e>__");
		$this->addRegExpPattern("/({$reStart}__|^__)([^_]+)___{$reEnd}/U", "<$s>$2</$s>_");

// 		$this->rmRegExpPattern( "/({$reStart}__|^__)(.+)__{$reEnd}/U");
// 		$this->addRegExpPattern("/({$reStart}__|^__)(.+)__{$reEnd}/U", "<$s>$2</$s>");
// 		$this->rmRegExpPattern( "/({$reStart}_|^_)(.+)_{$reEnd}/U");
// 		$this->addRegExpPattern("/({$reStart}_|^_)(.+)_{$reEnd}/U", "<$e>$2</$e>");

		$this->rmRegExpPattern('/\[\[(.+)\|(.+)\]\]/U');
		$this->rmRegExpPattern('!(?<=[\s>])(http://[^])\s,;<]+)!e');

		//$this->addPattern('&', '&amp;');

		$this->addContentBlock('annotation');
		$this->addContentBlock('infoblock');
	}


	public function getContent()
	{
		$eol = $this->getEol();
		$trepl = array(
			'<v>' . self::EOL  => '<v>',
			self::EOL . '</v>' => '</v>',
			'<p>' . self::EOL  => '<p>',
			self::EOL . '</p>' => '</p>',
		);

		return strtr('<?xml version="1.0" encoding="UTF-8"?>'
			. self::EOL
			. $this->out->getStartTag($this->rootElement, array(
				'xmlns'   => 'http://www.gribuser.ru/xml/fictionbook/2.0',
				'xmlns:l' => 'http://www.w3.org/1999/xlink',
			))                                . $eol
			. $this->getStylesheet()          . $eol
			. $this->getDescription()         . $eol
			. $this->getText()                . $eol
			. $this->getInfoblock()           . $eol
			. $this->getNotes(1)              . $eol
			. $this->getBinary()              . $eol
			. $this->out->getEndTag($this->rootElement)
			. self::EOL
			, $trepl);
	}


	public function getStylesheet()
	{
		if ($this->hasCustomStyles()) {
			return $this->out->xmlElement($this->stylesheetElement, $this->getStylesheetContent(), $this->stylesheetAttributes);
		}

		return '';
	}


	public function getStylesheetContent()
	{
		return file_get_contents($this->stylesheetFile);
	}


	public function getText()
	{
		return $this->out->xmlElement($this->bodyElement, $this->getContentBlock('main'));
	}

	public function getNotes($type = 0)
	{
		$footnotes = $this->getNotesBlock();

		if ( empty($footnotes) ) {
			return '';
		}

		switch ($type) {
			case 0:
			default: return $footnotes;
			case 1:  return $this->out->xmlElement($this->footnotesElement,
				$footnotes, array('name' => 'notes'));
		}
	}


	public function getAnnotation()
	{
		return $this->getContentBlock('annotation');
	}


	public function getInfoblock()
	{
		return $this->getContentBlock('infoblock');
	}


	public function getBinary()
	{
		return $this->binaryText;
	}


	public function getDescription()
	{
		$eol = $this->getEol();
		return $this->out->xmlElement('description',
			$this->getTitleInfo()      . $eol
			.$this->getSrcTitleInfo()  . $eol
			.$this->getDocumentInfo()  . $eol
		);
	}


	public function getTitleInfo()
	{
		$eol = $this->getEol();
		return $this->out->xmlElement('title-info',
			$this->getGenre()                                           . $eol
			.$this->getAuthors()                                        . $eol
			.$this->out->xmlElement('book-title', $this->getTitle())    . $eol
			.$this->getAnnotation()                                     . $eol
			.$this->out->xmlElementOrNone('keywords', $this->keywords)  . $eol
			//.$this->out->xmlElement('date', $this->textDate)          . $eol
			.$this->getCoverpage()                                      . $eol
			.$this->out->xmlElementOrNone('lang', $this->lang)          . $eol
			.$this->out->xmlElementOrNone('src-lang', $this->srcLang)   . $eol
			.$this->getPersonsFor($this->translators, 'translator')     . $eol
			.$this->getSequencesFor($this->sequences)                   . $eol
		);
	}


	public function getSrcTitleInfo()
	{
		if ( empty($this->srcAuthors) && empty($this->srcTitle) ) {
			return '';
		}
		$eol = $this->getEol();
		return $this->out->xmlElement('src-title-info',
			$this->getGenre()                                           . $eol
			.$this->getSrcAuthors()                                     . $eol
			.$this->out->xmlElement('book-title', $this->srcTitle)      . $eol
			.$this->out->xmlElementOrNone('date', $this->textDate)      . $eol
			.$this->out->xmlElementOrNone('lang', $this->srcLang)       . $eol
			.$this->getSequencesFor($this->srcSequences)                . $eol
		);
	}


	public function getDocumentInfo()
	{
		$eol = $this->getEol();
		return $this->out->xmlElement('document-info',
			$this->getPersonsFor($this->docAuthors)                     . $eol
			.$this->out->xmlElement('program-used', $this->programName) . $eol
			.$this->out->xmlElement('date', $this->date)                . $eol
			.$this->out->xmlElement('id', $this->docId)                 . $eol
			.$this->out->xmlElement('version', $this->docVersion)       . $eol
			.$this->getHistory()                                        . $eol
		);
	}


	public function getAuthors()
	{
		if ( empty($this->authors) ) {
			$this->addAuthor('(неизвестен автор)', false);
		}
		return $this->getPersonsFor($this->authors);
	}


	public function getTitle()
	{
		$title = $this->title;
		if ( ! empty($this->subtitle) ) {
			$title .= " ($this->subtitle)";
		}
		return $title;
	}


	public function getCoverpage()
	{
		if ( empty($this->coverpage) ) {
			return '';
		}
		return $this->out->xmlElement('coverpage', $this->out->getEmptyTag(
			'image', array('l:href' => '#'.$this->coverpage)
		));
	}


	public function getGenre()
	{
		$elements = array();
		foreach ($this->genre as $key => $genre) {
			$attrs = array();
			if (is_string($key)) {
				$attrs['match'] = $genre;
				$genre = $key;
			}
			$elements[] = $this->out->xmlElement('genre', $genre, $attrs);
		}
		return implode($this->getEol(), $elements);
	}


	public function getSrcAuthors()
	{
		if ( empty($this->srcAuthors) ) {
			$this->addSrcAuthor('(unknown author)', false);
		}
		return $this->getPersonsFor($this->srcAuthors);
	}


	public function getPersonsFor($data, $elm = 'author')
	{
		$persons = '';
		foreach ( (array) $data as $pdata ) {
			if ( is_array($pdata) ) {
				$first = $this->out->xmlElement('first-name', array_shift($pdata));
				$last = $this->out->xmlElement('last-name', empty($pdata) ? '' : array_pop($pdata));
				$middle = empty($pdata) ? '' : $this->out->xmlElement('middle-name', implode(' ', $pdata));
				$name = $first . $middle . $last;
			} else {
				$name = $this->out->xmlElement('nickname', $pdata);
			}

			$persons .= $this->out->xmlElement($elm, $name);
		}
		return $persons;
	}


	public function getSequencesFor($data)
	{
		$sequences = '';
		foreach ( (array) $data as $name => $nr ) {
			$sequences .= $this->out->getEmptyTag('sequence', array(
				'name'   => $name,
				'number' => ( empty($nr) ? null : (int) $nr ),
			));
		}
		return $sequences;
	}


	public function getHistory()
	{
		$text = '';
		foreach ( (array) $this->history as $line ) {
			$text .= $this->out->xmlElement($this->paragraphElement, htmlspecialchars($line));
		}
		return $this->out->xmlElementOrNone('history', $text);
	}


	/*************************************************************************/


	/**
	 * @param mixed $genre  Allowed values:
	 *     - string
	 *     - array, e.g.
	 *         array(
	 *           "nonfiction",
	 *           "sci_history" => 50 // used for the match attribute
	 *         )
	 */
	public function setGenre($genre)
	{
		$this->genre = (array) $genre;
	}
	public function addAuthor($name, $raw = true)
	{
		$this->authors[] = $raw ? $this->preparePersonName($name) : $name;
	}
	public function setTitle($title)
	{
		$this->title = strip_tags($title);
	}
	public function setSubtitle($subtitle)
	{
		$this->subtitle = strip_tags($subtitle);
	}
	public function setKeywords($keywords)
	{
		$this->keywords = $keywords;
	}
	public function setTextDate($date)
	{
		$this->textDate = $date;
	}
	public function addCoverpage($src)
	{
		$this->coverpage = $this->saveBinaryText($this->getCurrentImageId(), $src);
	}
	/** Only two-letter codes */
	public function setLang($lang)
	{
		$this->lang = $lang;
	}
	/** Only two-letter codes */
	public function setSrcLang($lang)
	{
		$this->srcLang = $lang;
	}
	public function addTranslator($name, $raw = true)
	{
		$this->translators[] = $raw ? $this->preparePersonName($name) : $name;
	}
	public function addSequence($name, $nr = null)
	{
		$this->sequences[$name] = $nr;
	}


	public function addSrcAuthor($name, $raw = true)
	{
		$this->srcAuthors[] = $raw ? $this->preparePersonName($name) : $name;
	}
	public function setSrcTitle($srcTitle)
	{
		$this->srcTitle = strip_tags($srcTitle);
	}
	public function setSrcSubtitle($srcSubtitle)
	{
		$this->srcSubtitle = strip_tags($srcSubtitle);
	}
	public function addSrcSequence($name, $nr = null)
	{
		$this->srcSequences[$name] = $nr;
	}


	public function addDocAuthor($name, $raw = true)
	{
		$this->docAuthors[] = $raw ? $this->preparePersonName($name) : $name;
	}
	public function setDocId($docId)
	{
		$this->docId = $docId;
	}
	public function setDocVersion($docVersion)
	{
		$this->docVersion = $docVersion;
	}
	public function setHistory($history)
	{
		$this->history = $history;
	}


	protected function preparePersonName($fullname)
	{
		return explode(' ', $fullname);
	}


	/*************************************************************************/


	protected function preDoText()
	{
		switch ($this->lcmd) {
			case self::HEADER:
			case self::TITLE_1:
			case self::TITLE_2:
			case self::TITLE_3:
			case self::TITLE_4:
			case self::TITLE_5:
			case self::ANNO_S:
			case self::INFO_S:
			case self::DEDICATION_S:
			case self::EPIGRAPH_S:
				break;
			default:
				/** TODO */
				if ( ! $this->isEmptyLine()
						&& $this->line != self::EPIGRAPH_E
						&& $this->line != self::DEDICATION_E
						&& ! $this->noteStarts()
						&& ! ( $this->isInNote() /*&& $this->noteEnds()*/ ) )
				{
					$this->openSectionIfNone();
				}
		}
	}


	/**
	* Enable tracking of the last saved content.
	* Useful for detecting empty sections.
	* @see isEmptySection()
	*
	* @param $text  The text to save
	*/
	protected function save($text, $forceEmpty = false)
	{
		$this->_lastSaved[$this->_curBlock] = $text;
		parent::save($text, $forceEmpty);
	}


	/*************************************************************************/


	/**
	* @param $lnes  Header lines
	*/
	protected function inHeader($lines)
	{
		$_lines = $lines;

		if ( count($_lines) >= 2 ) {
			// first is the author line
			foreach ( explode(', ', array_shift($_lines)) as $author ) {
				$this->addAuthor($author);
			}
		}

		$this->setTitle( self::removeNoteLinks(array_shift($_lines)) );

		parent::inHeader($lines);
	}


	/*************************************************************************/


	protected function closeSection()
	{
		if ( $this->isEmptySection() ) {
			$this->saveEmptyTag($this->emptyLineElement);
		}

		parent::closeSection();
	}


	/**
	*/
	protected function isEmptySection()
	{
		return $this->_lastSaved[$this->_curBlock] == '<section>'
			|| $this->_lastSaved[$this->_curBlock] == '</epigraph>'
			|| strpos($this->_lastSaved[$this->_curBlock], '<title>') === 0
			// check if following line messes with something
			|| $this->_lastSaved[$this->_curBlock] == '';

	}


	/**
	*/
	protected function imageIsOnSectionStart()
	{
		return $this->_lastSaved[$this->_curBlock] == '</epigraph>'
			|| strpos($this->_lastSaved[$this->_curBlock], '<title>') === 0;

	}

	/*************************************************************************/


	/**
	* @param $titleLines  Title lines
	* @param $marker      A title marker (self::TITLE_X)
	*/
	protected function inTitle($titleLines, $marker)
	{
		$text = '';
		foreach ($titleLines as $titleLine) {
			if ( ! empty($titleLine) ) {
				$text .= $this->out->xmlElement($this->paragraphElement, $titleLine);
			}
		}
		if ( ! empty($text) ) {
			$this->save( $this->out->xmlElement($this->titleElement, $text) );
		}
	}


	/*************************************************************************/


	protected function doNoticeStart()
	{
		$this->paragraphPrefix = $this->out->getStartTag($this->emphasisElement);
		$this->paragraphSuffix = $this->out->getEndTag($this->emphasisElement);
	}

	protected function doNoticeEnd()
	{
		$this->paragraphPrefix =
		$this->paragraphSuffix = '';
	}


	/*************************************************************************/


	protected function doAnnotationStart()
	{
		$this->enterContentBlock('annotation');
		parent::doAnnotationStart();
	}

	protected function doAnnotationEnd()
	{
		parent::doAnnotationEnd();
		$this->leaveContentBlock('annotation');
	}


	/*************************************************************************/


	protected function doInfoblockStart()
	{
		$this->enterContentBlock('infoblock');

		$this->saveStartTag($this->infoblockElement, array(
			 'name' => 'info'
		));
		$this->saveElement($this->titleElement,
			$this->out->xmlElement($this->paragraphElement, 'Информация за текста')
		);
		$this->saveStartTag($this->sectionElement);
	}

	protected function doInfoblockEnd()
	{
		$this->saveEndTag($this->sectionElement);
		parent::doInfoblockEnd();
		$this->leaveContentBlock('infoblock');
	}


	/*************************************************************************/


	protected function doSubheaderStart($isMulti)
	{
/*		if ( $this->isInPoem() && ! $this->isInStanza() ) {
			if ($isMulti) {
				$this->_subheaderAsTitle = true;
				$this->saveStartTag($this->titleElement);
			} else {
				$this->openStanza();
			}
		}*/
	}

	protected function doSubheaderEnd($isMulti)
	{
/*		if ( $this->_subheaderAsTitle ) {
			$this->saveEndTag($this->titleElement);
			$this->_subheaderAsTitle = false;
		}*/
	}

	protected function doSubheaderLineStart($isMulti)
	{
		if ( $this->acceptsSubheader() ) {
/*			if ( $this->_subheaderAsTitle ) {
				$this->saveStartTag($this->paragraphElement);
			} else {*/
				parent::doSubheaderLineStart($isMulti);
// 			}
		} else {
			$this->saveStartTag($this->paragraphElement);
			$this->saveStartTag($this->strongElement);
		}
	}

	protected function doSubheaderLineEnd($isMulti)
	{
		if ( $this->acceptsSubheader() ) {
/*			if ( $this->_subheaderAsTitle ) {
				$this->saveEndTag($this->paragraphElement);
			} else {*/
				parent::doSubheaderLineEnd($isMulti);
#			}
		} else {
			$this->saveEndTag($this->strongElement);
			$this->saveEndTag($this->paragraphElement);
		}
	}


	protected function acceptsSubheader()
	{
		return ! $this->isInMainEpigraphBody()
			&& ! $this->isInMainDedicationBody();
	}


	/*************************************************************************/


	protected function doPoemEnd()
	{
		$this->closeStanzaIfAny();
		parent::doPoemEnd();
	}


	protected function doPoemHeaderStart()
	{
		$this->saveStartTag($this->poemHeaderElement);
	}

	protected function doPoemHeaderEnd()
	{
		$this->saveEndTag($this->poemHeaderElement);
	}

	protected function doPoemHeaderLineStart()
	{
		$this->saveStartTag($this->paragraphElement);
	}

	protected function doPoemHeaderLineEnd()
	{
		$this->saveEndTag($this->paragraphElement);
	}


	protected function doStyleStart()
	{
		if ( $this->isInPoem() ) {
			$this->openStanza();
		}

		$this->saveTemp('paragraphPrefix', $this->paragraphPrefix);
		$this->saveTemp('paragraphSuffix', $this->paragraphSuffix);

		$this->paragraphPrefix = $this->out->getStartTag($this->blockStyleElement, array($this->blockStyleAttribute => $this->ltext)) . $this->paragraphPrefix;
		$this->paragraphSuffix .= $this->out->getEndTag($this->blockStyleElement);
	}

	protected function doStyleEnd()
	{
		if ( $this->isInPoem() ) {
			$this->closeStanzaIfAny();
		}

		$this->paragraphPrefix = $this->getTemp('paragraphPrefix');
		$this->paragraphSuffix = $this->getTemp('paragraphSuffix');
	}


	/*************************************************************************/


	protected function addTableCaption($text)
	{
		$this->saveStartTag($this->subheaderElement);
		$this->saveContent($text);
		$this->saveEndTag($this->subheaderElement);
	}

	protected function doTableEnd()
	{
		$this->save( $this->simpleTable($this->tableData) );
	}


	/*************************************************************************/


	protected function doAuthorStart()
	{
		if ( $this->isInPoem() ) {
			$this->closeStanzaIfAny();
		}
	}

	protected function doAuthorLineStart()
	{
		if ( $this->acceptsAuthor() ) {
			parent::doAuthorLineStart();
		} else {
			$this->saveStartTag($this->paragraphElement);
			$this->saveStartTag($this->emphasisElement);
		}
	}

	protected function doAuthorLineEnd()
	{
		if ( $this->acceptsAuthor() ) {
			parent::doAuthorLineEnd();
			#$this->revertParagraphElement();
		} else {
			$this->saveEndTag($this->emphasisElement);
			$this->saveEndTag($this->paragraphElement);
		}
	}


	/*************************************************************************/


	protected function doDate()
	{
		$this->doAuthor();
	}

	protected function doDateStart()
	{
		$this->doAuthorStart();
// 		if ( $this->acceptsDate() ) {
// 			$this->closeStanzaIfAny();
// 			$this->overwriteParagraphElement();
// 			parent::doDateStart();
// 		} else {
// 			$this->paragraphPrefix = $this->out->getStartTag($this->emphasisElement);
// 			$this->paragraphSuffix = $this->out->getEndTag($this->emphasisElement);
// 			$this->saveStartTag($this->paragraphElement);
// 		}
	}


	protected function doDateEnd()
	{
		$this->doAuthorEnd();
// 		if ( $this->acceptsDate() ) {
// 			parent::doDateEnd();
// 			$this->revertParagraphElement();
// 		} else {
// 			$this->saveEndTag($this->paragraphElement);
// 			$this->paragraphPrefix =
// 			$this->paragraphSuffix = '';
// 		}
	}


	protected function acceptsAuthor()
	{
		return $this->isAtEpigraphEnd()
			|| $this->isAtDedicationEnd()
			|| $this->isAtCiteEnd()
			|| $this->isAtPoemEnd()
			|| ($this->isInPoem() && $this->isAtDate())
			// TODO remove
			|| ($this->noteStarts() && (
				$this->isInCite() || $this->isInEpigraph() || $this->isInPoem() )
				);
	}


	protected function acceptsDate()
	{
		return $this->isInPoem();
	}


	/*************************************************************************/


	protected function doEmptyLine()
	{
		if ( $this->isInPoem() ) {
			$this->closeStanzaIfAny();
		} else {
			parent::doEmptyLine();
		}
	}


	/*************************************************************************/


	/** TODO refactor */
	protected function savePreformatted($content)
	{
		if ( $this->isInPoem() ) {
			$this->openStanzaIfNone();
			$this->saveStartTag($this->verseElement);
			$this->saveStartTag($this->codeElement);
			parent::savePreformatted($content);
			$this->saveEndTag($this->codeElement);
			$this->saveEndTag($this->verseElement);
		} else {
			$this->saveStartTag($this->paragraphElement);
			$this->saveStartTag($this->codeElement);
			parent::savePreformatted($content);
			$this->saveEndTag($this->codeElement);
			$this->saveEndTag($this->paragraphElement);
		}
	}


	/*************************************************************************/


	protected function doParagraphStart()
	{
		if ( $this->isInMainPoemBody() ) {
			$this->openStanzaIfNone();
			$this->overwriteParagraphElement($this->verseElement);
		}
		parent::doParagraphStart();
	}

	protected function doParagraphEnd()
	{
		parent::doParagraphEnd();
		if ( $this->isInMainPoemBody() ) {
			$this->revertParagraphElement();
		}
	}


	/*************************************************************************/


	protected function openStanzaIfNone()
	{
		if ( ! $this->_inStanza ) {
			$this->openStanza();
		}
	}

	protected function closeStanzaIfAny()
	{
		if ( $this->_inStanza ) {
			$this->closeStanza();
		}
	}

	protected function openStanza()
	{
		$this->closeStanzaIfAny();
		$this->_inStanza = true;
		$this->saveStartTag($this->stanzaElement);
	}

	protected function closeStanza()
	{
		$this->_inStanza = false;
		$this->saveEndTag($this->stanzaElement);
	}

	protected function isInStanza()
	{
		return $this->_inStanza;
	}


	/*************************************************************************/


	protected function inSeparator()
	{
		if ( $this->isInPoem() ) {
			$this->closeStanzaIfAny();
			$this->openStanza();
		} else if ( $this->isInEpigraph() || $this->isInDedication() ) {
			// separators as formatted as subtitles, but FB2 does not allow
			// a subtitle in an epigraph, so give it a plain paragraph instead
			parent::doParagraphStart();
			$this->inParagraph();
			parent::doParagraphEnd();
		} else {
			parent::inSeparator();
		}
	}


	/*************************************************************************/


	protected function doNoteStart()
	{
		parent::doNoteStart();
		$this->saveElement($this->titleElement,
			$this->out->xmlElement($this->paragraphElement, $this->_curNoteIndex/*$this->curFn*/)
		);
	}


	public function getNoteLink($curReference)
	{
		return $this->out->xmlElement('a', $curReference, array(
			'l:href' => '#' . self::getNoteId($curReference),
			'type'   => 'note',
		));
	}


	/*************************************************************************/


	private $_hasExtraSectionForImage = false;
	protected function doBlockImageStart()
	{
		if ( $this->acceptsBlockImage() ) {
			$this->overwriteParagraphElement();

			if ($this->imageHasNoteInTitle() && $this->imageIsOnSectionStart()) {
				// we'll add a paragraph for the image title,
				// so wrap them in a section
				$this->openSection();
				$this->_hasExtraSectionForImage = true;
			}

			if ( ! $this->imageIsOnSectionStart() ) {
				$this->doEmptyLine();
			}
		}
	}

	protected function doBlockImageEnd()
	{
		if ( $this->acceptsBlockImage() ) {
			$this->revertParagraphElement();

			if ($this->imageHasNoteInTitle()) {
				// we cannot have a link to a footnote in the title attribute,
				// so let the link live in a paragraph
				$this->appendParagraphIfImageTitle();
				if ($this->_hasExtraSectionForImage) {
					$this->closeSection();
					$this->_hasExtraSectionForImage = false;
				}
			}
		}
	}


	protected function appendParagraphIfImageTitle()
	{
		if ( preg_match('/\|#(.+)[|}]/U', $this->ltext, $m) ) {
			$this->saveStartTag($this->paragraphElement);
			$this->saveContent($m[1]);
			$this->saveEndTag($this->paragraphElement);
		}
	}

	protected function acceptsBlockImage()
	{
		return ! $this->isInCite()
			&& ! $this->isInEpigraph()
			&& ! $this->isInDedication()
			&& ! $this->isInAnnotation()
			&& ! $this->isInPoem();
	}

	protected function imageHasNoteInTitle()
	{
		return preg_match('/\*/', $this->ltext);
	}

	protected function getImage($src, $id, $alt, $title, $url, $size, $align)
	{
		$attrs = array('l:href' => '#' . $this->saveBinaryText($id, $src));
		if ( $this->isInBlockImage() && $this->acceptsBlockImage() ) {
			$attrs += array(
				'alt'    => $alt,
				'title'  => strtr($title, array('*' => '')),
				'id'     => $this->isStandardImageId($id) ? "l$id" : $id,
			);
		}

		return $this->out->getEmptyTag($this->imageElement, $attrs);
	}



	/**
	* Save an image data in the binary box.
	* If the same image was already saved, only return the corresponding ID.
	*/
	protected function saveBinaryText($id, $src)
	{
		$src = str_replace('thumb/', '', $src); // no thumbs for now
		$content = file_get_contents($src);
		$hash = md5($content);

		if ( isset($this->_binaryIds[$hash]) ) {
			return $this->_binaryIds[$hash];
		}

		$this->binaryText .= $this->out->xmlElement($this->binaryElement,
			$this->encodeImage($content),
			array(
				'content-type' => Sfblib_Util::guessMimeType($src),
				'id'           => $id,
			)
		);

		return $this->_binaryIds[$hash] = $id;
	}


	protected function encodeImage($data)
	{
		return base64_encode($data);
	}



	/*************************************************************************/


	public function simpleTable($data)
	{
		$t = '<table>';
		foreach ($data as $row) {
			$t .= '<tr>';
			foreach ($row as $cell) {
				$ctype = 'd';
				if ( is_array($cell) ) {
					if ( isset( $cell[0]['type'] ) ) {
						$ctype = $cell[0]['type'] == 'header' ? 'h' : 'd';
						unset( $cell[0]['type'] );
					}
					$cattrs = $this->out->makeAttribs($cell[0]);
					$content = $cell[1];
				} else {
					$cattrs = '';
					$content = $cell;
				}
				$t .= "<t{$ctype}{$cattrs}>{$content}</t{$ctype}>";
			}
			$t .= '</tr>';
		}
		return $t.'</table>';
	}


	/*************************************************************************/


	protected function doExternLink($href)
	{
		return $href;
	}

	protected function doInternalLinkElement($target, $text)
	{
		return $this->out->xmlElement('a', $text, array(
			'l:href'  => $this->internalLinkTarget . "#$target",
		), false);
	}

}
