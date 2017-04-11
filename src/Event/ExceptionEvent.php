<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Sturdy\Activity\Activity;

/**
 * In case an exception occured while executing an activity.
 */
final class ExceptionEvent implements Event
{
	private $activity;
	private $exception;

	/**
	 * Constructor
	 *
	 * @param $activity   the activity that got screwed
	 * @param $exception  the exception that occured
	 */
	public function __construct(Activity $activity, Throwable $exception)
	{
		$this->activity  = $activity;
		$this->exception = $exception;
	}

	/**
	 * Get activity
	 */
	public function getActivity(): Activity
	{
		return $this->activity;
	}

	/**
	 * Get exception
	 */
	public function getException(): Exception
	{
		return $this->exception;
	}
}
