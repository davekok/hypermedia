<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Exception;

/**
 * A specialised container for activities. Initialise the container from a
 * general service container.
 */
class Container
{
	private $cacheDir;
	private $journalRepository;
	private $activityEntityRepository;
	private $stateFactory;

	/**
	 * Constructor
	 */
	public function __construct(array $arguments)
	{
		$this->cacheDir = $arguments['cacheDir']??sys_get_temp_dir()."/cache/".strtr(__NAMESPACE__, "\\", "/");
		$this->journalRepository = $arguments['journalRepository'];
		assert($this->journalRepository instanceof JournalRepositoryInterface);
		$this->activityEntityRepository = $arguments['activityEntityRepository'];
		assert($this->activityEntityRepository instanceof ActivityEntityRepositoryInterface);
		$this->stateFactory = $arguments['stateFactory'];
		assert($this->stateFactory instanceof StateFactoryInterface);
	}

	/**
	 * Get the cache dir
	 */
	public function getCacheDir(): string
	{
		return $this->cacheDir;
	}

	/**
	 * Get journal repository
	 */
	public function getJournalRepository(): JournalRepositoryInterface
	{
		return $this->journalRepository;
	}

	/**
	 * Get activity entity repository
	 */
	public function getActivityEntityRepository(): ActivityEntityRepositoryInterface
	{
		return $this->activityEntityRepository;
	}

	/**
	 * Get state factory
	 */
	public function getStateFactory(): StateFactoryInterface
	{
		return $this->stateFactory;
	}

	/**
	 * Factory method to create activities.
	 *
	 * @param $unit           the unit to which the activity belongs
	 * @param $dimensions     the dimensions to use
	 * @return Activity
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
	 * @return Activity
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
}
