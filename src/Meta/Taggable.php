<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

/**
 * A taggable
 */
class Taggable
{
	private $tags = [];
	private $keyorder = [];

	/**
	 * Set that the object is only valid if the given tag is there.
	 *
	 * @param string $tag  the tag
	 */
	public function needsTag(string $tag)
	{
		$this->tags[$tag] = true;
	}

	/**
	 * Set that the object is only valid if the tag matches the value.
	 *
	 * @param string $tag  the tag
	 * @param ?string $value  the value
	 */
	public function matchTagValue(string $tag, ?string $value)
	{
		$this->tags[$tag] = $value;
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
	 * Get tag
	 *
	 * @return array
	 */
	public function getTags(): array
	{
		return $this->tags;
	}

	/**
	 * Has tag
	 *
	 * @param  string $key  the tag key
	 * @return bool
	 */
	public function hasTag(string $key): bool
	{
		return isset($this->tags[$key]);
	}

	/**
	 * Get tag
	 *
	 * @param  string $key  the tag key
	 * @return string, null or true
	 */
	public function getTag(string $key)
	{
		return $this->tags[$key] ?? null;
	}

	/**
	 * Set the key order for the tags
	 *
	 * @param array $keyorder  the key order
	 */
	public function setKeyOrder(array $keyorder): void
	{
		foreach ($keyorder as $key) {
			$tags[$key] = $this->tags[$key] ?? null;
		}
		$this->tags = $tags;
		$this->keyorder = $keyorder;
	}

	/**
	 * Create a matcher for this taggable to find other
	 * similar taggables.
	 *
	 * @return TagMatcher  the matcher object
	 */
	public function createMatcher(): TagMatcher
	{
		return new TagMatcher($this->tags, $this->keyorder);
	}

	/**
	 * To string
	 *
	 * @return string  text representation of object
	 */
	public function __toString(): string
	{
		$text = "";
		foreach ($this->getTags() as $key => $value) {
			$text.= "#$key";
			if ($value === true) {
			} elseif ($value === null) {
				$text.= "=";
			} else {
				$text.= "=$value";
			}
			$text.= " ";
		}
		return rtrim($text);
	}
}
