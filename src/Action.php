<?php declare(strict_types=1);

namespace Sturdy\Activity;

use \Doctrine\Common\Annotations\Annotation\{Annotation,Target,Attributes,Attribute};
use stdClass;

/**
 * The action annotation.
 *
 * Actions can reside in any class. An activity may span actions of one or more classes.
 * Only class methods can be actions. A method may have one or more action annotations.
 * However if multiple action annotations are used the must use different dimensions.
 *
 * The action annotation makes use of a simple syntax as documented by ActionParser class.
 *
 * @Annotation
 * @Target({"METHOD"})
 * @Attributes({
 *   @Attribute("value", type = "string"),
 * })
 *
 * ABNF:
 * <action> = [ <actionname> ] <options>
 * <actionname> = "[" <classname> "::" <name> "]"
 * <options> =  *( <s> / <start> / <readonly> / <next> / <dimension> )
 * <options> =/ *( <s> / <start> / <readonly> / <fork> / <dimension> )
 * <options> =/ *( <s> / <start> / <readonly> / <end> / <dimension> )
 * <options> =/ *( <s> / <start> / <readonly> / <retval> <s> ( <next> / <fork> / <end> ) / <dimension>  )
 * <options> =/ *( <s> / <join> / <readonly> / <next> / <dimension> )
 * <options> =/ *( <s> / <join> / <readonly> / <fork> / <dimension> )
 * <options> =/ *( <s> / <join> / <readonly> / <end> / <dimension> )
 * <options> =/ *( <s> / <join> / <readonly> / <retval> <s> ( <next> / <fork> / <end> ) / <dimension>  )
 * <readonly> = "readonly"
 * <start> = "start"
 * <end> = "end"
 * <join> = ">|"
 * <next> = ">" ( <method> / <end> )
 * <fork> = "|>" 1*( <s> <method> )
 * <retval> = "=" ( "true" / "false" / <int> )
 * <dimension> = "#" <name> [ "=" <value> ]
 * <method> = [ <classname> "::" ] <name>
 * <classname> = <startcchar> *<cchar>
 * <name> = <startnchar> *<nchar>
 * <startnchar> = %x41-5A / %x5F / %x61-7A ; name start character
 * <nchar> = %x30-39 / %x41-5A / %x5F / %x61-7A ; name character
 * <startcchar> = %x41-5A / %x5C / %x5F / %x61-7A ; class name start character
 * <cchar> = %x30-39 / %x41-5A / %x5C / %x5F / %x61-7A ; class name character
 * <int> =  %x30 ; 0
 * <int> =/ %x31-39 *(%x30-39) ; number not starting with zero
 * <s> = 1*(%x20) ; one or more spaces
 * <value> = 1*( %x21-7E )
 */
final class Action
{
	/**
	 * @var string
	 */
	private $key;

	/**
	 * @var string
	 */
	private $className;

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var bool
	 */
	private $start = false;

	/**
	 * @var bool
	 */
	private $readonly = false;

	/**
	 * @var bool
	 */
	private $join = false;

	/**
	 * @var bool
	 */
	private $detach = false;

	/**
	 * @var false|string|array
	 */
	private $next;

	/**
	 * @var array
	 */
	private $dimensions = [];

	/**
	 * @var string
	 */
	private $text;

	/**
	 * Constructor
	 *
	 * @param array $values  the values as injected by annotation reader
	 */
	public function __construct(array $values = null)
	{
		if (isset($values["value"])) {
			$this->text = $values["value"];
		}
	}

	/**
	 * Set the action key
	 */
	public function setKey(?string $className, string $name): self
	{
		$this->className = $className;
		$this->name = $name;
		if ($this->className && $this->name) {
			$this->key = "{$this->className}::{$this->name}";
		} elseif ($this->name) {
			$this->key = $this->name;
		} else {
			$this->key = null;
		}
		return $this;
	}

	/**
	 * Get the key of the action.
	 * @return string  the key
	 */
	public function getKey(): string
	{
		return $this->key;
	}

	/**
	 * Get class name
	 *
	 * @return string
	 */
	public function getClassName(): string
	{
		return $this->className;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Set start
	 *
	 * @param bool $start
	 * @return self
	 */
	public function setStart(bool $start): self
	{
		$this->start = $start;
		return $this;
	}

	/**
	 * Get start
	 *
	 * @return bool
	 */
	public function getStart(): bool
	{
		return $this->start;
	}

	/**
	 * Set read only
	 *
	 * @param bool $readonly
	 * @return self
	 */
	public function setReadonly(bool $readonly): self
	{
		$this->readonly = $readonly;
		return $this;
	}

	/**
	 * Get read only action
	 *
	 * @return bool
	 */
	public function getReadonly(): bool
	{
		return $this->readonly;
	}

	/**
	 * Set join
	 *
	 * @param bool $join
	 * @return self
	 */
	public function setJoin(bool $join): self
	{
		$this->join = $join;
		return $this;
	}

	/**
	 * Is join
	 *
	 * @return bool
	 */
	public function isJoin(): bool
	{
		return $this->join;
	}

	/**
	 * Set which action comes next.
	 *
	 * @param $next  either false, a string, a array or a object.
	 */
	public function setNext($next): self
	{
		$this->next = $next;
		return $this;
	}

	/**
	 * Get which action comes next.
	 *
	 * @return either false, a string, a array or a object.
	 */
	public function getNext()
	{
		return $this->next;
	}

	/**
	 * Get whether return values are used.
	 *
	 * @return bool
	 */
	public function hasReturnValues(): bool
	{
		return is_object($this->next);
	}

	/**
	 * Set that the action is only valid if the given dimension is there.
	 *
	 * @param string $dimension  the dimension
	 */
	public function needsDimension(string $dimension)
	{
		$this->dimensions[$dimension] = true;
	}

	/**
	 * Set that the action is only valid if the dimension matches the value.
	 *
	 * @param string $dimension  the dimension
	 * @param ?string $value  the value
	 */
	public function matchDimensionValue(string $dimension, ?string $value)
	{
		$this->dimensions[$dimension] = $value;
	}

	/**
	 * Get dimensions
	 *
	 * @return a array of dimensions
	 */
	public function getDimensions(): array
	{
		return $this->dimensions;
	}

	/**
	 * Has dimension
	 *
	 * @param  string $key  the dimension key
	 * @return bool
	 */
	public function hasDimension(string $key): bool
	{
		return isset($this->dimensions[$key]);
	}

	/**
	 * Get dimension
	 *
	 * @param  string $key  the dimension key
	 * @return string, null or true
	 */
	public function getDimension(string $key)
	{
		return $this->dimensions[$key]??null;
	}

	/**
	 * Order the dimensions and null missing dimensions.
	 *
	 * @param array $order  the keys to order by
	 */
	public function orderDimensions(array $order)
	{
		foreach ($order as $key) {
			$dimensions[$key] = $this->dimensions[$key]??null;
		}
		$this->dimensions = $dimensions;
	}

	/**
	 * Set text
	 *
	 * @param string $text
	 * @return self
	 */
	public function setText(string $text): self
	{
		$this->text = $text;
		return $this;
	}

	/**
	 * Get text
	 *
	 * @return string
	 */
	public function getText(): string
	{
		return $this->text;
	}

	/**
	 * Parse action text
	 */
	public function parse(): void
	{
		(new ActionParser)->parse($this);
	}

	/**
	 * Validate action
	 */
	public function validate(): void
	{
		if ($this->next === null) {
			throw new \LogicException("No next action defined.");
		}
	}

	/**
	 * Convert to string.
	 *
	 * @return string textual representation of action
	 */
	public function __toString(): string
	{
		$text = "";
		if ($this->className && $this->name) {
			$text.= ActionParser::NAME_START.$this->className.ActionParser::NAME_SEPARATOR.$this->name.ActionParser::NAME_END." ";
		} elseif ($this->className === null && $this->name === "start") {
			$text.= ActionParser::NAME_START.$this->name.ActionParser::NAME_END." ";
		}
		if ($this->start) {
			$text.= ActionParser::START." ";
		}
		if ($this->readonly) {
			$text.= ActionParser::READONLY." ";
		}
		if ($this->join) {
			$text.= ActionParser::JOIN." ";
		}
		if ($this->hasReturnValues()) {
			foreach ($this->next as $returnValue => $next) {
				$text.= ActionParser::EQUALS.$returnValue." ";
				if ($next === false) {
					$text.= ActionParser::END." ";
				} elseif (is_array($next)) {
					$text.= ActionParser::NEXT." ".implode(" ".ActionParser::FORK." ", $next)." ";
				} elseif (is_string($next)) {
					$text.= ActionParser::NEXT." ".$next." ";
				}
			}
		} else {
			if ($this->next === false) {
				$text.= ActionParser::END." ";
			} elseif (is_array($this->next)) {
				$text.= ActionParser::NEXT." ";
				reset($this->next);
				if (is_string(key($this->next))) {
					$i = 0;
					foreach ($this->next as $branch => $method) {
						if ($i++) $text.= " ".ActionParser::FORK." ";
						$text.= $branch.ActionParser::BRANCH_SEPARATOR.$method;
					}
				} else {
					$text.= implode(" ".ActionParser::FORK." ", $this->next);
				}
				$text.= " ";
			} elseif (is_string($this->next)) {
				$text.= ActionParser::NEXT." ".$this->next." ";
			}
		}
		foreach ($this->dimensions as $key => $value) {
			$text.= ActionParser::TAG.$key;
			if ($value === true) {
			} elseif ($value === null) {
				$text.= ActionParser::EQUALS;
			} else {
				$text.= ActionParser::EQUALS.$value;
			}
			$text.= " ";
		}
		return rtrim($text);
	}
}
