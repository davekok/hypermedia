<?php declare(strict_types=1);

namespace Sturdy\Activity\Entity;

use DateTime;

class JournalInterface
{
	/**
	 * Get the activity for which this Journal has been created.
	 */
	public function getActivity(): ActivityInterface;

	/**
	 * Get the current state for this activity.
	 */
	public function getState(): ?State;

	/**
	 * Set the current status.
	 */
	public function setStatus(int $status): self;

	/**
	 * Get the current status.
	 */
	public function getStatus(): int;

	/**
	 * Set return value.
	 */
	public function setReturnValue($returnValue): self;

	/**
	 * Get return value.
	 */
	public function getReturnValue();

	/**
	 * Set finished at.
	 */
	public function setFinishedAt(?DateTime $dateTime): self;

	/**
	 * Get finished at.
	 */
	public function getFinishedAt(): ?DateTime;

	/**
	 * Set error message.
	 */
	public function setErrorMessage(?string $errorMessage): self;

	/**
	 * Get error message.
	 */
	public function getErrorMessage(): ?string;

	/**
	 * Set current action.
	 */
	public function setCurrentAction(?string $action): self;

	/**
	 * Get current action.
	 */
	public function getCurrentAction(): ?string;
}
