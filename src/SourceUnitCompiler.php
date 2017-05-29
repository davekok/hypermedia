<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;

/**
 * Implementation of SourceUnitCompiler
 */
class SourceUnitCompiler
{
	private $unit;
	private $shouldHave;
	private $mustNotHave;
	private $joins;
	private $join;
	private $found;
	private $readonly;
	private $actions;

	/**
	 * Constructor
	 *
	 * @param SourceUnit $unit  the source unit to compile
	 */
	public function __construct(SourceUnit $unit)
	{
		$this->unit = $unit;
	}

	/**
	 * Create activity from the source unit for the specified dimensions
	 *
	 * @param array      $dimensions  dimensions to compile
	 * @return stdClass  return the activity for the given dimensions
	 */
	public function createActivity(array $dimensions): ?stdClass
	{
		$this->shouldHave = [];
		foreach ($this->unit->getDimensions() as $dim) {
			if (isset($dimensions[$dim])) {
				$this->shouldHave[$dim] = $dimensions[$dim];
			}
		}
		$this->mustNotHave = [];
		foreach ($this->unit->getDimensions() as $dim) {
			if (!isset($dimensions[$dim])) {
				$this->mustNotHave[] = $dim;
			}
		}
		$this->joins = [];
		$this->join = 0;
		$this->found = [];
		$this->readonly = true;
		$this->actions = [];

		$key = "start"; // activities begin at the start action
		$this->walk($key);
		if (count($this->actions)) {
			$activity = new stdClass;
			$activity->dimensions = $dimensions;
			$activity->readonly = $this->readonly;
			$activity->actions = $this->actions;
			return $activity;
		} else {
			return null;
		}
	}

	/**
	 * Walk over the actions.
	 *
	 * @param  string &$key  the action key
	 */
	private function walk(string &$key): void
	{
		$action = $this->unit->findBestMatch($key, $this->shouldHave, $this->mustNotHave);
		if ($action === null) {
			$this->actions = []; // no end found
			return;
		}

		if ($action->getReadonly() === false) {
			$this->readonly = false;
		}

		$hash = spl_object_hash($action);

		// return a join in case this is a join action
		if ($action->isJoin()) {
			if (isset($this->joins[$hash])) {
				$joinNumber = $this->joins[$hash];
			} else {
				$joinNumber = $this->joins[$hash] = ++$this->join;
			}
			$this->actions[$joinNumber] = $key;
			$key = $joinNumber;
		}

		// already walked over this action? should only happen for loops and joins
		if (isset($this->found[$hash])) {
			return;
		} else {
			$this->found[$hash] = true;
		}

		// get the next action(s)
		$next = $action->getNext();

		$this->actions[$key] = $next;

		// does the action have return values?
		if (is_object($next)) {
			foreach ($next as $returnValue => &$expr) {
				if (is_string($expr)) { // expr is key of next action
					$this->walk($expr);
				} elseif (is_array($expr)) { // expr is array of keys, forking the activity
					foreach ($expr as $expr) {
						$this->walk($expr);
					}
				} elseif ($expr === false) { // expr is end of activity
					return;
				}
			}
		} elseif (is_string($next)) { // expr is key of next action
			$this->walk($next);
		} elseif (is_array($next)) { // fork actions
			foreach ($next as &$expr) { // expr is array of keys, forking the activity
				$this->walk($expr);
			}
		} elseif ($next === false) { // expr is end of activity
			return;
		}
	}
}
