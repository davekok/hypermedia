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
	public function setTagOrder(array $tagorder): self
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
		$tagMatchers = [];

		foreach ([$this->activities??[], $this->resources??[]] as $items) {
			foreach ($items as $item) {
				foreach ($item->getTaggables() as $taggable) {
					$taggable->setKeyOrder($this->tagorder);
					foreach ($this->tagPermutations($taggable->getTags()) as $tags) {
						$hash = hash("md5", serialize($tags), true);
						if (!isset($tagMatchers[$hash])) {
							$tagMatchers[$hash] = new TagMatcher($tags, $this->tagorder);
						}
					}
				}
			}
		}

		foreach ($tagMatchers as $tagMatcher) {
			$compiler = new ActivityCompiler();
			foreach ($this->activities??[] as $activity) {
				// compile item
				$cacheItem = $compiler->compile($activity, $tagMatcher);
				if (!$cacheItem->valid()) continue;
				yield $cacheItem;
			}
			$compiler = new ResourceCompiler();
			foreach ($this->resources??[] as $resource) {
				// compile item
				$cacheItem = $compiler->compile($resource, $tagMatcher);
				if (!$cacheItem->valid()) continue;
				yield $cacheItem;
			}
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
	 * Return all permutations of tags without duplicates.
	 *
	 * @param  array  $tags  the tags to return the permutations for
	 * @return iterable  to iterate over the permutations
	 */
	private function tagPermutations(array $tags): iterable
	{
		$keys = array_keys($tags);
		$array = [];
		foreach ($tags as $key => $value) {
			if ($value !== null) {
				$array[] = [$key, $value];
			}
		}
		yield $this->orderTags([]);
		foreach ($this->permutations($array) as $permutation) {
			$tags = [];
			foreach ($permutation as [$key, $value]) {
				$tags[$key] = $value;
			}
			yield $this->orderTags($tags);
		}
	}

	/**
	 * Return all permutations of tags without duplicates.
	 *
	 * @param  array  $tags  the tags to return the permutations for
	 * @return iterable  to iterate over the permutations
	 */
	private function permutations(array $array): iterable
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
					foreach ($this->permutations($array) as $sub) {
						array_unshift($sub, $value);
						yield $sub;
					}
				}
		}
	}

	private function orderTags(array $array)
	{
		$tags = [];
		foreach ($this->tagorder as $key) {
			$tags[$key] = $array[$key] ?? null;
		}
		return $tags;
	}
}
