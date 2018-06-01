<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * Interface implementing a shared state store
 */
interface SharedStateStore
{
	/**
	 * Set shared state
	 *
	 * @param string $pool   shared state pool name
	 * @param string $key    shared state key
	 * @param mixed  $value  shared state value
	 */
	public function set(string $pool, string $key, $value): void;

	/**
	 * Get shared state
	 *
	 * @param string $pool  shared state pool name
	 * @param string $key   shared state key
	 * @return mixed  shared state value
	 */
	public function get(string $pool, string $key);

	/**
	 * Has shared state
	 *
	 * @param string $pool  shared state pool name
	 * @param string $key   shared state key
	 * @return boolean  true it has, false it has not
	 */
	public function has(string $pool, string $key): bool;
}
