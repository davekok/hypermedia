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
	private $resource;
	private $templated;
	private $label;

	public function __construct(Translator $translator, string $basePath, CacheItem_Resource $resource)
	{
		$this->translator = $translator;
		$this->basePath = $basePath;
		$this->resource = $resource;
		foreach ($this->resource->getFields()??[] as [$name, $type, $defaultValue, $flags, $autocomplete]) {
			$flags = new FieldFlags($flags);
			if ($flags->isMeta()) {
				$this->templated = true;
				return;
			}
		}
	}

	public function setLabel(string $label, array $values = [])/*: object */
	{
		$this->label = ($this->translator)($label, $values);
	}

	public function expand(array $values = [], bool $allowTemplated = true)/*: object */
	{
		$class = $this->resource->getClass();
		$obj = new stdClass;
		$obj->href = rtrim($this->basePath, "/") . "/" . trim(strtr($class, "\\", "/"), "/");
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
		if ($this->label) {
			$obj->label = $this->label;
		}
		return $obj;
	}

	public function getTemplated(): bool
	{
		return $this->templated;
	}
}
