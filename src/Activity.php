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
final class Activity implements ActivityInterface
{
	const STOP   = 0; // when advancing stop the activity
	const ACTION = 1; // when advancing go to the action
	const FORK   = 2; // when advancing fork the activity
	const SPLIT  = 3; // when advancing split the activity
	const JOIN   = 4; // when advancing join the activity
	const DETACH = 5; // when advanving detach the branch from the current activity

	// dependencies
	private $cache;
	private $journalRepository;

	// state
	private $unit;
	private $dimensions;
	private $journal;
	private $state;
	private $instance;
	private $actions;
	private $branch;
	private $branches;
	private $decision;

	/**
	 * Constructor
	 */
	public function __construct(
		ActivityCache $cache,
		JournalRepository $journalRepository)
	{
		$this->cache = $cache;
		$this->journalRepository = $journalRepository;
	}

	/**
	 * Load activity.
	 *
	 * @param string $unit        the unit to load the activity from
	 * @param array  $dimensions  the dimensions to load the activity for
	 * @return true if activity is loaded, false otherwise
	 */
	public function load(string $unit, array $dimensions): bool
	{
		$activity = $this->cache->getActivity($unit, $dimensions);
		if ($activity === null) {
			return false;
		}
		$this->unit = $unit;
		$this->dimensions = $dimensions;
		$this->actions = $activity->actions;
		return true;
	}

	/**
	 * Create a new journal for this activity.
	 *
	 * @return self
	 */
	public function createJournal(): self
	{
		$this->journal = $this->journalRepository->createJournal($this->unit, $this->dimensions);
		$this->branch = $this->journal->getMainBranch()
			->setCurrentAction("start")
			->setRunning(true);
		$this->branches = new \Ds\Set();
		return $this;
	}

	/**
	 * Load a previously persisted journal to continue an activity.
	 *
	 * @param int $journalId  the id of the journal to load
	 * @return self
	 */
	public function loadJournal(int $journalId): self
	{
		$this->journal = $this->journalRepository->findOneJournalById($journalId);

		if ($this->activity === null) {
			if (!$this->load($this->journal->getUnit(), $this->journal->getDimensions())) {
				throw new \Exception("Activity not found.");
			}
		} elseif ($this->unit !== $this->journal->getUnit() || $this->dimensions !== $this->journal->getDimensions()) {
			throw new \Exception("The journal has been created for a different activity.");
		}

		$this->branch = $this->journal->getMainBranch();
		$this->branches = new \Ds\Set;
		$branches = $this->journal->getConcurrentBranches();
		if ($branches) {
			$this->branches->allocate(count($branches));
			foreach ($branches as $branch) {
				$this->branches->add($branch);
			}
		}

		return $this;
	}

	/**
	 * Get the journal id
	 * @return int  the journal id
	 */
	public function getJournalId(): int
	{
		return $this->journal->getId();
	}

	/**
	 * Get unit
	 *
	 * @return string
	 */
	public function getUnit(): string
	{
		return $this->unit;
	}

	/**
	 * Get dimensions
	 *
	 * @return array
	 */
	public function getDimensions(): array
	{
		return $this->dimensions;
	}

	/**
	 * Get state
	 *
	 * @return object  a state instance
	 */
	public function getState()/*: object*/
	{
		return $this->branch->getState();
	}

	/**
	 * Is activity running?
	 */
	public function isRunning(): bool
	{
		return $this->branch->getRunning();
	}

	/**
	 * Pauses the activity until it is resumed.
	 */
	public function pause(): ActivityInterface
	{
		$this->branch->setRunning(false);
		return $this;
	}

	/**
	 * Resume the activity.
	 */
	public function resume(): ActivityInterface
	{
		$this->branch->setRunning(true);
		return $this;
	}

	/**
	 * Stop the activity.
	 */
	private function stop(): void
	{
		$this->branch->setCurrentAction("stop");
		$this->branch->setRunning(false);
	}

	/**
	 * Get the error message
	 */
	public function getErrorMessage(): ?string
	{
		return $this->branch->getErrorMessage();
	}

	/**
	 * Get current action
	 */
	public function getCurrentAction(): string
	{
		return $this->branch->getCurrentAction();
	}

	/**
	 * Save journal
	 */
	public function saveJournal(): void
	{
		$this->journalRepository->saveJournal($this->journal);
	}

	/**
	 * @InheritDocs
	 */
	public function decide($decision): ActivityInterface
	{
		if ($decision === true) {
			$this->decision = "+";
		} elseif ($decision === false) {
			$this->decision = "-";
		} else {
			$this->decision = $decision;
		}
		return $this;
	}

	/**
	 * @InheritDocs
	 */
	public function getBranches(): array
	{
		return array_keys($this->journal->getSplit());
	}

	/**
	 * @InheritDocs
	 */
	public function followBranch(string $branch): ActivityInterface
	{
		$split = $this->journal->getSplit();
		if (array_key_exists($branch, $split)) {
			$this->journal->setFollowBranch($branch);
		} else {
			throw new Exception("$branch does not exist for this split, use ".print_r(array_keys($split), true));
		}
		return $this;
	}

	/**
	 * @InheritDocs
	 */
	public function actions(): Generator
	{
		$action = $this->branch->getCurrentAction();
		switch ($action) {
			case "start":
				$this->advanceMainBranch($action); // move to first action
				break;

			case "split":
				$branch = $this->journal->getFollowBranch();
				if ($branch) {
					$this->branch->setCurrentAction($this->journal->getSplit()[$branch]);
					$this->journal->setFollowBranch(null);
					$this->branch->setRunning(true);
					break;
				} else {
					return;
				}
		}
		while ($this->branch->getRunning()) {
			if ($this->branches->isEmpty()) {
				// run main branch
				try {
					$action = $this->branch->getCurrentAction();
					yield $this->getCallback($action);
					$this->advanceMainBranch($action);
				} catch (Throwable $e) {
					$this->exception($e);
				} finally {
					$this->saveJournal();
				}
			} else {
				// run concurrent branches
				while (!$this->branches->isEmpty()) { // set should be empty if all branches finish
					foreach ($this->branches as $this->branch) {
						try {
							$action = $this->branch->getCurrentAction();
							yield $this->getCallback($action);
							$this->advanceConcurrentBranch($action);
						} catch (Throwable $e) {
							$this->exception($e);
							$this->branches->remove($this->branch);
						} finally {
							$this->saveJournal();
						}
					}
				}
				$this->journal->join(); // join branches
				$this->branch = $this->journal->getMainBranch();
			}
		}
	}

	/**
	 * Advance main branch
	 *
	 * @param string  $action
	 */
	private function advanceMainBranch(string $action): void
	{
		[$type, $next] = $this->evalNextExpressionFor($action);
		switch ($type) {
			case self::STOP:
				$this->stop();
				break;

			case self::ACTION:
				$this->branch->setCurrentAction($next);
				break;

			case self::FORK:
				foreach ($next as $action) {
					$this->branches->add($this->journal->fork()->setCurrentAction($action));
				}
				break;

			case self::SPLIT:
				$this->branch->setCurrentAction("split");
				$this->journal->setSplit($next);
				$this->branch->setRunning(false);
				break;

			case self::JOIN:
				$this->branch->setCurrentAction($this->actions[$next]); // resolve join indirection
				break;

			case self::DETACH:
				// should the main branch be able to detach??
				throw new Exception("Detaching is not yet implemented.");
		}
	}

	/**
	 * Advance concurrent branch
	 *
	 * @param string  $action
	 */
	private function advanceConcurrentBranch(string $action): void
	{
		[$type, $next] = $this->evalNextExpressionFor($action);
		switch ($type) {
			case self::STOP:
				$this->stop();
				$this->branches->remove($this->branch);
				break;

			case self::ACTION:
				$this->branch->setCurrentAction($next);
				break;

			case self::FORK:
				throw new Exception("A fork in a fork is not supported.");

			case self::SPLIT:
				throw new Exception("A split in a fork is not supported.");

			case self::JOIN:
				$this->journal->getMainBranch()->setCurrentAction($this->actions[$next]); // resolve join indirection
				$this->branch->setCurrentAction("join");
				$this->branch->setRunning(false);
				$this->branches->remove($this->branch);
				break;

			case self::DETACH:
				// TODO: clone activity, create new journal and move detaching branch to new
				// journal, the actions should remain the same
				throw new Exception("Detaching is not yet implemented.");
		}
	}

	private function getCallback(string $action): callable
	{
		if (!array_key_exists($action, $this->actions)) {
			throw new Exception("Action '$action' does not exist.");
		}
		$p = strpos($action, "::");
		if ($p === false) {
			throw new Exception("Action '$action' is not valid.");
		}
		$class = substr($action, 0, $p);
		$method = substr($action, $p+2);
		if (!isset($this->instance) || !$this->instance instanceof $class) {
			$this->instance = $this->journal->getInstance($class);
		}
		if (!method_exists($this->instance, $method)) {
			throw new Exception("Method '$method' missing in class '".get_class($this->instance)."'");
		}
		return [$this->instance, $method];
	}

	private function evalNextExpressionFor(string $currentAction)
	{
		$next = $this->actions[$currentAction];
		switch (true) {
			case false === $next: // should the activity end?
				if ($this->decision === null) { // there should be no decision
					return [self::STOP, null];
				}
				throw new Exception("Did not expect a decision.");

			case is_int($next): // join action
				return [self::JOIN, $next];

			case is_string($next): // the next action
			case is_array($next): // branching action
				if ($this->decision === null) { // there should be no decision
					break;
				}
				throw new Exception("Did not expect a decision.");

			case is_object($next): // decision
				foreach ($next as $nv => $subnext) {
					if ($nv === $this->decision) {
						$next = $subnext;
						$this->decision = null; // clear decision
						break 2;
					}
				}
				throw new Exception("Unexpected decision ".var_export($this->decision,true)." for $currentAction, expected one of ".implode(", ",array_keys($next)));

			default:
				throw new Exception("Unknown next expression ".var_export($next,true));
		}
		if (is_string($next)) {
			return [self::ACTION, $next]; // action
		} elseif (is_array($next)) { // array when branching otherwise null
			reset($next);
			if (is_int(key($next))) {
				return [self::FORK, $next];
			} else {
				return [self::SPLIT, $next];
			}
		}
	}

	private function exception(Throwable $e)
	{
		echo $e->getMessage()."\n".$e->getTraceAsString();
		$this->branch->setErrorMessage($e->getMessage()."\n".$e->getTraceAsString());
		$this->branch->setCurrentAction("exception");
		$this->branch->setRunning(false);
	}
}
