<?php namespace App\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateNewsletterCommand extends Command {

	private $input;
	private $output;

	public function getName() {
		return 'lib:generate-newsletter';
	}

	public function getDescription() {
		return 'Generate newsletter';
	}

	public function getHelp() {
		return 'The <info>%command.name%</info> generates the newsletter for a given month.';
	}

	protected function getRequiredArguments() {
		return [
			'month' => 'Month (3 or 2011-3)',
		];
	}

	/** {@inheritdoc} */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->input = $input;
		$this->output = $output;
		$this->generateNewsletter($input->getArgument('month'));
	}

	/**
	 * @param int $month
	 */
	private function generateNewsletter($month) {
		$booksByCat = $this->getBooks($month);
		ksort($booksByCat);
		foreach ($booksByCat as $cat => $bookRows) {
			//$this->output->writeln("\n== $cat ==\n");
			$this->output->writeln("\n<h3>$cat</h3>\n");
			ksort($bookRows);
			$this->output->writeln('<ul>');
			foreach ($bookRows as $bookRow) {
				$this->output->writeln($bookRow);
			}
			$this->output->writeln('</ul>');
		}

		$this->output->writeln("\n\n<h3>Произведения, невключени в книги</h3>\n");
		$textRows = $this->getTexts($month);
		ksort($textRows);
		$this->output->writeln('<ul>');
		foreach ($textRows as $textRow) {
			$this->output->writeln($textRow);
		}
		$this->output->writeln('</ul>');
	}

	private function getBooks($month) {
		$repo = $this->getEntityManager()->getBookRevisionRepository();
		$booksByCat = [];
		foreach ($repo->getByMonth($month) as $revision) {
			$authors = [];
			foreach ($revision['book']['authors'] as $author) {
				$authors[] = $author['name'];
			}
			$bookKey = $revision['book']['title'] . $revision['book']['subtitle'];
			$cat = $revision['book']['category']['name'];
//			$booksByCat[$cat][$bookKey] = sprintf('* „%s“%s%s — http://chitanka.info/book/%d',
//				$revision['book']['title'],
//				($revision['book']['subtitle'] ? " ({$revision['book']['subtitle']})" : ''),
//				($authors ? ' от ' . implode(', ', $authors) : ''),
//				$revision['book']['id']);
			$booksByCat[$cat][$bookKey] = sprintf('<li><a href="http://chitanka.info/book/%d">„%s“</a>%s%s</li>',
				$revision['book']['id'],
				$revision['book']['title'],
				($revision['book']['subtitle'] ? " ({$revision['book']['subtitle']})" : ''),
				($authors ? ' от ' . implode(', ', $authors) : ''));
		}
		return $booksByCat;
	}

	// TODO fetch only texts w/o books
	private function getTexts($month) {
		$repo = $this->getEntityManager()->getTextRevisionRepository();
		$texts = [];
		foreach ($repo->getByMonth($month) as $revision) {
			$authors = [];
			foreach ($revision['text']['authors'] as $author) {
				$authors[] = $author['name'];
			}
			$key = $revision['text']['title'] . $revision['text']['subtitle'];
//			$texts[$key] = sprintf('* „%s“%s%s — http://chitanka.info/text/%d',
//				$revision['text']['title'],
//				($revision['text']['subtitle'] ? " ({$revision['text']['subtitle']})" : ''),
//				($authors ? ' от ' . implode(', ', $authors) : ''),
//				$revision['text']['id']);
			$texts[$key] = sprintf('<li><a href="http://chitanka.info/text/%d">„%s“</a>%s%s</li>',
				$revision['text']['id'],
				$revision['text']['title'],
				($revision['text']['subtitle'] ? " ({$revision['text']['subtitle']})" : ''),
				($authors ? ' от ' . implode(', ', $authors) : ''));
		}
		return $texts;
	}
}
