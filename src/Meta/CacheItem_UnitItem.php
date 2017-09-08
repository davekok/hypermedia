<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

/**
 * Cache item
 *
 * Intended as package private class
 */
abstract class CacheItem_UnitItem implements CacheItem
{
	private $class;
	private $tags;

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

	/**
	 * Set tags
	 *
	 * @param array $tags
	 * @return self
	 */
	public function setTags(array $tags): self
	{
		$this->tags = $tags;
		return $this;
	}

	/**
	 * Get tags
	 *
	 * @return array
	 */
	public function getTags(): array
	{
		return $this->tags;
	}
}
