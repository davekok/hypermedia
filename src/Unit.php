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
		$className = $action->getClassName()
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
				// create a hash so activities are only compiled once
				$hash = hash("md5", json_encode($action->getDimensions()), true);

				// check that activity is not already compiled
				if (isset($activities[$hash]))
					continue;

				$activity = new stdClass;

				$activity->shouldHave = $this->shouldHave($action);
				$activity->mustNotHave = $this->mustNotHave($action);

				// find the start action for activity
				$start = $this->findBestMatch($activity, "start");
				if ($start === null) continue;

				// construct temporary object for activity
				$activity->readonly = $action->getReadonly();
				$activity->dimensions = $action->getDimensions();
				$this->walk($activity, $start);
				unset($activity->shouldHave, $activity->mustNotHave);

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
	public function walk(stdClass $activity, Action $action): void
	{
		$key = $action->getKey();
		$next = $action->getNext();

		// does the action have return values?
		if ($action->hasReturnValues()) {
			$activity->actions[$key] = $next;
			foreach ($next as $returnValue => $exp) {
				if ($exp === false) { // end
				} elseif (is_string($exp)) { // next
					$this->walk($activity, $this->actions[$exp]);
				} elseif (is_array($exp)) {
					foreach ($exp as $exp) { // fork
						$this->walk($activity, $this->actions[$exp]);
					}
				}
			}
		} else {
			if (is_string($next)) { // next action
				if (isset($activity->actions[$next])) // if already computed then skip, should only happen for loops
					return;

				$nextAction = $this->findBestMatch($next);
				if ($action->getReadonly() === false)
					$activity->readonly = false;

				// continue with next action
				$this->walk($activity, $nextAction);
			} elseif (is_array($next)) { // fork actions
				foreach ($next as $returnValue => $exp) {
					$this->walk($activity, $action);
				}
			}
			$activity->actions[$key] = $next;
		}
	}

	/**
	 * Find best match for an action given the should have dimensions and must not have dimensions
	 */
	public function findBestMatch(stdClass $activity, string $name): ?stdClass
	{
		// find best match
		$mostSpecific = 0;
		$matches = [];
		foreach ($this->actions[$name] as $ix => $action) {
			foreach ($activity->mustNotHave as $dim) {
				if (isset($action->dimensions[$dim])) {
					continue 2;
				}
			}
			foreach ($activity->shouldHave as $dim => $value) {
				if (isset($action->dimensions[$dim]) && $action->dimensions[$dim] !== $value) {
					continue 2;
				}
			}
			$specific = 0;
			foreach ($activity->shouldHave as $dim => $value) {
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
				foreach ($activity->shouldHave as $dim => $value) {
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
