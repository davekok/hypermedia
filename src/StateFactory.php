<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * An interface that should be implemented to by an application
 * to create instances.
 */
interface StateFactory
{
	/**
	 * Create a instance of state for the specified unit.
	 *
	 * @param $unit  the unit to create state for
	 */
	public function createState(string $unit): State;
}
