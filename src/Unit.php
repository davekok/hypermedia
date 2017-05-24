<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;

/**
 * Support class to represent a unit.
 */
final class Unit implements CacheUnit
{
	/**
	 * @var string;
	 */
	private $name;

	/**
	 * @var array<string>;
	 */
	private $classes = [];

	/**
	 * @var array<string>
	 */
	private $dimensions = [];

	/**
	 * @var array
	 */
	private $activities = [];

	/**
	 * @var array
	 */
	private $actions = [];

	/**
	 * Constructor
	 *
	 * @param $name  the name of the unit
	 */
	public function __construct(string $name)
	{
		$this->name = $name;
	}

	/**
	 * Get the name of the unit.
	 *
	 * @return the name of the unit
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Get classess
	 *
	 * @return array<string>  the classes
	 */
	public function getClasses(): array
	{
		return $this->classes;
	}

	/**
	 * Set dimensions
	 *
	 * Normally the dimensions are collected as you add actions.
	 * However it can be usefull to set the dimensions manually
	 * to control the order of the dimensions. The order is used
	 * to find the best matches for an activity.
	 *
	 * @param array<string>  the dimensions
	 */
	public function setDimensions(array $dimensions): self
	{
		$this->dimensions = $dimensions;

		return $this;
	}

	/**
	 * Get dimensions
	 *
	 * @return array<string>  the dimensions
	 */
	public function getDimensions(): array
	{
		return $this->dimensions;
	}

	/**
	 * Get activities
	 *
	 * @return array<stdClass>  the activities
	 */
	public function getActivities(): array
	{
		if (empty($this->activities)) {
			$this->compile();
		}
		return $this->activities;
	}

	/**
	 * Get all actions
	 *
	 * @return array<string=>array<stdClass>>  actions
	 */
	public function getActions(): array
	{
		return $this->actions;
	}

	/**
	 * Add an action to this unit.
	 *
	 * As actions are added also classes and dimensions are added.
	 *
	 * @param $action  the action
	 * @return $this
	 */
	public function addAction(Action $action): self
	{
		$className = $action->getClassName();
		if (!in_array($className, $this->classes)) {
			$this->classes[] = $className;
		}

		foreach ($action->getDimensions() as $dim => $value) {
			if (!in_array($dim, $this->dimensions)) {
				$this->dimensions[] = $dim;
			}
		}

		if ($action->getStart()) {
			$start = new Action();
			$start->setName("start");
			$start->setNext($action->getKey());
			$start->setReturnValues(false);
			$start->setDimensions($action->getDimensions());
			$this->_addAction($start);
		}
		$this->_addAction($action);

		return $this;
	}

	private function _addAction(Action $action): void
	{
		$name = $action->getName();
		if (isset($this->actions[$name])) {
			$this->actions[$name][] = $action;
		} else {
			$this->actions[$name] = [$action];
		}
	}

	/**
	 * Compile to produce all the activities.
	 *
	 * @return the activities
	 */
	public function compile(): array
	{
		// make sure dimensions are in order and missing dimensions are nulled;
		foreach ($this->actions as $actions) {
			foreach ($actions as $action) {
				$action->orderDimensions($this->dimensions);
			}
		}

		$activities = [];
		foreach ($this->actions as $actions) {
			foreach ($actions as $action) {
				$dimensions = $action->getDimensions();

				// create a hash so activities are only compiled once
				$hash = hash("md5", json_encode($dimensions), true);

				// check that activity is not already compiled
				if (isset($activities[$hash]))
					continue;

				$activity = $this->createActivity($dimensions);
				if ($activity === null) continue;

				// remember that this activity is already found
				$activities[$hash] = $activity;
			}
		}

		// discard hashes
		$this->activities = array_values($activities);

		return $this->activities;
	}

	/**
	 * Create an activity.
	 *
	 * @param  array  $dimensions  the dimensions to create the activity for
	 * @return {dimensions: array, readonly: bool, actions: array}
	 */
	public function createActivity(array $dimensions): ?stdClass
	{
		$shouldHave = [];
		foreach ($this->dimensions as $dim) {
			if (isset($dimensions[$dim])) {
				$shouldHave[$dim] = $dimensions[$dim];
			}
		}
		$mustNotHave = [];
		foreach ($this->dimensions as $dim) {
			if (!isset($dimensions[$dim])) {
				$mustNotHave[] = $dim;
			}
		}
		$joins = [];
		$join = 0;
		$found = [];
		$readonly = true;

		$walk = function($key)use($shouldHave,$mustNotHave,&$joins,&$join,&$found,&$readonly,&$walk):\Generator{
			// find best matching action to key
			$mostSpecific = 0;
			$matches = [];
			foreach ($this->actions[$key] as $ix => $action) {
				foreach ($mustNotHave as $dim) {
					if (isset($action->dimensions[$dim])) {
						continue 2;
					}
				}
				foreach ($shouldHave as $dim => $value) {
					if (isset($action->dimensions[$dim]) && $action->dimensions[$dim] !== $value) {
						continue 2;
					}
				}
				$specific = 0;
				foreach ($shouldHave as $dim => $value) {
					if (isset($action->dimensions[$dim]) && $action->dimensions[$dim] === $value) {
						++$specific;
					}
				}
				if ($specific < $mostSpecific) {
					continue;
				} elseif ($specific > $mostSpecific) {
					$mostSpecific = $specific;
					$matches = [$action];
				} else {
					$matches[] = $action;
				}
			}
			switch (count($matches)) {
				case 0:
					return;
				case 1:
					$action = reset($matches);
					break;
				default:
					foreach ($shouldHave as $dim => $value) {
						$dimFound = false;
						foreach ($matches as $action) {
							if (isset($action->dimensions[$dim])) {
								$dimFound = true;
								break;
							}
						}
						if ($dimFound) {
							foreach ($matches as $ix => $action) {
								if (!isset($action->dimensions[$dim])) {
									unset($matches[$ix]);
								}
							}
						}
					}
					$action = reset($matches);
					break;
			}

			if ($action->getReadonly() === false) {
				$readonly = false;
			}

			$hash = spl_object_hash($action);

			// return a join in case this is a join action
			if ($action->isJoin()) {
				if (isset($joins[$hash])) {
					$joinNumber = $joins[$hash];
				} else {
					$joinNumber = $joins[$hash] = ++$join;
				}
				$clone = clone $key; // clone first, key is part of a next expression
				yield $joinNumber => $clone;
				// modify key which has already been yielded by reference
				$key = $joinNumber;
			}

			// already walked over this action? should only happen for loops and joins
			if (isset($found[$hash])) {
				return;
			} else {
				$found[$hash] = true;
			}

			// get the next action(s)
			$next = $action->getNext();

			yield $key => $next; // yield action

			// does the action have return values?
			if (is_object($next)) {
				foreach ($next as $returnValue => &$expr) {
					if (is_string($expr)) { // expr is key of next action
						yield from $walk($expr);
					} elseif (is_array($expr)) { // expr is array of keys, forking the activity
						foreach ($expr as $expr) {
							yield from $walk($expr);
						}
					} elseif ($expr === false) { // expr is end of activity
						return;
					}
				}
			} else {
				if (is_string($next)) { // expr is key of next action
					yield from $walk($next);
				} elseif (is_array($next)) { // fork actions
					foreach ($next as &$expr) { // expr is array of keys, forking the activity
						yield from $walk($expr);
					}
				} elseif ($next === false) { // expr is end of activity
					return;
				}
			}
		};

		$key = "start";
		$actions = [];

		foreach ($walk($key) as $key => $next) {
			$actions[$key] = $next; // next may get changed, in case of a join
		}

		if (count($actions)) {
			$activity = new stdClass;
			$activity->dimensions = $dimensions;
			$activity->readonly = $readonly;
			$activity->actions = $actions;
			return $activity;
		} else {
			return null;
		}
	}
}
