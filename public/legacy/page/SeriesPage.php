<?php

class SeriesPage extends ViewPage {

	public function __construct() {
		parent::__construct();
		if ($this->mode2 == 'toc') {
			$this->redirectLegacy('series');
		}
	}

	public function makeSimpleList() {
		$this->redirectLegacy('series/alpha/' . urlencode($this->startwith));
	}

	public function makeExtendedList() {
		$res = $this->db->select(DBT_SERIES, array('name' => $this->startwith), 'slug');
		if ($this->db->numRows($res) > 0) {
			$data = $this->db->fetchAssoc($res);
			$this->redirectLegacy('serie/' . $data['slug']);
		}
		$this->redirectLegacy('series');
	}
}
