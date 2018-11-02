<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

/**
 * Cache item for root resources
 *
 * Intended as package private class
 */
class CacheItem_RootResource extends CacheItem_Resource
{
	/**
	 * Get type
	 *
	 * @return string
	 */
	public function getType(): string
	{
		return 'RootResource';
	}
}
