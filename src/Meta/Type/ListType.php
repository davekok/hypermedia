<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;

final class ListType extends Type
{
	const type = "list";

	private $link;
	private $placeHolder;

	/**
	 * Constructor
	 *
	 * @param string|null $state the objects state
	 */
	public function __construct(string $state = null)
	{
		if ($state !== null) {
			[$this->placeHolder, $this->link] = explode("\x1E", $state);
		}
	}

	/**
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		return self::type . ":" . $this->placeHolder . "\x1E" . $this->link;
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
		$meta->link = $this->link;
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
	 * Set link
	 *
	 * @param string $link
	 * @return self
	 */
	public function setLink(string $link): self
	{
		$this->link = $link;
		return $this;
	}

	/**
	 * Get link
	 *
	 * @return string
	 */
	public function getLink(): string
	{
		return $this->link;
	}

	/**
	 * Filter value
	 *
	 * @param  &$value string the value to filter
	 * @return bool whether the value is valid
	 */
	public function filter(&$value): bool
	{
		$string = filter_var($value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
		if ($string === false) {
			return false;
		}
		return true;
//		return 1 === preg_match("/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/", $value);
	}
}
