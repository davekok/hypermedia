<?php declare(strict_types=1);

namespace Sturdy\Activity;

use stdClass;

/**
 * Support class to represent a source unit.
 */
final class SourceUnit implements CacheSourceUnit
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
	 * @var array<string>
	 */
	private $wildCardDimensions = [];

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
	 * Get classes
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
	 * Set wild card dimensions
	 *
	 * @param array $wildCardDimensions
	 * @return self
	 */
	public function setWildCardDimensions(array $wildCardDimensions): self
	{
		$this->wildCardDimensions = $wildCardDimensions;
		return $this;
	}

	/**
	 * Get wild card dimensions
	 *
	 * @return array
	 */
	public function getWildCardDimensions(): array
	{
		return $this->wildCardDimensions;
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
	 * @return array<string:array<stdClass>>  actions
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
			$start->setReadonly($action->getReadonly());
			$start->setJoin(false);
			$start->setReturnValues(false);
			$start->setDimensions($action->getDimensions());
			$this->_addAction($start);
		}
		$this->_addAction($action);

		return $this;
	}

	private function _addAction(Action $action): void
	{
		$name = $action->getKey();
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

		$compiler = new SourceUnitCompiler($this);
		$activities = [];
		foreach ($this->actions as $actions) {
			foreach ($actions as $action) {
				$dimensions = $action->getDimensions();

				// create a hash so activities are only compiled once
				$hash = hash("md5", json_encode($dimensions), true);

				// check that activity is not already compiled
				if (isset($activities[$hash]))
					continue;

				$activity = $compiler->createActivity($dimensions);
				if ($activity === null) continue;

				// remember that this activity is already found
				$activities[$hash] = $activity;

				// check for wild card dimensions
				foreach ($dimensions as $key => $value) {
					if ($value === true) {
						$this->wildCardDimensions[] = $key;
					}
				}
			}
		}

		// discard hashes
		$this->activities = array_values($activities);

		return $this->activities;
	}

	/**
	 * Find the best match for given key.
	 *
	 * @param string $key         the key the search for
	 * @param array $shouldHave   dimensions the action should have
	 * @param array $mustNotHave  dimensions the action must not have
	 * @return Action              the best match
	 */
	public function findBestMatch(string $key, array $shouldHave, array $mustNotHave): ?Action
	{
		// find best matching action to key
		$mostSpecific = 0;
		$matches = [];
		foreach ($this->actions[$key] as $ix => $action) {
			foreach ($mustNotHave as $dim) {
				if ($action->hasDimension($dim)) {
					continue 2;
				}
			}
			foreach ($shouldHave as $dim => $value) {
				if ($action->hasDimension($dim) && $action->getDimension($dim) !== $value && $action->getDimension($dim) !== true) {
					continue 2;
				}
			}
			$specific = 0;
			foreach ($shouldHave as $dim => $value) {
				if ($action->hasDimension($dim)) {
					if ($action->getDimension($dim) === $value) {
						$specific += 2;
					} elseif ($action->getDimension($dim) === true) {
						++$specific;
					}
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
						if ($action->hasDimension($dim)) {
							$dimFound = true;
							break;
						}
					}
					if ($dimFound) {
						foreach ($matches as $ix => $action) {
							if (!$action->hasDimension($dim)) {
								unset($matches[$ix]);
							}
						}
					}
				}
				return reset($matches);
		}
	}
}
