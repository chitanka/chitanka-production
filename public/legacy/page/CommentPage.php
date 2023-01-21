<?php
class CommentPage extends Page {

	protected function buildContent() {
		// used by the “view per user” mode
		$username = $this->request->value('user');

		$textId = (int) $this->request->value('textId', 0, 1);
		if ( empty($textId) ) {
			if ( ! empty($username) ) {
				$this->redirectLegacy('user/'.urlencode($username).'/comments');
			}
			$this->redirectLegacy('texts/comments');
		}

		$this->redirectLegacy('text/'.$textId.'/comments');
	}
}
