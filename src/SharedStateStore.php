<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * Interface implementing a shared state store
 */
interface SharedStateStore
{
	/**
	 * Create a new persistent store, swapping out the old one if any.
	 *
	 * @return string  the id of the new store
	 */
	public function createPersistentStore(): string;

	/**
	 * Get the current persistent store id.
	 *
	 * @return ?string  persistent store id
	 */
	public function getPersistentStoreId(): ?string;

	/**
	 * Load a persistent store by id, swapping out the old one if any.
	 *
	 * @return string  id
	 */
	public function loadPersistentStore(string $id): void;

	/**
	 * Close the persistent store.
	 */
	public function closePersistentStore(): void;

	/**
	 * Fill a pool overriding existing
	 *
	 * @param string $pool        shared state pool name
	 * @param string $properties  shared state key
	 */
	public function fill(string $pool, array $properties): void;

	/**
	 * Set shared state
	 *
	 * @param string $pool     shared state pool name
	 * @param string $key      shared state key
	 * @param mixed  $value    shared state value
	 * @param bool   $persist  persist the value in the current store or retain until a new one is created
	 */
	public function set(string $pool, string $key, $value, bool $persist = false): void;

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
