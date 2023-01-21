<?php

class LabelPage extends ViewPage {

	public function makeSimpleList() {
		$this->redirectLegacy('texts');
	}

	public function makeExtendedList() {
		switch ($this->startwith) {
			case 'Книга-игра':
			case 'Книга игра':
				$this->redirectLegacy('texts/type/gamebook');
		}
		$res = $this->db->select(DBT_LABEL, array('name' => $this->startwith), 'slug');
		if ($this->db->numRows($res) > 0) {
			$data = $this->db->fetchAssoc($res);
			$this->redirectLegacy('texts/label/' . $data['slug']);
		}
		$this->redirectLegacy('texts');
	}

}
