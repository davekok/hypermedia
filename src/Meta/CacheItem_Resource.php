<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

/**
 * Cache item for resource
 *
 * Intended as package private class
 */
class CacheItem_Resource extends CacheItem_UnitItem
{
	private $fields;
	private $verbs;

	/**
	 * Set field
	 */
	public function setField(string $key, string $type, $default, int $flags = 0, ?string $autocomplete = null, ?string $validation = null, ?string $link = null): void
	{
		$this->fields[$key] = [$type, $default, $flags, $autocomplete, $validation, $link];
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
	 */
	public function setVerb(string $key, string $method, int $status = Verb::OK, ?string $location = null, bool $self = true): void
	{
		$this->verbs[$key] = [$method, $status, $location, $self];
	}

	/**
	 * Get verb
	 *
	 * @return [string $method, int $status, ?string $location, bool $self]
	 */
	public function getVerb(string $key): array
	{
		return $this->verb[$key];
	}

	/**
	 * Clear cache item
	 */
	public function clear(): void
	{
		$this->fields = null;
		$this->verbs = null;
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
