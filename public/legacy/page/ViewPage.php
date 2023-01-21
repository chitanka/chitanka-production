<?php

abstract class ViewPage extends Page
{

	protected
		$titles = array('simple' => '', 'extended' => '');


	public function __construct() {
		parent::__construct();

		$this->mode = $this->request->value('mode');
		if ( empty($this->mode) ) {
			$this->mode = empty($this->startwith) ? 'simple-toc' : 'extended';
		}

		$this->startwith = str_replace('_', ' ', $this->startwith);

		$this->country = $this->request->value('country');

		$modes = explode('-', $this->mode);
		$this->mode1 = isset($this->titles[ $modes[0] ]) ? $modes[0] : '';
		$this->mode2 = isset( $modes[1] ) ? $modes[1] : '';

		$this->mode1 == 'extended'
			? $this->makeExtendedList()
			: $this->makeSimpleList();
	}

	abstract protected function makeExtendedList();
	abstract protected function makeSimpleList();
}
