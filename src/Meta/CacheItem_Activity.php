<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

/**
 * Cache item for activity
 *
 * Intended as package private class
 */
class CacheItem_Activity extends CacheItem_UnitItem
{
	private $actions;

	/**
	 * Set actions
	 *
	 * @param array $actions
	 * @return self
	 */
	public function setActions(array $actions): self
	{
		$this->actions = $actions;
		return $this;
	}

	/**
	 * Get actions
	 *
	 * @return array
	 */
	public function getActions(): array
	{
		return $this->actions;
	}

	/**
	 * Set action
	 */
	public function setAction($key, $next): self
	{
		$this->actions[$key] = $next;
		return $this;
	}

	/**
	 * Set action
	 */
	public function getAction($key)
	{
		return $this->actions[$key];
	}

	/**
	 * Clear cache item
	 */
	public function clear(): void
	{
		$this->actions = null;
	}

	/**
	 * Whether the cache item is valid.
	 *
	 * @return bool  whether the cache item is valid
	 */
	public function valid(): bool
	{
		return !empty($this->actions);
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	public function getType(): string
	{
		return 'Activity';
	}
}
