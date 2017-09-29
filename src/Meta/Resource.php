<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Exception;
use Doctrine\Common\Annotations\Annotation\{
	Annotation,
	Target,
	Attributes,
	Attribute
};

/**
 * Resource meta class
 */
class Resource
{
	private $class;
	private $description;
	private $fields;
	private $verbs;

	/**
	 * Constructor
	 *
	 * @param string $class
	 */
	public function __construct(string $class, string $description)
	{
		$this->class = $class;
		$this->description = $description;
		$this->fields = [];
		$this->verbs = [];
	}

	/**
	 * Get class
	 *
	 * @return string
	 */
	public function getClass(): string
	{
		return $this->class;
	}

	/**
	 * Set description
	 *
	 * @param string $description
	 * @return self
	 */
	public function setDescription(string $description): self
	{
		$this->description = $description;
		return $this;
	}

	/**
	 * Get description
	 *
	 * @return string
	 */
	public function getDescription(): string
	{
		return $this->description;
	}

	/**
	 * Set root
	 *
	 * @param bool $root
	 * @return self
	 */
	public function setRoot(bool $root): self
	{
		$this->root = $root;
		return $this;
	}

	/**
	 * Get root
	 *
	 * @return bool
	 */
	public function getRoot(): bool
	{
		return $this->root;
	}

	/**
	 * Add field
	 *
	 * @param Field $field
	 * @return self
	 */
	public function addField(Field $field): self
	{
		$this->fields[$field->getName()][] = $field;
		return $this;
	}

	/**
	 * Get fields
	 *
	 * @return array fields
	 */
	public function getFields(): array
	{
		return $this->fields;
	}

	/**
	 * Add verb
	 *
	 * @param Verb $verb
	 * @return self
	 */
	public function addVerb(Verb $verb): self
	{
		$this->verbs[$verb->getName()][] = $verb;
		return $this;
	}

	/**
	 * Get verbs
	 *
	 * @return array gets
	 */
	public function getVerbs(): array
	{
		return $this->verbs;
	}

	/**
	 * Get taggables
	 *
	 * @return iterable
	 */
	public function getTaggables(): iterable
	{
		foreach ($this->fields??[] as $key => $fields) {
			foreach ($fields as $field) {
				yield $field;
			}
		}
		foreach ($this->verbs??[] as $key => $verbs) {
			foreach ($verbs as $verb) {
				yield $verb;
			}
		}
	}
}
