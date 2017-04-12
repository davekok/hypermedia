<?php declare(strict_types=1);

namespace Sturdy\Activity\Entity;


interface Name
{
	/**
	 * Get id
	 *
	 * @return int
	 */
	public function getId(): int;

	/**
	 * Set name
	 *
	 * @param string $name
	 * @return self
	 */
	public function setName(string $name): self;

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName(): string
}
