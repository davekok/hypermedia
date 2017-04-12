<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable;
use Exception;
use DateTime;
use Generator;

final class Activity implements ActivityFactory
{
	use MkDir;

	public const DEFAULT_UNIT     = "default";
	public const DEFAULT_ACTIVITY = "default";

	private const NEXT             =                   1;
	private const WAIT             = self::NEXT      + 1;
	private const DETACH           = self::WAIT      + 1;
	private const END              = self::DETACH    + 1;
	private const EXCEPTION        = self::END       + 1; // used when an exception is caught be the activity
	private const ACTION_NOT_FOUND = self::EXCEPTION + 1; // used when a action is not found

	public const STRINGS = [
		self::NEXT             => "next",
		self::WAIT             => "wait",
		self::DETACH           => "detach",
		self::END              => "end",
		self::EXCEPTION        => "exception",
		self::ACTION_NOT_FOUND => "action not found",
	];

	// resumable statuses, a Journal with one of these statuses can be resumed
	private const RESUMABLE = [
		self::NEXT,
		self::WAIT,
		self::DETACH,
	];

	private $nameRepository;
	private $dimensionRepository;
	private $journalRepository;
	private $stateFactory;
	private $instanceFactory;
	private $cacheDir;

	private $listeners;
	private $state;
	private $activity;
	private $journal;
	private $parent;
	private $activityEntity;
	private $atComponent;
	private $atAction;
	private $last;
	private $running;

	/**
	 * Constructor
	 */
	public function __construct(
		Repository\NameRepository $nameRepository,
		Repository\DimensionRepository $dimensionRepository,
		Repository\JournalRepository $journalRepository,
		StateFactory $stateFactory,
		InstanceFactory $instanceFactory,
		?string $cacheDir)
	{
		$this->nameRepository = $nameRepository;
		$this->dimensionRepository = $dimensionRepository;
		$this->journalRepository = $journalRepository;
		$this->stateFactory = $stateFactory;
		$this->instanceFactory = $instanceFactory;
		$this->cacheDir = $this->filterDir($cacheDir, 'cache');
	}

	/**
	 * Factory method to create a new activity.
	 *
	 * @param $unit        the unit name
	 * @param $dimensions  the dimensions to use
	 * @return new activity
	 */
	public function createActivity(string $unit, array $dimensions = []): self
	{
		$self = clone $this;
		$self->listeners = [];

		$self->unit = $self->nameRepository->findOrCreateOneName($unit);

		$self->dimensions = [];
		foreach ($dimensions??[] as $dimension => $value) {
			$self->dimensions[] = $self->dimensionRepository->findOrCreateOneDimension($dimension, $value);
		}

		$self->load();

		$self->state = $self->stateFactory->createState($self->unit->getName());
		$self->journal = $self->journalRepository->createJournal($self->unit, $self->dimensions, $self->state, self::NEXT);

		return $self;
	}

	/**
	 * Factory method to create an activity from stored journal.
	 *
	 * @param $unit        the unit name
	 * @param $dimensions  the dimensions to use
	 * @return loaded activity
	 */
	public function loadActivity(int $journalId): self
	{
		$self = clone $this;
		$self->listeners = [];

		$journal = $self->journalRepository->findOneById($journalId);
		if (empty($journal)) {
			throw new Exception('Journal not found.');
		}
		$self->journal = $journal;
		$self->state = $journal->getState();
		assert($self->state !== null);
		$self->unit = $journal->getUnit();
		assert($self->unit !== null);
		$self->dimensions = $journal->getDimensions();
		$self->load();

		return $self;
	}

	/**
	 * Load the actions from cache.
	 */
	private function load(): void
	{
		$file = $this->cacheDir.DIRECTORY_SEPARATOR.$this->unit->getId();
		foreach ($this->dimensions as $dimension) {
			$file.= "-".$dimension->getId();
		}
		$file.= ".php";

		if (!file_exists($file)) {
			$msg = "Activity $unit";
			if (count($this->dimensions)) {
				$msg.= " {"
				$i = 0;
				foreach ($this->dimensions as $dimension) {
					if ($i++) $msg.= ",";
					$msg.= $dimension->getDimension()->getName().":".$dimension->getValue();
				}
				$msg.= "}";
			}
			$msg.= " not found.";
			throw new Exception($msg);
		}

		$this->activity = include($file);
	}

	/**
	 * Get unit
	 *
	 * @return string
	 */
	public function getUnit(): string
	{
		return $this->unit->getName();
	}

	/**
	 * Get dimensions
	 *
	 * @return array
	 */
	public function getDimensions(): array
	{
		$dimensions = [];
		foreach ($this->dimensions as $dimension) {
			$dimensions[$dimension->getDimension()->getName()] = $dimension->getValue();
		}
		return $dimensions;
	}

	/**
	 * Get journal
	 */
	public function getJournal(): Entity\Journal
	{
		return $this->journal;
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
	 * Get return value
	 */
	public function getReturnValue()
	{
		return $this->journal->getReturnValue();
	}

	/**
	 * Add an event listener to this activity.
	 *
	 * @param $eventName  the event name
	 * @param $listener   a callable to call on event dispatch
	 */
	public function addEventListener(string $eventName, callable $listener): void
	{
		$this->listeners[$eventName][] = $listener;
	}

	/**
	 * Dispatch an event to all listeners for that event.
	 *
	 * @param $eventName  the event name
	 * @param $event      event object to dispatch
	 */
	private function dispatchEvent(string $eventName, $event): void
	{
		foreach ($this->listeners[$eventName] as $listener) {
			$listener($event);
		}
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
	 * Continue with the next action in this activity based on $nextValue.
	 *
	 * If an action does not return anything it is assumed to be next(null).
	 *
	 * @param $nextValue  either a bool, int or null indicating on which branch to continue the activity
	 *
	 * Usage:
	 *   return $activity->next($nextValue);
	 */
	public function next($nextValue = null): array
	{
		return [self::NEXT, $nextValue];
	}

	/**
	 * Pause the activity until it is resumed.
	 *
	 * This is useful if the activity needs to wait for an event. Resuming the activity
	 * needs to be handled elsewhere.
	 *
	 * @param $nextValue  either a bool, int or null indicating on which branch to continue the activity
	 *
	 * Usage:
	 *   return $activity->wait();
	 */
	public function wait($nextValue = null): array
	{
		return [self::WAIT];
	}

	/**
	 * Detach the activity from the current worker to be continued in another worker.
	 *
	 * @param $userData   extra data to send as part of the detach event
	 * @param $nextValue  either a bool, int or null indicating on which branch to continue the activity
	 *
	 * Usage:
	 *   return $activity->detach();
	 */
	public function detach($userData = null, $nextValue = null): array
	{
		return [self::DETACH, $userData, $nextValue];
	}

	/**
	 * End the current activity.
	 *
	 * @param $returnValue  a possible return value, this will be the journal's return value
	 *
	 * Usage:
	 *   return $activity->end($returnValue);
	 */
	public function end($returnValue = null): array
	{
		return [self::END, $returnValue];
	}

	/**
	 * Coroutine to run the activity.
	 *
	 * Note that if actions in the activity are themselfs coroutines. Their generator will be yielded.
	 */
	public function run()
	{
		if ($this->journal === null) {
			$this->createJournal();
		}
		if (!in_array($this->journal->getStatus(), self::RESUMABLE)) {
			return null;
		}

		if ($this->reset()) {
			$atAction = $this->journal->getCurrentAction(); // action activity is at, continue from there or if null start at the beginning
			if ($atAction !== null) {
				while ($atAction !== $this->atAction) { // find the action to continue from
					[$this->atClass, $this->atAction] = next($this->activity) ?: [null,null];
					// if the end is reached without finding the action the activity is at, call houston, we've got a problem
					if (empty($this->atAction)) {
						$this->journal->setStatus(self::ACTION_NOT_FOUND);
						$this->journal->setErrorMessage("corrupted activity");
						goto quit;
					}
				}
			}
			$this->journal->setCurrentAction($this->atAction);
		}

		while ($this->running) {

			// check if an instance of the class is already loaded for this activity
			if (!isset($instance) || !$instance instanceof $this->atClass) {
				$instance = $this->instanceFactory->createInstance($this->activityEntity->getUnitName(), $this->atClass, $this->state);
			}

			if (method_exists($instance, $this->atAction)) {
				try {
					$ret = $instance->{$this->atAction}($this->activityEntity->getName());
					if ($ret instanceof Generator) {
						$ret = yield $ret;
					}
					if ($ret === null) {
						$ret = [self::NEXT];
					} else if (is_array($ret) && is_int($ret[0])) {
						// nothing to do
					} else {
						$ret = [self::EXCEPTION, new Exception("Unexpected return value: ".print_r($ret,true))];
					}
				} catch (Throwable $e) {
					$ret = [self::EXCEPTION, $e];
				}
			} else {
				$ret = [self::ACTION_NOT_FOUND, new Exception("Job {$this->atClass}::{$this->atAction} does not exist.")];
			}

			// process status code
			switch ($status = array_shift($ret)) {

				case self::NEXT:
					$this->next();
					$this->saveJournal();
					break;

				case self::END:
					[$returnVal] = $ret;
					$this->_end($returnVal);
					$this->saveJournal();
					goto quit;

				case self::WAIT:
					$this->next(self::WAIT);
					$this->saveJournal();
					goto quit;

				case self::DETACH:
					[$returnVal] = $ret;
					$this->next(self::DETACH);
					$this->saveJournal();
					$this->queue(); // push activity to queue
					goto quit;

				// errors and such, end activity
				case self::EXCEPTION:
				case self::ACTION_NOT_FOUND:
					[$returnVal] = $ret;
					$this->journal->setErrorMessage($returnVal->getMessage());
					$this->_end(null, $status);
					$this->saveJournal();
					goto quit;
			}
		}
	quit:
		if (isset($returnVal)) {
			return $returnVal;
		} else {
			return;
		}
	}

	/**
	 * Reset
	 */
	private function reset(): bool
	{
		[$this->atClass, $this->atAction] = reset($this->activity) ?: [null,null];
		return $this->running = $this->atAction !== null;
	}

	/**
	 * Load next action
	 */
	private function next(int $status = self::NEXT): void
	{
		$next = next($this->activity);
		if ($next === false) {
			if (isset($this->state->activityStack)) {
				$activityStack = $this->state->activityStack;
				if (is_array($activityStack) && count($activityStack)) {
					[$activityId, $this->atClass, $this->atAction] = array_pop($activityStack);
					$this->state->activityStack = $activityStack;
					$this->setActivityById($activityId);
					$this->journal->setCurrentAction($this->atAction);
					return;
				}
			}
			$this->_end();
		} else {
			[$this->atClass, $this->atAction] = $next;
			$this->journal->setCurrentAction($this->atAction);
			$this->journal->setStatus($status);
			$this->journal->setState($this->state);
			$this->running = true;
		}
	}

	/**
	 * End the activity
	 */
	private function _end($returnValue = null, int $status = self::END): void
	{
		$this->journal->setStatus($status);
		$this->journal->setFinishedAt(new DateTime("now"));
		$this->journal->setReturnValue($returnValue);
		$this->running = false;
	}
}
