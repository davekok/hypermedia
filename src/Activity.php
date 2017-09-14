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
	public function __construct(Cache $cache, JournalRepository $journalRepository, string $sourceUnit)
	{
		$this->cache = $cache;
		$this->journalRepository = $journalRepository;
		$this->sourceUnit = $sourceUnit;
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
		$this->journal = $this->journalRepository->createJournal($this->sourceUnit, Journal::activity, $this->tags);
		$this->branch = $this->journal->getMainBranch()->addEntry(new $this->class, "start", 1);
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
		$class = get_class($this->journal->getMainBranch()->getLastEntry()->getObject());

		if ($this->activity === null) {
			if (!$this->load($class, $this->journal->getTags())) {
				throw new \Exception("Activity not found.");
			}
		} elseif (
			$this->type !== $this->journal->getType()
			&& $this->class !== $class
			&& $this->tags !== $this->journal->getTags()
		) {
			throw new \Exception("The journal has been created for a different activity or resource.");
		}
		$this->class = $class;

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
	 * Get the status code
	 */
	public function getStatusCode(): int
	{
		return $this->branch->getLastEntry()->getStatusCode();
	}

	/**
	 * Get the status text
	 */
	public function getStatusText(): ?string
	{
		return $this->branch->getLastEntry()->getStatusText();
	}

	/**
	 * Get current object
	 */
	public function getCurrentObject()/*: object */
	{
		return $this->branch->getLastEntry()->getObject();
	}

	/**
	 * Get current action
	 */
	public function getCurrentAction(): string
	{
		return $this->branch->getLastEntry()->getAction();
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
		$entry = $this->branch->getLastEntry();
		$object = $entry->getObject();
		$action = $entry->getAction();
		switch ($action) {
			case "start":
				$this->advanceMainBranch($object, $action); // move to first action
				break;

			case "split":
				$branch = $this->journal->getFollowBranch();
				if ($branch) {
					$this->branch->addEntry($object, $this->journal->getSplit()[$branch], 1);
					$this->journal->setFollowBranch(null);
					break;
				} else {
					return;
				}
		}
		while (($entry = $this->branch->getLastEntry())->getStatusCode() === 1) {
			if ($this->branches->isEmpty()) {
				// run main branch
				try {
					$object = $entry->getObject();
					$action = $entry->getAction();
					yield $this->getCallback($object, $action);
					$this->advanceMainBranch($object, $action);
				} catch (Throwable $e) {
					$this->branch->addEntry($object, $action, 0, $e->getMessage()."\n".$e->getTraceAsString());
				} finally {
					$this->saveJournal();
				}
			} else {
				// run concurrent branches
				while (!$this->branches->isEmpty()) { // set should be empty if all branches finish
					foreach ($this->branches as $this->branch) {
						try {
							$entry = $this->branch->getLastEntry();
							$object = $entry->getObject();
							$action = $entry->getAction();
							yield $this->getCallback($object, $action);
							$this->advanceConcurrentBranch($object, $action);
						} catch (Throwable $e) {
							$this->branch->addEntry($object, $action, 0, $e->getMessage()."\n".$e->getTraceAsString());
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
	private function advanceMainBranch(/*object*/ $object, string $action): void
	{
		[$type, $next] = $this->evalNextExpressionFor($action);
		switch ($type) {
			case self::STOP:
				$this->branch->addEntry($object, "stop", 0);
				break;

			case self::ACTION:
				$this->branch->addEntry($object, $next, 1);
				break;

			case self::FORK:
				foreach ($next as $action) {
					$this->branches->add($this->journal->fork()->addEntry($object, $action, 1));
				}
				break;

			case self::SPLIT:
				$this->branch->addEntry($object, "split", 0);
				$this->journal->setSplit($next);
				break;

			case self::JOIN:
				$this->branch->addEntry($object, $this->actions[$next], 1); // resolve join indirection
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
	private function advanceConcurrentBranch(/*object*/ $object, string $action): void
	{
		[$type, $next] = $this->evalNextExpressionFor($action);
		switch ($type) {
			case self::STOP:
				$this->branch->addEntry($object, "stop", 0);
				$this->branches->remove($this->branch);
				break;

			case self::ACTION:
				$this->branch->addEntry($object, $next, 1);
				break;

			case self::FORK:
				throw new Exception("A fork in a fork is not supported.");

			case self::SPLIT:
				throw new Exception("A split in a fork is not supported.");

			case self::JOIN:
				$this->journal->getMainBranch()->addEntry($object, $this->actions[$next], 1); // resolve join indirection
				$this->branch->addEntry($object, "join", 0);
				$this->branches->remove($this->branch);
				break;

			case self::DETACH:
				// TODO: clone activity, create new journal and move detaching branch to new
				// journal, the actions should remain the same
				throw new Exception("Detaching is not yet implemented.");
		}
	}

	private function getCallback(/*object*/ $object, string $action): callable
	{
		if (!array_key_exists($action, $this->actions)) {
			throw new Exception("Action '$action' does not exist.");
		}
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
}
