<?php

class HistoryPage extends Page {

	protected function buildContent() {
		$date = $this->request->value('date');
		if (empty($date) || ! preg_match('/(\d+)-(\d+)/', $date, $matches)) {
			$this->redirectLegacy('new');
		}

		$this->redirectLegacy("new/texts/$matches[1]/$matches[2]");
	}

}
