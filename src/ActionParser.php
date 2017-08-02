<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;
use Sturdy\Activity\ActionParserError as ParserError;

/**
 * Parser for action annotations.
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
 *   +>
 *
 * Start conditional next expression, valid when the return value of the action is true.
 *
 *
 *   ->
 *
 * Start conditional next expression, valid when the return value of the action is false.
 *
 *
 *   int>      regex: (0|[1-9][0-9]*)\>
 *
 * Start conditional next expression, valid when the return value of the action matches the integer
 *
 *
 *   name>     regex: [A-Za-z_][A-Za-z0-9_]+\>
 *
 * Start conditional next expression, valid when the return value of the action matches the name
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
 *   |
 *
 * Split the activity creating multiple branches. When the branches are unnamed all branches are concurrently executed.
 * Otherwise the branch that is followed must be specified by the code running the activity. Branches must either all
 * be named or unnamed.
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
 * [class::action3] +> action5 | action6 | action7  -> end
 * [class::action4] > action5 | action8 | action10
 * [class::action5] +> action6  -> action7
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
final class ActionParser
{
	const NAME_START = "[";
	const NAME_SEPARATOR = "::";
	const NAME_END = "]";
	const START = "start";
	const END = "end";
	const READONLY = "readonly";
	const DETACH = "detach";
	const NEXT = ">";
	const NEXT_IF_TRUE = "+>";
	const NEXT_IF_FALSE = "->";
	const JOIN = ">|";
	const EQUALS = "=";
	const SPLIT = "|";
	const BRANCH_SEPARATOR = ":";
	const TAG = "#";
	const REGEX_SPECIAL = "[]|/\\(){}*+?";

	private $spaceToken;
	private $nameToken;
	private $startToken;
	private $endToken;
	private $readonlyToken;
	private $detachToken;
	private $joinToken;
	private $needsDimension;
	private $matchDimensionNoValue;
	private $matchDimensionValue;
	private $nextToken;
	private $nextIfToken;
	private $nextIfTrueToken;
	private $nextIfFalseToken;
	private $nextIfIntToken;
	private $nextIfNameToken;
	private $splitToken;
	private $actionToken;
	private $branchActionToken;

	private $className;
	private $text;
	private $done;

	public function __construct()
	{
		$equals = addcslashes(self::EQUALS, self::REGEX_SPECIAL);
		$start = addcslashes(self::START, self::REGEX_SPECIAL);
		$end = addcslashes(self::END, self::REGEX_SPECIAL);
		$readonly = addcslashes(self::READONLY, self::REGEX_SPECIAL);
		$detach = addcslashes(self::DETACH, self::REGEX_SPECIAL);
		$next = addcslashes(self::NEXT, self::REGEX_SPECIAL);
		$nextIfTrue = addcslashes(self::NEXT_IF_TRUE, self::REGEX_SPECIAL);
		$nextIfFalse = addcslashes(self::NEXT_IF_FALSE, self::REGEX_SPECIAL);
		$split = addcslashes(self::SPLIT, self::REGEX_SPECIAL);
		$join = addcslashes(self::JOIN, self::REGEX_SPECIAL);
		$tag = addcslashes(self::TAG, self::REGEX_SPECIAL);
		$nameStart = addcslashes(self::NAME_START, self::REGEX_SPECIAL);
		$nameSep = addcslashes(self::NAME_SEPARATOR, self::REGEX_SPECIAL);
		$nameEnd = addcslashes(self::NAME_END, self::REGEX_SPECIAL);
		$branchSep = addcslashes(self::BRANCH_SEPARATOR, self::REGEX_SPECIAL);

		$int = '0|[1-9][0-9]*';
		$classname = '[A-Za-z\\\\_][A-Za-z0-9\\\\_]+';
		$name = '[A-Za-z_][A-Za-z0-9_]+';
		$e = '(?=\s|\*|$)'; // end of token
		$val = '\S*';

		$this->spaceToken = "/^[\s\*]+/"; // include * character as space character to allow annotation in docblocks
		$this->nameToken = "/^$nameStart($classname$nameSep$name|$start)$nameEnd/";
		$this->startToken = "/^$start$e/";
		$this->endToken = "/^$end$e/";
		$this->readonlyToken = "/^$readonly$e/";
		$this->detachToken = "/^$detach$e/";
		$this->joinToken = "/^$join$e/";
		$this->dimensionToken = "/^$tag($name)/";
		$this->equalsToken = "/^$equals/";
		$this->valueToken = "/^($val)$e/";
		$this->nextToken = "/^$next/";
		$this->nextIfTrueToken = "/^$nextIfTrue/";
		$this->nextIfFalseToken = "/^$nextIfFalse/";
		$this->nextIfIntToken = "/^($int)$next/";
		$this->nextIfNameToken = "/^($name)$next/";
		$this->splitToken = "/^$split/";
		$this->actionToken = "/^((?:$classname$nameSep)?$name)/";
		$this->branchActionToken = "/^($name$branchSep(?:$classname$nameSep)?$name)/";
	}

	private function valid(): bool
	{
		return strlen($this->text) > 0;
	}

	private function match(string $token, &$capture = null)
	{
		if (preg_match($this->$token, $this->text, $matches)) {
			$matched = array_shift($matches);
			$capture = array_shift($matches);
			$this->done.= $matched;
			$this->text = substr($this->text, strlen($matched));
			return true;
		} else {
			return false;
		}
	}

	private function clearbit(int &$mask, int $bit): void
	{
		$mask &= ~(1 << --$bit); // clear bit
	}

	private function setbit(int &$mask, int $bit): void
	{
		$bit = 1 << --$bit;
		$mask |= $bit; // set bit
	}

	private function isbitset(int $mask, int $bit): bool
	{
		$bit = 1 << --$bit;
		return (bool)($mask & $bit);
	}

	/**
	 * Parse the text and set the properties accordingly.
	 */
	public function parse(Action $action): Action
	{
		$this->action = $action;
		$this->text = $action->getText();
		$this->done = "";
		$this->parseName();
		$this->parseTokens();
		return $this->action;
	}

	private function parseName(): void
	{
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif ($this->match('nameToken', $name)) {
				if (strpos($name, self::NAME_SEPARATOR) !== false) {
					[$className, $name] = explode(self::NAME_SEPARATOR, $name);
					if ($this->action->getClassName() !== $className || $this->action->getName() !== $name) {
						throw new ParserError($this->parseError("Expected name token "
							. self::NAME_START . $this->action->getClassName() . self::NAME_SEPARATOR
							. $this->action->getName() . self::NAME_END	. " got " . $matches[0]));
					}
				} else {
					if ($this->action->getName() !== $name) {
						throw new ParserError($this->parseError("Expected name token "
							. self::NAME_START . $this->action->getName() . self::NAME_END));
					}
				}
				break;
			} else {
				break;
			}
		}
	}

	private function parseTokens(): void
	{
		$mask = ~0;
		$this->clearbit($mask, 6);
		$this->clearbit($mask, 7);
		$this->clearbit($mask, 8);
		$this->clearbit($mask, 9);
		$this->clearbit($mask, 10);
		$this->clearbit($mask, 11);
		$this->clearbit($mask, 12);
		$this->clearbit($mask, 13);
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif ($this->match('dimensionToken', $dimension)) {
				$this->parseDimension($dimension);
			} elseif ($this->isbitset($mask, 1) && $this->match('startToken')) {
				$this->clearbit($mask, 1);
				$this->action->setStart(true);
			} elseif ($this->isbitset($mask, 2) && $this->match('readonlyToken')) {
				$this->clearbit($mask, 2);
				$this->action->setReadonly(true);
			} elseif ($this->isbitset($mask, 3) && $this->match('detachToken')) {
				$this->clearbit($mask, 3);
				$this->action->setDetach(true);
			} elseif ($this->isbitset($mask, 4) && $this->match('joinToken')) {
				$this->clearbit($mask, 4);
				$this->action->setJoin(true);
			} elseif ($this->isbitset($mask, 5) && $this->match('endToken')) {
				$this->clearbit($mask, 5);
				$this->action->setNext(false);
			} elseif ($this->isbitset($mask, 5) && $this->match('nextToken')) {
				$this->clearbit($mask, 5);
				$this->action->setNext($this->parseNext());
			} elseif ($this->isbitset($mask, 5) && $this->match('nextIfTrueToken')) {
				$this->clearbit($mask, 5);
				$this->setbit($mask, 6);
				$this->setbit($mask, 10);
				$next = new stdClass;
				$this->action->setNext($next);
				$next->{"true"} = $this->parseNext();
			} elseif ($this->isbitset($mask, 6) && $this->match('nextIfFalseToken')) {
				$this->clearbit($mask, 10);
				$next->{"false"} = $this->parseNext();
			} elseif ($this->isbitset($mask, 5) && $this->match('nextIfFalseToken')) {
				$this->clearbit($mask, 5);
				$this->setbit($mask, 7);
				$this->setbit($mask, 11);
				$next = new stdClass;
				$this->action->setNext($next);
				$next->{"false"} = $this->parseNext();
			} elseif ($this->isbitset($mask, 7) && $this->match('nextIfTrueToken')) {
				$this->clearbit($mask, 11);
				$next->{"true"} = $this->parseNext();
			} elseif ($this->isbitset($mask, 5) && $this->match('nextIfIntToken', $key)) {
				$this->clearbit($mask, 5);
				$this->setbit($mask, 8);
				$this->setbit($mask, 12);
				$next = new stdClass;
				$this->action->setNext($next);
				$next->$key = $this->parseNext();
			} elseif ($this->isbitset($mask, 8) && $this->match('nextIfIntToken', $key)) {
				$this->clearbit($mask, 12);
				$next->$key = $this->parseNext();
			} elseif ($this->isbitset($mask, 5) && $this->match('nextIfNameToken', $key)) {
				$this->clearbit($mask, 5);
				$this->setbit($mask, 9);
				$this->setbit($mask, 13);
				$next = new stdClass;
				$this->action->setNext($next);
				$next->$key = $this->parseNext();
			} elseif ($this->isbitset($mask, 9) && $this->match('nextIfNameToken', $key)) {
				$this->clearbit($mask, 13);
				$next->$key = $this->parseNext();
			} else {
				throw new ParserError($this->parseError("Unexpected token"));
			}
		}
		if ($this->isbitset($mask, 10)) {
			throw new ParserError($this->parseError("Expected if false token"));
		} elseif ($this->isbitset($mask, 11)) {
			throw new ParserError($this->parseError("Expected if true token"));
		} elseif ($this->isbitset($mask, 12)) {
			throw new ParserError($this->parseError("Expected if int token"));
		} elseif ($this->isbitset($mask, 13)) {
			throw new ParserError($this->parseError("Expected if name token"));
		}
	}

	private function parseDimension(string $dimension): void
	{
		$sequence = 0;
		while ($this->valid()) {
			if ($sequence === 0 && $this->match('equalsToken')) {
				++$sequence;
			} elseif ($sequence === 1 && $this->match('valueToken', $value)) {
				++$sequence;
			} else {
				break;
			}
		}
		switch ($sequence) {
			case 0:
				$this->action->needsDimension($dimension);
				return;
			case 1:
				$this->action->matchDimensionValue($dimension, null);
				return;
			case 2:
				$this->action->matchDimensionValue($dimension, $value);
				return;
		}
	}

	private function parseNext()
	{
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif ($this->match('endToken')) {
				return false;
			} elseif ($this->match('branchActionToken', $action)) {
				[$branch, $action] = $this->parseBranchAction($action);
				$next = [$branch => $action];
				return $this->parseBranches($next);
			} elseif ($this->match('actionToken', $action)) {
				$next = $this->parseFork([$this->parseAction($action)]);
				if (count($next) === 1) {
					return $next[0];
				} else {
					return $next;
				}
			} else {
				break;
			}
		}
		throw new ParserError($this->parseError("Empty next clause"));
	}

	private function parseBranches(array $branches): array
	{
		$sequence = 0;
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif ($this->match('splitToken') && $sequence === 0) {
				++$sequence;
			} elseif ($this->match('branchActionToken', $action) && $sequence === 1) {
				[$branch, $action] = $this->parseBranchAction($action);
				$branches[$branch] = $action;
				$sequence = 0;
			} else {
				if ($sequence !== 0) {
					throw new ParserError($this->parseError("Expected branch action token"));
				}
				break;
			}
		}
		return $branches;
	}

	private function parseFork(array $fork): array
	{
		$sequence = 0;
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif ($this->match('splitToken') && $sequence === 0) {
				++$sequence;
			} elseif ($this->match('actionToken', $action) && $sequence === 1) {
				$fork[] = $this->parseAction($action);
				$sequence = 0;
			} else {
				if ($sequence !== 0) {
					throw new ParserError($this->parseError("Expected action token"));
				}
				break;
			}
		}
		return $fork;
	}

	private function parseAction(string $action): string
	{
		if (strpos($action, self::NAME_SEPARATOR) === false) {
			$action = $this->action->getClassName().self::NAME_SEPARATOR.$action;
		}
		return $action;
	}

	private function parseBranchAction(string $text): array
	{
		$p = strpos($text, self::BRANCH_SEPARATOR);
		$branch = substr($text, 0, $p);
		$action = $this->parseAction(substr($text, $p + strlen(self::BRANCH_SEPARATOR)));
		return [$branch, $action];
	}

	private function parseError(string $msg): string
	{
		return "$msg\n{$this->done}{$this->text}\n".str_repeat(" ",strlen($this->done))."^\n";
	}
}
