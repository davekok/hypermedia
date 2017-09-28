<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * This class wraps around the journal interfaces to implement
 * journaling.
 */
class Journaling
{
	private $journalRepository;
	private $journal;
	private $mainBranch;
	private $junction;

	/**
	 * Constructor
	 *
	 * @param JournalRepository $journalRepository  journal repository
	 */
	public function __construct(JournalRepository $journalRepository)
	{
		$this->journalRepository = $journalRepository;
	}

	/**
	 * Create a new journal.
	 *
	 * @param string $sourceUnit
	 * @param int    $type
	 * @param array  $tags
	 */
	public function create(string $sourceUnit, int $type, array $tags): void
	{
		$this->journal = $this->journalRepository->createJournal($sourceUnit, $type, $tags);
		$this->journalRepository->saveJournal($this->journal); // save it so we have an id
		$this->mainBranch = $this->journal->createBranch(0);
		$this->junction = 1;
	}

	/**
	 * Resume an existing journal.
	 *
	 * @param int $journalId  the id of the journal
	 */
	public function resume(int $journalId): void
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
	 * @return int  the id
	 */
	public function getId(): int
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
	 * Fork journal
	 *
	 * @return JournalBranch  new branch
	 */
	public function fork(): JournalBranch
	{
		return $this->journal->createBranch($this->junction);
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
	public function split(?array $branches): self
	{
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
