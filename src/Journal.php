<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * Interface to the journal to be implemented by the appliction.
 */
interface Journal
{
	const activity = 1;
	const resource = 2;

	/**
	 * Get the journal id.
	 *
	 * @return int  the journal id.
	 */
	public function getId(): int;

	/**
	 * Get source unit
	 *
	 * @return string
	 */
	public function getSourceUnit(): ?string;

	/**
	 * Get type
	 *
	 * @return int
	 */
	public function getType(): ?int;

	/**
	 * Get tags
	 *
	 * @return array
	 */
	public function getTags(): ?array;

	/**
	 * Get the main branch.
	 *
	 * @return JournalBranch  the main branch
	 */
	public function getMainBranch(): JournalBranch;

	/**
	 * Fork the current journal and return a new concurrent branch
	 * based on the main branch. The main branch will not be used
	 * until joined.
	 *
	 * @return JournalBranch
	 */
	public function fork(): JournalBranch;

	/**
	 * Join the concurrent branches from this point the main
	 * branch must be usable again and getConcurrentBranches
	 * should return null.
	 */
	public function join(): void;

	/**
	 * Get the concurrent branches from the last fork or null
	 * if no fork happened or a join has already occurred.
	 *
	 * @return iterable  iterator to iterate over the branches
	 */
	public function getConcurrentBranches(): ?iterable;

	/**
	 * A split has been reached. The $branches argument
	 * contains which named branches can be followed.
	 *
	 * @return Journal
	 */
	public function setSplit(array $branches): Journal;

	/**
	 * Get the named branches from the last split.
	 *
	 * @return array  the named branches
	 */
	public function getSplit(): array;

	/**
	 * Set follow branch
	 *
	 * @param ?string $followBranch
	 * @return self
	 */
	public function setFollowBranch(?string $followBranch): self;

	/**
	 * Get follow branch
	 *
	 * @return string
	 */
	public function getFollowBranch(): string;
}
