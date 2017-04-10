<?php declare(strict_types=1);

namespace Sturdy\Service\Activity;

/**
 * A interface to be implemented a the application to
 * provide this component with an activity entity repository.
 */
class ActivityEntityRepositoryInterface
{
	/**
	 * Find one by id or throw an exception.
	 *
	 * @param $id  the id of the activity entity
	 */
	public function findOneById(int $id): ActivityEntityInterface;

	/**
	 * Find or create a activity entity by unit and name.
	 *
	 * @param $unit  the unit name
	 * @param $name  the activity name
	 */
	public function findOrCreateOneByUnitAndName(string $unit, string $name): ActivityEntityInterface;
}
