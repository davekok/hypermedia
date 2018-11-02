<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;
use Sturdy\Activity\Meta\CacheItem_Resource;
use Sturdy\Activity\Meta\FieldFlags;
use Sturdy\Activity\Response\InternalServerError;
use Sturdy\Activity\SharedStateStore;

/**
 * A HyperMedia Link
 */
final class Link
{
	private $store;
	private $translator;
	private $basePath;
	private $namespace;
	private $resource;
	private $templated;
	private $name;
	private $slot;
	private $label;
	private $icon;
	private $disabled;
	private $target;
	private $phase;
	private $mainClass;
	private $mainQuery;

	public function __construct(SharedStateStore $store, Translator $translator, string $basePath, string $namespace, ?CacheItem_Resource $resource, bool $mainClass = false, array $mainQuery = [])
	{
		$this->store      = $store;
		$this->translator = $translator;
		$this->basePath   = $basePath;
		$this->namespace  = $namespace;
		$this->resource   = $resource;
		$this->mainClass  = $mainClass;
		$this->mainQuery  = $mainQuery;
		if ($this->resource) {
			foreach ($this->resource->getFields()??[] as [$name, $type, $defaultValue, $flags, $autocomplete, $label, $icon, $pool]) {
				$flags = new FieldFlags($flags);
				if ($flags->isMeta()) {
					$this->templated = true;
					return;
				}
			}
		}
	}

	/**
	 * Expand link with values.
	 *
	 * @param  array   &$values          the values, is updated if shared state is in play
	 * @param  bool     $allowTemplated  whether a templated link is allowed
	 * @return object
	 */
	public function expand(array &$values = [], bool $allowTemplated = true)/*: object */
	{
		$obj = new stdClass;

		if ($this->resource !== null) {
			$class = $this->resource->getClass();
			$path = strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', substr($class, strlen($this->namespace))));
			$obj->href = $this->basePath . trim(strtr($path, "\\", "/"), "/");
			$known = "";
			$unknown = "";
			$selectedTrue = false;
			$selectedFalse = false;

			foreach ($this->resource->getFields()??[] as [$name, $type, $defaultValue, $flags, $autocomplete, $label, $icon, $pool]) {
				$flags = new FieldFlags($flags);
				if ($flags->isMeta()) {
					if ($flags->isReadonly() || $flags->isDisabled()) continue;
					if (array_key_exists($name, $values)) {
						$value = $this->getValue($values, $name);
					} else if ($flags->isShared() && $this->store->has($pool, $name)) {
						$value = $values[$name] = $this->store->get($pool, $name);
					} else if ($allowTemplated) {
						$unknown.= "," . $name;
						if ($this->mainClass && !isset($this->mainQuery[$name])) {
							$selectedTrue = true;
						} else {
							$selectedFalse = true;
						}
						continue;
					} else if ($flags->isRequired()) {
						throw new InternalServerError("Attempted to create link to $class but required field $name is missing.");
					} else if ($this->mainClass && isset($this->mainQuery[$name])) {
						$selectedFalse = true;
						continue;
					} else {
						continue;
					}
					$known.= "&" . $name . "=" . $value;
					if ($this->mainClass && isset($this->mainQuery[$name]) && $this->mainQuery[$name] === $value) {
						$selectedTrue = true;
					} else {
						$selectedFalse = true;
					}
				} else if ($flags->isState()) {
					if (array_key_exists($name, $values)) {
						$value = $this->getValue($values, $name);
					} else if ($flags->isShared() && $this->store->has($pool, $name)) {
						$value = $values[$name] = $this->store->get($pool, $name);
					} else if (!$allowTemplated && $flags->isRequired()) {
						throw new InternalServerError("Attempted to create link to $class but required field $name is missing.");
					} else if ($this->mainClass && isset($this->mainQuery[$name])) {
						$selectedFalse = true;
						continue;
					} else {
						continue;
					}
					$known.= "&" . $name . "=" . $value;
					if ($this->mainClass && isset($this->mainQuery[$name]) && $this->mainQuery[$name] === $value) {
						$selectedTrue = true;
					} else {
						$selectedFalse = true;
					}
				}
			}

			if ($known) {
				$known[0] = "?";
				if ($unknown) $unknown[0] = "&";
			} else if ($unknown) {
				$unknown[0] = "?";
			}
			if ($known) {
				$obj->href.= $known;
			}
			if ($unknown) {
				$obj->href.= "{" . $unknown . "}";
			}
			if (!empty($unknown)) {
				$obj->templated = true;
			}
			if ($selectedTrue && !$selectedFalse) {
				$obj->selected = true;
			}
		} else {
			$obj->disabled = true;
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
		if ($this->disabled) {
			$obj->disabled = $this->disabled;
		}
		if ($this->target) {
			$obj->target = $this->target;
		}
		if ($this->phase !== null) {
			$obj->phase = $this->phase;
		}
		return $obj;
	}

	private function getValue(array $values, string $key): string
	{
		$value = $values[$key];
		if ($value instanceof \JsonSerializable) {
			$value = $value->jsonSerialize();
		} else {
			$value = (string)$value;
		}
		return urlencode($value);
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

	public function setDisabled(?bool $disabled): void
	{
		$this->disabled = $disabled;
	}

	public function getDisabled(): ?bool
	{
		return $this->disabled;
	}

	public function setTarget(?string $target): void
	{
		$this->target = $target;
	}

	public function getTarget(): ?string
	{
		return $this->target;
	}

	public function setPhase(?int $phase): void
	{
		$this->phase = $phase;
	}

	public function getPhase(): ?int
	{
		return $this->phase;
	}
}
