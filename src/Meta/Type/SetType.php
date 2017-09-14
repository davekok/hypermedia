<?php

namespace Sturdy\Activity\Meta\Type;
use Doctrine\Common\Annotations\Annotation\Enum;
use Doctrine\Common\Annotations\Annotation\IgnoreAnnotation;

/**
 * Class SetType
 * @package Sturdy\Activity\Meta\Type
 */
final class SetType
{
	
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
			if (strlen($options)) $this->options = explode(";", $options);
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
		return "set,$options";
	}
	
	/**
	 * Get set options
	 *
	 * @return mixed
	 */
	public function getOptions(): ?array
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
		$meta->type = "set";
		if(isset($this->options)) {
			$meta->options = $this->options;
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
		$set = new \Ds\Set;
		$value = explode(",", $value);
		$value = array_combine($value, array_fill(0, count($value), true));
		foreach ($value as $option => $set) {
			if (!in_array($option, $this->options)) return false;
		}
		return true;
	}
}
