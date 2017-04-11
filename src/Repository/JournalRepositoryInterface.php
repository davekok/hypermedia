<?php declare(strict_types=1);

namespace Sturdy\Activity\Repository;

/**
 * A repository for journals
 */
interface JournalRepositoryInterface
{
	/**
	 * Create a new journal.
	 *
	 * @param $activityEntity  the activity for which this journal is created
	 * @param $state           the state object to use
	 * @param $status          the current status of the activity, private to the Activity
	 * @return a freshly created journal
	 */
	public function createJournal(ActivityInterface $activityEntity, State $state, int $status): Entity\JournalInterface;

	/**
	 * Save the updated journal.
	 *
	 * @param $journal  the journal to save
	 */
	public function saveJournal(JournalInterface $journal): void;
}
