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
	const DETACH = 5; // when advancing detach the branch from the current activity

	// dependencies/configuration
	private $cache;
	private $journalRepository;
	private $sourceUnit;
	private $loop;

	// state
	private $class;
	private $tags;
	private $journal;
	private $actions;
	private $branch;
	private $branches;
	private $decision;

	/**
	 * Constructor
	 */
	public function __construct(Cache $cache, JournalRepository $journalRepository, string $sourceUnit, Loop $loop = null)
	{
		$this->cache = $cache;
		$this->journalRepository = $journalRepository;
		$this->sourceUnit = $sourceUnit;
		$this->loop = $loop;
	}

	/**
	 * Clear state making the object fresh again.
	 *
	 * @return $this
	 */
	public function clear(): ActivityInterface
	{
		$this->class    = null;
		$this->tags     = null;
		$this->journal  = null;
		$this->object   = null;
		$this->actions  = null;
		$this->branch   = null;
		$this->branches = null;
		$this->decision = null;
		return $this;
	}

	/**
	 * Load activity.
	 *
	 * @param array  $tags  the tags to load the activity for
	 * @return true if activity is loaded, false otherwise
	 */
	public function load(string $class, array $tags): bool
	{
		$activity = $this->cache->getActivity($this->sourceUnit, $class, $tags);
		if ($activity === null) {
			return false;
		}
		$this->class = $activity->getClass();
		$this->tags = $activity->getTags();
		$this->actions = $activity->getActions();
		return true;
	}

	/**
	 * Create a new journal for this activity.
	 *
	 * @return self
	 */
	public function createJournal(): self
	{
		$this->journal = $this->journalRepository->createJournal($this->sourceUnit, Journal::activity, $this->class, $this->tags);
		$class = $this->class;
		$this->branch = $this->journal->getMainBranch()
			->setCurrentObject(new $class)
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
			if (!$this->load($this->journal->getClass(), $this->journal->getTags())) {
				throw new \Exception("Activity not found.");
			}
		} elseif (
			$this->type !== $this->journal->getType()
			&& $this->class !== $this->journal->getClass()
			&& $this->tags !== $this->journal->getTags()
		) {
			throw new \Exception("The journal has been created for a different activity or resource.");
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
	 *
	 * @return int  the journal id
	 */
	public function getJournalId(): int
	{
		return $this->journal->getId();
	}

	/**
	 * Get source unit
	 *
	 * @return string
	 */
	public function getSourceUnit(): string
	{
		return $this->sourceUnit;
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
	 * Get tags
	 *
	 * @return array
	 */
	public function getTags(): array
	{
		return $this->tags;
	}

	/**
	 * Get loop
	 *
	 * @return Loop
	 */
	public function getLoop(): ?Loop
	{
		return $this->loop;
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
		if ($this->loop) $this->loop->addActivity($this);
		return $this;
	}

	/**
	 * Stop the activity.
	 */
	private function stop(): void
	{
		$this->branch->setCurrentAction("stop");
		$this->branch->setRunning(false);
		if ($this->loop) $this->loop->remove($this);
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
		$object = $this->branch->getCurrentObject();
		if (!method_exists($object, $action)) {
			throw new Exception("Method '$action' missing in class '".get_class($object)."'");
		}
		return [$object, $action];
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
