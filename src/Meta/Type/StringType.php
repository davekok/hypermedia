<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;

/**
 * String type
 */
final class StringType extends Type
{
	private $minimumLength;
	private $maximumLength;
	private $patternName;
	private $pattern;

	/**
	 * Constructor
	 *
	 * @param array|null $state  the objects state
	 */
	public function __construct(array $state = null)
	{
		if ($state !== null) {
			[$min, $max, $patternName] = $state;
			if (strlen($min) > 0) $this->minimumLength = (int)$min;
			if (strlen($max) > 0) $this->maximumLength = (int)$max;
			if (strlen($patternName) > 0) {
				$this->patternName = $patternName;
				$this->pattern = constant($patternName);
			}
		}
	}

	/**
	 * Set meta properties on object
	 *
	 * @param stdClass $meta
	 */
	public function meta(stdClass $meta): void
	{
		$meta->type = "string";
		if (isset($this->minimumLength)) {
			$meta->minlength = $this->minimumLength;
		}
		if (isset($this->maximumLength)) {
			$meta->maxlength = $this->maximumLength;
		}
		if (isset($this->pattern)) {
			$meta->pattern = $this->pattern;
		}
	}

	/**
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		return "string,".$this->minimumLength.",".$this->maximumLength.",".$this->patternName;
	}

	/**
	 * Set minimumLength
	 *
	 * @param ?int $minimumLength
	 * @return self
	 */
	public function setMinimumLength(?int $minimumLength): self
	{
		$this->minimumLength = $minimumLength;
		return $this;
	}

	/**
	 * Get minimumLength
	 *
	 * @return ?int
	 */
	public function getMinimumLength(): ?int
	{
		return $this->minimumLength;
	}

	/**
	 * Set maximum length
	 *
	 * @param ?int $maximumLength
	 * @return self
	 */
	public function setMaximumLength(?int $maximumLength): self
	{
		$this->maximumLength = $maximumLength;
		return $this;
	}

	/**
	 * Get maximum length
	 *
	 * @return ?int
	 */
	public function getMaximumLength(): ?int
	{
		return $this->maximumLength;
	}

	/**
	 * Set pattern name
	 *
	 * @param ?string $patternName
	 * @return self
	 */
	public function setPatternName(?string $patternName): self
	{
		$this->patternName = $patternName;
		if ($patternName !== null) {
			$this->pattern = constant($patternName);
		} else {
			$this->pattern = null;
		}
		return $this;
	}

	/**
	 * Get pattern name
	 *
	 * @return ?string
	 */
	public function getPatternName(): ?string
	{
		return $this->patternName;
	}

	/**
	 * Get pattern name
	 *
	 * @return ?string
	 */
	public function getPattern(): ?string
	{
		return $this->pattern;
	}

	/**
	 * Filter value
	 *
	 * @param  &$value  the value to filter
	 * @return bool  whether the value is valid
	 */
	public function filter(&$value): bool
	{
		$value = trim(filter_var($value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW));
		if ($value === false) {
			return false;
		}
		if (isset($this->minimumLength) && strlen($value) < $this->minimumLength) {
			return false;
		}
		if (isset($this->maximumLength) && strlen($value) > $this->maximumLength) {
			return false;
		}
		if (isset($this->pattern)) {
			return preg_match($this->pattern, $value);
		}
		return true;
	}
}
