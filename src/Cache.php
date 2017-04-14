<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable;
use Exception;
use ReflectionClass;
use Generator;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class dealing with caching.
 */
final class Cache
{
	private $cachePool;

	/**
	 * Constructor
	 *
	 * @param $cachePool  the cache pool
	 */
	public function __construct(CacheItemPoolInterface $cachePool)
	{
		$this->cachePool = $cachePool;
	}

	/**
	 * Update activities from a unit.
	 *
	 * @param $unit  unit to extract activities from
	 */
	public function updateActivities(Unit $unit): void
	{
		$name = $unit->getName();

		// save the order in which the dimensions are stored
		$dimensions = $unit->getDimensions();
		$item = $this->cachePool->getItem($this->dimensionsKey($name));
		$item->set(json_encode($dimensions));
		$this->cachePool->saveDeferred($item);

		// save the activities for each dimension
		foreach ($unit->getActions() as $dimensions => $actions) {
			$activity = [];
			foreach ($actions as $action => $next) {
				$activity[$action] = $next;
			}
			$item = $this->cachePool->getItem(strtr(__NAMESPACE__."\\$name\\$dimensions", "\\", "|"));
			$item->set(json_encode($activity));
			$this->cachePool->saveDeferred($item);
		}

		// commit cache
		$this->cachePool->commit();
	}

	/**
	 * Get actions for activity
	 *
	 * @param $activity  the activity to return the cached actions for
	 * @return the actions
	 */
	public function getActions(Activity $activity): array
	{
		$unit = $activity->getUnit();
		$dimensions = $activity->getDimensions();

		$item = $this->cachePool->getItem($this->dimensionsKey($unit));
		if (!$item->isHit()) {
			throw new Exception("Actions not found.");
		}
		$order = json_decode($item->get());

		$item = $this->cachePool->getItem($this->actionsKey($unit, $dimensions, $order));
		if (!$item->isHit()) {
			throw new Exception("Actions not found.");
		}
		$actions = $item->get();

		$actions = json_decode($actions, true);
		if (!$actions) {
			throw new Exception("Actions not found.");
		}

		return $actions;
	}

	/**
	 * Construct dimensions key to use for caching.
	 *
	 * @param $unit  the unit name
	 * @return the cache key
	 */
	public function dimensionsKey(string $unit): string
	{
		return strtr(__NAMESPACE__."\\$unit.dimensions", "\\", "|");
	}

	/**
	 * Construct actions key to use for caching.
	 *
	 * @param $unit        the unit name
	 * @param $dimensions  the dimensions
	 * @param $order       the order of the dimensions
	 * @return the cache key
	 */
	public function actionsKey(string $unit, array $dimensions, array $order): string
	{
		$dims = array_flip($order);
		foreach ($dimensions as $key => $value) {
			$dims[$key] = $value;
		}
		return strtr(__NAMESPACE__."\\$unit\\".implode(" ", $dims), "\\", "|");
	}
}
