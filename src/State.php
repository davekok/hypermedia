<?php declare(strict_types=1);

namespace Sturdy\Activity;

use Exception;

/**
 * Base class for creating state classes.
 *
 * Extend this class to provide lazy loaders for entities.
 */
class State
{
	/**
	 * @var array
	 */
	protected $readonly;

	/**
	 * @var array
	 */
	protected $readwrite;

	/**
	 * Create instance from json string.
	 */
	public static function createInstance(?string $json): self
	{
		[$class, $readonly, $readwrite] = json_decode($json, true);
		$self = new $class;
		$self->readonly = $readonly;
		$self->readwrite = $readwrite;
		return $self;
	}

	/**
	 * Constructor
	 *
	 * @param array $options  the options
	 */
	public function __construct(array $readonly = [], array $readwrite = [])
	{
		$this->readonly = $readonly;
		$this->readwrite = $readwrite;
	}

	/**
	 * Require that certain properties exists.
	 *
	 * @param $properties  the properties that should exist
	 */
	public function require(string ...$properties)
	{
		foreach ($properties as $property) {
			if (!$this->has($property)) {
				throw new Exception(ucfirst($property)." has not been found.");
			}
		}
	}

	/**
	 * Get a property
	 */
	public function get(string $name)
	{
		if (isset($this->readonly[$name])) {
			return $this->readonly[$name];
		} elseif (isset($this->readwrite[$name])) {
			return $this->readwrite[$name];
		} elseif (method_exists($this, $func = "get".ucfirst($name))) {
			return $this->$func();
		} else {
			return null;
		}
	}

	/**
	 * Set a property
	 */
	public function set(string $name, $value): self
	{
		if (isset($this->readonly[$name])) {
			throw new Exception("$name is readonly property");
		} elseif (method_exists($this, $func = "set".ucfirst($name))) {
			$this->$func($value);
		} elseif (is_scalar($value)) {
			$this->readwrite[$name] = $value;
		} else {
			throw new Exception("Value could not be stored for property $name because their is no handler and it is not scalar.");
		}
		return $this;
	}

	/**
	 * Check if a property exists.
	 */
	public function has(string $name): bool
	{
		if (isset($this->readonly[$name]) || isset($this->readwrite[$name])) {
			return true;
		} elseif (method_exists($this, $func = "has".ucfirst($name))) {
			return null !== $this->$func();
		} elseif (method_exists($this, $func = "get".ucfirst($name))) {
			return null !== $this->$func();
		} else {
			return false;
		}
	}

	/**
	 * Clear a property
	 */
	public function clear(string $name): self
	{
		if (isset($this->readonly[$name])) {
			throw new Exception("$name is readonly property");
		} elseif (isset($this->readwrite[$name])) {
			unset($this->readwrite[$name]);
		} elseif (method_exists($this, $func = "clear".ucfirst($name))) {
			$this->$func();
		} elseif (method_exists($this, $func = "set".ucfirst($name))) {
			$this->$func(null);
		}
		return $this;
	}

	/**
	 * Get state of object for storage.
	 */
	public function getJSON(): string
	{
		return json_encode([static::class, $this->readonly, $this->readwrite], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
	}

	// magic functions
	public function __isset(string $name)
	{
		return $this->has($name);
	}

	public function __get(string $name)
	{
		return $this->get($name);
	}

	public function __set(string $name, $value)
	{
		$this->set($name, $value);
	}

	public function __unset(string $name)
	{
		$this->clear($name);
	}
}
