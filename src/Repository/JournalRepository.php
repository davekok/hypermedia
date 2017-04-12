<?php declare(strict_types=1);

namespace Sturdy\Activity\Repository;

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
	public function findOneJournalById(int $id): Entity\Journal;

	/**
	 * Create a new journal.
	 *
	 * @param $unit        the unit
	 * @param $dimensions  the dimensions
	 * @param $state       the state object to use
	 * @param $status      the current status of the activity, private to the Activity
	 * @return a freshly created journal
	 */
	public function createJournal(Name $unit, array $dimensions, State $state, int $status): Entity\Journal;

	/**
	 * Save the updated journal.
	 *
	 * @param $journal  the journal to save
	 */
	public function saveJournal(Journal $journal): void;
}
