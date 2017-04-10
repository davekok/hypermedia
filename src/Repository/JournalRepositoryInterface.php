<?php declare(strict_types=1);

namespace Sturdy\Service\Activity;

/**
 *
 */
class JournalRepositoryInterface
{
	/**
	 * Create a new journal.
	 */
	public function createJournal(ActivityEntityInterface $activityEntity, State $state, int $status): JournalInterface;

	/**
	 * Save the updated journal.
	 */
	public function saveJournal(JournalInterface $journal): void;
}
