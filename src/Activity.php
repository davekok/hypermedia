<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable;
use Exception;
use DateTime;
use Generator;
use Psr\Cache\CacheItemPoolInterface;

/**
 * The main class of the component.
 *
 * Create or load an activity and run, enjoy.
 */
final class Activity implements ActivityFactory
{
	// dependencies
	private $cache;
	private $journalRepository;
	private $stateFactory;
	private $instanceFactory;

	// state
	private $state;
	private $journal;
	private $actions;

	/**
	 * Constructor
	 */
	public function __construct(
		Cache $cache,
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
	 * Factory method to create a new activity.
	 *
	 * @param $unit        the unit name
	 * @param $dimensions  the dimensions to use
	 * @return new activity
	 */
	public function createActivity(string $unit, array $dimensions = []): Activity
	{
		$self = clone $this;
		$self->state = $self->stateFactory->createState($unit, $dimensions);
		$self->journal = $self->journalRepository->createJournal($unit, $dimensions, $self->state);
		$self->actions = $this->cache->getActions($self);
		return $self;
	}

	/**
	 * Factory method to create an activity from stored journal.
	 *
	 * @param $unit        the unit name
	 * @param $dimensions  the dimensions to use
	 * @return loaded activity
	 */
	public function loadActivity(int $journalId): Activity
	{
		$self = clone $this;
		$self->journal = $self->journalRepository->findOneJournalById($journalId);
		$self->state = $self->journal->getState();
		$self->actions = $this->cache->getActions($self);
		return $self;
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
	 * Pauses the activity until it is resumed.
	 */
	public function pause(): self
	{
		$this->journal->setRunning(false);

		return $this;
	}

	/**
	 * Resume the activity.
	 */
	public function resume(): self
	{
		$this->journal->setRunning(true);

		return $this;
	}

	/**
	 * Get the error message
	 */
	public function getErrorMessage(): ?string
	{
		return $this->journal->getErrorMessage();
	}

	/**
	 * Get current action
	 */
	public function getCurrentAction(): string
	{
		return $this->journal->getCurrentAction();
	}

	/**
	 * Save journal
	 */
	public function saveJournal(): void
	{
		$this->journal->setState($this->state);
		$this->journalRepository->saveJournal($this->journal);
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
			return $coroutine->getReturn();
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
	 *     $worker->add($coroutine, function($yield)use($worker,$coroutine){ // $yield is the value yielded by $coroutine
	 *         $worker->pause($coroutine);
	 *         $worker->add($yield);
	 *         $worker->whenFinished($yield, function($return)use($worker,$coroutine){
	 *             $coroutine->send($return);
	 *             $worker->resume($coroutine);
	 *         });
	 *     });
	 *     $worker->run();
	 */
	public function coroutine(): Generator
	{
		try {
			$action = $this->journal->getCurrentAction();
			switch ($action) {
				case "start":
					$action = $this->actions["start"];
					$this->journal->setCurrentAction($action);
					break;
				case "stop":
				case "exception":
					return;
			}

			while ($this->journal->getRunning()) {

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
					$nextValue = $ret->next??$ret->getNext();
				} else {
					throw new Exception("Unexpected return value for $action: ".var_export($ret,true));
				}

				$next = $this->actions[$action];
				if ($next === null) {
					if ($nextValue !== null) {
						throw new Exception("Expected no next value.");
					}
					$this->journal->setCurrentAction("stop");
					$this->saveJournal();
					return;
				} elseif (is_string($next)) {
					if ($nextValue !== null) {
						throw new Exception("Expected no next value.");
					}
					$this->journal->setCurrentAction($action = $next);
					$this->saveJournal();
				} else if (is_array($next)) {
					foreach ($next as $nv => $na) {
						if (($nv === "true" && $ret === true) || ($nv === "false" && $ret === false) || (((int)$nv) === $ret)) {
							$this->journal->setCurrentAction($action = $na);
							$this->saveJournal();
							continue 2;
						}
					}
					throw new Exception("Unexpected next value ".var_export($ret,true)." for $action, expected one of ".implode(", ",array_keys($next)));
				}
			}
		} catch (Throwable $e) {
			$this->journal->setErrorMessage($e->getMessage());
			$this->journal->setCurrentAction("exception");
			$this->saveJournal();
			throw $e;
		}
	}
}
