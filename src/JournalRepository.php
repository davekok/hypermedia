<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * A interface to be implemented by the application to
 * provide this component with a journal repository.
 */
interface JournalRepository
{
	/**
	 * Find one journal by id or throw exception if not found.
	 *
	 * @param $id  the id of the journal
	 * @return the journal
	 */
	public function findOneJournalById(int $id): Journal;

	/**
	 * Create a new journal.
	 *
	 * @param string $sourceUnit    the source unit
	 * @param int    $type          Journal::activity or Journal::resource
	 * @param array  $tags          the tags
	 * @return a freshly created journal
	 */
	public function createJournal(string $sourceUnit, int $type, array $tags): Journal;

	/**
	 * Save the updated journal.
	 *
	 * @param $journal  the journal to save
	 */
	public function saveJournal(Journal $journal): void;
}
