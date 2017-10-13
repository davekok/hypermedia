<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use Ds\Set;
use stdClass;

final class EnumType extends Type
{
	const type = "enum";
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
		return self::type.",".$this->options->join(';');
	}

	/**
	 * Get all possible options
	 *
	 * @return Set
	 */
	public function getOptions(): ?Set
	{
		return $this->options;
	}

	/**
	 * Set possible options
	 *
	 * @param mixed $options
	 * @return EnumType
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
		if ($this->options->count()) {
			$meta->options = $this->options->toArray();
		}
	}

	/**
	 * Filter value
	 *
	 * @param  &$value  the value to filter
	 * @return bool  whether the value is valid
	 */
	public function filter(&$value): bool
	{
		return $this->options->contains($value = trim($value));
	}
}

