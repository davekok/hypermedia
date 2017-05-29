<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;
use Exception;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Class dealing with caching.
 */
final class Cache implements ActivityCache
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
	public function hasUnit(string $unit): bool
	{
		return $this->cachePool->getItem($this->dimensionsKey($unit))->isHit();
	}

	/**
	 * Update source unit in cache.
	 *
	 * @param $unit  the source unit to cache
	 */
	public function updateUnit(CacheSourceUnit $unit): void
	{
		$name = $unit->getName();

		// save the order in which the dimensions are stored
		$order = $unit->getDimensions();
		$item = $this->cachePool->getItem($this->dimensionsKey($name));
		$item->set(json_encode($order));
		$this->cachePool->saveDeferred($item);

		// save the activities for each dimension
		foreach ($unit->getActivities() as $activity) {
			$dimensions = $activity->dimensions;
			unset($activity->dimensions);
			$item = $this->cachePool->getItem($this->activityKey($name, $dimensions, $order));
			$item->set(json_encode($activity));
			$activity->dimensions = $dimensions;
			$this->cachePool->saveDeferred($item);
		}

		// commit cache
		$this->cachePool->commit();
	}

	/**
	 * Whether an activity is already cached.
	 *
	 * @param $unit        the unit name
	 * @param $dimensions  the dimensions
	 * @return true if cached, false otherwise
	 */
	public function hasActivity(string $unit, array $dimensions): bool
	{
		$item = $this->cachePool->getItem($this->dimensionsKey($unit));
		if (!$item->isHit()) {
			throw new Exception("Unit not found.");
		}
		$order = json_decode($item->get());

		$item = $this->cachePool->getItem($this->activityKey($unit, $dimensions, $order));

		return $item->isHit();
	}

	/**
	 * Get a cached activity
	 *
	 * @param $unit        the unit to retrieve the activity for
	 * @param $dimensions  the dimensions to retrieve the activity for
	 * @return the activity
	 */
	public function getActivity(string $unit, array $dimensions): array
	{
		$item = $this->cachePool->getItem($this->dimensionsKey($unit));
		if (!$item->isHit()) {
			throw new Exception("Unit not found.");
		}
		$order = json_decode($item->get());

		$item = $this->cachePool->getItem($this->activityKey($unit, $dimensions, $order));
		if (!$item->isHit()) {
			throw new Exception("Activity not found.");
		}
		$activity = $item->get();

		$activity = json_decode($activity, true);
		if (!$activity) {
			throw new Exception("Activity not found.");
		}

		return $activity;
	}

	/**
	 * Construct dimensions key to use for caching.
	 *
	 * @param $unit  the unit name
	 * @return the cache key
	 */
	public function dimensionsKey(string $unit): string
	{
		return $this->unitKey($unit) . ".dimensions";
	}

	/**
	 * Construct activity key to use for caching.
	 *
	 * @param $unit        the unit name
	 * @param $dimensions  the dimensions
	 * @param $order       the dimension order
	 * @return the cache key
	 */
	public function activityKey(string $unit, array $dimensions, array $order): string
	{
		$dims = [];
		foreach ($order as $dim) {
			$dims[$dim] = $dimensions[$dim] ?? null;
		}
		return $this->unitKey($unit) . "|" . hash("sha256", json_encode($dims));
	}

	/**
	 * Construct a unit key to use for caching.
	 *
	 * @param $unit        the unit name
	 * @return the cache key
	 */
	public function unitKey(string $unit): string
	{
		return "sturdy-activity|" . $unit;
	}
}
