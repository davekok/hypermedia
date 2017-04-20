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
	 * Whether a unit is already cached.
	 *
	 * @param $unit  the unit name
	 * @return true if cached, false otherwise
	 */
	public function haveUnit(string $unit): bool
	{
		return $this->cachePool->getItem($this->dimensionsKey($unit))->isHit();
	}

	/**
	 * Update unit in cache.
	 *
	 * @param $unit  the unit to cache
	 */
	public function updateUnit(Unit $unit): void
	{
		$name = $unit->getName();

		// save the order in which the dimensions are stored
		$order = $unit->getDimensions();
		$item = $this->cachePool->getItem($this->dimensionsKey($name));
		$item->set(json_encode($order));
		$this->cachePool->saveDeferred($item);

		// save the activities for each dimension
		foreach ($unit->getActions() as $dimensions => $actions) {
			$item = $this->cachePool->getItem($this->actionsKey($name, explode(" ",$dimensions), $order));
			$item->set(json_encode($actions));
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
		return $this->unitKey($unit).".dimensions";
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
		return $this->unitKey($unit)."|".implode(" ", $dims);
	}

	/**
	 * Construct a unit key to use for caching.
	 *
	 * @param $unit        the unit name
	 * @return the cache key
	 */
	public function unitKey(string $unit): string
	{
		return "sturdy-activity|".$unit;
	}
}
