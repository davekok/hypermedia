<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use stdClass;
use Exception;
use Generator;
use Psr\Cache\CacheItemPoolInterface;
use Sturdy\Activity\Cache as CacheInterface;

/**
 * Class dealing with caching.
 */
final class Cache implements CacheInterface
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
	 * Whether a source unit is already cached.
	 *
	 * @param $name  the source unit name
	 * @return true if cached, false otherwise
	 */
	public function hasSourceUnit(string $name): bool
	{
		return $this->getSourceUnit($name)->isHit();
	}

	/**
	 * Update source unit in cache.
	 *
	 * @param $unit  the source unit to cache
	 */
	public function updateSourceUnit(CacheSourceUnit $unit): void
	{
		$name = $unit->getName();

		// save the tagorder in which the tags are stored
		$tagorder = $unit->getTagOrder();
		$wildcards = $unit->getWildCardTags();
		$item = $this->getSourceUnit($name);
		$item->set(serialize([$tagorder, $wildcards]));
		$this->cachePool->saveDeferred($item);

		foreach ($unit->getCacheItems() as $item) {
			$cacheItem = $this->getSourceUnitItem($name, $item->getType(), $item->getClass(), $item->getTags());
			$cacheItem->set(serialize($item));
			$this->cachePool->saveDeferred($cacheItem);
		}

		// commit cache
		$this->cachePool->commit();
	}

	/**
	 * Get a cached activity item
	 *
	 * @param string $unit   the unit to retrieve the activity for
	 * @param array  $tags   the tags to retrieve the activity for
	 * @param string $class  the class of the activity
	 * @return the activity
	 */
	public function getActivity(string $unit, string $class, array $tags): ?CacheItem_Activity
	{
		return $this->getItem($unit, 'Activity', $class, $tags);
	}

	/**
	 * Get a cached resource item
	 *
	 * @param string $unit   the unit to retrieve the resource meta data for
	 * @param array  $tags   the tags to retrieve the resource meta data for
	 * @param string $class  the class of the resource
	 * @return the resource meta data
	 */
	public function getResource(string $unit, string $class, array $tags): ?CacheItem_Resource
	{
		return $this->getItem($unit, 'Resource', $class, $tags);
	}

	/**
	 * Get a cached root resource item
	 *
	 * @param string $unit   the unit to retrieve the resource meta data for
	 * @param string $class  the name if the resource
	 * @param array  $tags   the tags to retrieve the resource meta data for
	 * @return the resource meta data
	 */
	public function getRootResource(string $unit, array $tags): ?CacheItem_RootResource
	{
		return $this->getItem($unit, 'RootResource', "", $tags);
	}

	/**
	 * Get a cached item
	 *
	 * @param string $unit   the unit to item for
	 * @param string $type   the time of cache item
	 * @param string $class  the class of the source unit item
	 * @param array  $tags   the tags to item for
	 */
	private function getItem(string $unit, string $type, string $class, array $tags)
	{
		$item = $this->getSourceUnit($unit);
		if (!$item->isHit()) {
			return null;
		}
		[$order, $wildcards] = unserialize($item->get());

		$tags = $this->reorder($tags, $order);

		$item = $this->getSourceUnitItem($unit, $type, $class, $tags);

		if (!$item->isHit()) { // try wildcards
			// filter out any tag that are already wildcards
			foreach ($wildcards as $ix => &$wildcard) {
				if ($tags[$wildcard] === true || $tags[$wildcard] === null) {
					unset($wildcards[$ix]);
				}
			}

			// iterate all wildcard permutations
			foreach ($this->wildcardPermutations($wildcards) as $wcs) {
				$dup = $tags;
				foreach ($wcs as $wc) {
					$dup[$wc] = true; // set tag to wildcard
				}

				// check if an item exists
				$item = $this->getSourceUnitItem($unit, $type, $class, $dup);
				if ($item->isHit()) {
					break;
				}
			}
		}

		if (!$item->isHit()) {
			return null;
		}

		$item = unserialize($item->get());
		if (!$item) {
			return null;
		}

		return $item;
	}


	/**
	 * Return all permutations of wildcards without duplicates.
	 *
	 * @param  array  $wildcards  the wildcards to return the permutations for
	 * @return iterable  to iterate over the permutations
	 */
	private function wildcardPermutations(array $array): iterable
	{
		$l = count($array);
		switch ($l) {
			case 0:
				break;
			case 1:
				yield $array;
				break;
			default:
				foreach ($array as $value) {
					yield [$value];
				}
				for ($i = 0; $i < $l; ++$i) {
					$value = array_shift($array);
					foreach ($this->wildcardPermutations($array) as $sub) {
						array_unshift($sub, $value);
						yield $sub;
					}
				}
		}
	}

	/**
	 * Get a unit from cache.
	 *
	 * @param $unit  the unit name
	 */
	private function getSourceUnit(string $unit)
	{
		return $this->cachePool->getItem(hash("sha256", "/sturdy-activity/$unit"));
	}

	/**
	 * Get a unit item from cache
	 *
	 * @param string $unit   the unit name
	 * @param string $type   the item type
	 * @param string $class  the item class
	 * @param array  $tags   the tags
	 */
	private function getSourceUnitItem(string $unit, string $type, string $class, array $tags)
	{
		switch ($type) {
			case "RootResource":
				return $this->cachePool->getItem(hash("sha256", "/sturdy-activity/$unit/$type/".serialize($tags)));
			default:
				return $this->cachePool->getItem(hash("sha256", "/sturdy-activity/$unit/$type/$class/".serialize($tags)));
		}
	}

	/**
	 * Reorder array, any missing keys will be added and have a value of null.
	 *
	 * @param $array  the original array
	 * @param $order  the order of the new array
	 * @return reordered array
	 */
	public function reorder(array $array, array $order): array
	{
		$reordered = [];
		foreach ($order as $key) {
			$reordered[$key] = $array[$key] ?? null;
			if ($reordered[$key] === false) {
				$reordered[$key] = null;
			}
		}
		return $reordered;
	}
}
