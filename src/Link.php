<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;
use Sturdy\Activity\Meta\CacheItem_Resource;
use Sturdy\Activity\Meta\FieldFlags;
use Sturdy\Activity\Response\InternalServerError;

/**
 * A HyperMedia Link
 */
final class Link
{
	private $translator;
	private $basePath;
	private $namespace;
	private $resource;
	private $templated;
	private $name;
	private $slot;
	private $label;
	private $icon;
	private $selected;
	private $target;

	public function __construct(Translator $translator, string $basePath, string $namespace, CacheItem_Resource $resource)
	{
		$this->translator = $translator;
		$this->basePath = $basePath;
		$this->namespace = $namespace;
		$this->resource = $resource;
		foreach ($this->resource->getFields()??[] as [$name, $type, $defaultValue, $flags, $autocomplete]) {
			$flags = new FieldFlags($flags);
			if ($flags->isMeta()) {
				$this->templated = true;
				return;
			}
		}
	}

	public function expand(array $values = [], bool $allowTemplated = true)/*: object */
	{
		$class = $this->resource->getClass();
		$obj = new stdClass;
		$path = strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', substr($class, strlen($this->namespace))));
		$obj->href = $this->basePath . trim(strtr($path, "\\", "/"), "/");
		$known = "";
		$unknown = "";
		foreach ($this->resource->getFields()??[] as [$name, $type, $defaultValue, $flags, $autocomplete]) {
			$flags = new FieldFlags($flags);
			if ($flags->isMeta()) {
				if ($flags->isReadonly() || $flags->isDisabled()) continue;
				if (array_key_exists($name, $values)) {
					$known.= "&" . $name . "=" . urlencode((string)$values[$name]);
				} elseif ($allowTemplated) {
					$unknown.= "," . $name;
				} elseif ($flags->isRequired()) {
					throw new InternalServerError("Attempted to create link to $class but required field $name is missing.");
				}
			} elseif ($flags->isState()) {
				if (array_key_exists($name, $values)) {
					$known.= "&" . $name . "=" . urlencode((string)$values[$name]);
				} elseif (!$allowTemplated && $flags->isRequired()) {
					throw new InternalServerError("Attempted to create link to $class but required field $name is missing.");
				}
			}
		}
		if ($known) {
			$known[0] = "?";
			$obj->href.= $known;
			if ($unknown) {
				$unknown[0] = "&";
				$obj->href.= "{" . $unknown . "}";
			}
		} elseif ($unknown) {
			$unknown[0] = "?";
			$obj->href.= "{" . $unknown . "}";
		}
		if (!empty($unknown)) {
			$obj->templated = true;
		}
		if ($this->name) {
			$obj->name = $this->name;
		}
		if ($this->slot) {
			$obj->slot = $this->slot;
		}
		if ($this->label) {
			$obj->label = $this->label;
		}
		if ($this->icon) {
			$obj->icon = $this->icon;
		}
		if ($this->selected) {
			$obj->selected = $this->selected;
		}
		if ($this->target) {
			$obj->target = $this->target;
		}
		return $obj;
	}

	public function getTemplated(): bool
	{
		return $this->templated;
	}

	public function setName(string $name): void
	{
		$this->name = $name;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setSlot(?string $slot): void
	{
		$this->slot = $slot;
	}

	public function getSlot(): ?string
	{
		return $this->slot;
	}

	public function setLabel(?string $label, array $values = []): void
	{
		if ($label === null) {
			$this->label = null;
		} else {
			$this->label = ($this->translator)($label, $values);
		}
	}

	public function getLabel(): ?string
	{
		return $this->label;
	}

	public function setIcon(?string $icon): void
	{
		$this->icon = $icon;
	}

	public function getIcon(): ?string
	{
		return $this->icon;
	}

	public function setSelected(?bool $selected): void
	{
		$this->selected = $selected;
	}

	public function getSelected(): ?bool
	{
		return $this->selected;
	}

	public function setTarget(?string $target): void
	{
		$this->target = $target;
	}

	public function getTarget(): ?string
	{
		return $this->target;
	}
}
