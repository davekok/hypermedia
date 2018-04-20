<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;

/**
 * Float type
 */
final class FloatType extends Type
{
	const type = "float";
	private $minimumRange;
	private $maximumRange;
	private $step;

	/**
	 * Constructor
	 *
	 * @param string|null $state  the objects state
	 */
	public function __construct(string $state = null)
	{
		if ($state !== null) {
			[$min, $max, $step] = explode(",", $state);
			$this->minimumRange = strlen($min) ? (float)$min : null;
			$this->maximumRange = strlen($max) ? (float)$max : null;
			$this->step = strlen($step) ? (float)$step : null;
		}
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
		if (isset($this->minimumRange)) {
			$meta->min = $this->minimumRange;
		}
		if (isset($this->maximumRange)) {
			$meta->max = $this->maximumRange;
		}
		if (isset($this->step)) {
			$meta->step = $this->step;
		}
	}

	/**
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		return self::type.":".$this->minimumRange.",".$this->maximumRange.",".$this->step;
	}

	/**
	 * Set minimum range
	 *
	 * @param ?float $minimumRange
	 * @return self
	 */
	public function setMinimumRange(?float $minimumRange): self
	{
		$this->minimumRange = $minimumRange;
		return $this;
	}

	/**
	 * Get minimum range
	 *
	 * @return ?float
	 */
	public function getMinimumRange(): ?float
	{
		return $this->minimumRange;
	}

	/**
	 * Set maximum range
	 *
	 * @param ?float $maximumRange
	 * @return self
	 */
	public function setMaximumRange(?float $maximumRange): self
	{
		$this->maximumRange = $maximumRange;
		return $this;
	}

	/**
	 * Get maximum range
	 *
	 * @return ?float
	 */
	public function getMaximumRange(): ?float
	{
		return $this->maximumRange;
	}

	/**
	 * Set step
	 *
	 * @param ?float $step
	 * @return self
	 */
	public function setStep(?float $step): self
	{
		$this->step = $step;
		return $this;
	}

	/**
	 * Get step
	 *
	 * @return ?float
	 */
	public function getStep(): ?float
	{
		return $this->step;
	}

	/**
	 * Filter value
	 *
	 * @param  &$value  the value to filter
	 * @return bool  whether the value is valid
	 */
	public function filter(&$value): bool
	{
		if (is_string($value)) $value = trim($value);
		$float = filter_var($value, FILTER_VALIDATE_FLOAT);
		if ($float === false) {
			return false;
		}
		if (isset($this->minimumRange) && $float < $this->minimumRange) {
			return false;
		}
		if (isset($this->maximumRange) && $float > $this->maximumRange) {
			return false;
		}
		if (isset($this->step)) {
			// ratio should be an integer if value is a multiple of step
			$ratio = (($float-($this->minimumRange??0.0)) / $this->step);
			// distance should be 0, if ration is an integer
			$distance = abs($ratio - round($ratio, 0));
			// allow distance to be a little bit imprecise to allow for floating point rounding errors
			if ($distance > 0.00001) {
				return false;
			}
		}
		$value = $float;
		return true;
	}
}
