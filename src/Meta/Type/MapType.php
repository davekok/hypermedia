<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use Ds\Map;
use stdClass;
use Sturdy\Activity\Expression;

final class MapType extends Type
{
	const type = "map";
	private $options = [];
	private $placeHolder;

	/**
	 * Constructor
	 *
	 * @param string|null $state  the objects state
	 */
	public function __construct(string $state = null)
	{
		$this->options = new Map;
		if ($state !== null) {
			$state = explode("\x1E", $state);
			$this->placeHolder = "" === ($placeHolder = array_shift($state)) ? null : $placeHolder;
			foreach ($state as $option) {
				[$value, $label, $expression] = explode("\x1F", $option);
				$this->options->put($value, [$label, $expression]);
			}
		}
	}

	/**
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		$descriptor = self::type;
		$descriptor.= ":" . $this->placeHolder;
		foreach ($this->options->toArray() as $value => [$label, $expression]) {
			$descriptor .= "\x1E$value\x1F$label\x1F$expression";
		}
		return $descriptor;
	}

	/**
	 * Get all possible options
	 *
	 * @return ?Map
	 */
	public function getOptions(): ?Map
	{
		return $this->options;
	}

	/**
	 * Add option
	 *
	 * @param string $value
	 * @param string $label
	 * @param Expression|null $expression
	 * @return MapType
	 */
	public function addOption(string $value, string $label, ?Expression $expression = null): self
	{
		$this->options->put($value, [$label, $expression]);
		return $this;
	}

	/**
	 * Set place holder
	 *
	 * @param string|null $placeHolder
	 */
	public function setPlaceHolder(?string $placeHolder): void
	{
		$this->placeHolder = $placeHolder;
	}

	/**
	 * Get place holder
	 *
	 * @return string|null
	 */
	public function getPlaceHolder(): ?string
	{
		return $this->placeHolder;
	}

	/**
	 * Set meta properties on object
	 *
	 * @param stdClass $meta
	 * @param array $state
	 */
	public function meta(stdClass $meta, array $state): void
	{
		$meta->type = self::type;
		if ($this->placeHolder) {
			$meta->placeHolder = $this->placeHolder;
		}
		if ($this->options->count()) {
			$meta->options = [];
			foreach ($this->options->toArray() as $value => [$label, $expression]) {
				if ($expression !== null && !(Expression::evaluate($expression, $state)->active??true)) {
					continue;
				}
				$meta->options[] = ["value" => $value, "label" => $label];
			}
		}
	}

	/**
	 * Filter value
	 *
	 * @param  $value string the value to filter
	 * @return bool whether the value is valid
	 */
	public function filter(&$value): bool
	{
		if (!is_string($value)) return false;
		return $this->options->hasKey($value);
	}
}
