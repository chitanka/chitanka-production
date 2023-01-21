<?php
class BookPage extends ViewPage {

	public function makeSimpleList() {
		$this->redirectLegacy('books');
	}

	public function makeExtendedList() {
		$this->redirectLegacy('book/' . str_replace(' ', '_', $this->startwith));
	}
}
