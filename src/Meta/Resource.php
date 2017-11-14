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
	private $sections;
	private $object;
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
		$this->sections = [];
		$this->object = new Type\ObjectType;
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
	 * Add section
	 *
	 * @param Section $section
	 */
	public function addSection(Section $section): void
	{
		$this->sections[] = $section;
	}

	/**
	 * Get section
	 *
	 * @return iterable
	 */
	public function getSections(): iterable
	{
		return $this->sections;
	}

	/**
	 * Add field
	 *
	 * @param Field $field
	 * @return self
	 */
	public function addField(Field $field): self
	{
		$this->object->addField($field);
		return $this;
	}

	/**
	 * Get object type
	 *
	 * @return Type\ObjectType  the object type
	 */
	public function getObjectType(): Type\ObjectType
	{
		return $this->object;
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
		foreach ($this->sections??[] as $section) {
			yield $section;
		}
		yield from $this->object->getTaggables();
		foreach ($this->verbs??[] as $key => $variants) {
			foreach ($variants as $verb) {
				yield $verb;
			}
		}
	}
}
