<?php

class SuggestDataPage extends Page {

	public function __construct() {
		parent::__construct();

		$subaction = $this->request->value('sa', '', 1);
		$textId = (int) $this->request->value('textId', 0, 2);
		$subaction = strtr($subaction, array(
			'origTitle' => 'orig_title',
			'year' => 'year',
			'translator' => 'translator',
			'transYear' => 'trans_year',
			'annotation' => 'annotation'
		));

		$this->redirectLegacy(sprintf('text/%d/suggest/%s', $textId, $subaction));
	}
}
