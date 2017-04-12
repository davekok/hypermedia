<?php declare(strict_types=1);

namespace Sturdy\Activity\Entity;


interface Dimension
{
	/**
	 * Get id
	 *
	 * @return int
	 */
	public function getId(): int;

	/**
	 * Set dimension
	 *
	 * @param Name $dimension
	 * @return self
	 */
	public function setDimension(Name $dimension): self;

	/**
	 * Get dimension
	 *
	 * @return Name
	 */
	public function getDimension(): Name;

	/**
	 * Set value
	 *
	 * @param mixed $value
	 * @return self
	 */
	public function setValue($value): self

	/**
	 * Get value
	 *
	 * @return mixed
	 */
	public function getValue()
}
