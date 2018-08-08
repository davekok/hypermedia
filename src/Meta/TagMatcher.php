<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

/**
 * TagMatcher
 */
class TagMatcher
{
	private $alltags = [];
	private $tags = [];
	private $shouldHave = [];
	private $mustNotHave = [];

	/**
	 * Init tags for usage by findBestMatch
	 *
	 * @param  array  $tags     tags similar
	 * @param  array  $alltags  all possible tags
	 */
	public function __construct(array $tags, array $alltags)
	{
		$this->alltags = $alltags;
		$this->setTags($tags);
	}

	/**
	 * Set tags
	 *
	 * @param array  $tags     tags similar
	 */
	public function setTags(array $tags): self
	{
		$this->tags = $tags;
		$this->shouldHave = [];
		$this->mustNotHave = [];
		foreach ($this->alltags as $tag) {
			if (isset($tags[$tag])) {
				$this->shouldHave[$tag] = $tags[$tag];
			} else {
				$this->mustNotHave[] = $tag;
			}
		}
		return $this;
	}

	/**
	 * Get tags
	 *
	 * @return array  tags used to match with
	 */
	public function getTags(): array
	{
		return $this->tags;
	}

	/**
	 * Find the best match for given tags.
	 *
	 * @param array $taggables    the taggables to search
	 * @return ?Taggable          the best matching taggable
	 */
	public function findBestMatch(array $taggables): ?Taggable
	{
		// find best matching taggable
		$mostSpecific = 0;
		$matches = [];
		foreach ($taggables as $ix => $taggable) {
			foreach ($this->mustNotHave as $tag) {
				if ($taggable->hasTag($tag)) {
					continue 2;
				}
			}
			foreach ($this->shouldHave as $tag => $value) {
				if ($taggable->hasTag($tag) && $taggable->getTag($tag) != $value && $taggable->getTag($tag) !== true) {
					continue 2;
				}
			}
			$specific = 0;
			foreach ($this->shouldHave as $tag => $value) {
				if ($taggable->hasTag($tag)) {
					if ($taggable->getTag($tag) == $value) {
						$specific += 2;
					} elseif ($taggable->getTag($tag) === true) {
						++$specific;
					}
				}
			}
			if ($specific < $mostSpecific) {
				continue;
			} elseif ($specific > $mostSpecific) {
				$mostSpecific = $specific;
				$matches = [$taggable];
			} else {
				$matches[] = $taggable;
			}
		}
		switch (count($matches)) {
			case 0:
				return null;
			case 1:
				return reset($matches);
			default:
				foreach ($this->shouldHave as $tag => $value) {
					$tagFound = false;
					foreach ($matches as $taggable) {
						if ($taggable->hasTag($tag)) {
							$tagFound = true;
							break;
						}
					}
					if ($tagFound) {
						foreach ($matches as $ix => $taggable) {
							if (!$taggable->hasTag($tag)) {
								unset($matches[$ix]);
							}
						}
					}
				}
				return reset($matches);
		}
	}
}
