<?php declare(strict_types=1);

namespace Sturdy\Activity\Annotation;

use \Doctrine\Common\Annotations\Annotation\{Annotation,Target,Attributes,Attribute};

/**
 * @Annotation
 * @Target({"METHOD"})
 * @Attributes({
 *   @Attribute("start", type = "bool"),
 *   @Attribute("skip" , type = "bool"),
 *   @Attribute("next" , type = "string"),
 *   @Attribute("dims" , type = "array<string:string>"),
 * })
 */
class Action
{
	/**
	 * @var bool
	 */
	private $start;

	/**
	 * @var bool
	 */
	private $skip;

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
		$this->start = $values['start']??false;
		$this->skip = $values['skip']??false;
		$this->setNext($values['next']??null);
		$this->setDimensions($values['dims']??null);
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
	 * If skip is set the action is skipped over immediately
	 * continueing with next action.
	 *
	 * @return whether this action should be skipped.
	 */
	public function getSkip(): bool
	{
		return $this->skip;
	}

	/**
	 * Set which action comes next.
	 */
	private function setNext(?string $next): void
	{
		if ($next === null) {
			$this->next = null;
		} else {
			$retval = '(?:null|true|false|0|[1-9][0-9]*)';
			$func = '(?:[A-Za-z\\\\_][A-Za-z0-9\\\\_]+::)?[A-Za-z_][A-Za-z0-9_]+';
			if (preg_match("/^$func$/", $next)) {
				$this->next = $next;
			} elseif (preg_match("/^\s*\{\s*$retval\s*:\s*$func\s*(?:,\s*$retval\s*:\s*$func\s*)+\}\s*$/", $next)) {
				if ($this->skip) throw new \InvalidArgumentException("There are no return values if action is skipped.");
				$this->next = [];
				foreach (explode(",", trim($next, "\t\r\n {}")) as $v) {
					[$retval, $func] = explode(":", trim($v));
					$this->next[$retval] = $func;
				}
			}
		}
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
	 * Set dimensions
	 *
	 * @param $dimensions  the dimension for which this next is valid.
	 */
	private function setDimensions(?array $dimensions): void
	{
		$this->dimensions = [];
		foreach ($dimensions??[] as $name=>$value) {
			$this->dimensions[$name] = $value;
		}
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
