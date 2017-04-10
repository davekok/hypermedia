<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Exception;

/**
 * Support class to represent a unit.
 */
class Unit
{
	/**
	 * @var string;
	 */
	private $name;

	/**
	 * @var string;
	 */
	private $stateClass;

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
	 * Set stateClass
	 *
	 * @param string $stateClass
	 * @return self
	 */
	public function setStateClass(string $stateClass): self
	{
		$this->stateClass = $stateClass;
		return $this;
	}

	/**
	 * Get stateClass
	 *
	 * @return string
	 */
	public function getStateClass(): string
	{
		return $this->stateClass;
	}

	/**
	 * Get classess
	 *
	 * @return the classes
	 */
	public function getClasses(): array
	{
		return $this->classes;
	}

	/**
	 * Get classess
	 *
	 * @return the classes
	 */
	public function getDimensions(): array
	{
		return $this->dimensions;
	}

	/**
	 * Add an action to this unit.
	 *
	 * @param $className   the class name the action is implemented in
	 * @param $methodName  the method name the action is implemented in
	 * @param $start       the first action
	 * @param $next        the next action to execute
	 * @param $dimensions  dimensions to map this action on
	 * @return $this
	 */
	public function addAction(string $className, string $methodName, bool $start, array $next, array $dimensions): self
	{
		if (!in_array($className, $this->classes)) {
			$this->classes[] = $className;
		}
		foreach ($dimensions as $name => $value) {
			if (!in_array($name, $this->dimensions)) {
				$this->dimensions[] = $name;
				foreach ($this->actions as &$key => $actions) {
					$key .= ' ';
				}
			}
		}
		$dims = [];
		foreach ($this->dimensions as $dim) {
			$dims[] = $dimensions[$dim];
		}
		$dims = implode(" ", $dims);
		if (!isset($this->actions[$dims])) {
			$this->actions[$dims] = [];
		}
		$this->actions[$dims]["$className::$methodName"] = $next;
		if ($start) {
			if (isset($this->actions[$dims]["start"])) {
				throw new Exception("Activity for $dims already has {$this->actions[$dims]["start"]} as start action, however $className::$methodName is also declared as start action.");
			}
			$this->actions[$dims]["start"] = "$className::$methodName";
		}

		return $this;
	}

	/**
	 * Get all actions
	 *
	 * @return $actions
	 */
	public function getActions(): array
	{
		return $this->actions;
	}
}
