<?php declare(strict_types=1);

namespace Sturdy\Activity\Repository;

use Sturdy\Activity\Entity;

/**
 * A interface to be implemented by the application to
 * provide this component with a dimension repository.
 */
interface DimensionRepository
{
	/**
	 * Find one dimension by id or throw an exception.
	 *
	 * @param $id  the id of the name
	 */
	public function findOneDimensionById(int $id): Entity\Dimension;

	/**
	 * Find one dimension by name and value or throw an exception.
	 *
	 * @param $name   the name of the dimension
	 * @param $value  the value of the dimension
	 * @return a dimension entity
	 */
	public function findOneDimensionByNameAndValue(string $name, $value): Entity\Dimension;

	/**
	 * Find or create one dimension by name and value.
	 *
	 * @param $name   the name
	 * @param $value  the value of the dimension
	 * @return a dimension entity
	 */
	public function findOrCreateOneDimension(string $name, $value): Entity\Dimension;
}
