<?php

class AuthorPage extends ViewPage {

	public function __construct() {
		parent::__construct();
		if ($this->mode2 == 'toc') {
			$this->redirectLegacy('authors');
		}
		if ( ! empty($this->country) ) {
			$this->redirectLegacy('authors/by-country/' . $this->country);
		}
	}

	public function makeSimpleList() {
		if (empty($this->startwith)) {
			$this->redirectLegacy('authors');
		}
		$sort = $this->request->value('sortby', 'first') == 'last' ? 'last-name' : 'first-name';
		$this->redirectLegacy("authors/$sort/" . urlencode($this->startwith));
	}

	public function makeExtendedList() {
		$res = $this->db->select(DBT_PERSON, array('name' => $this->startwith), 'slug');
		if ($this->db->numRows($res) > 0) {
			$data = $this->db->fetchAssoc($res);
			$this->redirectLegacy('person/' . $data['slug']);
		}
		$this->redirectLegacy('authors');
	}
}
