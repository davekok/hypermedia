<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * Interface to the journal to be implemented by the appliction.
 */
interface JournalBranch
{
	/**
	 * Set error message.
	 */
	public function setErrorMessage(?string $errorMessage): JournalBranch;

	/**
	 * Get error message.
	 */
	public function getErrorMessage(): ?string;

	/**
	 * Set current object
	 *
	 * @param object $object
	 * @return self
	 */
	public function setCurrentObject(/*object*/ $object): self;

	/**
	 * Get current object
	 *
	 * @return object
	 */
	public function getCurrentObject()/*: object*/;

	/**
	 * Set current action.
	 *
	 * Predefined actions:
	 * - start      the begin state of the activity
	 * - stop       the end state of the activity
	 * - exception  activity is in a error state
	 * - read       a read action, generate a view for the user
	 * - write      a write action, input is expected from the user
	 *
	 * @param $action  the action to execute
	 */
	public function setCurrentAction(string $action): JournalBranch;

	/**
	 * Get current action.
	 *
	 * @param $branch  the branch number
	 * @return get current action
	 */
	public function getCurrentAction(): string;

	/**
	 * Set whether a concurrent branch is running.
	 *
	 * @param int  $branch   the branch number
	 * @param bool $running  true is running, false is not running
	 * @return self
	 */
	public function setRunning(bool $running): JournalBranch;

	/**
	 * Whether the activity is running.
	 *
	 * @param int  $branch   the branch number
	 * @return bool
	 */
	public function getRunning(): bool;
}
