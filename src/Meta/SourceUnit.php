<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use stdClass;

/**
 * A source unit represents a bunch of source files
 * containing Action, Resource and Field annotations.
 */
final class SourceUnit implements CacheSourceUnit
{
	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string[]
	 */
	private $classes = [];

	/**
	 * @var string[]
	 */
	private $tagorder = [];

	/**
	 * @var string[]
	 */
	private $wildCardTags = [];

	/**
	 * @var array
	 */
	private $items = [];

	/**
	 * @var array
	 */
	private $activities;

	/**
	 * @var array
	 */
	private $resources;

	/**
	 * Constructor
	 *
	 * @param $name  the name of the unit
	 */
	public function __construct(string $name)
	{
		$this->name = $name;
		$this->activities = [];
		$this->resources = [];
	}

	/**
	 * Get the name of the unit.
	 *
	 * @return the name of the unit
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Get classes
	 *
	 * @return string[]  the classes
	 */
	public function getClasses(): array
	{
		return $this->classes;
	}

	/**
	 * Set key order of tags
	 *
	 * Normally the tags are collected as you add actions.
	 * However it can be usefull to set the tags manually
	 * to control the order of the tags. The order is used
	 * to find the best matches for an activity.
	 *
	 * @param string[]  the tags
	 */
	public function setTagOrder(array $tags): self
	{
		$this->tagorder = $tagorder;
		return $this;
	}

	/**
	 * Get key order of tags
	 *
	 * @return string[]  the key order of tags
	 */
	public function getTagOrder(): array
	{
		return $this->tagorder;
	}

	/**
	 * Set wild card tags
	 *
	 * @param string[] $wildCardTags
	 * @return self
	 */
	public function setWildCardTags(array $wildCardTags): self
	{
		$this->wildCardTags = $wildCardTags;
		return $this;
	}

	/**
	 * Get wild card tags
	 *
	 * @return string[]
	 */
	public function getWildCardTags(): array
	{
		return $this->wildCardTags;
	}

	/**
	 * Add activity to source unit
	 *
	 * @param Activity $activity  the activity to add
	 * @return $this
	 */
	public function addActivity(Activity $activity): self
	{
		$this->activities[] = $activity;
		$this->processItem($activity);
		return $this;
	}

	/**
	 * Get activities
	 *
	 * @return Activity[]  the activities
	 */
	public function getActivities(): array
	{
		return $this->activities;
	}

	/**
	 * Add a resource to this source unit
	 *
	 * @param Resource $resource  the resource to add
	 * @return $this
	 */
	public function addResource(Resource $resource): self
	{
		$this->resources[] = $resource;
		$this->processItem($resource);
		return $this;
	}

	/**
	 * Get the resources in this source unit
	 *
	 * @return Resource[]  the resources
	 */
	public function getResources(): array
	{
		return $this->resources;
	}

	/**
	 * Get cache items
	 *
	 * @return CacheItem[]  the cache items
	 */
	public function getCacheItems(): iterable
	{
		$compiler = new ActivityCompiler();
		foreach ($this->activities??[] as $activity) {
			yield from $this->compileItem($activity, $compiler);
		}
		$compiler = new ResourceCompiler();
		foreach ($this->resources??[] as $resource) {
			yield from $this->compileItem($resource, $compiler);
		}
	}

	/**
	 * Process item
	 */
	private function processItem($item): void
	{
		$this->classes[] = $item->getClass();

		foreach ($item->getTaggables() as $taggable) {
			foreach ($taggable->getTags() as $key => $value) {
				if (!in_array($key, $this->tagorder)) {
					$this->tagorder[] = $key;
				}
				if ($value === true && !in_array($key, $this->wildCardTags)) {
					$this->wildCardTags[] = $key;
				}
			}
		}
	}

	/**
	 * Compile one item
	 *
	 * @param  $item      item to compile
	 * @param  $compiler  compiler to use
	 */
	private function compileItem($item, $compiler): iterable
	{
		foreach ($item->getTaggables() as $taggable) {
			$taggable->setKeyOrder($this->tagorder);
		}

		// compile item
		$hashes = [];
		foreach ($item->getTaggables() as $taggable) {
			$tags = $taggable->getTags();

			// create a hash so items are only compiled once
			$hash = hash("md5", serialize($tags), true);

			// check that item is not already compiled
			if (isset($hashes[$hash]))
				continue;

			$cacheItem = $compiler->compile($item, $taggable->createMatcher());
			if (!$cacheItem->valid()) continue;

			// remember that this variant has been found
			$hashes[$hash] = $cacheItem;
			yield $cacheItem;
		}
	}
}
