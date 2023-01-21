<?php

class HelpPage extends Page
{
	public function __construct()
	{
		parent::__construct();

		$newPages = array(
			'' => '%D0%9D%D0%B0%D1%87%D0%B0%D0%BB%D0%BD%D0%B0_%D1%81%D1%82%D1%80%D0%B0%D0%BD%D0%B8%D1%86%D0%B0',
			'sfb' => '%D0%9E%D0%BF%D0%B8%D1%81%D0%B0%D0%BD%D0%B8%D0%B5_%D0%BD%D0%B0_%D1%84%D0%BE%D1%80%D0%BC%D0%B0%D1%82%D0%B0_SFB',
			'digitalizing' => '%D0%A1%D1%8A%D0%B2%D0%B5%D1%82%D0%B8_%D0%B7%D0%B0_%D1%86%D0%B8%D1%84%D1%80%D0%BE%D0%B2%D0%B8%D0%B7%D0%B8%D1%80%D0%B0%D0%BD%D0%B5_%D0%BD%D0%B0_%D0%BF%D0%B5%D1%87%D0%B0%D1%82%D0%BD%D0%BE_%D0%B8%D0%B7%D0%B4%D0%B0%D0%BD%D0%B8%D0%B5',
			'scantailor' => '%D0%9E%D0%B1%D1%80%D0%B0%D0%B1%D0%BE%D1%82%D0%BA%D0%B0_%D0%BD%D0%B0_%D1%81%D0%BA%D0%B0%D0%BD%D0%B8%D1%80%D0%B0%D0%BD%D0%B8_%D0%B8%D0%B7%D0%BE%D0%B1%D1%80%D0%B0%D0%B6%D0%B5%D0%BD%D0%B8%D1%8F_%D1%87%D1%80%D0%B5%D0%B7_Scan_Tailor',
		);
		if (isset($newPages[$this->startwith])) {
			$url = str_replace('$1', $newPages[$this->startwith], Setup::setting('wiki_url'));
			$this->redirectLegacy($url);
		}

		$this->redirectLegacy('');
	}

}
