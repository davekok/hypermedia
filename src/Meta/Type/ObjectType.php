<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;
use Sturdy\Activity\Meta\Field;

/**
 * String type
 */
final class ObjectType extends Type
{
	const type = "object";

	private $fields;

	/**
	 * Constructor
	 *
	 * @param array|null $state  the objects state
	 */
	public function __construct(array $state = null)
	{
		if ($state !== null) {
			[$fields] = $state;
			$this->fields = unserialize($fields);
		}
	}

	/**
	 * Set meta properties on object
	 *
	 * @param stdClass $meta
	 */
	public function meta(stdClass $meta): void
	{
		$meta->type = self::type;
		$meta->fields = [];
		foreach ($this->fields as $field) {
			$submeta = new stdClass;
			$field->meta($submeta);
			$meta->fields[$field->getName()] = $submeta;
		}
	}

	/**
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		return self::type.",".serialize($this->fields);
	}

	/**
	 * Add a field
	 *
	 * @param Field $field  the field to add
	 */
	public function addField(Field $field): self
	{
		$this->fields[$field->getName()] = $field;
		return $this;
	}

	/**
	 * Get fields
	 *
	 * @return iterable  the fields
	 */
	public function getFields(): iterable
	{
		return $this->fields;
	}

	/**
	 * Filter value
	 *
	 * @param  &$value  the value to filter
	 * @return bool  whether the value is valid
	 */
	public function filter(&$value): bool
	{
		return is_object($value);
	}
}
