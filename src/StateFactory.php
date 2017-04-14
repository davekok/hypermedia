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
	 * The state object should mimic a stdClass object. However
	 * stdClass may be extended to provide custom properties,
	 * lazy loaders and so on. Whatever your application needs.
	 *
	 * @param $unit        the unit to create state for
	 * @param $dimensions  the dimensions in use
	 * @return state object
	 */
	public function createState(string $unit, array $dimensions): \stdClass;
}
