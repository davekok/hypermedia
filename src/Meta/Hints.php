<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Doctrine\Common\Annotations\Annotation\{Annotation,Target,Attributes,Attribute};

/**
 * The hints annotation.
 *
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("value", type = "string")
 * })
 */
final class Hints extends Taggable
{
	/**
	 * @var string
	 */
	private $label;

	/**
	 * @var string
	 */
	private $icon;

	/**
	 * @var string
	 */
	private $section;

	/**
	 * @var string
	 */
	private $component;

	/**
	 * @var string
	 */
	private $layout;

	/**
	 * @var string
	 */
	private $variant;

	/**
	 * @var array | boolean
	 */
	private $clear;

	/**
	 * Constructor
	 *
	 * @param string|array|null $text  the text to parse or the values as injected by annotation reader
	 */
	public function __construct($text = null)
	{
		if (is_string($text)) {
			$this->parse($text);
		} elseif (isset($text["value"])) {
			$this->parse($text["value"]);
		}
	}

	/**
	 * Parse text
	 *
	 * @param  string $text  the text
	 */
	public function parse(string $text): void
	{
		(new HintsParser)->parse($this, $text);
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
	 * @return ?string
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
	 * @return ?string
	 */
	public function getIcon(): ?string
	{
		return $this->icon;
	}

	/**
	 * Set section
	 *
	 * @param ?string $section
	 */
	public function setSection(?string $section): void
	{
		$this->section = $section;
	}

	/**
	 * Get section
	 *
	 * @return ?string
	 */
	public function getSection(): ?string
	{
		return $this->section;
	}

	/**
	 * Set component
	 *
	 * @param ?string $component
	 */
	public function setComponent(?string $component): void
	{
		$this->component = $component;
	}

	/**
	 * Get component
	 *
	 * @return ?string
	 */
	public function getComponent(): ?string
	{
		return $this->component;
	}

	/**
	 * Set layout
	 *
	 * @param ?string $layout
	 */
	public function setLayout(?string $layout): void
	{
		$this->layout = $layout;
	}

	/**
	 * Get layout
	 *
	 * @return ?string
	 */
	public function getLayout(): ?string
	{
		return $this->layout;
	}

	/**
	 * Set variant
	 *
	 * @param ?string $variant
	 */
	public function setVariant(?string $variant): void
	{
		$this->variant = $variant;
	}

	/**
	 * Get variant
	 *
	 * @return ?string
	 */
	public function getVariant(): ?string
	{
		return $this->variant;
	}

	/**
	 * Set clear
	 *
	 * @param array|boolean $clear
	 */
	public function setClear($clear): void
	{
		$this->clear = $clear;
	}

	/**
	 * Get clear
	 *
	 * @return array|boolean
	 */
	public function getClear()
	{
		return $this->clear;
	}

	/**
	 * Return a string representation of this object.
	 */
	public function __toString(): string
	{
		$r = "";
		if ($this->label) {
			$r.= "label('".str_replace("'", "''", $this->label)."') ";
		}
		if ($this->icon) {
			$r.= "icon({$this->icon}) ";
		}
		if ($this->section) {
			$r.= "section({$this->section}) ";
		}
		if ($this->component) {
			$r.= "component({$this->component}) ";
		}
		if ($this->layout) {
			$r.= "layout({$this->layout}) ";
		}
		if ($this->variant) {
			$r.= "variant({$this->variant}) ";
		}
		if ($this->clear === true) {
			$r.= "clear ";
		} else if (is_array($this->clear)) {
			$r.= "clear(".implode(",",$this->clear).") ";
		}
		$r.= parent::__toString();
		return rtrim($r);
	}
}
