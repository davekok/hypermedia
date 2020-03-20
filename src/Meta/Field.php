<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Doctrine\Common\Annotations\Annotation\{Annotation,Target,Attributes,Attribute};
use Exception;
use Sturdy\Activity\Expression;

/**
 * The field annotation.
 *
 * Fields are only allowed in resources.
 *
 * @Annotation
 * @Target({"PROPERTY"})
 * @Attributes({
 *   @Attribute("value", type = "string"),
 * })
 */
final class Field extends Taggable
{
	private $resouce;      // the resource
	private $text;         // the resource
	private $name;         // the name of field
	private $description;  // the description of field
	private $type;         // the type of field
	private $defaultValue; // the default value
	private $flags;        // bitmask of the above constants
	private $autocomplete; // autocomplete expression, see HTML 5 autofill documentation
	private $label;        // label
	private $icon;         // icon
	private $pool;         // shared state pool name
	private $expr;         // expression
	private $slot;         // slot
	private $bind;         // bind

	/**
	 * Constructor
	 *
	 * @param string|array|null $text  the text to parse or the values as injected by annotation reader
	 */
	public function __construct($text = null)
	{
		$this->flags = new FieldFlags();
		if (is_string($text)) {
			$this->text = $text;
		} elseif (isset($text["value"])) {
			$this->text = $text["value"];
		}
	}

	/**
	 * Parse text
	 *
	 * @param  string $text  the text
	 */
	public function parse(string $text = null): void
	{
		(new FieldParser)->parse($this, $text??$this->text);
	}

	/**
	 * Set resource
	 *
	 * @param Resource $resource
	 * @return self
	 */
	public function setResource(Resource $resource): self
	{
		$this->resource = $resource;
		return $this;
	}

	/**
	 * Get resource
	 *
	 * @return Resource
	 */
	public function getResource(): ?Resource
	{
		return $this->resource;
	}

	/**
	 * Set name
	 *
	 * @param string $name
	 * @return self
	 */
	public function setName(string $name): self
	{
		$this->name = $name;
		return $this;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName(): ?string
	{
		return $this->name;
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
	 * Set type
	 *
	 * @param string $type
	 * @return self
	 */
	public function setType(Type\Type $type): self
	{
		$this->type = $type;
		return $this;
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	public function getType(): Type\Type
	{
		return $this->type;
	}

	/**
	 * Set default value
	 *
	 * @param $defaultValue
	 * @return self
	 */
	public function setDefaultValue($defaultValue): self
	{
		$this->defaultValue = $defaultValue;
		return $this;
	}

	/**
	 * Get default value
	 *
	 * @return mixed
	 */
	public function getDefaultValue()
	{
		return $this->defaultValue;
	}

	/**
	 * Set flags
	 *
	 * @param FieldFlags $flags
	 * @return self
	 */
	public function setFlags(FieldFlags $flags): self
	{
		$this->flags = $flags;
		return $this;
	}

	/**
	 * Get flags
	 *
	 * @return FieldFlags
	 */
	public function getFlags(): FieldFlags
	{
		return $this->flags;
	}

	/**
	 * Set autocomplete
	 *
	 * @param string $autocomplete
	 * @return self
	 */
	public function setAutocomplete(string $autocomplete): self
	{
		$this->autocomplete = $autocomplete;
		return $this;
	}

	/**
	 * Get autocomplete
	 *
	 * @return string
	 */
	public function getAutocomplete(): ?string
	{
		return $this->autocomplete;
	}

	/**
	 * Set label
	 *
	 * @param ?string $label
	 */
	public function setLabel(?string $label): void
	{
		$this->label = $label;
	}

	/**
	 * Get label
	 *
	 * @return string
	 */
	public function getLabel(): ?string
	{
		return $this->label;
	}

	/**
	 * Set icon
	 *
	 * @param ?string $icon
	 */
	public function setIcon(?string $icon): void
	{
		$this->icon = $icon;
	}

	/**
	 * Get icon
	 *
	 * @return string
	 */
	public function getIcon(): ?string
	{
		return $this->icon;
	}

	/**
	 * Set shared state pool name
	 *
	 * @param string $sharedStatePoolName
	 */
	public function setSharedStatePoolName(string $sharedStatePoolName): void
	{
		$this->pool = $sharedStatePoolName;
	}

	/**
	 * Get shared state pool name
	 *
	 * @return string
	 */
	public function getSharedStatePoolName(): ?string
	{
		return $this->pool;
	}

	/**
	 * Set expr
	 *
	 * @param ?Expression $expr
	 */
	public function setExpr(?Expression $expr): void
	{
		$this->expr = $expr;
	}

	/**
	 * Get expr
	 *
	 * @return ?Expression
	 */
	public function getExpr(): ?Expression
	{
		return $this->expr;
	}

	/**
	 * Set slot
	 *
	 * @param ?string slot
	 */
	public function setSlot(?string $slot): void
	{
		$this->slot = $slot;
	}

	/**
	 * Get slot
	 *
	 * @return ?string
	 */
	public function getSlot(): ?string
	{
		return $this->slot;
	}

	/**
	 * Set bind
	 *
	 * @param ?string bind
	 */
	public function setBind(?string $bind): void
	{
		$this->bind = $bind;
	}

	/**
	 * Get bind
	 *
	 * @return ?string
	 */
	public function getBind(): ?string
	{
		return $this->bind;
	}
	/**
	 * To string
	 *
	 * @return string  text representation of object
	 */
	public function __toString(): string
	{
		$text = "";
		if ($this->flags->isMeta()) $text.= "meta ";
		if ($this->flags->isData()) $text.= "data ";
		if ($this->flags->isState()) $text.= "state ";
		if ($this->flags->isRecon()) $text.= "recon ";
		if ($this->flags->isRequired()) $text.= "required ";
		if ($this->flags->isReadonly()) $text.= "readonly ";
		if ($this->flags->isDisabled()) $text.= "disabled ";
		if ($this->flags->isMultiple()) $text.= "multiple ";
		$text.= $this->type::type;
		if ($this->flags->isArray()) $text.= "[]";
		$text.= " ";
		if ($this->autocomplete) $text.= "autocomplete({$this->autocomplete}) ";
		if ($this->label) $text.= "label('" . str_replace("'", "''", $this->label) . "') ";
		if ($this->icon) $text.= "icon({$this->icon}) ";
		if ($this->expr) $text.= "expr({$this->expr}) ";
		if ($this->slot) $text.= "slot({$this->slot}) ";
		if ($this->bind) $text.= "bind({$this->bind}) ";
		if (method_exists($this->type, "getMinimumRange") && $min = $this->type->getMinimumRange()) {
			$text.= "min($min) ";
		}
		if (method_exists($this->type, "getMaximumRange") && $max = $this->type->getMaximumRange()) {
			$text.= "max($max) ";
		}
		if (method_exists($this->type, "getStep") && $step = $this->type->getStep()) {
			$text.= "step($step) ";
		}
		if (method_exists($this->type, "getMinimumLength") && $minlength = $this->type->getMinimumLength()) {
			$text.= "minlength($minlength) ";
		}
		if (method_exists($this->type, "getMaximumLength") && $maxlength = $this->type->getMaximumLength()) {
			$text.= "maxlength($maxlength) ";
		}
		if (method_exists($this->type, "getPatternName") && $patternName = $this->type->getPatternName()) {
			$text.= "pattern($patternName) ";
		}
		if (method_exists($this->type, "getLink") && $link = $this->type->getLink()) {
			$text.= "link($link) ";
		}
		$text.= parent::__toString();
		return rtrim($text);
	}
}
