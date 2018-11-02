<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

/**
 * Cache item
 *
 * Intended as package private class
 */
abstract class CacheItem_UnitItem extends Taggable implements CacheItem
{
	private $class;

	/**
	 * Set class
	 *
	 * @param string $class
	 * @return self
	 */
	public function setClass(string $class): self
	{
		$this->class = $class;
		return $this;
	}

	/**
	 * Get class
	 *
	 * @return string
	 */
	public function getClass(): string
	{
		return $this->class;
	}
}
