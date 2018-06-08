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
	 * @param string $name  the source unit name
	 * @return true if cached, false otherwise
	 */
	public function hasSourceUnit(string $name): bool
	{
		return $this->getSourceUnit($name)->isHit();
	}

	/**
	 * Update source unit in cache.
	 *
	 * @param CacheSourceUnit $unit  the source unit to cache
	 */
	public function updateSourceUnit(CacheSourceUnit $unit): void
	{
		$name = $unit->getName();
		$tagorder = $unit->getTagOrder();
		$wildcards = $unit->getWildCardTags();

		$unitHash = $this->getSourceUnitHash($name);
		$cachedUnit = $this->cachePool->getItem($unitHash);
		$cachedItems = $this->cachePool->getItem($unitHash."items");
		$oldItems = $cachedItems->get();
		$oldItems = !empty($oldItems) ? array_flip(unserialize($oldItems)) : [];
		$newItems = [];

		foreach ($unit->getCacheItems() as $variants) {
			$variant = reset($variants);
			$itemHash = $this->getSourceUnitItemHash($name, $variant->getType(), $variant->getClass());
			unset($oldItems[$itemHash]);
			$newItems[] = $itemHash;

			foreach ($variants as $variant) {
				$variant->setKeyOrder($tagorder);
			}

			$cachedItem = $this->cachePool->getItem($itemHash);
			$cachedItem->set(serialize($variants));
			$this->cachePool->saveDeferred($cachedItem);
		}

		$cachedItems->set(serialize($newItems));
		$this->cachePool->saveDeferred($cachedItems);
		$cachedUnit->set(serialize($tagorder));
		$this->cachePool->saveDeferred($cachedUnit);

		// commit cache
		$this->cachePool->commit();

		foreach ($oldItems as $itemHash) {
			$this->cachePool->deleteItem($itemHash);
		}
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
		$cachedUnit = $this->cachePool->getItem($this->getSourceUnitHash($unit));
		if (!$cachedUnit->isHit()) return null;
		$cachedItem = $this->cachePool->getItem($this->getSourceUnitItemHash($unit, $type, $class));
		if (!$cachedItem->isHit()) return null;
		return (new TagMatcher($tags, unserialize($cachedUnit->get())))->findBestMatch(unserialize($cachedItem->get()));
	}

	/**
	 * Get a unit from cache.
	 *
	 * @param $unit  the unit name
	 */
	private function getSourceUnitHash(string $unit): string
	{
		return hash("sha256", "/sturdy-activity/$unit");
	}

	/**
	 * Get a unit item from cache
	 *
	 * @param string $unit   the unit name
	 * @param string $type   the item type
	 * @param string $class  the item class
	 * @return string
	 */
	private function getSourceUnitItemHash(string $unit, string $type, string $class): string
	{
		switch ($type) {
			case "RootResource":
				return hash("sha256", "/sturdy-activity/$unit/$type");
			default:
				return hash("sha256", "/sturdy-activity/$unit/$type/$class");
		}
	}
}
