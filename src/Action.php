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
 * The action annotation makes use of a simple syntax.
 *
 * Syntax:
 *
 * start
 * - Marks the action as the start action of an activity.
 *
 * readonly
 * - Marks the action as readonly, if all actions in an activity are readonly the activity is not journalled.
 * - A readonly activity is not supposed to change any persisted state in any way. However it may read
 * - persisted state. It is considered safe for readonly activity to be repeated or aborted.
 *
 * end
 * - End the activity after this action.
 *
 * >methodname
 * - Marks method methodname as the next action to be executed after this one. The method resides in the same class
 * - as the current action.
 *
 * >classname::methodname
 * - Marks method methodname of class classname to be executed as the next action. The InstanceFactory must be able to
 * - load an instance of this class. Either shared or unshared.
 *
 * >methodname1|methodname2|classname::methodname3
 * - Forks the activity, each action will be executed concurrently.
 *
 * =true/false/integer end
 * - End the activity if the defined return value is matched.
 *
 * =true/false/integer >methodname/classname::methodname
 * - The next action will only be executed if the defined return value is matched.
 *
 * =true/false/integer >methodname1|methodname2|classname::methodname3
 * - Forks the activity if defined return value is matched, each action will be executed concurrently.
 *
 * >|
 * - Join concurrent flows of the activity before this action is executed. All concurrent flows started with a fork
 * - must be able to reach this action or end. Once all concurrent flows have reach the join action or have ended
 * - the join action is executed. For every fork action there can only be one join action.
 *
 * #dimension=value
 * - The action is only valid for when dimension has the given value.
 *
 * Predefined actions:
 * end - end the activity, omitted a next action is same as defining end as the next action.
 * exception - raise an exception, only used for the journal
 *
 * Example:
 * [class::action1] start >action2
 * [class::action2] >action3
 * [class::action3] =true >action5|action6|action7  =false end
 * [class::action4] >action5|action8|action10
 * [class::action5] =true >action6  =false >action7
 * [class::action6] >action11
 * [class::action7] >action11
 * [class::action8] >action9
 * [class::action9]
 * [class::action10] >action11
 * [class::action11] >| end
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
 * <dimension> = "#" <name> "=" <value>
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
	const NAME_START = "[";
	const NAME_SEPARATOR = "::";
	const NAME_END = "]";
	const START = "start";
	const END = "end";
	const READONLY = "readonly";
	const EQUALS = "=";
	const NEXT = ">";
	const FORK = "|";
	const JOIN = self::NEXT.self::FORK;
	const TAG = "#";

	/**
	 * @var string
	 */
	private $text;

	/**
	 * @var string
	 */
	private $done;

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
	private $start;

	/**
	 * @var bool
	 */
	private $readonly;

	/**
	 * @var false|string|array
	 */
	private $next;

	/**
	 * @var bool
	 */
	private $returnValues;

	/**
	 * @var array
	 */
	private $dimensions;

	/**
	 * @var int
	 */
	private $joinNumber;

	/**
	 * Constructor
	 *
	 * @param array $values  values as injected by annotation reader
	 */
	public function __construct(array $values = null)
	{
		if (isset($values["value"])) {
			$this->text = $values["value"];
		}
	}

	/**
	 * Set class name
	 *
	 * @param string $className
	 * @return self
	 */
	public function setClassName(string $className): self
	{
		$this->className = $className;
		$this->constructKey();
		return $this;
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
	 * Set name
	 *
	 * @param string $name
	 * @return self
	 */
	public function setName(string $name): self
	{
		$this->name = $name;
		$this->constructKey();
		return $this;
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
	 * Construct key from class name and action name.
	 */
	private function constructKey(): void
	{
		if ($this->className && $this->name) {
			$this->key = "{$this->className}::{$this->name}";
		} elseif ($this->name) {
			$this->key = $this->name;
		} else {
			$this->key = "";
		}
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
	 * Get which action comes next.
	 *
	 * @param $next  either false, a string or an array.
	 */
	public function setNext($next): self
	{
		$this->next = $next;
		return $this;
	}

	/**
	 * Get which action comes next.
	 *
	 * @return either false, a string or an array.
	 */
	public function getNext()
	{
		return $this->next;
	}

	/**
	 * Set whether return values are used.
	 *
	 * @param bool $returnValues
	 * @return self
	 */
	public function setReturnValues(bool $returnValues): self
	{
		$this->returnValues = $returnValues;
		return $this;
	}

	/**
	 * Get whether return values are used.
	 *
	 * @return bool
	 */
	public function hasReturnValues(): bool
	{
		return $this->returnValues;
	}

	/**
	 * Set dimensions
	 *
	 * @param array $dimensions
	 * @return self
	 */
	public function setDimensions(array $dimensions): self
	{
		$this->dimensions = $dimensions;
		return $this;
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
		return $this->text??(string)$this;
	}

	/**
	 * Parse the text and set the properties accordingly.
	 */
	public function parse(): void
	{
		$respecial = '[]|/\\(){}*+?';
		$equals = addcslashes(self::EQUALS, $respecial);
		$start = addcslashes(self::START, $respecial);
		$end = addcslashes(self::END, $respecial);
		$readonly = addcslashes(self::READONLY, $respecial);
		$next = addcslashes(self::NEXT, $respecial);
		$fork = addcslashes(self::FORK, $respecial);
		$join = addcslashes(self::JOIN, $respecial);
		$tag = addcslashes(self::TAG, $respecial);
		$nameStart = addcslashes(self::NAME_START, $respecial);
		$nameSep = addcslashes(self::NAME_SEPARATOR, $respecial);
		$nameEnd = addcslashes(self::NAME_END, $respecial);
		$retval = 'true|false|0|[1-9][0-9]*';
		$classname = '[A-Za-z\\\\_][A-Za-z0-9\\\\_]+';
		$name = '[A-Za-z_][A-Za-z0-9_]+';
		$func = "(?:$classname$nameSep)?$name";
		$e = '(?=\s|$)'; // end of token
		$val = '\S*';

		$spacerule = "/^\s+/";
		$namerule = "/^$nameStart($classname)$nameSep($name)$nameEnd/";
		$namecapture = function(&$value, $matches){
			$value->className = $matches[1];
			$value->name = $matches[2];
		};
		$nextrule = "/^$next($func)$e/";
		$nextcapture = function(&$value, $matches){
			$value = $this->parseNextAction($matches[1]);
		};
		$forkrule = "/^$next$func($fork$func)+$e/";
		$forkcapture = function(&$value, $matches){
			$value = array_map([$this, "parseNextAction"], explode(self::FORK, ltrim($matches[0], self::NEXT)));
		};
		$endrule = "/^$end$e/";
		$endcapture = function(&$value, $matches){
			$value = false;
		};
		$startrule = "/^$start$e/";
		$readonlyrule = "/^$readonly$e/";
		$joinrule = "/^$join$e/";
		$equalsrule = "/^$equals($retval)$e/";
		$equalscapture = function(&$value, $matches){
			$value = $matches[1];
		};
		$dimensionrule = "/^$tag($name)$equals($val)$e/";
		$dimensioncapture = function(&$value, $matches){
			$value[$matches[1]] = $matches[2];
		};

		$this->done = "";
		$this->start = false;
		$this->readonly = false;
		$this->join = false;
		$this->next = null;
		$this->dimensions = [];
		$this->returnValues = null;
		$returnValue = null;

		while (strlen($this->text) > 0) {
			if ($returnValue !== null) {
				if (preg_match($spacerule, $this->text, $matches)) {
					// nothing to do
				} elseif (preg_match($nextrule, $this->text, $matches)) {
					$nextcapture($this->next->$returnValue, $matches);
					$returnValue = null;
				} elseif (preg_match($forkrule, $this->text, $matches)) {
					$forkcapture($this->next->$returnValue, $matches);
					$returnValue = null;
				} elseif (preg_match($endrule, $this->text, $matches)) {
					$endcapture($this->next->$returnValue, $matches);
					$returnValue = null;
				} else {
					throw $this->parseError("Parse error");
				}
			} else {
				if (preg_match($spacerule, $this->text, $matches)) {
					// nothing to do
				} elseif (preg_match($startrule, $this->text, $matches)) {
					if ($this->start === true) {
						throw $this->parseError("Token '".self::START."' is only expected once.");
					}
					$this->start = true;
				} elseif (preg_match($readonlyrule, $this->text, $matches)) {
					if ($this->readonly === true) {
						throw $this->parseError("Token '".self::READONLY."' is only expected once.");
					}
					$this->readonly = true;
				} elseif (preg_match($joinrule, $this->text, $matches)) {
					if ($this->join === true) {
						throw $this->parseError("Token '".self::JOIN."' is only expected once.");
					}
					$this->join = true;
				} elseif (preg_match($equalsrule, $this->text, $matches)) {
					$this->turnOnReturnValues();
					$equalscapture($returnValue, $matches);
				} elseif (preg_match($nextrule, $this->text, $matches)) {
					$this->turnOffReturnValues();
					$nextcapture($this->next, $matches);
				} elseif (preg_match($forkrule, $this->text, $matches)) {
					$this->turnOffReturnValues();
					$forkcapture($this->next, $matches);
				} elseif (preg_match($endrule, $this->text, $matches)) {
					$this->turnOffReturnValues();
					$endcapture($this->next, $matches);
				} elseif (preg_match($dimensionrule, $this->text, $matches)) {
					$dimensioncapture($this->dimensions, $matches);
				} elseif (preg_match($namerule, $this->text, $matches)) {
					if (strlen($this->done) !== 0) {
						throw $this->parseError("Action name must be at the start of the text.");
					}
					$namecapture($this, $matches);
					$this->constructKey();
				} else {
					throw $this->parseError("Parse error");
				}
			}
			$this->done.= $matches[0];
			$this->text = substr($this->text, strlen($matches[0]));
		}
		if ($this->returnValues === true) {
			if (count(get_object_vars($this->next)) < 2) {
				throw new \LogicException("At least two next expressions are required when using return values.");
			} else {
				$trueValue = false;
				$falseValue = false;
				foreach ($this->next as $returnValue => $next) {
					if ($returnValue === "true") {
						$trueValue = true;
					}
					if ($returnValue === "false") {
						$falseValue = true;
					}
				}
				if ($trueValue xor $falseValue) {
					throw new \LogicException("When using boolean return values, you must declare a next expression for both true and false.");
				}
				if ($trueValue and count(get_object_vars($this->next)) > 2) {
					throw new \LogicException("When using boolean return values, only two next expressions are allowed.");
				}
			}
		}
		$this->text = $this->done;
		$this->done = null;
	}

	private function parseNextAction(string $nextAction)
	{
		if (strpos($nextAction, self::NAME_SEPARATOR) === false) {
			$nextAction = $this->className.self::NAME_SEPARATOR.$nextAction;
		}
		return $nextAction;
	}

	private function turnOffReturnValues()
	{
		if ($this->returnValues !== null) {
			throw $this->parseError("Next already defined.");
		}
		$this->returnValues = false;
	}

	private function turnOnReturnValues()
	{
		if ($this->returnValues === false) {
			throw $this->parseError("Already have a token with a return value.");
		}
		$this->returnValues = true;
		if ($this->next === null) {
			$this->next = new stdClass;
		}
	}

	private function parseError(string $msg)
	{
		return new \LogicException("$msg\n{$this->done}{$this->text}\n".str_repeat(" ",strlen($this->done))."^\n");
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
			$text.= self::NAME_START.$this->className.self::NAME_SEPARATOR.$this->name.self::NAME_END." ";
		}
		if ($this->start) {
			$text.= self::START." ";
		}
		if ($this->readonly) {
			$text.= self::READONLY." ";
		}
		if ($this->join) {
			$text.= self::JOIN." ";
		}
		if ($this->returnValues) {
			foreach ($this->next as $returnValue => $next) {
				$text.= self::EQUALS.$returnValue." ";
				if ($next === false) {
					$text.= self::END." ";
				} elseif (is_array($next)) {
					$text.= self::NEXT.implode(self::FORK, $next)." ";
				} elseif (is_string($next)) {
					$text.= self::NEXT.$next." ";
				}
			}
		} else {
			if ($this->next === false) {
				$text.= self::END." ";
			} elseif (is_array($this->next)) {
				$text.= self::NEXT.implode(self::FORK, $this->next)." ";
			} elseif (is_string($this->next)) {
				$text.= self::NEXT.$this->next." ";
			}
		}
		foreach ($this->dimension as $key => $value) {
			$text.= self::TAG.$key.self::EQUALS.$value." ";
		}
		return rtrim($text);
	}
}
