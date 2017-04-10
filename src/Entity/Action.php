<?php declare(strict_types=1);

namespace Sturdy\Activity\Entity;

/**
 */
class ActionInterface
{
	/**
	 * Get the name of the unit this action belongs to.
	 */
	public function getUnitName(): string;

	/**
	 * Get the name of the class in which this action is implemented.
	 */
	public function getClassName(): string;
}
