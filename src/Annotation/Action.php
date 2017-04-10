<?php declare(strict_types=1);

namespace Sturdy\Activity\Annotation;

/**
 * @Annotation
 * @Target({"METHOD"})
 * @Attributes({
 *   @Attribute("start", type = "bool"),
 *   @Attribute("next" , type = "string"),
 *   @Attribute("dims" , type = "array<Dimension>"),
 * })
 */
class Action
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
	public function __constructor(array $values)
	{
		$this->start = $values['start']??false;
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
	 * Set which action comes next.
	 */
	private function setNext(?string $next): void
	{
		if ($next === null) {
			$this->next = null;
		} else {
			$func = "[A-Za-z_][A-Za-z0-9_]+";
			$retval = "(?:null|true|false|0|[1-9][0-9]*)";
			if (preg_match("/^$func$/", $next)) {
				$this->next = ['null'=>$next];
			} elseif (preg_match("/^\s*{\s*$retval\s*:\s*$func\s*(?:,\s*$retval\s*:\s*$func\s*)*}\s*$/", $next)) {
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
	 * @return a array of possible actions, the next action should be chosen based on the return value of the action.
	 */
	public function getNext(): ?array
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
