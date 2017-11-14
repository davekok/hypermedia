<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

/**
 * Cache item for resource
 *
 * Intended as package private class
 */
class CacheItem_Resource extends CacheItem_UnitItem
{
	private $section;
	private $fields;
	private $verbs;

	/**
	 * Set section
	 *
	 * @param ?string $section
	 */
	public function setSection(?string $section): self
	{
		$this->section = $section;
		return $this;
	}

	/**
	 * Get section
	 *
	 * @return ?string
	 */
	public function getSection(): ?string
	{
		return $this->section;
	}

	/**
	 * Set field
	 *
	 * @return $this
	 */
	public function setField(string $key, string $type, $default, int $flags = 0, ?string $autocomplete = null): self
	{
		$this->fields[$key] = [$type, $default, $flags, $autocomplete];
		return $this;
	}

	/**
	 * Get fields
	 *
	 * @return [string $key => [string $type, int $flags, ?string $autocomplete, string $validation, string $link]]
	 */
	public function getFields()
	{
		return $this->fields;
	}

	/**
	 * Set verb
	 *
	 * @return $this
	 */
	public function setVerb(string $key, string $method, int $status = Verb::OK, ?string $location = null, bool $self = true, bool $data = true): self
	{
		$this->verbs[$key] = [$method, $status, $location, $self, $data];
		return $this;
	}

	/**
	 * Get verb
	 *
	 * @return [string $method, int $status, ?string $location, bool $self]
	 */
	public function getVerb(string $key): array
	{
		return $this->verbs[$key];
	}

	/**
	 * Clear cache item
	 *
	 * @return $this
	 */
	public function clear(): self
	{
		$this->section = null;
		$this->fields = null;
		$this->verbs = null;
		return $this;
	}

	/**
	 * Whether the cache item is valid.
	 *
	 * @return bool  whether the cache item is valid
	 */
	public function valid(): bool
	{
		return !empty($this->verbs);
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	public function getType(): string
	{
		return 'Resource';
	}
}
