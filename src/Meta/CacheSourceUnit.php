<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

/**
 * The source unit interface as required by cache
 */
interface CacheSourceUnit
{
	/**
	 * Get the name of the unit.
	 *
	 * @return the name of the unit
	 */
	public function getName(): string;

	/**
	 * Get tags
	 *
	 * @return array<string>  the tags
	 */
	public function getTagOrder(): array;

	/**
	 * Get wild card tags
	 *
	 * @return array<string>  the wild card tags
	 */
	public function getWildCardTags(): array;

	/**
	 * Get cache items
	 *
	 * @return iterable  get cash item
	 */
	public function getCacheItems(): iterable;
}
