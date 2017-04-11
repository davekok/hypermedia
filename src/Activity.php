<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Throwable;
use Exception;
use DateTime;
use Generator;

final class Activity
{
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

	private $facade;
	private $state; // shared state
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
	public function __construct(Facade $facade)
	{
		$this->facade = $facade;
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
	 * Set activity
	 */
	public function setActivity(Entity\ActivityInterface $activityEntity): self
	{
		$unit = $activityEntity->getUnitName();
		$name = $activityEntity->getName();
		$file = $this->container->getCacheDir()."/{$unit}-{$name}.php";

		if (!file_exists($file)) {
			throw new Exception("Activity {$unit}:{$name} not found.");
		}

		$this->activity = include($file);
		$this->activityEntity = $activityEntity;
		return $this;
	}

	/**
	 * Set activity by name
	 */
	public function setActivityByName(string $unit, string $name): self
	{
		return $this->setActivity($this->container->getActivityEntityRepository()->findOrCreateOneByUnitAndName($unit, $name));
	}

	/**
	 * Set activity by id
	 */
	public function setActivityById(int $activityId): self
	{
		return $this->setActivity($this->container->getActivityEntityRepository()->findOneById($activityId));
	}

	/**
	 * Create journal
	 */
	public function createJournal(): self
	{
		$this->state = $this->container->getStateFactory()->createState($this->activityEntity->getUnitName());
		$this->state->action = self::NEXT;
		$journalRepository = $this->container->getJournalRepository();
		$this->journal = $journalRepository->createJournal($this->activityEntity, $this->state);
		$journalRepository->saveJournal($this->journal);
		return $this;
	}

	/**
	 * Set journal
	 */
	public function setJournal(Entity\JournalInterface $journal): self
	{
		$this->journal = $journal;
		$this->state = $journal->getState();
		assert($this->state !== null);
		$activity = $journal->getActivity();
		assert($activity !== null);
		$this->setActivity($activity);
		return $this;
	}

	/**
	 * Get journal
	 */
	public function getJournal(): Entity\JournalInterface
	{
		return $this->journal;
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
	 * Get return value
	 */
	public function getReturnValue()
	{
		return $this->journal->getReturnValue();
	}

	/**
	 * Coroutine to run the activity.
	 *
	 * Note that if actions in the activity are themselfs coroutines. Their generator will be yielded.
	 */
	public function run()
	{
		if (empty($this->journal) || !in_array($this->journal->getStatus(), self::RESUMABLE))
			return null;

		if ($this->reset()) {
			$atAction = $this->journal->getCurrentAction(); // action activity is at, continue from there or if null start at the beginning
			if ($atAction !== null) {
				while ($atAction !== $this->atAction) { // find the action to continue from
					[$this->atComponent, $this->atAction] = next($this->activity) ?: [null,null];
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

			// check if an instance of the component is already loaded for this activity
			if (!isset($instance) || !$instance instanceof $this->atComponent) {
				if (isset($instance) && method_exists($instance, 'shutdown')) {
					yield $instance->shutdown();
				}
				$class = $this->atComponent;
				$instance = new $class($this->container, $this->state);
				if (method_exists($instance, 'boot')) {
					yield $instance->boot();
				}
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
				$ret = [self::ACTION_NOT_FOUND, new Exception("Job {$this->atComponent}::{$this->atAction} does not exist.")];
			}

			// process status code
			switch ($status = array_shift($ret)) {

				case self::NEXT:
					$this->next();
					$this->flush();
					break;

				case self::END:
					[$returnVal] = $ret;
					$this->_end($returnVal);
					$this->flush();
					goto quit;

				case self::WAIT:
					$this->next(self::WAIT);
					$this->flush();
					goto quit;

				case self::DETACH:
					[$returnVal] = $ret;
					$this->next(self::DETACH);
					$this->flush();
					$this->queue(); // push activity to queue
					goto quit;

				// errors and such, end activity
				case self::EXCEPTION:
				case self::ACTION_NOT_FOUND:
					[$returnVal] = $ret;
					$this->journal->setErrorMessage($returnVal->getMessage());
					$this->_end(null, $status);
					$this->flush();
					goto quit;
			}
		}
	quit:
		if (isset($instance) && method_exists($instance, 'shutdown')) {
			yield $instance->shutdown();
		}
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
		[$this->atComponent, $this->atAction] = reset($this->activity) ?: [null,null];
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
					[$activityId, $this->atComponent, $this->atAction] = array_pop($activityStack);
					$this->state->activityStack = $activityStack;
					$this->setActivityById($activityId);
					$this->journal->setCurrentAction($this->atAction);
					return;
				}
			}
			$this->_end();
		} else {
			[$this->atComponent, $this->atAction] = $next;
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

	/**
	 * Save journal
	 */
	private function flush(): void
	{
		$this->journal->setState($this->state);
		$this->container->getJournalRepository()->saveJournal($this->journal);
	}
}
