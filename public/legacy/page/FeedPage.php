<?php

class FeedPage extends Page {

	protected function buildContent() {
		switch ($this->request->value('obj', 'new', 1)) {
			case 'liternews':
				$this->redirectLegacy('http://blog.chitanka.info/section/liternews/feed');
			case 'news':
				$this->redirectLegacy('http://blog.chitanka.info/section/news/feed');
			case 'new': case 'edit':
			default:
				$this->redirectLegacy('new-texts.rss');
			case 'comment':
				$this->redirectLegacy('texts/comments.rss');
			case 'work':
				$this->redirectLegacy('workroom.rss');
		}
	}
}
