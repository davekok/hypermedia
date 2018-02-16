<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Ds\Map;

/**
 * This class wraps around the journal interfaces to implement
 * journaling.
 */
class Journaling
{
	private $journalRepository;
	private $di;
	private $journal;
	private $mainBranch;
	private $junction;
	private $objects;

	/**
	 * Constructor
	 *
	 * @param JournalRepository $journalRepository  journal repository
	 * @param                   $di                 container containing dependencies to inject
	 */
	public function __construct(JournalRepository $journalRepository, $di)
	{
		$this->journalRepository = $journalRepository;
		$this->di = $di;
		$this->objects = new Map;
	}

	/**
	 * Create a new journal.
	 *
	 * @param string $sourceUnit
	 * @param int    $type
	 * @param array  $tags
	 * @return object  the activity object
	 */
	public function create(string $sourceUnit, array $tags, string $class)/*: object*/
	{
		$this->journal = $this->journalRepository->createJournal($sourceUnit, $tags);
		$this->mainBranch = $this->journal->createBranch(0);
		$this->junction = 1;

		$object = new $class;
		if (method_exists($object, "setContainer")) {
			$object->setContainer($this->di);
		}
		$this->objects->put($this->mainBranch, $object);
		$this->mainBranch->addEntry($object, "start", 1);

		return $object;
	}

	/**
	 * Resume an existing journal.
	 *
	 * @param string $journalId  the id of the journal
	 */
	public function resume(string $journalId): void
	{
		$this->journal = $this->journalRepository->findOneJournalById($journalId);
		$this->mainBranch = $this->journal->getFirstBranch();
		if ($this->mainBranch->getJunction() !== 0) {
			throw new \Exception("First branch is not the main branch.");
		}
		$last = $this->journal->getLastBranch();
		$this->junction = $last->getJunction();
		if ($this->junction > 0) {
			foreach ($this->journal->getBranchesForJunction($this->junction) as $branch) {
				// not all branches for this junction have converged yet
				if ($branch->getLastEntry() !== "join") {
					return;
				}
			}
			// if all branches of last junction have converged than next fork starts on a new junction
			++$this->junction;
		}
	}

	/**
	 * Check if journal exists
	 */
	public function hasJournal(): bool
	{
		return isset($this->journal);
	}

	/**
	 * Save the journal
	 */
	public function save(): void
	{
		$this->journalRepository->saveJournal($this->journal);
	}

	/**
	 * Close the journal
	 */
	public function close(): void
	{
		unset($this->journal, $this->mainBranch);
	}

	/**
	 * Get id
	 *
	 * @return string  the id
	 */
	public function getId(): string
	{
		return $this->journal->getId();
	}

	/**
	 * Get type
	 *
	 * @return int  the type
	 */
	public function getType(): int
	{
		return $this->journal->getType();
	}

	/**
	 * Get tags
	 *
	 * @return array  the tags
	 */
	public function getTags(): array
	{
		return $this->journal->getTags();
	}

	/**
	 * Get the main branch
	 *
	 * @return JournalBranch  the main branch
	 */
	public function getMainBranch(): JournalBranch
	{
		return $this->mainBranch;
	}

	/**
	 * Get concurrent branches
	 *
	 * @return iterable  iterator to iterate of the branches
	 */
	public function getConcurrentBranches(): iterable
	{
		return $this->journal->getBranchesForJunction($this->junction);
	}

	/**
	 * Get the current entry for a branch.
	 *
	 * @return [$object, $action]  teh current entry
	 */
	public function current(?JournalBranch $branch = null): array
	{
		$branch = $branch ?? $this->mainBranch;
		$entry = $branch->getLastEntry();
		if ($this->objects->hasKey($branch)) {
			return [$this->objects->get($branch), $entry->getAction()];
		} else {
			$object = $entry->getObject();
			if (method_exists($object, "setContainer")) {
				$object->setContainer($this->di);
			}
			$this->objects->put($branch, $object);
			return [$object, $entry->getAction()];
		}
	}

	/**
	 * Get the object for a branch
	 *
	 * @return object  the main object
	 */
	public function getObject(?JournalBranch $branch = null)/*: object*/
	{
		$branch = $branch ?? $this->mainBranch;
		if ($this->objects->hasKey($branch)) {
			return $this->objects->get($branch);
		} else {
			$object = $branch->getLastEntry()->getObject();
			if (method_exists($object, "setContainer")) {
				$object->setContainer($this->di);
			}
			$this->objects->put($branch, $object);
			return $object;
		}
	}

	/**
	 * Get the action for a branch
	 *
	 * @return object  the main object
	 */
	public function getAction(?JournalBranch $branch = null)/*: object*/
	{
		$branch = $branch ?? $this->mainBranch;
		return $branch->getLastEntry()->getAction();
	}

	/**
	 * Get status code for a branch
	 *
	 * @return int  status code
	 */
	public function getStatusCode(?JournalBranch $branch = null)/*: object*/
	{
		$branch = $branch ?? $this->mainBranch;
		return $branch->getLastEntry()->getStatusCode();
	}

	/**
	 * Get status text for a branch
	 *
	 * @return string  status text
	 */
	public function getStatusText(?JournalBranch $branch = null)/*: object*/
	{
		$branch = $branch ?? $this->mainBranch;
		return $branch->getLastEntry()->getStatusText();
	}

	/**
	 * Stop branch
	 *
	 * @param  JournalBranch $branch     the branch to stop
	 */
	public function stop(?JournalBranch $branch = null): void
	{
		$branch = $branch ?? $this->mainBranch;
		$branch->addEntry($this->objects->get($branch), "stop", 0);
	}

	/**
	 * Set action for branch
	 *
	 * @param  JournalBranch $branch     the branch to stop
	 * @param  string        $action     set action for branch
	 */
	public function action(string $action, ?JournalBranch $branch = null): void
	{
		$branch = $branch ?? $this->mainBranch;
		$branch->addEntry($this->objects->get($branch), $action, 1);
	}


	/**
	 * Fork journal
	 *
	 * @param JournalBranch $branch   the branch to fork
	 * @param string[]      $actions  the actions to start the new branches with
	 * @return JournalBranch[]  the forked branches
	 */
	public function fork(array $actions, ?JournalBranch $branch = null): array
	{
		$object = $this->objects->get($branch);
		$branches = [];
		foreach ($actions as $action) {
			$branches[] = $this->journal->createBranch($this->junction)->addEntry($object, $action, 1);
		}
		return $branches;
	}

	/**
	 * Prepare joining of branches
	 *
	 * @param  JournalBranch $branch      the branch to prepare the join for
	 * @param  string        $joinAction  the join action
	 */
	public function preJoin(string $joinAction): void
	{
		$branch->addEntry($this->objects->get($this->mainBranch), $joinAction, 1); // resolve join indirection
	}

	/**
	 * End a branch because of a join.
	 *
	 * @param  JournalBranch $branch      the branch to prepare the join for
	 */
	public function endJoin(JournalBranch $branch): void
	{
		$branch->addEntry($this->objects->get($branch), "join", 0);
	}

	/**
	 * Join journal
	 */
	public function join(): void
	{
		++$this->junction;
	}

	/**
	 * A split has been reached. The $branches argument
	 * contains which named branches can be followed.
	 *
	 * @param array $branches  the named branches
	 * @return $this
	 */
	public function split(JournalBranch $branch, ?array $branches): self
	{
		$branch->addEntry($this->objects->get($branch), "split", 0);
		$this->journal->setSplit($branches);
		return $this;
	}

	/**
	 * In case of a split in the activity, get the named
	 * branches from which you can choose.
	 *
	 * @return array<string>  the named branches
	 */
	public function getBranches(): array
	{
		return array_keys($this->journal->getSplit());
	}

	/**
	 * In case of a split in the activity, choose which
	 * branch you wish to follow.
	 *
	 * If this function is not called before the actions
	 * generator continues, the activity is paused and
	 * the actions generator will return.
	 *
	 * You can use a split for interactive activities
	 * or for events.
	 *
	 * @param string $branch  the name of the branch.
	 */
	public function followBranch(string $branch): self
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
	 * Based on the branch that should be followed, return the next action.
	 *
	 * @return ?string  the next action based on branch to follow.
	 */
	public function getSplitAction(): ?string
	{
		$branch = $this->journal->getFollowBranch();
		if ($branch) {
			$action = $this->journal->getSplit()[$branch];
			$this->journal->setFollowBranch(null);
			return $action;
		} else {
			return null;
		}
	}
}
