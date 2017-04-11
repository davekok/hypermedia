<?php declare(strict_types=1);

namespace Sturdy\Activity\Event;

use Sturdy\Activity\Activity;

/**
 * Detach Event
 *
 * Event describing a detaching activity.
 */
final class DetachEvent implements Event
{
	private $activity;
	private $userData;

	/**
	 * Constructor
	 *
	 * @param $activity  the detaching activity
	 * @param $userData  extra user data to be consumed by the application (usually in a controller)
	 */
	public function __construct(Activity $activity, $userData)
	{
		$this->activity = $activity;
		$this->userData = $userData;
	}

	/**
	 * Get activity
	 */
	public function getActivity(): Activity
	{
		return $this->activity;
	}

	/**
	 * Get user data
	 */
	public function getUserData()
	{
		return $this->userData;
	}
}
