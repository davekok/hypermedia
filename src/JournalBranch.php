<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * Interface to the journal to be implemented by the application.
 */
interface JournalBranch
{
	/**
	 * Create a new entry from arguments and add it to the branch, making this entry the new last entry.
	 *
	 * @param object  $object         the object
	 * @param ?string $action         the action
	 * @param int     $statusCode     some code representing the current status
	 * @param ?string $statusText     a possible status text
	 * @return $this
	 *
	 * Predefined actions are:
	 * - start  start the activity
	 * - stop   stop the activity
	 * - join   branches are joined
	 * - split  main branch is split
	 */
	public function addEntry(/*object*/ $object, ?string $action, int $statusCode, ?string $statusText = null): JournalBranch;

	/**
	 * Get the last branch entry.
	 *
	 * @return JournalBranchEntry  the last entry
	 */
	public function getLastEntry(): JournalBranchEntry;

	/**
	 * Get all branch entries
	 *
	 * @return JournalBranchEntry[]  the branch entries
	 */
	public function getEntries(): array;
}
