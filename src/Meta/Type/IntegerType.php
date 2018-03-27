<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;

/**
 * Integer type
 */
final class IntegerType extends Type
{
	const type = "integer";
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
			$this->minimumRange = strlen($min) ? ($min[0] == '$' ? $min : (int)$min) : null;
			$this->maximumRange = strlen($max) ? ($max[0] == '$' ? $max : (int)$max) : null;
			$this->step = strlen($step) ? (int)$step : null;
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
			if (is_int($this->minimumRange)) {
				$meta->min = $this->minimumRange;
			} else {
				$key = substr($this->minimumRange,1);
				if (isset($state[$key])) {
					$meta->min = $state[$key];
				}
			}
		}
		if (isset($this->maximumRange)) {
			if (is_int($this->maximumRange)) {
				$meta->max = $this->maximumRange;
			} else {
				$key = substr($this->maximumRange,1);
				if (isset($state[$key])) {
					$meta->max = $state[$key];
				}
			}
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
	 * @param ?int $minimumRange
	 * @return self
	 */
	public function setMinimumRange(?string $minimumRange): self
	{
		if ($minimumRange === null || $minimumRange[0] == '$') {
			$this->minimumRange = $minimumRange;
		} else {
			$this->minimumRange = (int)$minimumRange;
		}

		return $this;
	}

	/**
	 * Get minimum range
	 *
	 * @return ?int
	 */
	public function getMinimumRange()
	{
		return $this->minimumRange;
	}

	/**
	 * Set maximum range
	 *
	 * @param ?int $maximumRange
	 * @return self
	 */
	public function setMaximumRange(?string $maximumRange): self
	{
		if ($maximumRange === null || $maximumRange[0] == '$') {
			$this->maximumRange = $maximumRange;
		} else {
			$this->maximumRange = (int)$maximumRange;
		}

		return $this;
	}

	/**
	 * Get maximum range
	 *
	 * @return ?int
	 */
	public function getMaximumRange()
	{
		return $this->maximumRange;
	}

	/**
	 * Set step
	 *
	 * @param ?int $step
	 * @return self
	 */
	public function setStep(?string $step): self
	{
		if ($step === null || $step[0] == '$') {
			$this->step = $step;
		} else {
			$this->step = (int)$step;
		}
		return $this;
	}

	/**
	 * Get step
	 *
	 * @return ?int
	 */
	public function getStep(): ?int
	{
		return $this->step;
	}

	/**
	 * Filter value
	 *
	 * @param  &$value integer the value to filter
	 * @return bool  whether the value is valid
	 */
	public function filter(&$value): bool
	{
		if (is_string($value)) $value = trim($value);
		$integer = filter_var($value, FILTER_VALIDATE_INT);
		if ($integer === false) {
			return false;
		}
		if (isset($this->minimumRange) && $integer < $this->minimumRange) {
			return false;
		}
		if (isset($this->maximumRange) && $integer > $this->maximumRange) {
			return false;
		}
		if (isset($this->step) && 0 !== (($integer - ($this->minimumRange ?? 0)) % $this->step)) {
			return false;
		}
		$value = $integer;
		return true;
	}
}
