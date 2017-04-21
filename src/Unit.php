<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Exception;

/**
 * Support class to represent a unit.
 */
final class Unit
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
	 * @return array<string=>\stdClass>  the activities
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
	 * @return array<string=>array<\stdClass>>  actions
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
	 * @param $next        the next action to execute
	 * @param $dimensions  dimensions to map this action on
	 * @return $this
	 */
	public function addAction(string $className, string $methodName, bool $start, $next, array $dimensions): self
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
			$this->_addAction("start", $name, $dimensions);
		}
		$this->_addAction($name, $next, $dimensions);

		return $this;
	}

	private function _addAction(string $name, $next, array $dimensions): void
	{
		$action = new \stdClass;
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
				$hash = hash("md5", json_encode($action->dimensions), true);

				if (isset($activities[$hash]))
					continue;

				$start = $this->findBestMatch("start", $this->shouldHave($action), $this->mustNotHave($action));
				if ($start === null) continue;

				$activity = new \stdClass;
				$activity->dimensions = $action->dimensions;
				$activity->actions = ["start" => $start->next];
				$this->walk($activity->actions, $start->next, $this->shouldHave($action), $this->mustNotHave($action));

				$activities[$hash] = $activity;
			}
		}

		$this->activities = array_values($activities);

		return $this->activities;
	}

	public function walk(array &$actions, $next, array $shouldHave, array $mustNotHave): void
	{
		if (is_array($next)) {
			foreach ($next as $nextValue => $action) {
				$this->walk($actions, $action, $shouldHave, $mustNotHave);
			}
		} elseif (is_string($next)) {
			if (isset($actions[$next])) // if already computed then skip
				return;
			$action = $this->findBestMatch($next, $shouldHave, $mustNotHave);
			$actions[$next] = $action->next;
			$this->walk($actions, $action->next, $shouldHave, $mustNotHave);
		}
	}

	public function findBestMatch(string $name, array $shouldHave, array $mustNotHave): ?\stdClass
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

	public function shouldHave(\stdClass $action): array
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

	public function mustNotHave(\stdClass $action): array
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
