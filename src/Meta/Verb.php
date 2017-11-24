<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Doctrine\Common\Annotations\Annotation\{Annotation,Target,Attributes,Attribute};
use Exception;
use InvalidArgumentException;


/**
 * Base class for HTTP verbs
 */
abstract class Verb extends Taggable
{
	const OK = 200;
	const CREATED = 201;
	const ACCEPTED = 202;
	const NO_CONTENT = 204;

	private $method;
	private $description;
	private $flags;
	private $location;

	/**
	 * Constructor
	 *
	 * @param array $values  the values as injected by annotation reader
	 */
	public function __construct(array $values = null)
	{
		$this->flags = new VerbFlags();
		if (isset($values["value"])) {
			$this->parse($values["value"]);
		}
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	abstract public function getName(): string;

	/**
	 * Set method
	 *
	 * @param string $method
	 * @return self
	 */
	public function setMethod(string $method): self
	{
		$this->method = $method;
		return $this;
	}

	/**
	 * Get method
	 *
	 * @return string
	 */
	public function getMethod(): string
	{
		return $this->method;
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
	 * Set flags
	 *
	 * @param VerbFlags $flags
	 * @return self
	 */
	public function setFlags(VerbFlags $flags): self
	{
		$this->flags = $flags;
		return $this;
	}

	/**
	 * Get flags
	 *
	 * @return VerbFlags
	 */
	public function getFlags(): VerbFlags
	{
		return $this->flags;
	}

	/**
	 * Set location
	 *
	 * @param string $location
	 * @return self
	 */
	public function setLocation(string $location): self
	{
		$this->location = $location;
		return $this;
	}

	/**
	 * Get location
	 *
	 * @return string
	 */
	public function getLocation(): ?string
	{
		return $this->location;
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
			switch ($token) {
				case "creates":
					$this->flags->setStatus(self::CREATED);
					$this->location = strtok(")");
					break;

				case "accepts":
					$this->flags->setStatus(self::ACCEPTED);
					break;

				case "no-content":
					$this->flags->setStatus(self::NO_CONTENT);
					break;

				case "hidden":
					$this->flags->setHidden();
					break;

				case "links":
					$this->flags->useLinks();
					break;

				case "fields":
					$this->flags->useFields();
					break;

				case "root":
					$this->flags->setRoot(true);
					break;

				default:
					if ($token[0] === "#") {
						$token = ltrim($token, "#");
						$token = explode("=", $token);
						if (count($token) === 2) {
							$this->matchTagValue($token[0], $token[1]);
						} else {
							$this->needsTag($token[0]);
						}
					}
					break;
			}
			$token = strtok($delimiters);
		} while ($token !== false);
	}

	/**
	 * To string
	 *
	 * @return string  text representation of object
	 */
	public function __toString(): string
	{
		$text = "";
		switch ($this->flags->getStatus()) {
			case self::OK:
				break;

			case self::CREATED:
				$text.= "creates({$this->location}) ";
				break;

			case self::ACCEPTED:
				$text.= "accepts ";
				break;

			case self::NO_CONTENT:
				$text.= "no-content ";
				break;
		}
		if (!$this->flags->hasData()) {
			if ($this->flags->hasFields()) {
				$text.= "fields ";
			} elseif ($this->flags->hasLinks()) {
				$text.= "links ";
			} elseif ($this->flags->isHidden()) {
				$text.= "hidden ";
			}
		}
		if ($this->flags->getRoot()) {
			$text.= "root ";
		}
		$text.= parent::__toString();
		return rtrim($text);
	}
}
