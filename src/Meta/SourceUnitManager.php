<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

/**
 * Manages one or more source units
 */
class SourceUnitManager
{
	private $cache;
	private $sourceUnits;

	/**
	 * Constructor the source unit manager
	 *
	 * @param Cache      $cache        the cache system
	 * @param SourceUnit $sourceUnits  one or more source units
	 */
	public function __construct(Cache $cache, SourceUnit ...$sourceUnits)
	{
		$this->cache = $cache;
		$this->sourceUnits = $sourceUnits;
	}

	/**
	 * Update the cache
	 */
	public function updateCache(): void
	{
		$fp = fopen(sys_get_temp_dir()."/sturdy-activity-cache.lock", "w");
		if ($fp === false) return;
		if (flock($fp, LOCK_EX|LOCK_NB)) {
			foreach ($this->sourceUnits as $sourceUnit) {
				$this->cache->updateSourceUnit($sourceUnit);
			}
			flock($fp, LOCK_UN);
		}
		fclose($fp);
	}

	/**
	 * Get the source units
	 *
	 * @return array  the source units
	 */
	public function getSourceUnits(): array
	{
		return $this->sourceUnits;
	}
}
