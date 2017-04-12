<?php declare(strict_types=1);

namespace Sturdy\Activity\Entity;

use DateTime;

interface Journal
{
	/**
	 * Get id
	 *
	 * @return int
	 */
	public function getId(): int;

	/**
	 * Set unit
	 *
	 * @param Name $unit
	 * @return self
	 */
	public function setUnit(Name $unit);

	/**
	 * Get unit
	 *
	 * @return Name
	 */
	public function getUnit(): Name;

	/**
	 * Set the dimensions for which this journal has been created.
	 *
	 * @param $dimensions  the dimensions
	 */
	public function setDimensions(array $dimensions): self;

	/**
	 * Get the Dimension for which this journal has been created.
	 *
	 * @return array<Dimension>
	 */
	public function getDimensions(): array;

	/**
	 * Set the current state of this activity.
	 */
	public function setState(State $state): self;

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
