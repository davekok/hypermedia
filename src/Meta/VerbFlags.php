<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use InvalidArgumentException;

/**
 * Verb flags
 */
class VerbFlags
{
	private const ok = 0;
	private const noContent = 1;
	private const seeOther = 2;
	private const maxStatus = 15;

	private const hidden = 0; // no parts are used
	private const links = 16; // links part is used
	private const fields = 32; // links and fields parts are used
	private const data = 48; // links, fields and data parts are used

	private const root = 64; // a root resource

	private $flags;

	/**
	 * Constructor
	 */
	public function __construct(int $flags = self::ok | self::data)
	{
		$this->flags = $flags;
	}

	/**
	 * Get flags as integer
	 *
	 * @return int  flags as integer
	 */
	public function toInt(): int
	{
		return $this->flags;
	}

	/**
	 * Set the status to return when the request is done.
	 *
	 * @param int $status
	 * @return self
	 */
	public function setStatus(int $status): void
	{
		$this->flags &= ~self::maxStatus; // clear bits
		switch ($status) {
			case Verb::OK:
				$this->flags |= self::ok;
				break;
			case Verb::NO_CONTENT:
				$this->flags |= self::noContent;
				break;
			case Verb::SEE_OTHER:
				$this->flags |= self::seeOther;
				break;
			default:
				new InvalidArgumentException("Unsupported status $status.");
		}
	}

	/**
	 * Get the status to return when the request is done.
	 *
	 * @return int
	 */
	public function getStatus(): int
	{
		switch ($this->flags & self::maxStatus) {
			case self::ok:        return Verb::OK;
			case self::noContent: return Verb::NO_CONTENT;
			case self::seeOther:  return Verb::SEE_OTHER;
			default:              throw new \LogicException("unkown status");
		}
	}

	/**
	 * Set whether all parts are hidden
	 */
	public function setHidden(): void
	{
		$this->flags &= ~self::data;
	}

	/**
	 * Get whether all parts are hidden
	 */
	public function isHidden(): bool
	{
		return ($this->flags & self::data) == self::hidden;
	}

	/**
	 * Set whether the links part is used.
	 */
	public function useLinks(): void
	{
		$this->flags &= ~self::data;
		$this->flags |= self::links;
	}

	/**
	 * Get whether the links part is used.
	 *
	 * @return bool
	 */
	public function hasLinks(): bool
	{
		return ($this->flags & self::data) >= self::links;
	}

	/**
	 * Set whether the fields part is used.
	 */
	public function useFields(): void
	{
		$this->flags &= ~self::data;
		$this->flags |= self::fields;
	}

	/**
	 * Get whether the fields part is used.
	 */
	public function hasFields(): bool
	{
		return ($this->flags & self::data) >= self::fields;
	}

	/**
	 * Set whether the data part is used.
	 */
	public function useData(): void
	{
		$this->flags |= self::data;
	}

	/**
	 * Get whether the data part is used.
	 *
	 * @return bool
	 */
	public function hasData(): bool
	{
		return ($this->flags & self::data) == self::data;
	}

	/**
	 * Set whether this is a root resource.
	 *
	 * @param bool $use
	 */
	public function setRoot(bool $use): void
	{
		if ($use) {
			$this->flags |= self::root;
		} else {
			$this->flags &= ~self::root;
		}
	}

	/**
	 * Get whether the root part is used.
	 *
	 * @return bool
	 */
	public function getRoot(): bool
	{
		return (bool)($this->flags & self::root);
	}

	/**
	 * Get whether the data part is used.
	 *
	 * @return bool
	 */
	public function hasSelfLink(): bool
	{
		return ($this->flags & self::data) > self::links;
	}
}
