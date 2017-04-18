<?php declare(strict_types=1);

namespace Sturdy\Activity;

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
	public function setState(\stdClass $state): Journal;

	/**
	 * Get the current state for this activity.
	 */
	public function getState(): ?\stdClass;

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
	 * Will be called with $action = "stop" when the activity is finished.
	 * Will be called with $action = "exception" when an exception occurred.
	 * Also setErrorMessage() will be called before setCurrentAction("exception") is called.
	 *
	 * @param $action  the action to execute
	 */
	public function setCurrentAction(string $action): Journal;

	/**
	 * Get current action.
	 *
	 * Should return 'start' when the activity has not yet started.
	 *
	 * @return get current action
	 */
	public function getCurrentAction(): string;

	/**
	 * Set whether the activity is running.
	 *
	 * @param bool $running
	 * @return self
	 */
	public function setRunning(bool $running): self;

	/**
	 * Whether the activity is running (not paused).
	 *
	 * @return bool
	 */
	public function getRunning(): bool;
}
