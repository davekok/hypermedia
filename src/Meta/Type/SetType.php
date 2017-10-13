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

	/**
	 * Constructor
	 *
	 * @param array|null $state  the objects state
	 */
	public function __construct(array $state = null)
	{
		if($state !== null){
			[$options] = $state;
			if (strlen($options)) $this->options = new Set(...explode(";", $options));
		}
	}

	/**
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		$options = implode(";",$this->options);
		return self::type.",".$options;
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
	 * Set set options
	 *
	 * @param mixed $options
	 * @return SetType
	 */
	public function setOptions(array $options): self
	{
		$this->options = $options;
		return $this;
	}

	/**
	 * Set meta properties on object
	 *
	 * @param stdClass $meta
	 */
	public function meta(stdClass $meta): void
	{
		$meta->type = self::type;
		if(isset($this->options)) {
			$meta->options = $this->options;
		}
	}

	/**
	 * Filter value
	 *
	 * @param  &$value the value to filter
	 * @return bool  whether the value is valid
	 */
	public function filter(&$values): bool
	{
		return $this->options->contains(...explode(',', $values));
	}
}
