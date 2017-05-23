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
	public function setState(stdClass $state): Journal;

	/**
	 * Get the current state for this activity.
	 */
	public function getState(): ?stdClass;

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
	public function setErrorMessage(?string $errorMessage): Journal;

	/**
	 * Get error message.
	 */
	public function getErrorMessage(): ?string;

	/**
	 * Set current action.
	 *
	 * The predefined actions "start", "stop", "exception" are used to mark
	 * when an activity has started, has stopped or when an exception has
	 * occured.
	 *
	 * An activity may fork into concurrent flows. The flow number 0 is the
	 * default flow. However when setting the current action. The flow number
	 * indicates for which flow the action is set.
	 *
	 * @param $action  the action to execute
	 * @param $flow    the flow number
	 */
	public function setCurrentAction(string $action, int $flow): Journal;

	/**
	 * Get current action.
	 *
	 * @param $flow    the flow number
	 * @return get current action
	 */
	public function getCurrentAction(int $flow): string;

	/**
	 * Set whether the activity is running.
	 *
	 * @param bool $running
	 * @return self
	 */
	public function setRunning(bool $running): Journal;

	/**
	 * Whether the activity is running (not paused).
	 *
	 * @return bool
	 */
	public function getRunning(): bool;
}
