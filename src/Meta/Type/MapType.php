<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use Ds\Map;
use stdClass;
use Sturdy\Activity\Expression;

final class MapType extends Type
{
	const type = "map";
	private $options = [];

	/**
	 * Constructor
	 *
	 * @param string|null $state  the objects state
	 */
	public function __construct(string $state = null)
	{
		$this->options = new Map;
		if ($state !== null) {
			foreach (explode("\x1E", $state) as $option) {
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
		$i = 0;
		foreach ($this->options->toArray() as $value => [$label, $expression]) {
			$descriptor .= $i++ ? "\x1E" : ":";
			$descriptor .= "$value\x1F$label\x1F$expression";
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
	 * @param Expression $expression
	 * @return MapType
	 */
	public function addOption(string $value, string $label, Expression $expression): self
	{
		$this->options->put($value, [$label, $expression]);
		return $this;
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
		if ($this->options->count()) {
			$meta->options = [];
			foreach ($this->options->toArray() as $value => [$label, $expression]) {
				if (!(Expression::evaluate($expression, $state)->active??true)) {
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

