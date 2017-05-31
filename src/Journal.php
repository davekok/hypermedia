<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;

/**
 * Interface to the journal to be implemented by the appliction.
 */
interface Journal
{
	/**
	 * Get unit
	 *
	 * @return string
	 */
	public function getUnit(): ?string;

	/**
	 * Get dimensions
	 *
	 * @return array
	 */
	public function getDimensions(): ?array;

	/**
	 * Set the current state of this activity.
	 */
	public function setState(int $branch, stdClass $state): Journal;

	/**
	 * Get the current state for this activity.
	 */
	public function getState(int $branch): ?stdClass;

	/**
	 * Set return
	 */
	public function setReturn($return): Journal;

	/**
	 * Get return
	 */
	public function getReturn();

	/**
	 * Set error message.
	 */
	public function setErrorMessage(int $branch, ?string $errorMessage): Journal;

	/**
	 * Get error message.
	 */
	public function getErrorMessage(int $branch): ?string;

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
	 * @param $branch  the branch number
	 * @param $action  the action to execute
	 */
	public function setCurrentAction(int $branch, string $action): Journal;

	/**
	 * Get current action.
	 *
	 * @param $branch  the branch number
	 * @return get current action
	 */
	public function getCurrentAction(int $branch): string;

	/**
	 * Set whether a concurrent branch is running.
	 *
	 * @param int  $branch   the branch number
	 * @param bool $running  true is running, false is not running
	 * @return self
	 */
	public function setRunning(int $branch, bool $running): Journal;

	/**
	 * Whether the activity is running.
	 *
	 * @param int  $branch   the branch number
	 * @return bool
	 */
	public function getRunning(int $branch): bool;
}
