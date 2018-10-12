<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;
use Sturdy\Activity\Meta\Field;
use Sturdy\Activity\Meta\FieldFlags;
use Sturdy\Activity\Translator;

/**
 * Object type
 */
final class ObjectType extends Type
{
	const type = "object";

	private $fieldDescriptors;
	private $fields; // only used for during compilation
	private $component = "";

	/**
	 * Constructor
	 *
	 * @param string|null $state  the objects state
	 */
	public function __construct(string $state = null)
	{
		if ($state !== null) {
			$p = strpos($state, ":");
			if ($p !== false) {
				$this->component = substr($state, 0, $p);
				$this->fieldDescriptors = unserialize(substr($state, $p+1));
			}
		}
	}

	/**
	 * Set meta properties on object
	 *
	 * @param stdClass $meta
	 * @param array $state
	 */
	public function meta(stdClass $meta, array $state): void
	{
		$meta->type = self::type;
		if (isset($this->component)) {
			$meta->component = $this->component;
		}
	}

	/**
	 * Set component
	 *
	 * @param string $component
	 */
	public function setComponent(string $component): void
	{
		$this->component = $component;
	}

	/**
	 * Get component
	 *
	 * @return string
	 */
	public function getComponent(): string
	{
		return $this->component;
	}

	/**
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		return self::type.":{$this->component}:".serialize($this->fieldDescriptors);
	}

	/**
	 * Set field descriptors
	 *
	 * @param array $fieldDescriptors  field descriptors
	 */
	public function setFieldDescriptors(array $fieldDescriptors): self
	{
		$this->fieldDescriptors = $fieldDescriptors;
		return $this;
	}

	/**
	 * Get field descriptors
	 */
	public function getFieldDescriptors(): array
	{
		return $this->fieldDescriptors??[];
	}

	/**
	 * Add a field
	 *
	 * @param Field $field  the field to add
	 */
	public function addField(Field $field): self
	{
		$this->fields[$field->getName()][] = $field;
		return $this;
	}

	/**
	 * Get fields
	 *
	 * @return iterable  the fields
	 */
	public function getFields(): iterable
	{
		return $this->fields??[];
	}

	/**
	 * Get fields
	 *
	 * @return iterable  the fields
	 */
	public function getTaggables(): iterable
	{
		foreach ($this->fields??[] as $name => $fields) {
			foreach ($fields as $field) {
				yield $field;
				$type = $field->getType();
				if ($type instanceof self) {
					yield from $type->getTaggables();
				}
			}
		}
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
