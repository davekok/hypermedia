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
	 * Constructor
	 *
	 * @param array $values  the values as injected by annotation reader
	 */
	public function __construct(array $values = null)
	{
		if (isset($values["value"])) {
			$this->parse($values["value"]);
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
	 * Return a string representation of this object.
	 */
	public function __toString(): string
	{
		$r = "";
		if ($this->label) {
			$r.= "label('".str_replace("'", "''", $this->label)."') ";
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
		$r.= parent::__toString();
		return rtrim($r);
	}
}
