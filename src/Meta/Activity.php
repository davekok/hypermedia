<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Exception;
use Doctrine\Common\Annotations\Annotation\{
	Annotation,
	Target,
	Attributes,
	Attribute
};

/**
 * Activity meta class
 */
class Activity
{
	private $class;
	private $description;
	private $actions;

	/**
	 * Constructor
	 *
	 * @param string $class
	 */
	public function __construct(string $class, string $description)
	{
		$this->class = $class;
		$this->description = $description;
	}

	/**
	 * Get class
	 *
	 * @return string
	 */
	public function getClass(): string
	{
		return $this->class;
	}

	/**
	 * Set description
	 *
	 * @param string $description
	 * @return self
	 */
	public function setDescription(string $description): self
	{
		$this->description = $description;
		return $this;
	}

	/**
	 * Get description
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * Add an action to this unit.
	 *
	 * As actions are added also classes and tags are added.
	 *
	 * @param $action  the action
	 * @return $this
	 */
	public function addAction(Action $action): self
	{
		if ($action->getStart()) {
			$start = new Action();
			$start->setName("start");
			$start->setNext($action->getName());
			$start->setJoin(false);
			$start->setTags($action->getTags());
			$this->actions[$start->getName()][] = $start;
		}
		$this->actions[$action->getName()][] = $action;

		return $this;
	}

	/**
	 * Get actions with $name.
	 *
	 * @param string $name  the action name
	 * @return Action[]  actions
	 */
	public function getActionsWithName(string $name): array
	{
		return $this->actions[$name];
	}

	/**
	 * Get all taggables
	 *
	 * @return iterable  the taggables
	 */
	public function getTaggables(): iterable
	{
		foreach ($this->actions??[] as $name => $actions) {
			foreach ($actions as $action) {
				yield $action;
			}
		}
	}
}
