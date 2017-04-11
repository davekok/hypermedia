<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Exception;

/**
 * A façade for the activity component.
 */
final class Facade implements StateFactoryInterface
{
	private $activityRepository;
	private $journalRepository;
	private $stateFactory;
	private $cacheDir;
	private $listeners;

	/**
	 * Constructor
	 */
	public function __construct(
		Repository\ActivityRepositoryInterface $activityRepository,
		Repository\JournalRepositoryInterface $journalRepository,
		StateFactoryInterface $stateFactory,
		?string $cacheDir = null)
	{
		$this->activityRepository = $activityRepository;
		$this->journalRepository = $journalRepository;
		$this->stateFactory = $stateFactory;
		$this->cacheDir = $cacheDir ?? (sys_get_temp_dir()."/cache/".strtr(__NAMESPACE__, "\\", "/"));
		$this->listeners = [];
	}

	/**
	 * Get repository for journal entities
	 *
	 * @return journal repository
	 */
	public function getJournalRepository(): Repository\JournalRepositoryInterface
	{
		return $this->journalRepository;
	}

	/**
	 * Get repository for activity entities
	 *
	 * @return activity repository
	 */
	public function getActivityRepository(): Repository\ActivityRepositoryInterface
	{
		return $this->activityRepository;
	}

	/**
	 * Get the cache dir
	 *
	 * @return cache directory
	 */
	public function getCacheDir(): string
	{
		return $this->cacheDir;
	}

	/**
	 * Create a instance of state for the specified unit.
	 *
	 * @param $unit  the unit to create state for
	 *
	 * @return state object
	 */
	public function createState(string $unit): State
	{
		return $this->stateFactory->createState($unit);
	}

	/**
	 * Factory method to create activities.
	 *
	 * @param $unit           the unit to which the activity belongs
	 * @param $dimensions     the dimensions to use
	 * @return freshly created activity
	 */
	public function createActivity(string $unit, array $dimensions): Activity
	{
		return (new Activity($this))
			->setActivityByName($unit, $activityName)
			->createJournal();
	}

	/**
	 * Factory method to load the journal of a running activity.
	 *
	 * @param $journalId   The journal to load.
	 * @return stored activity
	 */
	public function loadActivity(int $journalId): Activity
	{
		$journal = $this->journalRepository->findOneById($journalId);
		if (empty($journal)) {
			throw new Exception('Journal not found.');
		}
		return (new Activity($this))
			->setJournal($journal);
	}

	/**
	 * Add an event listener to this activity.
	 *
	 * @param $eventName  the event name
	 * @param $listener   a callable to call on event dispatch
	 */
	public function addEventListener(string $eventName, callable $listener): void
	{
		$this->listeners[$eventName][] = $listener;
	}

	/**
	 * Dispatch an event to all listeners for that event.
	 *
	 * @param $eventName  the event name
	 * @param $event      event object to dispatch
	 */
	private function dispatchEvent(string $eventName, $event): void
	{
		foreach ($this->listeners[$eventName] as $listener) {
			$listener($event);
		}
	}

	/**
	 * Get cache object.
	 *
	 * @param $annotationReader  an annotation reader
	 */
	public function getCache(\Doctrine\Common\Annotations\Reader $annotationReader): Cache
	{
		return new Cache($annotationReader, $this->cacheDir);
	}
}
