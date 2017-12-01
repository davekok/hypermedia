<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

/**
 * Cache item for resource
 *
 * Intended as package private class
 */
class CacheItem_Resource extends CacheItem_UnitItem
{
	private $label;
	private $section;
	private $component;
	private $layout;
	private $fields;
	private $verbs;

	/**
	 * Set section
	 *
	 * @param ?string $section
	 */
	public function setHints(?string $label, ?string $section, ?string $component, ?string $layout): self
	{
		$this->label = $label;
		$this->section = $section;
		$this->component = $component;
		$this->layout = $layout;
		return $this;
	}

	/**
	 * Get section
	 *
	 * @return [?$label, ?$section, ?$component, ?$layout]
	 */
	public function getHints(): array
	{
		return [$this->label, $this->section, $this->component, $this->layout];
	}

	/**
	 * Add field
	 *
	 * @return $this
	 */
	public function addField(string $name, string $type, $default, int $flags = 0, ?string $autocomplete = null, ?string $label = null): self
	{
		$this->fields[] = [$name, $type, $default, $flags, $autocomplete, $label];
		return $this;
	}

	/**
	 * Get fields
	 *
	 * @return [string $name, string $type, $default, int $flags, ?string $autocomplete, string $label]
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
	public function setVerb(string $key, string $method, int $flags): self
	{
		$this->verbs[$key] = [$method, $flags];
		return $this;
	}

	/**
	 * Get verb
	 *
	 * @return [string $method, int $flags]
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
