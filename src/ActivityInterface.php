<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * The main class of the component.
 *
 * Create or load a journal and run, enjoy.
 */
interface ActivityInterface
{
	/**
	 * Get unit
	 *
	 * @return string
	 */
	public function getUnit(): string;

	/**
	 * Get dimensions
	 *
	 * @return array
	 */
	public function getDimensions(): array;

	/**
	 * Set state variable
	 */
	public function set(string $name, $value): self;

	/**
	 * Get state variable
	 */
	public function get(string $name);

	/**
	 * Set return
	 */
	public function setReturn($return): self;

	/**
	 * Get return
	 */
	public function getReturn();

	/**
	 * Is constant activity?
	 */
	public function isReadonly(): bool;

	/**
	 * Is activity running?
	 */
	public function isRunning(int $branch): bool;

	/**
	 * Pauses the activity until it is resumed.
	 */
	public function pause(int $branch): self;

	/**
	 * Resume the activity.
	 */
	public function resume(int $branch): self;

	/**
	 * Get the error message
	 */
	public function getErrorMessage(int $branch): ?string;
}
