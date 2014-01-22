<?php
/**
* Generic SFB to XML-ish language converter
*/
class Sfblib_SfbConverter
{

	const
		EOL          = "\n",
		CMD_DELIM    = "\t",

		HEADER       = '|',
		POEM_HEADER  = '|',
		TABLE_HEADER = '#',
		SUBHEADER    = '#',

		TITLE_1 = '>',
		TITLE_2 = '>>',
		TITLE_3 = '>>>',
		TITLE_4 = '>>>>',
		TITLE_5 = '>>>>>',

		ANNO_S         = 'A>',  ANNO_E         = 'A$',
		INFO_S         = 'I>',  INFO_E         = 'I$',
		DEDICATION_S   = 'D>',  DEDICATION_E   = 'D$',
		EPIGRAPH_S     = 'E>',  EPIGRAPH_E     = 'E$',
		NOTICE_S       = 'N>',  NOTICE_E       = 'N$',
		POEM_S         = 'P>',  POEM_E         = 'P$',
		CITE_S         = 'C>',  CITE_E         = 'C$',
		PREFORMATTED_S = 'F>',  PREFORMATTED_E = 'F$',
		TABLE_S        = 'T>',  TABLE_E        = 'T$',
		STYLE_S        = 'M>',  STYLE_E        = 'M$',

		/* raw input, save as is */
		RAW_S          = 'R>',  RAW_E          = 'R$',

		/* one-liners */
		NOTICE_OL       = 'N',
		PREFORMATTED_OL = 'F',
		AUTHOR_OL       = '@',
		DATE_OL         = '@@',
		/* paragraphs are marked with an empty marker */
		PARAGRAPH       = '',
		EMPTYLINE       = '^',

		NOTE_START         = '[*',
		NOTE_START_REGEXP  = '\[(\*+)(\d*)',
		NOTE_END           = ']',

		TABLE_CELL  = '|',
		TABLE_HCELL = '!',
		/* cell alignment */
		TABLE_CELL_LEFT   = '<',
		TABLE_CELL_CENTER = '.',
		TABLE_CELL_RIGHT  = '>',
		TABLE_CELL_TOP    = '-',
		TABLE_CELL_MIDDLE = '=',
		TABLE_CELL_BOTTOM = '_',

		TABLE_CELL_ROWSPAN = '^',

		SEPARATOR = '* * *',
		LINE      = '----',

		IMG_SEP   = '|',
		IMG_ID    = ':',
		IMG_TITLE = '#',
		IMG_ALIGN = '-',
		IMG_SIZE  = '=',

		JUMP_ID    = ':';


	protected static
		$objCount = 0,

		/** How many sections should stay opened on a title start */
		$remainingSectionsByTitle = array(
			self::TITLE_1 => 0,
			self::TITLE_2 => 1,
			self::TITLE_3 => 2,
			self::TITLE_4 => 3,
			self::TITLE_5 => 4,
		);


	protected
		/* block elements */
		$sectionElement        = 'section',
		$epigraphElement       = 'epigraph',
		$dedicationElement     = 'dedication',
		$citeElement           = 'cite',
		$noticeElement         = 'notice',
		$annotationElement     = 'annotation',
		$infoblockElement      = 'information',
		$poemElement           = 'poem',
		$preformattedElement   = 'pre',
		$tableElement          = 'table',
		$paragraphElement      = 'p',
		$authorElement         = 'author',
		$dateElement           = 'date',
		$emptyLineElement      = 'empty-line',
		$separatorElement      = 'separator',
		$subheaderElement      = 'subheader',
		$poemHeaderElement     = 'title',
		$titleElement          = 'title',
		$footnoteElement       = 'footnote',
		$footnotesElement      = 'footnotes',
		$stylesheetElement     = 'stylesheet',
		$stylesheetAttributes  = array('type' => 'text/css'),

		/* inline elements */
		$imageElement          = 'image',
		$superscriptElement    = 'sup',
		$subscriptElement      = 'sub',
		$emphasisElement       = 'emphasis',
		$strongElement         = 'strong',
		$deletedElement        = 'del',
		$codeElement           = 'code',
		$blockStyleElement     = 'style',
		$inlineStyleElement    = 'style',
		$blockStyleAttribute   = 'name',
		$inlineStyleAttribute  = 'name',

		$alignments = array(
			self::TABLE_CELL_LEFT   => array('style' => 'text-align: left'),
			self::TABLE_CELL_CENTER => array('style' => 'text-align: center'),
			self::TABLE_CELL_RIGHT  => array('style' => 'text-align: right'),
			self::TABLE_CELL_TOP    => array('style' => 'vertical-align: top'),
			self::TABLE_CELL_MIDDLE => array('style' => 'vertical-align: middle'),
			self::TABLE_CELL_BOTTOM => array('style' => 'vertical-align: bottom'),
		),

		/** how many sections have we entered */
		$sectionsEntered = 0,

		/** how many foot notes have we entered */
		$fnEntered   = 0,

		/** auto number foot notes */
		$autoNumNote = true,

		/** current reference number */
		$curRef      = 0,
		/** current foot note number */
		$curFn       = 0,

		/** A string to prepend to the current paragraph content */
		$paragraphPrefix = '',
		/** A string to append to the current paragraph content */
		$paragraphSuffix = '',

		/** append this after each call of save() */
		$saveSuffix      = '',

		$debug           = false,

		/** all content is saved here */
		$_text           = array(
			'main'       => array(0 => ''),
			'footnotes'  => array(0 => ''),
		),

		/** points to the currently active content block */
		$_curBlock        = 'main',
		/** points to the currently active content subblock */
		$_curSubBlock     = 0,

		/** pointers to previously active content blocks */
		$_prevBlocks      = array(),

		/** notes container */
		$_notes                = array(),
		/** seen references for which no note is found */
		$_refsWaiting          = array(
			0 => null, // reserved for the eventual note concerning the text title
		),
		/** curently active note index (from the _notes array) */
		$_curNoteIndex         = 0,

		/** Prefix used for internal linking */
		$internalLinkTarget = '',

		/**
		 * current block ID used for internal linking
		 * input format:
		 *   :glava_10
		 */
		$_curJumpId = null;


	private
		/** save here the empty lines */
		$_emptyLineBuffer = '',

		/** save here old paragraph elements in case of overwriting */
		$_oldParagraphElements  = array(),

		/** save here old elements in case of overwriting */
		$_oldElements  = array(),

		/** should an empty line be saved */
		$_acceptEmptyLine    = false,

		/** are we in a epigraph */
		$_inEpigraph         = false,
		/** how many epigraphs do we have entered */
		$_epigraphsEntered   = 0,
		/** are we in a dedication */
		$_inDedication       = false,
		/** are we in a poem */
		$_inPoem             = false,
		/** how many poems do we have entered */
		$_poemsEntered       = 0,
		/** are we in a cite */
		$_inCite             = false,
		/** are we in a foot note */
		$_inNote             = false,

		$_inBlockImage       = false,

		$_inAnnotation       = false,

		$_inInfoblock        = false,

		$_newLineOutput      = self::EOL,

		/** have we processed the end of the text */
		$_textHasEnded       = false,

		/** Sfblib_LineReader */
		$_reader             = null;


	public function __construct($file, $imgDir = 'img')
	{
		self::$objCount++;

		$this->out = new Sfblib_XmlElement;

		$this->imgDir = rtrim($imgDir, '/');
		$this->curImgNr = 0;
		$this->putLineId = false; // not used currently

		$reStart = '(?<=[\s([„«>])';
		$reEnd = '(?![\w\d])';
		$e = $this->emphasisElement;
		$s = $this->strongElement;

		$this->patterns = array(
		"/({$reStart}___|^___)(.+)___{$reEnd}/U" => "<$s><$e>$2</$e></$s>",
		"/({$reStart}__|^__)(.+)__{$reEnd}/U"    => "<$s>$2</$s>",
		"/({$reStart}_|^_)(.+)_{$reEnd}/U"       => "<$e>$2</$e>",

		'/{m ([^}]+)}/'  => "<$this->inlineStyleElement $this->inlineStyleAttribute=\"$1\">",

		'/\[\[(.+)\|(.+)\]\]/U' => '<a href="$1" title="$1 — $2">$2</a>',
		'!(?<=[\s>])(https?://[^])\s<*]+[^])\s<*,.])!e' => "\$this->doExternLink('$1')",

		'/{img:([^'.self::IMG_SEP.'}]+)([^}]*)}/e' => "\$this->doImage('$1', '$2')",

		// foot notes
		'/(?<=[^\s\\\\(])(\*+)(\d*)/e' => "\$this->getRef('$1', '$2')",
		#'/&([^;]) /' => '&amp;$1 ',

		// internal links
		'%{#([^}]+)}([^{]+){/#}%e' => "\$this->doInternalLink('$1', '$2')",
		'%{#([^}]+)}%e' => "\$this->doInternalLink('$1')",
		);

		$this->replPairs = array(
			"\t"     => '        ', // eight nbspaces
			'`'      => '&#768;', // ударение, гравис (тежко, твърдо)
			'´'      => '&#769;', // ударение, акут (остро, меко)

			'{sup}'  => "<$this->superscriptElement>",
			'{/sup}' => "</$this->superscriptElement>",
			'{sub}'  => "<$this->subscriptElement>",
			'{/sub}' => "</$this->subscriptElement>",
			'{e}'    => "<$this->emphasisElement>",
			'{/e}'   => "</$this->emphasisElement>",
			'{s}'    => "<$this->strongElement>",
			'{/s}'   => "</$this->strongElement>",
			'{del}'  => "<$this->deletedElement>",
			'{/del}' => "</$this->deletedElement>",
			'{pre}'  => "<$this->codeElement>",
			'{/pre}' => "</$this->codeElement>",
			'{/m}' => "</$this->inlineStyleElement>",

			// escape sequences
			'\*'    => '*',
			'\\\\'  => '\\',
			'\{'    => '{',
			'\}'    => '}',
			'\_'    => '_',
		);

		if ( is_readable($file) ) {
			$this->_reader = new Sfblib_FileLineReader($file);
		} else {
			$this->_reader = new Sfblib_StringLineReader($file);
		}

		$this->lcmd = $this->ltext = '';
		$this->startpos = 0;
		$this->linecnt = 0;
		$this->maxlinecnt = 100000;
		$this->hasNextLine = false;
	}


	public static function setObjectCount($cnt)
	{
		self::$objCount = $cnt;
	}

	public static function getObjectCount()
	{
		return self::$objCount;
	}

	/**
	* Return a unique number across all converter objects
	*/
	public static function getUniqueObjectNr($nr)
	{
		return self::createNoteIdSuffix(self::$objCount, $nr);
	}


	public static function createNoteIdSuffix($objCount, $noteNr)
	{
		return $objCount . '-' . $noteNr;
	}

	public static function addMissingCommandDelimiters($content)
	{
		$fixedContent = '';
		$commands = array(
			self::HEADER,
			self::POEM_HEADER,
			self::TABLE_HEADER,
			self::SUBHEADER,
			self::TITLE_1,
			self::TITLE_2,
			self::TITLE_3,
			self::TITLE_4,
			self::TITLE_5,
			self::ANNO_S,
			self::INFO_S,
			self::DEDICATION_S,
			self::EPIGRAPH_S,
			self::NOTICE_S,
			self::POEM_S,
			self::CITE_S,
			self::PREFORMATTED_S,
			self::TABLE_S,
			self::STYLE_S,
			self::RAW_S,
			self::NOTICE_OL,
			self::PREFORMATTED_OL,
			self::AUTHOR_OL,
			self::DATE_OL,
			self::PARAGRAPH,
		);
		$endCommands = array(
			self::ANNO_E,
			self::INFO_E,
			self::DEDICATION_E,
			self::EPIGRAPH_E,
			self::NOTICE_E,
			self::POEM_E,
			self::CITE_E,
			self::PREFORMATTED_E,
			self::TABLE_E,
			self::STYLE_E,
			self::RAW_E,
		);
		$delim = self::CMD_DELIM;
		foreach (explode(self::EOL, $content) as $line) {
			if ($line === '' || strpos($line, $delim) !== false || in_array($line, $endCommands)) {
				$fixedContent .= $line . self::EOL;
				continue;
			}
			if (strpos($line, ' ') === false) {
				$fixedContent .= $delim . $line . self::EOL;
				continue;
			}
			list($command, $rest) = explode(' ', $line, 2);
			if (in_array($command, $commands)) {
				$fixedContent .= $command . $delim . $rest . self::EOL;
				continue;
			}
			if ($command[0] != self::JUMP_ID) {
				$fixedContent .= $delim . $command . ' ' . $rest . self::EOL;
				continue;
			}
		}
		return rtrim($fixedContent) . self::EOL;
	}

	/** TODO remove */
	public function setCurRef($nr)
	{
		$this->curRef = $nr;
	}


	public function convert()
	{
		if ( ! $this->_reader ) {
			return $this;
		}
		$this->kpatterns = array_keys($this->patterns);
		$this->vpatterns = array_values($this->patterns);

		$this->_reader->setStartPosition($this->startpos);
		while ( $this->nextLine() !== false ) {
			$this->doText();
		}
		return $this;
	}


	public function output()
	{
		print $this->getContent();
	}


	public function saveFile($filename)
	{
		myfile_put_contents($filename, $this->getContent());
	}


	/**
	* TODO remove
	*/
	public function content($withNotes = true, $plainNotes = true)
	{
		return $this->getText() . ($withNotes ? $this->getNotes($plainNotes ? 0 : 1) : '');
	}


	public function getContent()
	{
		return $this->getText() . $this->getNotes(1);
	}


	public function getText()
	{
		return $this->getContentBlock('main');
	}


	public function getNotes($type = 0)
	{
		$footnotes = $this->getNotesBlock();

		if ( empty($footnotes) ) {
			return '';
		}

		return $this->out->xmlElement($this->footnotesElement, $footnotes);
	}


	protected function getNotesBlock()
	{
		$footnotes = $this->getContentBlock('footnotes', -1);
		ksort($footnotes);

		return implode('', $footnotes);
	}

	public function setSaveSuffix($suffix)
	{
		$this->saveSuffix = $suffix;
	}


	public function enablePrettyOutput()
	{
		$this->_newLineOutput = self::EOL;
	}
	public function disablePrettyOutput()
	{
		$this->_newLineOutput = '';
	}

	public function hasPrettyOutput()
	{
		return $this->_newLineOutput != '';
	}

	public function getEol()
	{
		return $this->_newLineOutput;
	}


	/**
	* Tells whether there is a foot note without corresponding reference.
	* Such a foot note should refer to the text title.
	*/
	public function hasTitleNote()
	{
		if ( ! $this->_reader ) {
			return null;
		}
		$line = $this->_reader->getFirstLine();
		return strpos($line, self::CMD_DELIM . self::NOTE_START) === 0;
	}


	public function addPattern($pattern, $repl)
	{
		$this->replPairs[$pattern] = $repl;
		return $this;
	}

	public function addRegExpPattern($pattern, $repl)
	{
		$this->patterns[$pattern] = $repl;
		return $this;
	}

	public function rmPattern($pattern)
	{
		unset($this->replPairs[$pattern]);
		return $this;
	}

	public function rmRegExpPattern($pattern)
	{
		unset($this->patterns[$pattern]);
		return $this;
	}


	public function hasCustomStyles()
	{
		$content = $this->getContentBlock('main') . $this->getNotesBlock();

		$has = strpos($content, "<$this->blockStyleElement") !== false;
		if ($this->inlineStyleElement != $this->blockStyleElement && ! $has) {
			$has = strpos($content, "<$this->inlineStyleElement") !== false;
		}

		return $has;
	}


	/**
	* TODO catch infinite loops
	* @param $canMarkEnd   Sometimes we read several lines in a buffer.
	*                      If false, we may be at the end, but our buffer
	*                      still holds lines to be processed.
	*/
	protected function nextLine($canMarkEnd = true)
	{
		if ($this->hasNextLine) {
			$this->hasNextLine = false;
			return $this->line;
		}

		$rawLine = $this->_reader->getNextLine();

		// ВАЖНО: с > се показва и следващото заглавие
		// с >= има някакъв проблем в края (напр. при blockquote)
		if ( $this->linecnt >= $this->maxlinecnt || $rawLine === false ) {
			$this->lcmd = $this->ltext = $this->line = null;
			if ( $canMarkEnd && ! $this->_textHasEnded ) {
				$this->_curBlock = 'main';
				$this->_curSubBlock = 0;
				$this->flushEmptyLineBuffer();
				$this->doTextEnd();
				$this->_textHasEnded = true;
			}
			return false;
		}

		$this->linecnt++;
		$this->line = rtrim($rawLine);
		$parts = explode(self::CMD_DELIM, $this->line, 2);
		$this->lcmd = $parts[0];
		$this->ltext = isset($parts[1]) ? $parts[1] : '';

		if ($this->debug) {
			echo sprintf("\033[44;1m%6d: %s\033[0m\n", $this->linecnt, $this->line);
		}

		return $this->line;
	}


	protected function getLinesForMultiLineMarker($marker)
	{
		$lines = array();
		do {
			$lines[] = $this->ltext;
			$this->nextLine(false);
		} while ( $this->lcmd == $marker );

		// we have read the next non-marker line, make sure this is known
		$this->hasNextLine = true;

		return $lines;
	}


	/*************************************************************************/


	/**
	* body (image?, title?, epigraph*, section+)
	*
	* section (title?, epigraph*, image?, annotation?,
	*		( (section+) |
	*	 	((p | poem | subtitle | cite | empty-line | table),
	*			(p | image | poem | subtitle | cite | empty-line | table)*)))
	*		id ID #IMPLIED
	*/
	protected function doText()
	{
		$this->preDoText();
		switch ($this->lcmd) {
			case self::PARAGRAPH:       $this->doParagraph();        break;
			case self::TITLE_1:
			case self::TITLE_2:
			case self::TITLE_3:
			case self::TITLE_4:
			case self::TITLE_5:         $this->doTitle($this->lcmd); break;
			case self::DEDICATION_S:    $this->doDedication();       break;
			case self::EPIGRAPH_S:      $this->doEpigraph();         break;
			case self::NOTICE_S:        $this->doNotice();           break;
			case self::ANNO_S:          $this->doAnnotation();       break;
			case self::INFO_S:          $this->doInfoblock();        break;
			case self::POEM_S:          $this->doPoem();             break;
			case self::CITE_S:          $this->doCite();             break;
			case self::PREFORMATTED_S:  $this->doPreformatted();     break;
			case self::TABLE_S:         $this->doTable();            break;
			case self::NOTICE_OL:       $this->doNoticeOl();         break;
			case self::PREFORMATTED_OL: $this->doPreformattedOl();   break;
			case self::SUBHEADER:       $this->doSubheader();        break;
			case self::AUTHOR_OL:       $this->doAuthor();           break;
			case self::DATE_OL:         $this->doDate();             break;
			case self::HEADER:          $this->doHeader();           break;
			case self::STYLE_S:         $this->doStyle();            break;
			case self::RAW_S:           $this->doRaw();              break;
			case self::EMPTYLINE:       $this->doEmptyLine();        break;
			default:                    $this->doUnknownContent();
		}
		$this->postDoText();
	}


	protected function preDoText()
	{
	}

	protected function postDoText()
	{
	}

	protected function doTextEnd()
	{
		$this->closeSections();
	}


	/*************************************************************************/


	protected function openSectionIfNone()
	{
		if ( ! $this->isSectionOpened() ) {
			$this->openSection();
		}
	}


	protected function closeSections()
	{
		while ( $this->isSectionOpened() ) {
			$this->closeSection();
		}
	}


	/**
	* Close some of the opened sections according to a given title level.
	*
	* By level X close all except X-1 sections.
	*
	* @param $marker  A title marker (self::TITLE_X)
	*/
	protected function closeSectionsForTitle($marker)
	{
		while ( $this->areSectionsToCloseForTitle($marker) ) {
			$this->closeSection();
		}
	}


	protected $sectionAttributes = array();
	protected function openSection($id = null)
	{
		$attrs = $id ? array('id' => $id) : array();
		$this->saveStartTag($this->sectionElement, $attrs + $this->sectionAttributes);
		$this->sectionsEntered++;
	}

	protected function closeSection()
	{
		$this->saveEndTag($this->sectionElement);
		$this->sectionsEntered--;
	}


	protected function isSectionOpened()
	{
		return $this->sectionsEntered > 0;
	}


	/**
	* Check if there are sections to be closed on a title start.
	*
	* @param $marker  A title marker (self::TITLE_X)
	*/
	protected function areSectionsToCloseForTitle($marker)
	{
		return self::$remainingSectionsByTitle[$marker] < $this->sectionsEntered;
	}


	/*************************************************************************/


	protected function doHeader()
	{
		$this->flushEmptyLineBuffer();
		$this->doHeaderStart();

		$header = array();
		do {
			$header[] = $this->doInlineElements($this->ltext);
			$this->nextLine();
		} while ( $this->lcmd == self::HEADER );

		// we have read the next non-header line, make sure this is known
		$this->hasNextLine = true;

		$this->inHeader($header);

		$this->disableEmptyLines();

		$this->doHeaderEnd();
	}

	/**
	* @param $headerLines  Header lines
	*/
	protected function inHeader($headerLines)
	{
		$text = $this->getHeaderText($headerLines);
		if ( ! empty($text) ) {
			$this->save( $this->out->xmlElement($this->titleElement, $text) );
		}
	}

	protected function doHeaderStart()
	{
	}

	protected function doHeaderEnd()
	{
	}

	protected function getHeaderText($lines)
	{
		$text = '';
		foreach ($lines as $line) {
			if ( ! empty($line) ) {
				$text .= $this->out->xmlElement($this->paragraphElement, $line);
			}
		}
		return $text;
	}


	/*************************************************************************/


	/**
	* Title processing
	*
	* title (p | empty-line)*
	*
	*/
	protected function doTitle($marker)
	{
		$this->flushEmptyLineBuffer();

		$title = array( $this->doInlineElements($this->ltext) );

		$this->nextLine();
		while ( $this->lcmd == $marker ) {
			$title[] = $this->doInlineElements($this->ltext);
			$this->nextLine();
		}
		// we have read the next non-title line, make sure this is known
		$this->hasNextLine = true;

		$this->doTitleStart($marker, $this->generateInternalId($title));

		$this->inTitle($title, $marker);

		$this->disableEmptyLines();

		$this->doTitleEnd($marker);
	}


	protected function generateInternalId($name, $unique = true)
	{
		if (is_array($name)) {
			$name = implode('-', $name);
		}
		return 'l-' . $this->out->getAnchorName($name, $unique);
	}


	protected function inTitle($titleLines, $marker)
	{
		$elm = $this->titleElement . strlen($marker);
		$this->save( $this->out->xmlElement($elm, implode('. ', $titleLines)) );
	}


	protected function doTitleStart($marker, $id = null)
	{
		$this->closeSectionsForTitle($marker);
		$this->openSection($id);
	}

	protected function doTitleEnd($marker)
	{
	}


	/*************************************************************************/


	/**
	* Epigraph processing
	*/
	protected function doEpigraph()
	{
		$this->_inEpigraph = $this->linecnt;
		$this->flushEmptyLineBuffer();

		$this->doEpigraphStart();
		$this->checkForParagraphOnBlockStart();

		do {
			$this->nextLine();
			$this->inEpigraph();
		} while ( $this->isInBlock(self::EPIGRAPH_E) );

		$this->fixHasNextLine(self::EPIGRAPH_E);

		$this->lcmd = '';
		$this->disableEmptyLines();
		$this->doEpigraphEnd();
		$this->_inEpigraph = false;
	}

	/**
	* epigraph ((p | poem | cite | empty-line)*, text-author*)
	*/
	protected function inEpigraph()
	{
		switch ($this->lcmd) {
			case self::PARAGRAPH:       $this->doParagraph();           break;
			case self::POEM_S:          $this->doPoem();                break;
			case self::CITE_S:          $this->doCite();                break;
			case self::AUTHOR_OL:       $this->doAuthor();              break;
			case self::SUBHEADER:       $this->doSubheader();           break;
			case self::NOTICE_S:        $this->doNotice();              break;
			case self::NOTICE_OL:       $this->doNoticeOl();            break;
			case self::PREFORMATTED_S:  $this->doPreformatted();        break;
			case self::PREFORMATTED_OL: $this->doPreformattedOl();      break;
			case self::DATE_OL:         $this->doDate();                break;
			case self::STYLE_S:         $this->doStyle();               break;
			case self::EPIGRAPH_E:                                      break;
			default:                    $this->doUnknownContent();
		}
	}

	protected function doEpigraphStart()
	{
		$this->saveStartTag($this->epigraphElement);
	}

	protected function doEpigraphEnd()
	{
		$this->saveEndTag($this->epigraphElement);
	}

	protected function isInEpigraph()
	{
		return $this->_inEpigraph;
	}

	protected function isAtEpigraphEnd()
	{
		return $this->lcmd == self::EPIGRAPH_E;
	}


	protected function isInMainEpigraphBody()
	{
		return $this->isInEpigraph()
			&& ! $this->isInEpigraphPoem()
			&& ! $this->isInEpigraphCite()
			&& ! $this->isInEpigraphNote();
	}

	protected function isInEpigraphCite()
	{
		$e = $this->isInEpigraph();
		$c = $this->isInCite();
		return $e && $c && $e < $c;
	}

	protected function isInEpigraphNote()
	{
		$e = $this->isInEpigraph();
		$n = $this->isInNote();
		return $e && $n && $e < $n;
	}


	/*************************************************************************/


	/**
	* Dedication processing
	*/
	protected function doDedication()
	{
		$this->_inDedication = $this->linecnt;
		$this->flushEmptyLineBuffer();

		$this->doDedicationStart();
		$this->checkForParagraphOnBlockStart();

		do {
			$this->nextLine();
			$this->inDedication();
		} while ( $this->isInBlock(self::DEDICATION_E) );

		$this->fixHasNextLine(self::DEDICATION_E);

		$this->lcmd = '';
		$this->disableEmptyLines();
		$this->doDedicationEnd();
		$this->_inDedication = false;
	}

	/**
	* As epigraph
	*/
	protected function inDedication()
	{
		switch ($this->lcmd) {
			case self::PARAGRAPH:       $this->doParagraph();           break;
			case self::POEM_S:          $this->doPoem();                break;
			case self::CITE_S:          $this->doCite();                break;
			case self::AUTHOR_OL:       $this->doAuthor();              break;
			case self::SUBHEADER:       $this->doSubheader();           break;
			case self::NOTICE_S:        $this->doNotice();              break;
			case self::NOTICE_OL:       $this->doNoticeOl();            break;
			case self::PREFORMATTED_S:  $this->doPreformatted();        break;
			case self::PREFORMATTED_OL: $this->doPreformattedOl();      break;
			case self::DATE_OL:         $this->doDate();                break;
			case self::STYLE_S:         $this->doStyle();               break;
			case self::DEDICATION_E:                                    break;
			default:                    $this->doUnknownContent();
		}
	}

	protected function doDedicationStart()
	{
		$this->saveStartTag($this->dedicationElement);
	}

	protected function doDedicationEnd()
	{
		$this->saveEndTag($this->dedicationElement);
	}

	protected function isInDedication()
	{
		return $this->_inDedication;
	}

	protected function isAtDedicationEnd()
	{
		return $this->lcmd == self::DEDICATION_E;
	}

	protected function isInMainDedicationBody()
	{
		return $this->isInDedication()
			&& ! $this->isInDedicationPoem()
			&& ! $this->isInDedicationCite()
			&& ! $this->isInDedicationNote();
	}

	protected function isInDedicationCite()
	{
		$d = $this->isInDedication();
		$c = $this->isInCite();
		return $d && $c && $d < $c;
	}

	protected function isInDedicationNote()
	{
		$d = $this->isInDedication();
		$n = $this->isInNote();
		return $d && $n && $d < $n;
	}


	/*************************************************************************/


	/**
	* Notice processing
	*/
	protected function doNotice()
	{
		$this->doNoticeStart();
		$this->checkForParagraphOnBlockStart();

		do {
			$this->nextLine(false);
			$this->inNotice();
		} while ( $this->isInBlock(self::NOTICE_E) );

		$this->lcmd = '';
		$this->enableEmptyLines();
		$this->doNoticeEnd();
	}

	protected function inNotice()
	{
		switch ($this->lcmd) {
			case self::PARAGRAPH:       $this->doParagraph();           break;
			case self::STYLE_S:         $this->doStyle();               break;
			case self::NOTICE_E:                                        break;
			default:                    $this->doUnknownContent();
		}
	}

	protected function doNoticeStart()
	{
		$this->saveStartTag($this->noticeElement);
	}

	protected function doNoticeEnd()
	{
		$this->saveEndTag($this->noticeElement);
	}

	protected function doNoticeOl()
	{
		$this->doNoticeStart();
		$this->doParagraph();
		$this->doNoticeEnd();
	}


	/*************************************************************************/


	/**
	* Annotation processing
	*/
	protected function doAnnotation()
	{
		$this->_inAnnotation = true;
		$this->flushEmptyLineBuffer();

		$this->doAnnotationStart();
		$this->checkForParagraphOnBlockStart();

		do {
			$this->nextLine();
			$this->inAnnotation();
		} while ( $this->isInBlock(self::ANNO_E) );

		$this->fixHasNextLine(self::ANNO_E);

		$this->lcmd = '';
		$this->disableEmptyLines();
		$this->doAnnotationEnd();
		$this->_inAnnotation = false;
	}

	/**
	* annotation (p | poem | cite | subtitle | table | empty-line)*
	* 	id ID #IMPLIED
	*/
	protected function inAnnotation()
	{
		switch ($this->lcmd) {
			case self::PARAGRAPH:       $this->doParagraph();           break;
			case self::POEM_S:          $this->doPoem();                break;
			case self::CITE_S:          $this->doCite();                break;
			case self::AUTHOR_OL:       $this->doAuthor();              break;
			case self::SUBHEADER:       $this->doSubheader();           break;
			case self::NOTICE_S:        $this->doNotice();              break;
			case self::NOTICE_OL:       $this->doNoticeOl();            break;
			case self::PREFORMATTED_S:  $this->doPreformatted();        break;
			case self::PREFORMATTED_OL: $this->doPreformattedOl();      break;
			case self::DATE_OL:         $this->doDate();                break;
			case self::STYLE_S:         $this->doStyle();               break;
			case self::ANNO_E:                                          break;
			default:                    $this->doUnknownContent();
		}
	}

	protected function doAnnotationStart()
	{
		$this->saveStartTag($this->annotationElement);
	}

	protected function doAnnotationEnd()
	{
		$this->saveEndTag($this->annotationElement);
	}

	protected function isInAnnotation()
	{
		return $this->_inAnnotation;
	}

	protected function isAtAnnotationEnd()
	{
		return $this->lcmd == self::ANNO_E;
	}


	/*************************************************************************/


	/**
	* Infoblock processing
	*/
	protected function doInfoblock()
	{
		$this->_inInfoblock = true;
		$this->flushEmptyLineBuffer();

		$this->doInfoblockStart();
		$this->checkForParagraphOnBlockStart();

		do {
			$this->nextLine(false);
			$this->inInfoblock();
		} while ( $this->isInBlock(self::INFO_E) );

		$this->lcmd = '';
		$this->disableEmptyLines();
		$this->doInfoblockEnd();
		$this->_inInfoblock = false;
	}

	protected function inInfoblock()
	{
		switch ($this->lcmd) {
			case self::PARAGRAPH:       $this->doParagraph();           break;
			case self::TITLE_1:
			case self::TITLE_2:
			case self::TITLE_3:
			case self::TITLE_4:
			case self::TITLE_5:         $this->doTitle($this->lcmd); break;
			case self::POEM_S:          $this->doPoem();                break;
			case self::CITE_S:          $this->doCite();                break;
			case self::AUTHOR_OL:       $this->doAuthor();              break;
			case self::SUBHEADER:       $this->doSubheader();           break;
			case self::NOTICE_S:        $this->doNotice();              break;
			case self::NOTICE_OL:       $this->doNoticeOl();            break;
			case self::PREFORMATTED_S:  $this->doPreformatted();        break;
			case self::PREFORMATTED_OL: $this->doPreformattedOl();      break;
			case self::DATE_OL:         $this->doDate();                break;
			case self::STYLE_S:         $this->doStyle();               break;
			case self::INFO_E:                                          break;
			default:                    $this->doUnknownContent();
		}
	}

	protected function doInfoblockStart()
	{
		$this->saveStartTag($this->infoblockElement);
	}

	protected function doInfoblockEnd()
	{
		$this->saveEndTag($this->infoblockElement);
	}

	protected function isInInfoblock()
	{
		return $this->_inInfoblock;
	}

	protected function isAtInfoblockEnd()
	{
		return $this->lcmd == self::INFO_E;
	}


	/*************************************************************************/


	/**
	* Poem processing
	*/
	protected function doPoem()
	{
		$this->_inPoem = $this->linecnt;
		$this->_poemsEntered++;
		$this->saveEmptyLineBuffer();
		$this->disableEmptyLines();
		$this->doPoemStart();
		$this->checkForParagraphOnBlockStart();

		do {
			$this->nextLine(false);
			$this->inPoem();
		} while ( $this->isInBlock(self::POEM_E) );

		$this->fixHasNextLine(self::POEM_E);

		$this->lcmd = '';
		$this->doPoemEnd();
		$this->enableEmptyLines();
		if ( ! --$this->_poemsEntered ) {
			$this->_inPoem = false;
		}
	}

	/**
	* poem (title?, epigraph*, stanza+, text-author*, date?)
	* stanza (title?, subtitle?, v+)
	* v (#PCDATA | strong | emphasis | style | a | strikethrough | sub | sup | code | image)*
	*
	* TODO handle poem in a foot note within a poem
	*/
	protected function inPoem()
	{
		switch ($this->lcmd) {
			case self::DEDICATION_S:    $this->doDedication();      break;
			case self::EPIGRAPH_S:      $this->doEpigraph();        break;
			case self::PARAGRAPH:       $this->doParagraph();       break;
			case self::AUTHOR_OL:       $this->doAuthor();          break;
			case self::SUBHEADER:       $this->doSubheader();       break;
			case self::POEM_HEADER:     $this->doPoemHeader();      break;
			case self::DATE_OL:         $this->doDate();            break;
			case self::PREFORMATTED_S:  $this->doPreformatted();    break;
			case self::PREFORMATTED_OL: $this->doPreformattedOl();  break;
			case self::STYLE_S:         $this->doStyle();           break;
			case self::POEM_E:                                      break;
			// TODO handle poem in a foot note
			//case self::POEM_S:          $this->doPoem();            break;

			default:
				if ( $this->isAtVerseNumber() ) {
					$this->doVerseNumber();
				} else {
					$this->doUnknownContent();
				}
		}
	}

	protected function doPoemStart()
	{
		$this->saveStartTag($this->poemElement);
	}

	protected function doPoemEnd()
	{
		$this->saveEndTag($this->poemElement);
	}

	protected function isInPoem()
	{
		return $this->_inPoem;
	}

	protected function isAtPoemEnd()
	{
		return $this->lcmd == self::POEM_E;
	}

	protected function isInMainPoemBody()
	{
		return $this->isInPoem()
			&& ! $this->isInPoemEpigraph()
			&& ! $this->isInPoemDedication()
			&& ! $this->isInPoemNote();
	}

	/**
	* Are we in a poem within an epigraph
	*/
	protected function isInEpigraphPoem()
	{
		$e = $this->isInEpigraph();
		$p = $this->isInPoem();
		return  $e && $p && $e < $p;
	}

	/**
	* Are we in an epigraph within a poem
	*/
	protected function isInPoemEpigraph()
	{
		$e = $this->isInEpigraph();
		$p = $this->isInPoem();
		return  $e && $p && $e > $p;
	}


	/**
	* Are we in a poem within a dedication
	*/
	protected function isInDedicationPoem()
	{
		$d = $this->isInDedication();
		$p = $this->isInPoem();
		return  $d && $p && $d < $p;
	}

	/**
	* Are we in a dedication within a poem
	*/
	protected function isInPoemDedication()
	{
		$d = $this->isInDedication();
		$p = $this->isInPoem();
		return  $d && $p && $d > $p;
	}


	/**
	* Are we in a foot note within a poem
	*/
	protected function isInPoemNote()
	{
		$p = $this->isInPoem();
		$n = $this->isInNote();
		return  $p && $n && $p < $n;
	}


	protected function isAtVerseNumber()
	{
		return ! empty($this->lcmd) && preg_match('/^\d/', $this->lcmd);
	}


	protected function doVerseNumber()
	{
		$this->prepareVerseNumber();
		$this->doParagraph();
		$this->clearVerseNumber();
	}

	protected function prepareVerseNumber()
	{
		$this->paragraphSuffix = " [$this->lcmd]";
	}

	protected function clearVerseNumber()
	{
		$this->paragraphSuffix = '';
	}


	/*************************************************************************/


	/**
	* Processing of text with styles
	*/
	protected function doStyle()
	{
		$this->doStyleStart();

		do {
			$this->nextLine();
			$this->inStyle();
		} while ( $this->isInBlock(self::STYLE_E) );

		$this->fixHasNextLine(self::STYLE_E);

		$this->doStyleEnd();
		// a HACK to allow nested styles
		$this->lcmd = '';
	}


	protected function inStyle()
	{
		switch ($this->lcmd) {
			case self::PARAGRAPH:       $this->doParagraph();        break;
			case self::TITLE_1:
			case self::TITLE_2:
			case self::TITLE_3:
			case self::TITLE_4:
			case self::TITLE_5:         $this->doTitle($this->lcmd); break;
			case self::EPIGRAPH_S:      $this->doEpigraph();         break;
			case self::NOTICE_S:        $this->doNotice();           break;
			case self::POEM_S:          $this->doPoem();             break;
			case self::CITE_S:          $this->doCite();             break;
			case self::PREFORMATTED_S:  $this->doPreformatted();     break;
			case self::TABLE_S:         $this->doTable();            break;
			case self::NOTICE_OL:       $this->doNoticeOl();         break;
			case self::PREFORMATTED_OL: $this->doPreformattedOl();   break;
			case self::SUBHEADER:       $this->doSubheader();        break;
			case self::AUTHOR_OL:       $this->doAuthor();           break;
			case self::DATE_OL:         $this->doDate();             break;
			case self::HEADER:          $this->doHeader();           break;
			case self::EMPTYLINE:       $this->doEmptyLine();        break;
			case self::STYLE_S:         $this->doStyle();            break;
			case self::STYLE_E:                                      break;
			default:                    $this->doUnknownContent();
		}
	}

	protected function doStyleStart()
	{
		$this->saveStartTag($this->blockStyleElement, array($this->blockStyleAttribute => $this->ltext));
	}

	protected function doStyleEnd()
	{
		$this->saveEndTag($this->blockStyleElement);
	}


	/*************************************************************************/


	/**
	* Cite processing
	*/
	protected function doCite()
	{
		$this->_inCite = $this->linecnt;
		$this->saveEmptyLineBuffer();
		$this->enableEmptyLines();
		$this->doCiteStart();
		$this->checkForParagraphOnBlockStart();

		do {
			$this->nextLine(false);
			$this->inCite();
		} while ( $this->isInBlock(self::CITE_E) );

		$this->fixHasNextLine(self::CITE_E);

		$this->lcmd = '';
		$this->doCiteEnd();
		$this->_inCite = false;
	}

	/**
	* cite ((p | poem | empty-line | subtitle | table)*, text-author*)
	*/
	protected function inCite()
	{
		switch ($this->lcmd) {
			case self::PARAGRAPH:        $this->doParagraph();       break;
			case self::POEM_S:           $this->doPoem();            break;
			case self::AUTHOR_OL:        $this->doAuthor();          break;
			case self::SUBHEADER:        $this->doSubheader();       break;
			case self::PREFORMATTED_S:   $this->doPreformatted();    break;
			case self::PREFORMATTED_OL:  $this->doPreformattedOl();  break;
			case self::NOTICE_S:         $this->doNotice();          break;
			case self::NOTICE_OL:        $this->doNoticeOl();        break;
			case self::DATE_OL:          $this->doDate();            break;
			case self::TABLE_S:          $this->doTable();           break;
			case self::STYLE_S:          $this->doStyle();           break;
			case self::CITE_E:                                       break;
			default:                     $this->doUnknownContent();
		}
	}

	protected function doCiteStart()
	{
		$this->saveStartTag($this->citeElement);
	}

	protected function doCiteEnd()
	{
		$this->saveEndTag($this->citeElement);
	}

	protected function isInCite()
	{
		return $this->_inCite;
	}

	protected function isAtCiteEnd()
	{
		return $this->lcmd == self::CITE_E;
	}


	/*************************************************************************/


	/**
	* Preformatted processing
	*/
	protected function doPreformatted()
	{
		$this->saveEmptyLineBuffer();
		$this->doPreformattedStart();

		do {
			$this->nextLine(false);
			$this->inPreformatted();
		} while ( $this->isInBlock(self::PREFORMATTED_E) );

		$this->doPreformattedEnd();
	}

	protected function inPreformatted()
	{
		switch ($this->lcmd) {
			case self::PREFORMATTED_E: break;
			default:                   $this->savePreformatted($this->ltext);
		}
	}

	protected function doPreformattedStart()
	{
		$this->saveStartTag($this->preformattedElement);
	}

	protected function doPreformattedEnd()
	{
		$this->saveEndTag($this->preformattedElement);
	}


	protected function doPreformattedOl()
	{
		$this->doPreformattedStart();
		$this->savePreformatted($this->ltext);
		$this->doPreformattedEnd();
	}


	protected function savePreformatted($content)
	{
		$this->save($this->doInlineElements($content));
		//$this->save( htmlspecialchars($content) );
	}


	/*************************************************************************/


	/**
	* Paragraph processing
	*
	* p (#PCDATA | strong | emphasis | style | a | strikethrough | sub | sup | code | image)*
	* 	id ID #IMPLIED
	*/
	protected function doParagraph()
	{
		if ( $this->isEmptyLine() ) {
			if ($this->acceptsEmptyLines()) {
				$this->doEmptyLine();
			}
		}
		else if ($this->ltext == self::SEPARATOR) {
			$this->doSeparator();
		}
		else if ( $this->noteStarts() ) {
			$this->doNote();
		}
		else if ( $this->fnEntered && $this->noteEnds() ) {
			$this->inEndingNote();
		}
		else if ( $this->paragraphContainsBlockImage() ) {
			$this->doBlockImage();
		}
		else {
			$this->doParagraphReally();
		}
	}


	protected function doParagraphReally()
	{
		$this->saveEmptyLineBuffer();
		$this->enableEmptyLines();
		$this->doParagraphStart();
		$this->inParagraph();
		$this->doParagraphEnd();
	}


	protected function inParagraph()
	{
		$this->save($this->paragraphPrefix);
		$this->saveContent($this->ltext);
		$this->save($this->paragraphSuffix);
	}


	protected function doParagraphStart()
	{
		$this->saveStartTag($this->paragraphElement, array(
			'id' => 'p-'.$this->linecnt,
		));
	}

	protected function doParagraphEnd()
	{
		$this->saveEndTag($this->paragraphElement);
	}


	/*************************************************************************/


	/**
	* Author processing
	*/
	protected function doAuthor()
	{
		$this->saveEmptyLineBuffer();
		$this->doAuthorStart();
		$this->inAuthor( $this->getLinesForMultiLineMarker(self::AUTHOR_OL) );
		$this->doAuthorEnd();
		$this->enableEmptyLines();
	}


	protected function inAuthor($lines)
	{
		foreach ($lines as $line) {
			$this->doAuthorLineStart();
			$this->saveContent($line);
			$this->doAuthorLineEnd();
		}
	}

	protected function doAuthorStart()
	{
	}

	protected function doAuthorEnd()
	{
	}

	protected function doAuthorLineStart()
	{
		$this->saveStartTag($this->authorElement);
	}

	protected function doAuthorLineEnd()
	{
		$this->saveEndTag($this->authorElement);
	}


	/*************************************************************************/


	/**
	* Date/year processing
	*/
	protected function doDate()
	{
		$this->saveEmptyLineBuffer();
		$this->doDateStart();
		$this->inDate( $this->getLinesForMultiLineMarker(self::DATE_OL) );
		$this->doDateEnd();
		$this->enableEmptyLines();
	}

	protected function inDate($lines)
	{
		foreach ($lines as $line) {
			$this->doDateLineStart();
			$this->saveContent($line);
			$this->doDateLineEnd();
		}
	}

	protected function doDateStart()
	{
	}

	protected function doDateEnd()
	{
	}

	protected function doDateLineStart()
	{
		$this->saveStartTag($this->dateElement);
	}

	protected function doDateLineEnd()
	{
		$this->saveEndTag($this->dateElement);
	}

	protected function isAtDate()
	{
		return $this->lcmd == self::DATE_OL;
	}

	/*************************************************************************/


	/**
	* Subheader processing
	*/
	protected function doSubheader()
	{
		$this->flushEmptyLineBuffer();
		$lines = $this->getLinesForMultiLineMarker(self::SUBHEADER);
		$isMulti = count($lines) > 1;
		$this->doSubheaderStart($isMulti);
		$this->inSubheader($lines, $isMulti);
		$this->doSubheaderEnd($isMulti);
		$this->disableEmptyLines();
	}

	protected function inSubheader($lines, $isMulti)
	{
		foreach ($lines as $line) {
			$this->doSubheaderLineStart($isMulti, $line);
			$this->saveContent($line);
			$this->doSubheaderLineEnd($isMulti);
		}
	}

	protected function doSubheaderStart($isMulti)
	{
	}

	protected function doSubheaderEnd($isMulti)
	{
	}

	protected function doSubheaderLineStart($isMulti, $line)
	{
		$this->saveStartTag($this->subheaderElement);
	}

	protected function doSubheaderLineEnd($isMulti)
	{
		$this->saveEndTag($this->subheaderElement);
	}


	/*************************************************************************/


	/**
	* Poem header processing
	*/
	protected function doPoemHeader()
	{
		$this->doPoemHeaderStart();
		$this->inPoemHeader( $this->getLinesForMultiLineMarker(self::POEM_HEADER) );
		$this->doPoemHeaderEnd();
		$this->disableEmptyLines();
	}


	protected function inPoemHeader($lines)
	{
		foreach ($lines as $line) {
			$this->doPoemHeaderLineStart();
			$this->saveContent($line);
			$this->doPoemHeaderLineEnd();
		}
	}

	protected function doPoemHeaderStart()
	{
	}

	protected function doPoemHeaderEnd()
	{
	}

	protected function doPoemHeaderLineStart()
	{
		$this->saveStartTag($this->poemHeaderElement);
	}

	protected function doPoemHeaderLineEnd()
	{
		$this->saveEndTag($this->poemHeaderElement);
	}


	/*************************************************************************/


	/**
	* empty-line ANY
	*/
	protected function doEmptyLine()
	{
		$this->saveEmptyLine( $this->out->getEmptyTag($this->emptyLineElement) );
	}

	protected function saveEmptyLine($content)
	{
		$this->_emptyLineBuffer .= $content . $this->saveSuffix;
	}

	protected function saveEmptyLineBuffer()
	{
		$buffer = $this->flushEmptyLineBuffer();
		if ( ! empty($buffer) ) {
			$this->save($buffer);
		}
	}

	/**
	* Clear the empty line buffer and return its previous content
	*/
	protected function flushEmptyLineBuffer()
	{
		$buffer = $this->_emptyLineBuffer;
		$this->_emptyLineBuffer = '';
		return $buffer;
	}


	protected function acceptsEmptyLines()
	{
		return $this->_acceptEmptyLine;
	}

	protected function enableEmptyLines()
	{
		$this->_acceptEmptyLine = true;
	}

	protected function disableEmptyLines()
	{
		$this->_acceptEmptyLine = false;
	}


	/*************************************************************************/


	protected function doSeparator()
	{
		$this->flushEmptyLineBuffer();
		$this->inSeparator();
		$this->disableEmptyLines();
	}


	protected function inSeparator()
	{
		$this->saveStartTag($this->separatorElement);
		$this->save($this->ltext);
		$this->saveEndTag($this->separatorElement);
	}


	/*************************************************************************/


	/**
	* Raw processing
	*/
	protected function doRaw()
	{
		$this->doRawStart();

		do {
			$this->nextLine(false);
			$this->inRaw();
		} while ( $this->isInBlock(self::RAW_E) );

		$this->doRawEnd();
	}

	protected function inRaw()
	{
		switch ($this->lcmd) {
			case self::RAW_E:     break;
			default:              $this->saveRaw($this->line);
		}
	}

	protected function doRawStart()
	{
	}

	protected function doRawEnd()
	{
	}

	protected function saveRaw($line)
	{
		$this->save($line, true);
	}


	/*************************************************************************/


	/**
	* Foot note processing
	*/
	protected function doNote()
	{
		$this->_inNote = $this->linecnt;
		$this->preNoteStart();
		$this->doNoteStart();
		if ( $this->noteEnds() ) {
			$this->preNoteEnd();
			$this->removeNoteMarkersFromLine();
			$this->doParagraphReally();
			$this->doNoteEnd();
			$this->postNoteEnd();
			$this->_inNote = false;
		} else {
			$this->removeNoteStartMarkerFromLine();
			if ($this->ltext || $this->paragraphPrefix) {
				$this->doParagraphReally();
			}
			$this->postNoteStart();
		}
	}


	protected function inEndingNote()
	{
		$this->preNoteEnd();
		$this->removeNoteEndMarkerFromLine();
		if ($this->ltext || $this->paragraphSuffix) {
			$this->doParagraphReally();
		}
		$this->doNoteEnd();
		$this->postNoteEnd();
		$this->_inNote = false;
	}


	protected function doNoteStart()
	{
		$this->overwriteParagraphElement('p');
		$this->saveStartTag($this->footnoteElement, array(
			'id' => $this->getCurrentNoteId()
		));
	}

	protected function doNoteEnd()
	{
		$this->saveEndTag($this->footnoteElement);
		$this->revertParagraphElement();
	}

	protected function preNoteStart()
	{
		$this->fnEntered++;
		$this->saveEmptyLineBuffer();
		$this->updateCurFn();
		$this->enterContentBlock('footnotes', $this->_curNoteIndex);
	}

	protected function postNoteStart()
	{
	}

	protected function preNoteEnd()
	{
	}

	protected function postNoteEnd()
	{
		$this->fnEntered--;
		if ( ! $this->fnEntered ) {
			$this->leaveContentBlock('footnotes');
		}
	}

	protected function isInNote()
	{
		return $this->_inNote;
	}

	protected function noteStarts()
	{
		return substr($this->ltext, 0, strlen(self::NOTE_START)) == self::NOTE_START;
	}

	protected function noteEnds()
	{
		return substr($this->ltext, -strlen(self::NOTE_END) ) == self::NOTE_END;
	}

	protected function removeNoteStartMarkerFromLine()
	{
		$this->ltext = ltrim(preg_replace('/^' . self::NOTE_START_REGEXP . '/', '', $this->ltext));
	}

	protected function removeNoteEndMarkerFromLine()
	{
		$this->ltext = substr($this->ltext, 0, -strlen(self::NOTE_END) );
	}

	protected function removeNoteMarkersFromLine()
	{
		$this->removeNoteStartMarkerFromLine();
		$this->removeNoteEndMarkerFromLine();
	}


	/* TODO remove $this->curFn */
	protected function updateCurFn()
	{
		preg_match('/'. self::NOTE_START_REGEXP .'/', $this->ltext, $m);
		$noteMarker = $m[1] . $m[2];
		$noteNumber = $m[2];
		if ( $noteNumber === '' || $this->curFn >= $noteNumber ) {
			if ( $this->curRef == 0 ) {
				$this->curFn = 0; // this is a title footnote
				$this->_refsWaiting[0] = $noteMarker;
			} else {
				$this->curFn++;
			}
		} else {
			$this->curFn = $noteNumber;
		}

		foreach ($this->_refsWaiting as $i => $ref) {
			if ($noteMarker == $ref) {
				$this->_curNoteIndex = $i;
				$this->addContentBlock('footnotes', $this->_curNoteIndex);
				unset($this->_refsWaiting[$i]);
			}
		}
	}


	/**
	* Return a note link.
	* Called as a callback of a preg_replace.
	*/
	public function getRef($stars, $num)
	{
		$this->updateCurRef($num);
		$this->_refsWaiting[$this->curRef] = $stars . $num;

		return $this->getNoteLink($this->curRef);
	}


	protected function updateCurRef($num)
	{
		if ( $num === '' || $this->curRef >= $num ) {
			$this->curRef++;
		} else {
			$this->curRef = $num;
		}
	}


	public function getNoteLink($curReference)
	{
		return "[$curReference]";
	}


	/**
	* Return an element ID for the current foot note
	*/
	protected function getCurrentNoteId()
	{
		return self::getNoteId($this->_curNoteIndex/*$this->curFn*/);
	}

	/**
	* Return an element ID for a foot note
	*/
	protected static function getNoteId($nr)
	{
		return 'note_' . self::getNoteNr($nr);
	}

	/**
	* Return the current normalized note number
	*/
	protected function getCurrentNoteNr()
	{
		return self::getNoteNr($this->_curNoteIndex/*$this->curFn*/);
	}

	/**
	* Return a normalized note number
	*/
	protected static function getNoteNr($nr)
	{
		return self::getUniqueObjectNr($nr);
	}


	public static function removeNoteLinks($text)
	{
		return preg_replace('|<a.[^>]+>[^<]+</a>|', '', $text);
	}


	/*************************************************************************/


	/**
	* Table processing
	*/
	protected function doTable()
	{
		$this->saveEmptyLineBuffer();
		$this->doTableStart();

		do {
			$this->nextLine();
			$this->inTable();
		} while ( $this->isInBlock(self::TABLE_E) );

		$this->doTableEnd();
		$this->enableEmptyLines();
	}


	/**
	* table (tr)+
	* 	style   xs:string   optional
	* 	id   xs:ID   optional
	*
	* tr (th | td)+
	* 	align   alignType   optional   left
	*
	* td|th (#PCDATA | strong | emphasis | style | a | strikethrough | sub | sup | code | image)*
	*/
	protected function inTable()
	{
		$this->inHeaderRow = false;
		switch ($this->lcmd) {
			case self::TABLE_HEADER:
				$this->addTableCaption($this->ltext);
				break;

			case self::TABLE_HCELL:
				$this->inHeaderRow = true;
				// go to default
			default:
				$this->curTableRow++;
				$this->inTableRow();

			case self::TABLE_E: break;
		}
	}


	protected function doTableStart()
	{
		$this->tableData = array();
		$this->tableCaption = '';
		$this->curTableRow = -1;
		// here we keep track of spanned rows
		$this->spanRows = array();
	}


	protected function addTableCaption($text)
	{
		$this->tableCaption .= $text;
	}


	protected function doTableEnd()
	{
		$table = $this->out->simpleTable($this->tableCaption, $this->tableData);
		$this->save( $table );
	}


	protected function inTableRow()
	{
		// add missing leading marker
		if ( $this->ltext[0] != self::TABLE_CELL ) {
			$this->ltext = self::TABLE_CELL . $this->ltext;
		}
		// … and remove possible trailing one
		$this->ltext = rtrim($this->ltext, self::TABLE_CELL);
		// are we expecting a cell modifier for alignment, span, or header cell
		$expectModif = false;
		$cc = -1; // curent column index
		$row = array(); // contents of the cells

		$re = '!({img:[^}|]+\|[^}]+}|\[\[[^]|]+\|[^]|]+\]\])!';
		$placeholder = 'IN_TABLE_PLACEHOLDER';
		if (preg_match_all($re, $this->ltext, $savedContent)) {
			$savedContent = $savedContent[0];
			$this->ltext = preg_replace($re, $placeholder, $this->ltext);
		}

		for ($i = 0, $len = strlen($this->ltext); $i < $len; $i++) {
			$ch = $this->ltext[$i];
			if ( $expectModif ) {
				switch ( $ch ) {
					// alignment modifier
					case self::TABLE_CELL_LEFT:
					case self::TABLE_CELL_CENTER:
					case self::TABLE_CELL_RIGHT:
					case self::TABLE_CELL_TOP:
					case self::TABLE_CELL_MIDDLE:
					case self::TABLE_CELL_BOTTOM:
						foreach ($this->alignments[$ch] as $attr => $value) {
							$row[$cc][0][$attr] = isset( $row[$cc][0][$attr] )
								? $row[$cc][0][$attr] .'; '. $value
								: $value;
						}
						break;
					// header cell
					case self::TABLE_HCELL:
						$row[$cc][0]['type'] = 'header';
						break;
					// column span
					case self::TABLE_CELL:
						$cc++;
						$row[$cc] = $row[$cc - 1];
						// delete old cell
						unset( $row[$cc - 1] );
						Sfblib_Util::initOrIncArrayValue( $row[$cc][0], 'colspan', 2 );
						break;
					// row span
					case self::TABLE_CELL_ROWSPAN:
						if ( !isset( $this->spanRows[ $cc ] ) ) {
							// no spanning row till now, take previous one
							$this->spanRows[$cc] = $this->curTableRow - 1;
						}
						$sr = $this->spanRows[$cc];
						// will not be set if there is a previos row with column span
						// in such a case we must skip this column
						if ( isset( $this->tableData[$sr][$cc] ) ) {
							Sfblib_Util::initOrIncArrayValue(
								$this->tableData[$sr][$cc][0], 'rowspan', 2 );
						}
						// delete current cell
						unset( $row[$cc] );
						$expectModif = false;
						break;
					// cell content
					default:
						$row[$cc][1] .= $ch;
						$expectModif = false;
				}
				if ( $ch != self::TABLE_CELL_ROWSPAN ) {
					// clear row span
					unset( $this->spanRows[ $cc ] );
				}
			} else {
				if ( $ch == self::TABLE_CELL ) {
					// open a new cell
					$row[ ++$cc ] = $this->getEmptyTableCell();
					$expectModif = true;
				} else if ( isset( $row[$cc] ) ) {
					$row[$cc][1] .= $ch;
				}
			}
		}

		// convert raw content
		foreach ($row as $i => $cdata) {
			$cell = trim($cdata[1], ' ');
			while ( strpos($cell, $placeholder) !== false ) {
				$cell = preg_replace("!$placeholder!", array_shift($savedContent), $cell, 1);
			}
			$row[$i][1] = $this->doInlineElements($cell);
		}

		$this->tableData[ $this->curTableRow ] = $row;
	}


	protected function getEmptyTableCell()
	{
		$attributes = array();
		if ( $this->inHeaderRow ) {
			$attributes['type'] = 'header';
		}
		return array( $attributes, '' );
	}


	/*************************************************************************/


	/**
	* Image processing
	*
	* image (EMPTY)
	* 	xmlns:xlink CDATA       #FIXED "http://www.w3.org/1999/xlink"
	* 	xlink:type  CDATA       #FIXED "simple"
	* 	xlink:href  CDATA       #IMPLIED
	* 	alt         xs:string   optional
	* 	title       xs:string   optional
	* 	id          xs:ID       optional
	*
	* binary (#PCDATA)
	* 	content-type CDATA #REQUIRED
	* 	id           ID    #REQUIRED
	*/
	protected function doBlockImage()
	{
		$this->_inBlockImage = $this->linecnt;
		$this->doBlockImageStart();
		$this->doParagraphReally();
		$this->doBlockImageEnd();
		$this->_inBlockImage = false;
	}

	protected function doBlockImageStart()
	{
	}

	protected function doBlockImageEnd()
	{
	}

	protected function isInBlockImage()
	{
		return $this->_inBlockImage;
	}

	/**
	 *
	 * Called as a callback of a preg_replace.
	 */
	protected function doImage($name, $modifs)
	{
		$alt = $name;
		$title = $align = $size = '';
		$id = $this->getCurrentImageId();
		$imgsrc = $url = $this->imgDir . '/' . $name;

		$modifs = trim( $modifs, self::IMG_SEP . ' ' );
		foreach ( explode( self::IMG_SEP, $modifs ) as $modif ) {
			if ( empty( $modif ) ) {
				continue;
			}
			switch ( $modif[0] ) {
				case self::IMG_TITLE:
					$title = substr( $modif, 1 );
					break;
				case self::IMG_ID:
					$id = substr( $modif, 1 );
					break;
				case self::IMG_ALIGN:
					$align = substr( $modif, 1 );
					break;
				case self::IMG_SIZE:
					$imgsrc = $this->imgDir .'/thumb/'. $name;
					$size = substr( $modif, 1 );
					break;
				default:
					$alt = $modif;
			}
		}

		return $this->getImage($imgsrc, $id, $alt, $title, $url, $size, $align);
	}


	protected function getImage($src, $id, $alt, $title, $url, $size, $align)
	{
		return $this->out->xmlElement($this->imageElement, array(
			'id'    => $id,
			'src'   => $src,
			'alt'   => $alt,
			'title' => $title,
		));
	}

	protected function getCurrentImageId()
	{
		return 'img_' . self::getUniqueObjectNr( ++$this->curImgNr );
	}

	protected function isStandardImageId($id)
	{
		return strpos($id, 'img_') === 0;
	}



	/*************************************************************************/

	protected function doUnknownContent()
	{
		if ($this->hasJumpId()) {
			if ($this->ltext != '') {
				$this->doParagraph();
			}
			return;
		}
		$this->saveUnknownContent();
	}

	protected function saveUnknownContent()
	{
		echo "Unknown content at line $this->linecnt: $this->line\n";
		$this->saveContent($this->line);
	}

	protected function hasJumpId()
	{
		if ($this->lcmd[0] == self::JUMP_ID) {
			$this->_curJumpId = substr($this->lcmd, 1);
			return true;
		}
		return false;
	}

	/*************************************************************************/


	protected function saveStartTag($elm, $attrs = array())
	{
		if ( ! empty($elm) ) {
			if ($this->_curJumpId !== null) {
				if ( !isset($attrs['id'])) {
					$attrs['id'] = $this->generateInternalId($this->_curJumpId);
				}
				$this->_curJumpId = null;
			}
			$this->save( $this->out->getStartTag($elm, $attrs) );
		}
	}

	protected function saveEndTag($elm)
	{
		if ( ! empty($elm) ) {
			$this->save( $this->out->getEndTag($elm) );
		}
	}

	protected function saveEmptyTag($elm, $attrs = array())
	{
		if ( ! empty($elm) ) {
			$this->save( $this->out->getEmptyTag($elm, $attrs) );
		}
	}

	protected function saveElement($elm, $content, $attrs = array())
	{
		if ( ! empty($elm) ) {
			$this->save( $this->out->xmlElement($elm, $content, $attrs) );
		}
	}


	protected function saveContent($cont)
	{
		$this->save($this->doInlineElements($cont) . $this->saveSuffix);
	}


	protected function save($text, $forceEmpty = false)
	{
		if ( ! empty($text) || $forceEmpty ) {
			$this->_text[$this->_curBlock][$this->_curSubBlock] .= $text . $this->_newLineOutput;
		}
	}


	/**
	* Replace inline elements thru string and regular expression replacements
	*/
	protected function doInlineElements($s)
	{
		$s = $this->doInlineElementsEscape($s);
		$s = preg_replace($this->kpatterns, $this->vpatterns, $s);
		$s = strtr($s, $this->replPairs);

		return $s;
	}

	/** Escape some characters */
	protected function doInlineElementsEscape($s)
	{
		return htmlspecialchars($s, ENT_COMPAT, 'UTF-8');
	}



	/**
	* Generate an extern link.
	* Called as a callback of a preg_replace.
	* Escaping is already done by SfbConverter::doInlineElementsEscape()
	*/
	protected function doExternLink($href)
	{
		return $this->out->xmlElement('a', $href, array(
			'href'  => $href,
			'title' => $href,
		), false);
	}


	/**
	 * Generate an internal link
	 *
	 * @param string	$target	Link target
	 * @param string	$text	Link text
	 * @return string	An XML anchor element
	 */
	protected function doInternalLink($target, $text = null)
	{
		if ($text === null) {
			$target = rtrim($target, '/');
			if (strpos($target, '|') !== false) {
				list($target, $text) = explode('|', $target);
			} else {
				$text = $target;
			}
		}
		$target = $this->generateInternalId($target, false);
		return $this->doInternalLinkElement($target, $text);
	}

	protected function doInternalLinkElement($target, $text)
	{
		return $this->out->xmlElement('a', $text, array(
			'href'  => $this->internalLinkTarget . "#$target",
		), false);
	}


	public function setInternalLinkTarget($target)
	{
		$this->internalLinkTarget = $target;
	}

	/**
	* Sometimes a paragraph can be on the same line as the starting block marker.
	* If that is the case, ensure we process the paragraph.
	*/
	protected function checkForParagraphOnBlockStart()
	{
		if ( ! empty($this->ltext) ) {
			$this->doParagraph();
		}
	}


	/**
	* The current processing of multiline markers as "author" and "date"
	* sets $hasNextLine to true after the surrounding block has been read.
	* At this point though the buffer contains the block end marker, so
	* $hasNextLine should be really false, as we still do not have the next line.
	* So, revert $hasNextLine to false if $lcmd is equal to the given $endMarker.
	*
	* @param $endMarker  A block end marker
	*/
	protected function fixHasNextLine($endMarker)
	{
		if ( $this->lcmd == $endMarker ) {
			$this->hasNextLine = false;
		}
	}


	protected function isEmptyLine()
	{
		return empty($this->line);
	}


	protected function isEmptyTextLine()
	{
		return empty($this->ltext);
	}


	protected function isInBlock($blockEndMarker)
	{
		return $this->lcmd != $blockEndMarker && ! is_null($this->lcmd);
	}


	protected function paragraphContainsBlockImage()
	{
		return preg_match('/^\{img:[^}]+\}$/', $this->ltext);
	}

	protected function overwriteParagraphElement($newParagraphElement = '')
	{
		$this->_oldParagraphElements[] = $this->paragraphElement;
		$this->paragraphElement = $newParagraphElement;
	}

	protected function revertParagraphElement()
	{
		$this->paragraphElement = array_pop($this->_oldParagraphElements);
	}



	protected function saveTemp($key, $value)
	{
		if ( ! isset($this->_oldElements[$key]) ) {
			$this->_oldElements[$key] = array();
		}
		$this->_oldElements[$key][] = $value;
	}

	protected function getTemp($key)
	{
		return array_pop($this->_oldElements[$key]);
	}


	/*************************************************************************/


	protected function getContentBlock($block, $subBlock = 0)
	{
		if ($subBlock === 0) {
			return $this->_text[$block][0];
		}

		return $this->_text[$block];
	}

	protected function addContentBlock($block, $subBlock = 0)
	{
		$this->_text[$block][$subBlock] = '';
	}

	/**
	* Activate a new content block.
	* @param $newBlock  The new content block
	* @param $subBlock  ...
	*/
	protected function enterContentBlock($newBlock, $subBlock = 0)
	{
		$this->_prevBlocks[$newBlock] = $this->_curBlock;
		$this->_curBlock = $newBlock;
		$this->_curSubBlock = $subBlock;
	}

	/**
	* Leave the current content block and activate the previous one.
	* @param $key       This key should have been used by enterContentBlock()
	*/
	protected function leaveContentBlock($key = '')
	{
		$this->_curBlock = $this->_prevBlocks[$key];
		$this->_curSubBlock = 0;
	}

}


interface Sfblib_LineReader
{
	public function getNextLine();
	public function setStartPosition($pos);
	public function getFirstLine();
}


class Sfblib_FileLineReader implements Sfblib_LineReader
{

	protected $_handle = null;

	public function __construct($file)
	{
		if ( is_readable($file) ) {
			$this->_handle = fopen($file, 'r');
		} else {
			throw new Exception("$file is not readable");
		}
	}

	public function __destruct()
	{
		if ( $this->_handle ) {
			fclose( $this->_handle );
		}
	}

	public function getNextLine()
	{
		if ( feof($this->_handle) ) {
			return false;
		}

		return fgets($this->_handle);
	}

	public function getFirstLine()
	{
		$oldPos = ftell( $this->_handle );
		fseek( $this->_handle, 0 );
		$line = fgets($this->_handle);
		fseek( $this->_handle, $oldPos );
		return $line;
	}

	public function setStartPosition($pos)
	{
		fseek($this->_handle, $pos);
	}
}


class Sfblib_StringLineReader implements Sfblib_LineReader
{
	protected
		$_delim = "\n",
		$_lines;

	public function __construct($string)
	{
		$this->_lines = explode($this->_delim, $string);
	}

	public function getNextLine()
	{
		$c = current($this->_lines);
		next($this->_lines);
		return $c;
	}

	public function getFirstLine()
	{
		return $this->_lines[0];
	}

	public function setStartPosition($pos)
	{
		$dl = strlen($this->_delim);
		while ($pos > 0) {
			$pos -= strlen( $this->getNextLine() ) + $dl;
		}
	}
}
