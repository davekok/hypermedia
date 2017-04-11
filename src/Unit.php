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
	public function addAction(string $className, string $methodName, bool $start, $next, array $dimensions): self
	{
		if (!in_array($className, $this->classes)) {
			$this->classes[] = $className;
		}
		foreach ($dimensions as $name => $value) {
			if (!in_array($name, $this->dimensions)) {
				$this->dimensions[] = $name;
				$keys = array_keys($this->actions);
				foreach ($keys as &$key) {
					$key .= ' ';
				}
				$this->actions = array_combine($keys, array_values($this->actions));
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
		if ($start) {
			if (isset($this->actions[$dims]["start"])) {
				throw new Exception("Activity for $dims already has {$this->actions[$dims]["start"]} as start action, however $className::$methodName is also declared as start action.");
			}
			$this->actions[$dims]["start"] = "$className::$methodName";
		}
		$this->actions[$dims]["$className::$methodName"] = $next;

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
