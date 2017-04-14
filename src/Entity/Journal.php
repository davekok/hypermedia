<?php declare(strict_types=1);

namespace Sturdy\Activity\Entity;

/**
 * Interface to the journal to be implemented by the appliction.
 */
interface Journal
{
	/**
	 * Set unit
	 *
	 * @param string $unit
	 * @return self
	 */
	public function setUnit(string $unit): Journal;

	/**
	 * Get unit
	 *
	 * @return string
	 */
	public function getUnit(): ?string;

	/**
	 * Set dimensions
	 *
	 * @param array $dimensions
	 * @return self
	 */
	public function setDimensions(array $dimensions): Journal;

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
	 * @param $action  the action to execute
	 */
	public function setCurrentAction(string $action): Journal;

	/**
	 * Get current action.
	 *
	 * Return 'start' as the default action.
	 *
	 * @return get current action
	 */
	public function getCurrentAction(): string;
}
