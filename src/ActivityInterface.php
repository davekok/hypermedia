<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Generator;

/**
 * The main class of the component.
 *
 * Create or load a journal and run, enjoy.
 */
interface ActivityInterface
{
	/**
	 * Generator to iterate over the actions in this activity. So you can run them one
	 * by one.
	 *
	 * This function will never rewind the activity. It will simply continue where
	 * the activity got paused or quit if the end of the activity has already been
	 * reached. Manipulate the journal to rewind the activity.
	 *
	 * Example:
	 *
	 *     $generator = $activity->actions();
	 *     foreach ($generator as $action) {
	 *         try {
	 *             $action($activity);
	 *         } catch (Throwable $e) {
	 *             $generator->throw($e);
	 *         }
	 *     }
	 *
	 * @return Generator<callable>  a generator that yields the next action as a callable
	 */
	public function actions(): Generator;

	/**
	 * In case of a decision in the activity, decide which
	 * way to continue the activity
	 *
	 * This function is expected to be called before the
	 * actions generator continues. For instance inside
	 * your action. If no decision is made the actions
	 * generator will throw an exception and the activity
	 * will set to the exception state.
	 *
	 * @param $decision  the decision
	 */
	public function decide($decision): ActivityInterface;

	/**
	 * In case of a split in the activity, get the named
	 * branches from which you can choose.
	 *
	 * @return array<string>  the named branches
	 */
	public function getBranches(): array;

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
	 * @param string $branch the name of the branch.
	 */
	public function followBranch(string $branch): ActivityInterface;

	/**
	 * Get the journal id
	 *
	 * @return int
	 */
	public function getJournalId(): int;

	/**
	 * Get source unit
	 *
	 * @return string
	 */
	public function getSourceUnit(): string;

	/**
	 * Get class
	 *
	 * @return string
	 */
	public function getClass(): string;

	/**
	 * Get tags
	 *
	 * @return array
	 */
	public function getTags(): array;

	/**
	 * Is activity running?
	 */
	public function isRunning(): bool;

	/**
	 * Pauses the activity until it is resumed.
	 */
	public function pause(): ActivityInterface;

	/**
	 * Resume the activity.
	 */
	public function resume(): ActivityInterface;

	/**
	 * Get the error message
	 */
	public function getErrorMessage(): ?string;

	/**
	 * Get the current action
	 */
	public function getCurrentAction(): string;
}
