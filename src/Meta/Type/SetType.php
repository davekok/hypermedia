<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use Ds\Set;
use stdClass;

/**
 * Class SetType
 * @package Sturdy\Activity\Meta\Type
 */
final class SetType extends Type
{
	const type = "set";
	private $options;
	private $placeHolder;

	/**
	 * Constructor
	 *
	 * @param string|null $state  the objects state
	 */
	public function __construct(string $state = null)
	{
		$this->options = new Set;
		if ($state !== null) {
			$state = explode("\x1E", $state);
			$this->placeHolder = "" === ($placeHolder = array_shift($state)) ? null : $placeHolder;
			$this->options->add(...$state);
		}
	}

	/**
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		return self::type.":".$this->placeHolder."\x1E".$this->options->join("\x1E");
	}

	/**
	 * Get set options
	 *
	 * @return Set
	 */
	public function getOptions(): ?Set
	{
		return $this->options;
	}

	/**
	 * Add option
	 *
	 * @param string $option
	 * @return EnumType
	 */
	public function addOption(string $option): self
	{
		$this->options->add($option);

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
			$meta->options = $this->options->toArray();
		}
	}

	/**
	 * Filter value
	 *
	 * @param  &$value string the value to filter
	 * @return bool  whether the value is valid
	 */
	public function filter(&$values): bool
	{
		if (!is_string($values)) return false;
		return $this->options->contains(...explode(',', $values));
	}
}
