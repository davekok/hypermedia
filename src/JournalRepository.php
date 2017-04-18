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
	 * @param $unit        the unit
	 * @param $dimensions  the dimensions
	 * @param $state       the state object to use
	 * @return a freshly created journal
	 */
	public function createJournal(string $unit, array $dimensions, \stdClass $state): Journal;

	/**
	 * Save the updated journal.
	 *
	 * @param $journal  the journal to save
	 */
	public function saveJournal(Journal $journal): void;
}
