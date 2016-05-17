<?php namespace Sfblib;

/**
* SFB to XHTML 1.0 converter
*/
class SfbToHtmlConverter extends SfbConverter {

	protected static
		$titleElements = array(
			self::TITLE_1 => 'h2',
			self::TITLE_2 => 'h3',
			self::TITLE_3 => 'h4',
			self::TITLE_4 => 'h5',
			self::TITLE_5 => 'h6',
		);

	protected
		/* block elements */
		$sectionElement        = 'div',
		$sectionAttributes     = array('class' => 'section'),
		$epigraphElement       = 'blockquote',
		$dedicationElement     = 'blockquote',
		$citeElement           = 'blockquote',
		$noticeElement         = 'div',
		$annotationElement     = 'fieldset',
		$infoblockElement      = 'fieldset',
		$poemElement           = 'blockquote',
		$poemCenterElement     = 'div',
		$preformattedElement   = 'pre',
		$tableElement          = 'table',
		$paragraphElement      = 'p',
		$authorElement         = 'div',
		$dateElement           = 'div',
		$emptyLineElement      = 'p',
		$separatorElement      = 'p',
		$subheaderElement      = 'h6',
		$poemHeaderElement     = 'h5',
		$titleElement          = 'h1',
		$footnoteElement       = 'div',
		$footnotesElement      = 'fieldset',
		$blockImageElement     = 'div',

		/* inline elements */
		$imageElement          = 'img',
		$superscriptElement    = 'sup',
		$subscriptElement      = 'sub',
		$emphasisElement       = 'em',
		$strongElement         = 'strong',
		$deletedElement        = 'del',
		$codeElement           = 'code',
		$blockStyleElement     = 'div',
		$inlineStyleElement    = 'span',
		$blockStyleAttribute   = 'class',
		$inlineStyleAttribute  = 'class',

		$breakLineElement      = 'br',
		$legendElement         = 'legend';

	/**
	 *
	 * @var array
	 */
	private $verseCnts;

	public function __construct($file, $imgDir = 'img') {
		parent::__construct($file, $imgDir);
	}


	public function getNotes($type = 0) {
		$footnotes = $this->getNotesBlock();

		if ( empty($footnotes) ) {
			return '';
		}

		switch ($type) {
			case 1:
				return $this->out->xmlElement($this->footnotesElement,
					$this->out->xmlElement($this->legendElement, 'Бележки') . $footnotes,
					array('class' => 'footnotes')
				);
			case 2:
				return '<hr />' . $footnotes;
			case 0:
			default:
				return $footnotes;
		}
	}


	/*************************************************************************/


	/**
	 * @param $lnes  Header lines
	 */
	protected function getHeaderText($lines) {
		return implode('<br />', $lines);
	}


	/*************************************************************************/


	protected function inTitle($titleParts, $marker) {
		$elm = self::$titleElements[$marker];
		$text = implode("<$this->breakLineElement />", $titleParts);
		if ( ! empty($text) ) {
			$heading = $this->out->xmlElement($elm, $text);
			$this->save($heading);
		}
	}


	protected function doEpigraphStart() {
		$this->saveStartTag($this->epigraphElement, array('class' => 'epigraph'));
	}


	protected function doDedicationStart() {
		$this->saveStartTag($this->dedicationElement, array('class' => 'dedication'));
	}


	protected function doNoticeStart() {
		$this->saveStartTag($this->noticeElement, array('class' => 'notice'));
	}


	protected function doAnnotationStart() {
		$this->saveStartTag($this->annotationElement, array('class' => 'annotation'));
		$this->saveElement($this->legendElement, 'Анотация');
	}


	protected function doInfoblockStart() {
		$this->saveStartTag($this->annotationElement, array('class' => 'infobox'));
		$this->saveElement($this->legendElement, 'Допълнителна информация');
	}


	protected function doPoemStart() {
		$this->saveStartTag($this->poemElement, array('class' => 'poem'));
	}



	protected function prepareVerseNumber() {
		$this->paragraphPrefix = $this->getVerseAnchor();
	}

	protected function clearVerseNumber() {
		$this->paragraphPrefix = '';
	}


	protected function getVerseAnchor() {
		$id = $this->getVerseAnchorId($this->lcmd);
		return $this->out->xmlElement('a', $this->lcmd, array(
			'href'  => '#' . $id,
			'id'    => $id,
			'class' => 'verse-num'
		));
	}

	protected function getVerseAnchorId($num) {
		$id = "verse_$num";
		if ( isset( $this->verseCnts[$num] ) ) {
			$id .= '.' . ( ++$this->verseCnts[$num] );
		} else {
			$this->verseCnts[$num] = 1;
		}
		return $id;
	}


	protected function doPoemCenterStart() {
		$this->saveStartTag($this->poemCenterElement, array('class' => 'poem-middle'));
	}


	protected function doCiteStart() {
		$this->saveStartTag($this->citeElement, array('class' => 'cite'));
	}


	protected function addTableCaption($text) {
		if ( ! empty($this->tableCaption) ) {
			$this->tableCaption .= $this->out->getEmptyTag($this->breakLineElement);
		}
		$this->tableCaption .= $this->out->xmlElement('span', $this->doInlineElements($text), array(
			'id' => $this->generateInternalId($text)
		));
	}



	protected function getImage($src, $id, $alt, $title, $url, $size, $align) {
		$ititle = empty($title) ? $alt : $title;
		$img = $this->out->xmlElement('img', null, array(
			'id' => $id,
			'src' => $src,
			'alt' => $alt,
			'title' => strtr($ititle, array('*' => ''))
		));

		$out = $this->out->link( $url, $img );
		$outerClass = array();
		$outerAttrs = array();

		if ( ! empty( $size ) ) {
			$outerClass[] = 'thumb';
			$outerAttrs['style'] = "width: {$size}px";
			$out .= $this->out->link($url, $this->getImageTitleElm($alt), 'Щракнете за увеличен размер', array('class' => 'zoom'));
		} else if ( ! empty( $title ) ) {
			$out .= $this->getImageTitleElm($title);
		}

		if ( ! empty( $align ) ) {
			$outerClass[] = "float-$align";
		}

		if ( ! empty($outerClass) || ! empty($outerAttrs) ) {
			$attrs = $outerAttrs;
			if ( ! empty($outerClass) ) {
				$attrs['class'] = implode(' ', $outerClass);
			}
			$elm = $this->isInBlockImage() ? 'div' : 'span';
			$out = $this->out->xmlElement($elm, $out, $attrs);
		}

		return $out;
	}


	protected function getImageTitleElm($title) {
		return $this->out->xmlElement('span', $title, array('class' => 'image-title') );
	}


	protected function doEmptyLine() {
		$this->saveEmptyLine($this->out->xmlElement($this->paragraphElement, '&#160;'));
	}


	protected function inSeparator() {
		$this->saveElement($this->separatorElement, $this->ltext, array(
			'class' => 'separator'
		));
	}


	protected function doParagraphReally() {
		if ( $this->paragraphContainsBlockImage() ) {
			$this->saveStartTag($this->blockImageElement, array('class' => 'image'));
			$this->inParagraph();
			$this->saveEndTag($this->blockImageElement);
		} else {
			parent::doParagraphReally();
		}
	}


	protected function doAuthorStart() {
		$this->saveStartTag($this->authorElement, array('class' => 'author'));
	}

	protected function doAuthorEnd() {
		$this->saveEndTag($this->authorElement);
	}

	protected function doAuthorLineStart() {
		$this->saveStartTag($this->paragraphElement);
	}

	protected function doAuthorLineEnd() {
		$this->saveEndTag($this->paragraphElement);
	}


	protected function doDateStart() {
		$this->saveStartTag($this->dateElement, array('class' => 'placeyear'));
	}

	protected function doDateEnd() {
		$this->saveEndTag($this->dateElement);
	}

	protected function doDateLineStart() {
		$this->saveStartTag($this->paragraphElement);
	}

	protected function doDateLineEnd() {
		$this->saveEndTag($this->paragraphElement);
	}


	protected function doSubheaderLineStart($isMulti, $line) {
		$this->saveStartTag(self::$titleElements[self::TITLE_5], array(
			'id' => $this->generateInternalId($line),
			'class' => 'subheader',
		));
	}

	protected function doSubheaderLineEnd($isMulti) {
		$this->saveEndTag(self::$titleElements[self::TITLE_5]);
	}


	protected function doNoteStart() {
		parent::doNoteStart();
		$this->paragraphPrefix = $this->getRefLink($this->_curNoteIndex/*$this->curFn*/);
	}

	protected function postNoteStart() {
		$this->paragraphPrefix = '';
	}

	protected function preNoteEnd() {
		$this->paragraphSuffix = $this->getRefLink($this->_curNoteIndex/*$this->curFn*/, '↑');
	}

	protected function postNoteEnd() {
		$this->paragraphPrefix =
		$this->paragraphSuffix = '';
		parent::postNoteEnd();
	}


	/**  */
	public function getNoteLink($curReference) {
		$anchor = $this->out->xmlElement('a', "[$curReference]", array(
			'href'  => '#' . self::getNoteId($curReference),
			'title' => 'Към бележката',
			#'rel'   => 'footnote',
		));
		return $this->out->xmlElement($this->superscriptElement, $anchor, array(
			'id'    => $this->getRefId($curReference),
			'class' => 'ref',
		));
	}


	protected function getRefLink($curReference, $text = '') {
		if (empty($text)) $text = "[$curReference]";

		return $this->out->xmlElement('a', $text, array(
			'href'  => '#' . $this->getRefId($curReference),
			'title' => 'Обратно към текста',
			#'rev'   => 'footnote',
		));
	}


	/**
	 * Return an element ID for a reference to a note
	 */
	protected function getRefId($nr) {
		return 'ref_' . self::getNoteNr($nr);
	}


	protected function saveUnknownContent() {
		$this->saveContent("SFB Error: Unknown content at line $this->linecnt: $this->line\n");
	}

}
