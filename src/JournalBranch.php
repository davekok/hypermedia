<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * Interface to the journal to be implemented by the appliction.
 */
interface JournalBranch
{
	/**
	 * Get the current state for this branch.
	 *
	 * @return object  a state instance
	 */
	public function getState()/*: object*/;

	/**
	 * Set error message.
	 */
	public function setErrorMessage(?string $errorMessage): JournalBranch;

	/**
	 * Get error message.
	 */
	public function getErrorMessage(): ?string;

	/**
	 * Set current action.
	 *
	 * The predefined actions "start", "stop", "exception" are used to mark
	 * when an activity has started, has stopped or when an exception has
	 * occurred.
	 *
	 * An activity may fork into concurrent branches. The branch number 0 is the
	 * default branch. The branch number indicates for which branch the action is
	 * set.
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
