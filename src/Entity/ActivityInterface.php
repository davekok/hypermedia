<?php declare(strict_types=1);

namespace Sturdy\Activity\Entity;

interface ActivityInterface
{
	/**
	 * Get the id of the activity.
	 */
	public function getId(): ?int;

	/**
	 * Get the name of the unit this activity belongs to.
	 */
	public function getUnitName(): ?string;

	/**
	 * Get the name of the activity.
	 */
	public function getName(): ?string;
}
