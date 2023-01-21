<?php
class TextPage extends Page {

	protected function buildContent() {
		$this->_initTextId();
		if (empty($this->textId)) {
			$this->redirectLegacy('texts');
		}
		if ($this->textId == 'random') {
			$this->redirectLegacy('text/random');
		}
		if (in_array($this->chunkId, array('comments', 'comments.rss', 'ratings'))) {
			$this->redirectLegacy("text/$this->textId/$this->chunkId");
		}

		if (is_numeric($this->chunkId) && $this->chunkId != 1) {
			$this->redirectLegacy("text/$this->textId/$this->chunkId");
		}

		$this->redirectLegacy('text/'. $this->request->value('textId', 0, 1));
	}

	private function _initTextId()
	{
		$this->textId = $this->_getTextId();
		$this->chunkId = trim($this->request->value('chunkId', 1, 2 ));
	}

	private function _getTextId() {
		$dataParts = explode('.', $this->request->value('textId', 0, 1));
		if ( ($pos = strpos($dataParts[0], '-')) !== false ) {
			$textId = substr($dataParts[0], 0, $pos);
		} else {
			$textId = $dataParts[0];
		}
		return $textId;
	}

}
