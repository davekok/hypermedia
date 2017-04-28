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
	 * @param $className   the class name the action is implemented in
	 * @param $methodName  the method name the action is implemented in
	 * @param $start       the first action
	 * @param $const       a constant action
	 * @param $next        the next action to execute
	 * @param $dimensions  dimensions to map this action on
	 * @return $this
	 */
	public function addAction(string $className, string $methodName, bool $start, bool $const, $next, array $dimensions): self
	{
		if (!in_array($className, $this->classes)) {
			$this->classes[] = $className;
		}

		foreach ($dimensions as $dim => $value) {
			if (!in_array($dim, $this->dimensions)) {
				$this->dimensions[] = $dim;
			}
		}

		$name = "$className::$methodName";
		if ($start) {
			$this->_addAction("start", true, $name, $dimensions);
		}
		$this->_addAction($name, $const, $next, $dimensions);

		return $this;
	}

	private function _addAction(string $name, bool $const, $next, array $dimensions): void
	{
		$action = new stdClass;
		$action->const = $const;
		$action->next = $next;
		$action->dimensions = $dimensions;
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
				$dimensions = [];
				foreach ($this->dimensions as $dimension) {
					$dimensions[$dimension] = $action->dimensions[$dimension]??null;
				}
				$action->dimensions = $dimensions;
			}
		}

		$activities = [];
		foreach ($this->actions as $actions) {
			foreach ($actions as $action) {
				// create a hash so activities are only compiled once
				$hash = hash("md5", json_encode($action->dimensions), true);

				// check that activity is not already compiled
				if (isset($activities[$hash]))
					continue;

				$shouldHave = $this->shouldHave($action);
				$mustNotHave = $this->mustNotHave($action);

				// find the start action for activity
				$start = $this->findBestMatch("start", $shouldHave, $mustNotHave);
				if ($start === null) continue;

				// construct temporary object for activity
				$activity = new stdClass;
				$activity->const = $action->const;
				$activity->dimensions = $action->dimensions;
				$activity->actions = ["start" => $start->next];
				$this->walk($activity, $start->next, $shouldHave, $mustNotHave);

				// remember that this activity is already found
				$activities[$hash] = $activity;
			}
		}

		// discard hashes
		$this->activities = array_values($activities);

		return $this->activities;
	}

	/**
	 * Walk through the actions to construct the activity.
	 */
	public function walk(stdClass $activity, /*array|string|null*/ $next, array $shouldHave, array $mustNotHave): void
	{
		// does the action have multiple next posibilities?
		if (is_array($next)) {
			foreach ($next as $nextValue => $action) {
				$this->walk($activity, $action, $shouldHave, $mustNotHave);
			}
		} elseif (is_string($next)) {
			if (isset($activity->actions[$next])) // if already computed then skip, should only happen for loops
				return;

			$action = $this->findBestMatch($next, $shouldHave, $mustNotHave);
			$activity->actions[$next] = $action->next;
			if ($action->const === false)
				$activity->const = false;

			// continue with next action
			$this->walk($activity, $action->next, $shouldHave, $mustNotHave);
		} elseif ($next !== null) {
			throw new \InvalidArgumentException("Argument should either be an array, a string or null, got ".get_type($next));
		}
	}

	/**
	 * Find best match for an action given the should have dimensions and must not have dimensions
	 */
	public function findBestMatch(string $name, array $shouldHave, array $mustNotHave): ?stdClass
	{
		// find best match
		$mostSpecific = 0;
		$matches = [];
		foreach ($this->actions[$name] as $ix => $action) {
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
				return null;
			case 1:
				return reset($matches);
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
				return reset($matches);
		}
	}

	/**
	 * Retrieve the should have dimensions.
	 */
	public function shouldHave(stdClass $action): array
	{
		// set should have array but in order of $this->dimensions
		$shouldHave = [];
		foreach ($this->dimensions as $dim) {
			if (isset($action->dimensions[$dim])) {
				$shouldHave[$dim] = $action->dimensions[$dim];
			}
		}
		return $shouldHave;
	}

	/**
	 * Retrieve the must not have dimensions.
	 */
	public function mustNotHave(stdClass $action): array
	{
		$mustNotHave = [];
		foreach ($this->dimensions as $dim) {
			if (!isset($action->dimensions[$dim])) {
				$mustNotHave[] = $dim;
			}
		}
		return $mustNotHave;
	}
}
