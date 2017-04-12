<?php declare(strict_types=1);

namespace Sturdy\Activity\Repository;

use Sturdy\Activity\Entity;

/**
 * A interface to be implemented by the application to
 * provide this component with a name repository.
 */
interface NameRepository
{
	/**
	 * Find one by id or throw an exception.
	 *
	 * @param $id  the id of the name
	 */
	public function findOneNameById(int $id): Entity\Name;

	/**
	 * Find or create one name.
	 *
	 * @param $name  the name
	 */
	public function findOrCreateOneName(string $name): Entity\Name;
}
