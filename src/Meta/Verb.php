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
	private $location;
	private $status = self::OK;
	private $self = true;
	private $root = false;

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
	 * Get name
	 *
	 * @return string
	 */
	abstract public function getName(): string;

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
	 * Set the class method
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
	 * Get the class method
	 *
	 * @return string
	 */
	public function getMethod(): string
	{
		return $this->method;
	}

	/**
	 * Set the status to return when the request is done.
	 *
	 * @param int $status
	 * @return self
	 */
	public function setStatus(int $status): self
	{
		assert(in_array($status, [self::OK, self::CREATED, self::ACCEPTED, self::NO_CONTENT]), new InvalidArgumentException("Status should be on of the class constants."));
		$this->status = $status;
		return $this;
	}

	/**
	 * Get the status to return when the request is done.
	 *
	 * @return int
	 */
	public function getStatus(): int
	{
		return $this->status;
	}

	/**
	 * Set whether to include self in output
	 *
	 * @param bool $self
	 * @return self
	 */
	public function setSelf(bool $self): self
	{
		$this->self = $self;
		return $this;
	}

	/**
	 * Get whether to include self in output
	 *
	 * @return bool
	 */
	public function getSelf(): bool
	{
		return $this->self;
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
	public function getLocation(): string
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
					$this->status = self::CREATED;
					$this->location = strtok(")");
					break;

				case "accepts":
					$this->status = self::ACCEPTED;
					break;

				case "no-content":
					$this->status = self::NO_CONTENT;
					break;

				case "no-self":
					$this->self = false;
					break;

				case "root":
					$this->root = true;
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
		switch ($this->status) {
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
		if (!$this->self) {
			$text.= "no-self ";
		}
		if ($this->root) {
			$text.= "root ";
		}
		$text.= parent::__toString();
		return rtrim($text);
	}
}
