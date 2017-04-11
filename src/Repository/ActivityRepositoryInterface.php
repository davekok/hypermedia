<?php declare(strict_types=1);

namespace Sturdy\Activity\Repository;

use Sturdy\Activity\Entity;

/**
 * A interface to be implemented a the application to
 * provide this component with an activity entity repository.
 */
interface ActivityRepositoryInterface
{
	/**
	 * Find one by id or throw an exception.
	 *
	 * @param $id  the id of the activity entity
	 */
	public function findOneById(int $id): Entity\ActivityInterface;

	/**
	 * Find or create a activity entity by unit and name.
	 *
	 * @param $unit  the unit name
	 * @param $name  the activity name
	 */
	public function findOrCreateOneByUnitAndName(string $unit, string $name): Entity\ActivityInterface;
}
