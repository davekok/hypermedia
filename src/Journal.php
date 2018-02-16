<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * Interface to the journal to be implemented by the appliction.
 */
interface Journal
{
	/**
	 * Get the journal id.
	 *
	 * @return string  the journal id.
	 */
	public function getId(): string;

	/**
	 * Get source unit
	 *
	 * @return string
	 */
	public function getSourceUnit(): string;

	/**
	 * Get tags
	 *
	 * @return array
	 */
	public function getTags(): array;

	/**
	 * Creates a new branch, belonging to this journal
	 *
	 * @param int $junction   the junction number to create the branch for.
	 * @return JournalBranch  a new journal branch
	 *
	 * Note that junction zero will only ever contain one branch, the main branch.
	 */
	public function createBranch(int $junction): JournalBranch;

	/**
	 * Get the first branch created for this journal.
	 *
	 * @return JournalBranch  the first branch
	 */
	public function getFirstBranch(): JournalBranch;

	/**
	 * Get the last branch created for this journal.
	 *
	 * @return JournalBranch  the last branch
	 */
	public function getLastBranch(): JournalBranch;

	/**
	 * Get branches for the given junction.
	 *
	 * @param int $junction   the junction number
	 * @return iterable  a iterable to iterate the branches
	 */
	public function getBranchesForJunction(int $junction): iterable;

	/**
	 * Get the branches from all junctions.
	 *
	 * @return iterable  a iterable to iterate the branches
	 */
	public function getBranches(): iterable;

	/**
	 * A split has been reached. The $branches argument
	 * contains which named branches can be followed.
	 *
	 * @param array $branches  the named branches
	 * @return Journal
	 */
	public function setSplit(?array $branches): Journal;

	/**
	 * Get the named branches from the last split.
	 *
	 * @return ?array  the named branches
	 */
	public function getSplit(): ?array;

	/**
	 * Set follow branch
	 *
	 * @param ?string $followBranch
	 * @return Journal
	 */
	public function setFollowBranch(?string $followBranch): Journal;

	/**
	 * Get follow branch
	 *
	 * @return ?string
	 */
	public function getFollowBranch(): ?string;
}
