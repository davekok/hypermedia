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
 *
 * Syntax
 * -------------------------------------------
 *
 *
 *   start
 *
 * Marks the action as the start action of an activity.
 *
 *
 *   end
 *
 * Marks the action as the end action of an activity, shortcut for '> end'
 *
 *
 *   readonly
 *
 * Marks the action as readonly, if all actions in an activity are readonly the activity is not journaled.
 * A readonly activity is not supposed to change any persisted state in any way. However it may read
 * persisted state. It is considered safe for readonly activity to be repeated or aborted.
 *
 *
 *   detach
 *
 * Detach the activity from the current activity starting a new journal.
 *
 *
 *   >
 *
 * Start a next expression. The > character must be followed by a next expression.
 *
 *
 *   >?
 *
 * Start multiple next expressions. The return value of the action decides which next expression is used.
 *
 *
 *   >|
 *
 * Join branches of the activity before this action is executed. All branches started by either forking or splitting
 * must be able to reach this action or end or detach. Once all flows have reached the join action or have ended
 * or detached the join action is executed. For every split/fork action there can only be one join action.
 *
 *
 *   #dimension
 *
 * The action is only valid when the dimension is set, with any value.
 *
 *
 *   #dimension=
 *
 * The action is only valid when the dimension is set and has no value.
 *
 *
 *   #dimension=value
 *
 * The action is only valid when the dimension is set and has the given value.
 *
 *
 *
 * Next expression syntax
 * -------------------------------------------
 *
 *
 *   action   regex: [A-Za-z_][A-Za-z0-9_]+
 *
 * 'action' is a method of the current class which is the next action.
 *
 *
 *   class::action   regex: [A-Za-z\\_][A-Za-z0-9\\_]+::[A-Za-z_][A-Za-z0-9_]+
 *
 * Same as above but action is in a different class. An instance of the class is retrieved through the instance factory.
 *
 *
 *   branch:action   regex: [A-Za-z_][A-Za-z0-9_]+:[A-Za-z_][A-Za-z0-9_]+
 *
 * 'branch' is the name of the branch, 'action' is as above, the first action of this named branch.
 *
 *
 *   branch:class::action   regex: [A-Za-z\\_][A-Za-z0-9\\_]+::[A-Za-z_][A-Za-z0-9_]+
 *
 * Same as above but action is in a different class.
 *
 *
 *   =true/false/integer  regex: =(true|false|0|[1-9][0-9]*)
 *
 * When using multiple next expressions start a new expression. The value behind the = character must be unique
 * within the action. When this value is returned by the action the following next expression is used.
 *
 *
 *   |
 *
 * Split the activity creating multiple branches. When the branches are unnamed all branches are concurrently executed.
 * Otherwise the branch that is followed must be specified by the code running the activity. Branches must either all
 * be named or unnamed. The actions have no control over this.
 *
 *
 *   end
 *
 * End the activity. Can not be combined with the split operator.
 *
 *
 *
 * Predefined actions:
 * end - end the activity, omitting a next action is same as defining end as the next action.
 * exception - exception has been raised, only used for the journal
 *
 * Example:
 * [class::action1] start >action2
 * [class::action2] > action3
 * [class::action3] >? =true action5 | action6 | action7  =false end
 * [class::action4] > action5 | action8 | action10
 * [class::action5] >? =true action6  =false action7
 * [class::action6] > action11
 * [class::action7] > action11
 * [class::action8] > action9
 * [class::action9]
 * [class::action10] > action11
 * [class::action11] >| end
 *
 * @Annotation
 * @Target({"METHOD"})
 * @Attributes({
 *   @Attribute("value", type = "string"),
 * })
 */
final class Action
{
	const NAME_START = "[";
	const NAME_SEPARATOR = "::";
	const NAME_END = "]";
	const START = "start";
	const END = "end";
	const READONLY = "readonly";
	const DETACH = "detach";
	const NEXT = ">";
	const NEXT_IF = ">?";
	const JOIN = ">|";
	const EQUALS = "=";
	const SPLIT = "|";
	const BRANCH_SEPARATOR = ":";
	const TAG = "#";

	const STATE_START = 0;
	const STATE_TOP = self::STATE_START + 1;
	const STATE_EQUALS = self::STATE_TOP + 1;
	const STATE_NEXT_EXP = self::STATE_EQUALS + 1;

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
	 * @var bool
	 */
	private $join;

	/**
	 * @var bool
	 */
	private $detach;

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
	 * Create a new instance of action based on the given text.
	 *
	 * @param  string $text  the text describing the action
	 * @return self  an instance of this class
	 */
	public static function createFromText(string $text): self
	{
		$inst = new self;
		$inst->text = $text;
		$inst->parse();
		return $inst;
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
	 * Set detach
	 *
	 * @param bool $detach
	 * @return self
	 */
	public function setDetach(bool $detach): self
	{
		$this->detach = $detach;
		return $this;
	}

	/**
	 * Get detach
	 *
	 * @return bool
	 */
	public function getDetach(): bool
	{
		return $this->detach;
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
		$detach = addcslashes(self::DETACH, $respecial);
		$next = addcslashes(self::NEXT, $respecial);
		$nextIf = addcslashes(self::NEXT_IF, $respecial);
		$split = addcslashes(self::SPLIT, $respecial);
		$join = addcslashes(self::JOIN, $respecial);
		$tag = addcslashes(self::TAG, $respecial);
		$nameStart = addcslashes(self::NAME_START, $respecial);
		$nameSep = addcslashes(self::NAME_SEPARATOR, $respecial);
		$nameEnd = addcslashes(self::NAME_END, $respecial);
		$branchSep = addcslashes(self::BRANCH_SEPARATOR, $respecial);
		$retval = 'true|false|0|[1-9][0-9]*';
		$classname = '[A-Za-z\\\\_][A-Za-z0-9\\\\_]+';
		$name = '[A-Za-z_][A-Za-z0-9_]+';
		$e = '(?=\s|\*|$)'; // end of token
		$val = '\S*';

		$spaceToken = "/^[\s\*]+/"; // include * character as space character to allow annotation in docblocks
		$nameToken = "/^$nameStart($classname$nameSep$name|start)$nameEnd/";
		$startToken = "/^$start$e/";
		$endToken = "/^$end$e/";
		$readonlyToken = "/^$readonly$e/";
		$detachToken = "/^$detach$e/";
		$joinToken = "/^$join$e/";
		$dimensionRequiredAnyValue = "/^$tag($name)$e/";
		$dimensionRequiredNoValue = "/^$tag($name)$equals$e/";
		$dimensionRequiredWithGivenValue = "/^$tag($name)$equals($val)$e/";
		$nextToken = "/^$next/";
		$nextIfToken = "/^$nextIf/";
		$equalsToken = "/^$equals($retval)$e/";
		$splitToken = "/^$split/";
		$actionToken = "/^(?:$classname$nameSep)?$name/";
		$branchActionToken = "/^$name$branchSep(?:$classname$nameSep)?$name/";

		$this->done = "";
		$this->start = false;
		$this->readonly = false;
		$this->join = false;
		$this->next = null;
		$this->dimensions = [];
		$this->returnValues = false;

		$states = [];
		$state = self::STATE_START;
		while (strlen($this->text) > 0) {
			echo "[$state] ", $this->text, "\n";
			switch ($state) {
				case self::STATE_START:
					if (preg_match($spaceToken, $this->text, $matches)) {
						echo "matched spaceToken\n";
						// nothing to do
					} elseif (preg_match($nameToken, $this->text, $matches)) {
						echo "matched nameToken\n";
						if (strpos($matches[1], self::NAME_SEPARATOR) !== false) {
							[$this->className, $this->name] = explode(self::NAME_SEPARATOR, $matches[1]);
						} else {
							$this->className = null;
							$this->name = $matches[1];
						}
						$this->constructKey();
					} else {
						echo "matched nothing\n";
						$state = self::STATE_TOP;
						continue 2; // do not consume token
					}
					break;

				case self::STATE_TOP:
					if (preg_match($spaceToken, $this->text, $matches)) {
						echo "matched spaceToken\n";
						// nothing to do
					} elseif (preg_match($dimensionRequiredAnyValue, $this->text, $matches)) {
						echo "matched dimensionRequiredAnyValue\n";
						$this->dimensions[$matches[1]] = true;
					} elseif (preg_match($dimensionRequiredNoValue, $this->text, $matches)) {
						echo "matched dimensionRequiredNoValue\n";
						$this->dimensions[$matches[1]] = "";
					} elseif (preg_match($dimensionRequiredWithGivenValue, $this->text, $matches)) {
						echo "matched dimensionRequiredWithGivenValue\n";
						$this->dimensions[$matches[1]] = $matches[2];
					} elseif (preg_match($startToken, $this->text, $matches)) {
						echo "matched startToken\n";
						if ($this->start === true) {
							throw $this->parseError("Token '".self::START."' is only expected once.");
						}
						$this->start = true;
					} elseif (preg_match($readonlyToken, $this->text, $matches)) {
						echo "matched readonlyToken\n";
						if ($this->readonly === true) {
							throw $this->parseError("Token '".self::READONLY."' is only expected once.");
						}
						$this->readonly = true;
					} elseif (preg_match($detachToken, $this->text, $matches)) {
						echo "matched detachToken\n";
						if ($this->detach === true) {
							throw $this->parseError("Token '".self::DETACH."' is only expected once.");
						}
						$this->detach = true;
					} elseif (preg_match($joinToken, $this->text, $matches)) {
						echo "matched joinToken\n";
						if ($this->join === true) {
							throw $this->parseError("Token '".self::JOIN."' is only expected once.");
						}
						$this->join = true;
					} elseif (preg_match($nextToken, $this->text, $matches)) {
						echo "matched nextToken\n";
						if ($this->next !== null) {
							throw $this->parseError("Only one next expression allowed.");
						}
						$this->next = [];
						$nextref = &$this->next;
						$states[] = $state;
						$state = self::STATE_NEXT_EXP;
					} elseif (preg_match($nextIfToken, $this->text, $matches)) {
						echo "matched nextIfToken\n";
						if ($this->next !== null) {
							throw $this->parseError("Only one next expression allowed.");
						}
						$states[] = $state;
						$state = self::STATE_EQUALS;
						$this->returnValues = true;
						$this->next = new stdClass;
					} elseif (preg_match($endToken, $this->text, $matches)) {
						echo "matched endToken\n";
						if ($this->next !== null) {
							throw $this->parseError("Only one next expression allowed.");
						}
						$this->next = false;
					} else {
						throw $this->parseError("Parse error");
					}
					break;

				case self::STATE_EQUALS: // equals expressions
					if (preg_match($spaceToken, $this->text, $matches)) {
						echo "matched spaceToken\n";
						// nothing to do
					} elseif (preg_match($equalsToken, $this->text, $matches)) {
						echo "matched equalsToken\n";
						$returnValue = $matches[1];
						$this->next->$returnValue = [];
						$nextref = &$this->next->$returnValue;
						$states[] = $state;
						$state = self::STATE_NEXT_EXP;
					} else {
						$state = array_pop($states);
						continue 2;
					}
					break;

				case self::STATE_NEXT_EXP: // next expression
					if (preg_match($spaceToken, $this->text, $matches)) {
						echo "matched spaceToken\n";
						// nothing to do
					} elseif (preg_match($endToken, $this->text, $matches)) {
						echo "matched endToken\n";
						$nextref = false;
					} elseif (preg_match($splitToken, $this->text, $matches)) {
						echo "matched splitToken\n";
					} elseif (preg_match($branchActionToken, $this->text, $matches)) {
						echo "matched branchActionToken\n";
						$text = $matches[0];
						$p = strpos($text, self::BRANCH_SEPARATOR);
						$branch = substr($text, 0, $p);
						$action = $this->parseNextAction(substr($text, $p + strlen(self::BRANCH_SEPARATOR)));
						$nextref[$branch] = $action;
					} elseif (preg_match($actionToken, $this->text, $matches)) {
						echo "matched actionToken\n";
						$nextref[] = $this->parseNextAction($matches[0]);
					} else {
						$state = array_pop($states);
						continue 2;
					}
					break;
			}
			$this->done.= $matches[0];
			$this->text = substr($this->text, strlen($matches[0]));
		}
		echo "done\n";
		unset($nextref); // break next reference

		if ($this->next === null) {
			throw new \LogicException("No next action defined.");
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
		} else {
			if (is_array($this->next) && count($this->next) === 1 && reset($this->next) && key($this->next) === 0) {
				$this->next = $this->next[0];
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
		} elseif ($this->className === null && $this->name === "start") {
			$text.= self::NAME_START.$this->name.self::NAME_END." ";
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
					$text.= self::NEXT." ".implode(" ".self::FORK." ", $next)." ";
				} elseif (is_string($next)) {
					$text.= self::NEXT." ".$next." ";
				}
			}
		} else {
			if ($this->next === false) {
				$text.= self::END." ";
			} elseif (is_array($this->next)) {
				$text.= self::NEXT." ";
				reset($this->next);
				if (is_string(key($this->next))) {
					$i = 0;
					foreach ($this->next as $branch => $method) {
						if ($i++) $text.= " ".self::FORK." ";
						$text.= $branch.self::BRANCH_SEPARATOR.$method;
					}
				} else {
					$text.= implode(" ".self::FORK." ", $this->next);
				}
				$text.= " ";
			} elseif (is_string($this->next)) {
				$text.= self::NEXT." ".$this->next." ";
			}
		}
		foreach ($this->dimensions as $key => $value) {
			$text.= self::TAG.$key;
			if ($value === true) {
			} elseif ($value === "") {
				$text.= self::EQUALS;
			} else {
				$text.= self::EQUALS.$value;
			}
			$text.= " ";
		}
		return rtrim($text);
	}
}
