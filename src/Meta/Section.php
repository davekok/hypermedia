<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Doctrine\Common\Annotations\Annotation\{Annotation,Target,Attributes,Attribute};

/**
 * The section annotation.
 *
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("value", type = "string")
 * })
 */
final class Section extends Taggable
{
	/**
	 * @var string
	 */
	private $name;

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
	 * Set name
	 *
	 * @param string $name
	 */
	public function setName(string $name): void
	{
		$this->name = $name;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name;
	}

	/**
	 * Parse annotation text
	 *
	 * @param  string $text  the annotation text
	 */
	public function parse(string $text): void
	{
		$delimiters = " (*\t\r\n";
		$token = strtok($text, $delimiters);
		do {
			if ($token[0] === "#") {
				$token = ltrim($token, "#");
				$token = explode("=", $token);
				if (count($token) === 2) {
					$this->matchTagValue($token[0], $token[1]);
				} else {
					$this->needsTag($token[0]);
				}
			} else {
				$this->section = $token;
			}
			$token = strtok($delimiters);
		} while ($token !== false);
	}

	/**
	 * Return a string representation of this object.
	 */
	public function __toString(): string
	{
		return $this->name." ".parent::__toString();
	}
}
