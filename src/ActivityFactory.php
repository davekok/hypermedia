<?php declare(strict_types=1);

namespace Sturdy\Activity;

interface ActivityFactory
{
	/**
	 * Factory method to create a new activity.
	 *
	 * @param $unit        the unit name
	 * @param $dimensions  the dimensions to use
	 * @return new activity
	 */
	public function createActivity(string $unit, array $dimensions = []): Activity;

	/**
	 * Factory method to create an activity from stored journal.
	 *
	 * @param $unit        the unit name
	 * @param $dimensions  the dimensions to use
	 * @return loaded activity
	 */
	public function loadActivity(int $journalId): Activity;
}
