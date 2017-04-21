<?php declare(strict_types=1);

namespace Sturdy\Activity\Annotation;

use \Doctrine\Common\Annotations\Annotation\{Annotation,Target,Attributes,Attribute};

/**
 * @Annotation
 * @Target({"METHOD"})
 * @Attributes({
 *   @Attribute("value", type = "string"),
 * })
 */
final class Action
{
	/**
	 * @var bool
	 */
	private $start;

	/**
	 * @var string
	 */
	private $next;

	/**
	 * @var array
	 */
	private $dimensions;

	/**
	 * Constructor
	 */
	public function __construct(array $values)
	{
		$text = $values["value"]??"";

		$retval = '(true|false|0|[1-9][0-9]*)';
		$func = '((?:[A-Za-z\\\\_][A-Za-z0-9\\\\_]+::)?[A-Za-z_][A-Za-z0-9_]+)';

		$dim = '([A-Za-z_][A-Za-z0-9_]+)';
		$val = '(\S*)';

		$done = "";
		$this->start = false;
		$this->dimensions = [];
		while (strlen($text) > 0) {
			if (preg_match("/^\s+/", $text, $matches)) {
				$done.= $matches[0];
				$text = substr($text, strlen($matches[0]));
			} elseif ($this->start === false && preg_match("/^start(?=\s|$)/", $text, $matches)) {
				$this->start = true;
				$done.= $matches[0];
				$text = substr($text, strlen($matches[0]));
			} elseif ($this->next === null && preg_match("/^=>$func(?=\s|$)/", $text, $matches)) {
				$this->next = $matches[1];
				$done.= $matches[0];
				$text = substr($text, strlen($matches[0]));
			} elseif (!is_string($this->next) && preg_match("/^$retval=>$func(?=\s|$)/", $text, $matches)) {
				if ($this->next === null) $this->next = [];
				$this->next[$matches[1]] = $matches[2];
				$done.= $matches[0];
				$text = substr($text, strlen($matches[0]));
			} elseif (preg_match("/^#$dim=$val(?=\s|$)/", $text, $matches)) {
				$this->dimensions[$matches[1]] = $matches[2];
				$done.= $matches[0];
				$text = substr($text, strlen($matches[0]));
			} else {
				throw new \LogicException("Syntax error near |$done| |$text|.");
			}
		}
	}

	/**
	 * This is the first action.
	 *
	 * @return whether this is the first action.
	 */
	public function getStart(): bool
	{
		return $this->start;
	}

	/**
	 * Get which action comes next.
	 *
	 * @return either null, a string or an array of possible actions.
	 */
	public function getNext()
	{
		return $this->next;
	}

	/**
	 * Get dimensions
	 *
	 * @return a array of Dimension objects
	 */
	public function getDimensions(): array
	{
		return $this->dimensions;
	}
}
