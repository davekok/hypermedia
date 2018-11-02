<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Doctrine\Common\Annotations\Annotation\{Annotation,Target,Attributes,Attribute};
use stdClass;

/**
 * The action annotation.
 *
 * Actions can reside in any class. An activity may span actions of one or more classes.
 * Only class methods can be actions. A method may have one or more action annotations.
 * However if multiple action annotations are used the must use different tags.
 *
 * The action annotation makes use of a simple syntax as documented by ActionParser class.
 *
 * @Annotation
 * @Target({"METHOD"})
 * @Attributes({
 *   @Attribute("value", type = "string"),
 * })
 */
final class Action extends Taggable
{
	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string
	 */
	private $description;

	/**
	 * @var bool
	 */
	private $start = false;

	/**
	 * @var bool
	 */
	private $join = false;

	/**
	 * @var bool
	 */
	private $detach = false;

	/**
	 * @var false|string|array
	 */
	private $next;

	/**
	 * @var string
	 */
	private $text = "";

	/**
	 * Constructor
	 *
	 * @param string|array|null $text  the text to parse or the values as injected by annotation reader
	 */
	public function __construct($text = null)
	{
		if (is_string($text)) {
			$this->text = $text;
		} elseif (isset($text["value"])) {
			$this->text = $text["value"];
		}
	}

	public static function createFromText(string $text): self
	{
		$inst = new self;
		$inst->setText($text);
		$inst->parse();
		return $inst;
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
	 * @param ?string $description
	 * @return self
	 */
	public function setDescription(?string $description): self
	{
		$this->description = $description;
		return $this;
	}

	/**
	 * Get description
	 *
	 * @return ?string
	 */
	public function getDescription(): ?string
	{
		return $this->description;
	}

	/**
	 * Set start
	 *
	 * @param bool $start
	 * @return self
	 */
	public function setStart(bool $start): self
	{
		$this->start = $start;
		return $this;
	}

	/**
	 * Get start
	 *
	 * @return bool
	 */
	public function getStart(): bool
	{
		return $this->start;
	}

	/**
	 * Set detach
	 *
	 * @param bool $detach
	 * @return self
	 */
	public function setDetach(bool $detach): self
	{
		$this->detach = $detach;
		return $this;
	}

	/**
	 * Get detach
	 *
	 * @return bool
	 */
	public function getDetach(): bool
	{
		return $this->detach;
	}

	/**
	 * Set join
	 *
	 * @param bool $join
	 * @return self
	 */
	public function setJoin(bool $join): self
	{
		$this->join = $join;
		return $this;
	}

	/**
	 * Is join
	 *
	 * @return bool
	 */
	public function isJoin(): bool
	{
		return $this->join;
	}

	/**
	 * Set which action comes next.
	 *
	 * @param $next  either false, a string, a array or a object.
	 */
	public function setNext($next): self
	{
		$this->next = $next;
		return $this;
	}

	/**
	 * Get which action comes next.
	 *
	 * @return either false, a string, a array or a object.
	 */
	public function getNext()
	{
		return $this->next;
	}

	/**
	 * Get whether return values are used.
	 *
	 * @return bool
	 */
	public function hasReturnValues(): bool
	{
		return is_object($this->next);
	}

	/**
	 * Set text
	 *
	 * @param string $text
	 * @return self
	 */
	public function setText(string $text): self
	{
		$this->text = $text;
		return $this;
	}

	/**
	 * Get text
	 *
	 * @return string
	 */
	public function getText(): string
	{
		return $this->text;
	}

	/**
	 * Parse action text
	 */
	public function parse(): void
	{
		(new ActionParser)->parse($this);
	}

	/**
	 * Convert to string.
	 *
	 * @return string textual representation of action
	 */
	public function __toString(): string
	{
		$text = "";
		if ($this->name) {
			$text.= ActionParser::NAME_START.$this->name.ActionParser::NAME_END." ";
		}
		if ($this->start) {
			$text.= ActionParser::START." ";
		}
		if ($this->join) {
			$text.= ActionParser::JOIN." ";
		}
		if ($this->hasReturnValues()) {
			foreach ($this->next as $returnValue => $next) {
				if ($returnValue === "true") {
					$text.= ActionParser::NEXT_IF_TRUE." ";
				} elseif ($returnValue === "false") {
					$text.= ActionParser::NEXT_IF_FALSE." ";
				} else {
					$text.= $returnValue.ActionParser::NEXT." ";
				}
				if ($next === false) {
					$text.= ActionParser::END." ";
				} elseif (is_array($next)) {
					reset($this->next);
					if (is_string(key($this->next))) {
						$i = 0;
						foreach ($this->next as $branch => $method) {
							if ($i++) $text.= " ".ActionParser::SPLIT." ";
							$text.= $branch.ActionParser::BRANCH_SEPARATOR.$method;
						}
					} else {
						$text.= implode(" ".ActionParser::SPLIT." ", $this->next);
					}
					$text.= " ";
				} elseif (is_string($next)) {
					$text.= $next." ";
				}
			}
		} else {
			if ($this->next === false) {
				$text.= ActionParser::END." ";
			} elseif (is_array($this->next)) {
				$text.= ActionParser::NEXT." ";
				reset($this->next);
				if (is_string(key($this->next))) {
					$i = 0;
					foreach ($this->next as $branch => $method) {
						if ($i++) $text.= " ".ActionParser::SPLIT." ";
						$text.= $branch.ActionParser::BRANCH_SEPARATOR.$method;
					}
				} else {
					$text.= implode(" ".ActionParser::SPLIT." ", $this->next);
				}
				$text.= " ";
			} elseif (is_string($this->next)) {
				$text.= ActionParser::NEXT." ".$this->next." ";
			}
		}
		$text.= parent::__toString();
		return rtrim($text);
	}
}
