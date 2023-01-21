<?php
class WorkPage extends Page {

	protected function buildContent() {
		$subaction = $this->request->value('sa', '', 1);

		if ($subaction == 'edit') {
			$entry = (int) $this->request->value('entry', 0, 2);
			$this->redirectLegacy('workroom/entry/' . $entry);
		}

		$viewList = $this->request->value('vl', 'work');
		if ($viewList == 'contrib') {
			$this->redirectLegacy('workroom/contributors');
		}

		$url = 'workroom';
		if ($subaction != '') {
			$url .= '/' . $subaction;
		}
		$this->redirectLegacy($url);
	}
}
