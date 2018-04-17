<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Doctrine\Common\Annotations\Annotation\{Annotation,Target,Attributes,Attribute};

/**
 * The order annotation.
 *
 * @Annotation
 * @Target({"CLASS"})
 * @Attributes({
 *   @Attribute("value", type = "string")
 * })
 */
final class Order extends Taggable
{
	private $fields;

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
	 * @param array $fields
	 */
	public function setFields(array $fields): self
	{
		$this->fields = $fields;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getFields(): array
	{
		return $this->fields;
	}

	/**
	 * Parse annotation text
	 *
	 * @param  string $text  the annotation text
	 */
	public function parse(string $text): void
	{
		$delimiters = " *\t\r\n";
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
				$this->fields[] = $token;
			}
			$token = strtok($delimiters);
		} while ($token !== false);
	}
}
