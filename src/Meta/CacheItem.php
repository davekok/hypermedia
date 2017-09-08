<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

/**
 * Cache item
 */
interface CacheItem
{
	/**
	 * Get type
	 *
	 * @return string
	 */
	public function getType(): string;

	/**
	 * Whether the cache item is valid.
	 *
	 * @return bool  whether the cache item is valid
	 */
	public function valid(): bool;
}
