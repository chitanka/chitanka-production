<?php namespace Sfblib;

class XmlElement {

	/**
	 * Here we keep track of all generated anchor names
	 * @var array
	 */
	private $_anchorNames = array();

	public function xmlElement($name, $content = '', $attrs = array(), $doEscape = true) {
		$end = is_null($content) ? ' />' : ">$content</$name>";
		return '<'.$name . $this->makeAttribs($attrs, $doEscape) . $end;
	}

	public function xmlElementOrNone($name, $content, $attrs = array(), $doEscape = true) {
		if ( empty($content) ) {
			return '';
		}
		return $this->xmlElement($name, $content, $attrs, $doEscape);
	}


	public function makeAttribs($attrs, $doEscape = true) {
		$o = '';
		foreach ($attrs as $attr => $value) {
			$o .= $this->attrib($attr, $value, $doEscape);
		}
		return $o;
	}

	public function attrib($attrib, $value, $doEscape = true) {
		if ( is_null($value) || ( empty($value) && $attrib == 'title' ) ) {
			return '';
		}

		$value = strip_tags($value);
		return ' '. $attrib .'="'
			. ( $doEscape ? htmlspecialchars($value, ENT_COMPAT, 'UTF-8') : $value )
			.'"';
	}

	/**
	 *	Creates an HTML table.
	 *
	 *	@param string $caption Table caption
	 *	@param array $data Array of arrays, i.e.
	 *		array(
	 *			array(CELL, CELL, ...),
	 *			array(CELL, CELL, ...),
	 *			...
	 *		)
	 *		CELL can be:
	 *		— a string — equivalent to a simple table cell
	 *		— an array:
	 *			— first element must be an associative array for cell attributes;
	 *				if this array contains a key 'type' with the value 'header',
	 *				then the cell is rendered as a header cell
	 *			— second element must be a string representing the cell content
	 *	@param array $attrs Optional associative array for table attributes
	 */
	public function simpleTable($caption, $data, $attrs = array()) {
		$ext = $this->makeAttribs($attrs);
		$t = "\n<table class=\"content\"$ext>";
		if ( !empty($caption) ) {
			$t .= "<caption>$caption</caption>";
		}
		$curRowClass = '';
		foreach ($data as $row) {
			$curRowClass = $curRowClass == 'even' ? 'odd' : 'even';
			$t .= "\n<tr class=\"$curRowClass\">";
			foreach ($row as $cell) {
				$ctype = 'd';
				if ( is_array($cell) ) {
					if ( isset( $cell[0]['type'] ) ) {
						$ctype = $cell[0]['type'] == 'header' ? 'h' : 'd';
						unset( $cell[0]['type'] );
					}
					$cattrs = $this->makeAttribs($cell[0]);
					$content = $cell[1];
				} else {
					$cattrs = '';
					$content = $cell;
				}
				$t .= "\n\t<t{$ctype}{$cattrs}>{$content}</t{$ctype}>";
			}
			$t .= "\n</tr>";
		}
		return $t.'</table>';
	}


	public function getStartTag($elm, $attrs = array()) {
		return '<'. $elm . $this->makeAttribs($attrs) . '>';
	}

	public function getEndTag($elm) {
		return '</'. $elm . '>';
	}

	public function getEmptyTag($elm, $attrs = array(), $xml = true) {
		$end = $xml ? '/>' : ' />';
		return '<'. $elm . $this->makeAttribs($attrs) . $end;
	}

	/**
	 * Generate an anchor name for a given string.
	 *
	 * @param string  $text    A string
	 * @param bool    $unique  Always generate a unique name
	 *                         (consider all previously generated names)
	 */
	public function getAnchorName($text, $unique = true) {
		$text = Char::cyr2lat($text);
		$text = strtolower($text);
		$text = strtr($text, array(
			' ' => '_',
			'/' => '-',
			'<br />' => '_',
		));
		$text = strip_tags($text);
		$text = preg_replace('/[^\w_-]/', '', $text);
		$text = urlencode( iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text) );
		if ($text === '') {
			$text = '_' . $text;
		}

		if ($unique) {
			if (isset($this->_anchorNames[$text])) {
				$this->_anchorNames[$text]++;
				$text .= '.' . $this->_anchorNames[$text];
			} else {
				$this->_anchorNames[$text] = 1;
			}
		}

		return $text;
	}

	public function link($url, $text, $title = '', $attrs = array()) {
		$attrs = array( 'href' => $url ) + $attrs;
		if ( ! empty( $title ) ) $attrs['title'] = $title;

		return $this->xmlElement('a', $text, $attrs);
	}
}
