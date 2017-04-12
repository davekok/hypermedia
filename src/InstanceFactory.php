<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * An interface that should be implemented to by an application
 * to create instances.
 */
interface InstanceFactory
{
	/**
	 * Create a instance of a class on which actions are called.
	 *
	 * @param $unit       the unit the instance is created for
	 * @param $className  the class to create a instance of
	 * @param $state      the state to be used by the instance
	 */
	public function createInstance(string $unit, string $className, State $state);
}
