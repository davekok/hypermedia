<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable;
use Exception;
use Generator;
use stdClass;

/**
 * The main class of the component.
 *
 * Create or load a journal and run, enjoy.
 */
final class Activity
{
	// dependencies
	private $cache;
	private $journalRepository;
	private $stateFactory;
	private $instanceFactory;

	// state
	private $readonly;
	private $journal;
	private $state;
	private $actions;

	/**
	 * Constructor
	 */
	public function __construct(
		ActivityCache $cache,
		JournalRepository $journalRepository,
		StateFactory $stateFactory,
		InstanceFactory $instanceFactory)
	{
		$this->cache = $cache;
		$this->journalRepository = $journalRepository;
		$this->stateFactory = $stateFactory;
		$this->instanceFactory = $instanceFactory;
	}

	/**
	 * Create a new journal for this activity.
	 *
	 * @param $unit        the unit name
	 * @param $dimensions  the dimensions to use
	 * @return self
	 */
	public function createJournal(string $unit, array $dimensions = []): self
	{
		$this->loadActivityFromCache($unit, $dimensions);

		if (!$this->readonly) {
			$this->journal = $this->journalRepository->createJournal($unit, $dimensions);
		} else {
			// use a dummy journal in case of readonly activity
			$this->journal = new class($unit, $dimensions) implements Journal {
				private $unit;
				private $dimensions;
				private $states;
				private $return;
				private $errorMessages;
				private $currentActions;
				private $running;

				public function __construct(string $unit, array $dimensions)
				{
					$this->unit = $unit;
					$this->dimensions = $dimensions;
					$this->currentActions = [];
					$this->running = [];
					$this->states = [];
					$this->errorMessages = [];
				}

				public function getUnit(): ?string
				{
					return $this->unit;
				}

				public function getDimensions(): ?array
				{
					return $this->dimensions;
				}

				public function setState(int $branch, stdClass $state): Journal
				{
					$this->states[$branch] = $state;
					return $this;
				}

				public function getState(int $branch): ?stdClass
				{
					return $this->states[$branch];
				}

				public function setReturn($return): Journal
				{
					$this->return = $return;
					return $this;
				}

				public function getReturn()
				{
					return $this->return;
				}

				public function setErrorMessage(int $branch, ?string $errorMessage): Journal
				{
					$this->errorMessages[$branch] = $errorMessage;
					return $this;
				}

				public function getErrorMessage(int $branch): ?string
				{
					return $this->errorMessages[$branch];
				}

				public function setCurrentAction(int $branch, string $currentAction): Journal
				{
					$this->currentActions[$branch] = $currentAction;
					return $this;
				}

				public function getCurrentAction(int $branch): string
				{
					return $this->currentActions[$branch];
				}

				public function setRunning(int $branch, bool $running): Journal
				{
					$this->running[$branch] = $running;
					return $this;
				}

				public function getRunning(int $branch): bool
				{
					return $this->running[$branch];
				}
			};
		}

		$this->journal->setCurrentAction(0, "start");
		$this->journal->setRunning(0, false);

		$this->state = $this->stateFactory->createState($unit, $dimensions);
		$this->journal->setState(0, $this->state);

		return $this;
	}

	/**
	 * Load a previously persisted journal to continue an activity.
	 *
	 * @param $unit        the unit name
	 * @param $dimensions  the dimensions to use
	 * @return self
	 */
	public function loadJournal(int $journalId): self
	{
		$this->journal = $this->journalRepository->findOneJournalById($journalId);
		$this->state = $this->journal->getState(0);
		$this->loadActivityFromCache($this->journal->getUnit(), $this->journal->getDimensions());
		return $this;
	}

	/**
	 * Wether an activity is available.
	 *
	 * @param  $unit        the activity
	 * @param  $dimensions  the dimensions
	 * @return bool
	 */
	public function hasActivity($unit, $dimensions): bool
	{
		return $this->cache->hasActivity($unit, $dimensions);
	}

	/**
	 * Load activity from cache.
	 *
	 * @param  string $unit       the unit to load the activity from
	 * @param  array  $dimensions the dimensions to load the activity for
	 */
	private function loadActivityFromCache(string $unit, array $dimensions): void
	{
		$activity = $this->cache->getActivity($unit, $dimensions);
		$this->readonly = $activity["readonly"];
		$this->actions = $activity["actions"];
	}

	/**
	 * Get unit
	 *
	 * @return string
	 */
	public function getUnit(): string
	{
		return $this->journal->getUnit();
	}

	/**
	 * Get dimensions
	 *
	 * @return array
	 */
	public function getDimensions(): array
	{
		return $this->journal->getDimensions();
	}

	/**
	 * Set state variable
	 */
	public function set(string $name, $value): self
	{
		$this->state->$name = $value;

		return $this;
	}

	/**
	 * Get state variable
	 */
	public function get(string $name)
	{
		return $this->state->$name;
	}

	/**
	 * Set return
	 */
	public function setReturn($return): self
	{
		$this->journal->setReturn($return);

		return $this;
	}

	/**
	 * Get return
	 */
	public function getReturn()
	{
		return $this->journal->getReturn();
	}

	/**
	 * Is constant activity?
	 */
	public function isReadonly(): bool
	{
		return $this->readonly;
	}

	/**
	 * Is activity running?
	 */
	public function isRunning(int $branch): bool
	{
		return $this->journal->getRunning($branch);
	}

	/**
	 * Pauses the activity until it is resumed.
	 */
	public function pause(int $branch): self
	{
		$this->journal->setRunning($branch, false);

		return $this;
	}

	/**
	 * Resume the activity.
	 */
	public function resume(int $branch): self
	{
		$this->journal->setRunning($branch, true);

		return $this;
	}

	/**
	 * Get the error message
	 */
	public function getErrorMessage(int $branch): ?string
	{
		return $this->journal->getErrorMessage($branch);
	}

	/**
	 * Get current action
	 */
	public function getCurrentAction(int $branch): string
	{
		return $this->journal->getCurrentAction($branch);
	}

	/**
	 * Save journal
	 */
	public function saveJournal(): void
	{
		if (!$this->readonly) {
			$this->journal->setState(0, $this->state);
			$this->journalRepository->saveJournal($this->journal);
		}
	}

	/**
	 * Run the activity.
	 *
	 * This will only work if your actions are simple functions not generators.
	 * If they are not you will have to use a worker.
	 */
	public function run()
	{
		$coroutine = $this->coroutine();
		$coroutine->rewind();
		if ($coroutine->valid()) {
			throw new Exception("Activity has actions that are generators.");
		} else {
			return $this->journal->getReturn();
		}
	}

	/**
	 * Coroutine to run the activity.
	 *
	 * Note that if actions in the activity are themselfs coroutines they will be yielded
	 * instead of executed. Use a worker to execute them and send the return of the
	 * coroutine back to this one.
	 *
	 * Pseudo example to get you started:
	 *     $coroutine = $activity->coroutine();
	 *     $worker = new Worker();
	 *     $worker->add($coroutine, function($action)use($worker,$coroutine){ // $action is the value yielded by $coroutine
	 *         $worker->pause($coroutine);
	 *         $worker->add($action);
	 *         $worker->whenFinished($action, function($return)use($worker,$coroutine){
	 *             $coroutine->send($return);
	 *             $worker->resume($coroutine);
	 *         });
	 *     });
	 *     $worker->run();
	 */
	public function coroutine(): Generator
	{
		try {
			$action = $this->journal->getCurrentAction(0);
			switch ($action) {
				case "start":
					if (!array_key_exists($action, $this->actions)) {
						throw new Exception("Start action does not exist.");
					}
					$action = $this->actions["start"];
					$this->journal->setCurrentAction(0, $action);
					$this->journal->setRunning(0, true);
					break;
				case "stop":
				case "exception":
					if ($this->journal->getRunning(0)) {
						$this->journal->setRunning(0, false);
						$this->saveJournal();
					}
					return;
			}

			while ($this->journal->getRunning(0)) {

				if (!array_key_exists($action, $this->actions)) {
					throw new Exception("Action '$action' does not exist.");
				}
				$p = strpos($action, "::");
				if ($p === false) {
					throw new Exception("Action '$action' is not valid.");
				}
				$class = substr($action, 0, $p);
				$method = substr($action, $p+2);
				if (!isset($instance) || !$instance instanceof $class) {
					$instance = $this->instanceFactory->createInstance($this->getUnit(), $class);
				}
				if (!method_exists($instance, $method)) {
					throw new Exception("Method '$method' missing in class '".get_class($instance)."'");
				}

				$ret = $instance->$method($this);
				if ($ret instanceof Generator) {
					$ret = yield $ret;
				}

				$nextValue = null;
				if ($ret === null || is_bool($ret) || is_int($ret)) {
					$nextValue = $ret;
				} elseif (is_object($ret)) {
					$nextValue = $ret->next ?? $ret->getNext();
				} else {
					throw new Exception("Unexpected return value for $action: ".var_export($ret,true));
				}

				$next = $this->actions[$action];
				if ($next === false) {
					if ($nextValue !== null) {
						throw new Exception("Expected no next value.");
					}
					$this->journal->setCurrentAction(0, "stop");
					$this->journal->setRunning(0, false);
					$this->saveJournal();
					return;
				} elseif (is_string($next)) {
					if ($nextValue !== null) {
						throw new Exception("Expected no next value.");
					}
					$this->journal->setCurrentAction(0, $action = $next);
					$this->saveJournal();
				} else if (is_array($next)) {
					foreach ($next as $nv => $na) {
						if (($nv === "true" && $ret === true) || ($nv === "false" && $ret === false) || (((int)$nv) === $ret)) {
							$this->journal->setCurrentAction(0, $action = $na);
							$this->saveJournal();
							continue 2;
						}
					}
					throw new Exception("Unexpected next value ".var_export($ret,true)." for $action, expected one of ".implode(", ",array_keys($next)));
				}
			}
		} catch (Throwable $e) {
			$this->journal->setCurrentAction(0, "exception");
			$this->journal->setErrorMessage(0, $e->getMessage());
			$this->journal->setRunning(0, false);
			$this->saveJournal();
			throw $e;
		}
	}
}
