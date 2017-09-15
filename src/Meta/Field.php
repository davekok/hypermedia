<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Doctrine\Common\Annotations\Annotation\{Annotation,Target,Attributes,Attribute};
use Exception;


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
	private $name;         // the name of field
	private $description;  // the description of field
	private $type;         // the type of field
	private $default;      // the default value
	private $flags;        // bitmask of the above constants
	private $autocomplete; // autocomplete expression, see HTML 5 autofill documentation

	/**
	 * Constructor
	 *
	 * @param array $values  the values as injected by annotation reader
	 */
	public function __construct(array $values = null)
	{
		$this->flags = new FieldFlags();
		if (isset($values["value"])) {
			$this->parse($values["value"]);
		}
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
	public function getName(): string
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
	public function setType(string $type): self
	{
		$this->type = $type;
		return $this;
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	public function getType(): string
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
	public function getAutocomplete(): string
	{
		return $this->autocomplete;
	}

	/**
	 * Parse the annotation text
	 *
	 * @param  string $text  the annotation text
	 */
	public function parse(string $text): void
	{
		$delimiters = " (*\t\r\n";
		$token = strtok($text, $delimiters);
		do {
			switch ($token) {

				// basic types
				case "string":
					$this->type = new Type\StringType();
					break;

				case "integer":
					$this->type = new Type\IntegerType();
					break;

				case "float":
					$this->type = new Type\FloatType();
					break;

				case "boolean":
					$this->type = new Type\BooleanType();
					break;

				case "set":
					$this->type = new Type\SetType();
					break;

				case "enum":
					$this->type = new Type\EnumType();
					break;

				// calendar types
				case "date":
					$this->type = new Type\DateType();
					break;

				case "datetime":
					$this->type = new Type\DateTimeType();
					break;

				case "time":
					$this->type = new Type\TimeType();
					break;

				case "day":
					$this->type = new Type\DayType();
					break;

				case "month":
					$this->type = new Type\MonthType();
					break;

				case "year":
					$this->type = new Type\YearType();
					break;

				case "week":
					$this->type = new Type\WeekType();
					break;

				case "weekday":
					$this->type = new Type\WeekDayType();
					break;

				// special types
				case "uuid":
					$this->type = new Type\UUIDType();
					break;

				case "password":
					$this->type = new Type\PasswordType();
					break;

				case "color":
					$this->type = new Type\ColorType();
					break;

				case "email":
					$this->type = new Type\EmailType();
					break;

				case "url":
					$this->type = new Type\URLType();
					break;

				case "link": // a link pointing to a resource within this API
					$this->type = new Type\LinkType();
					break;

				case "list": // like enum but resolve link and use data section for options
					$this->type = new Type\ListType();
					break;

				case "table": // an table containing data, requires Column annotations
					$this->type = new Type\TableType();
					break;

				case "html": // string containing HTML
					$this->type = new Type\HTMLType();
					break;

				case "meta":
					$this->flags->setMeta();
					break;

				case "data":
					$this->flags->setData();
					break;

				case "required":
					$this->flags->setRequired();
					break;

				case "readonly":
					$this->flags->setReadonly();
					break;

				case "disabled":
					$this->flags->setDisabled();
					break;

				case "multiple":
					$this->flags->setMultiple();
					break;

				case "autocomplete":
					$this->setAutocomplete(strtok(")"));
					break;

				case "min":
					$this->type->setMinimumRange(trim(strok(")")));
					break;

				case "max":
					$this->type->setMaximumRange(trim(strok(")")));
					break;

				case "step":
					$this->type->setStep(trim(strok(")")));
					break;

				case "minlength":
					$this->type->setMinimumLength(trim(strok(")")));
					break;

				case "maxlength":
					$this->type->setMaximumLength(trim(strok(")")));
					break;

				case "pattern":
					$this->type->setPatternName(trim(strok(")")));
					break;

				case "options":
					$this->type->setOptions(explode(',',trim(strok(")"))));
					break;

				case "link":
					$this->type->setLink(trim(strok(")")));
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
					} elseif (substr($token, -2, 2) == "[]") {
						$this->flags->setArray();
						$token = substr($token, 0, -2);
						continue;
					} else {
						throw new Exception("Parse error at $token");
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
		if ($this->flags->isMeta()) $text.= "meta ";
		if ($this->flags->isData()) $text.= "data ";
		$text.= $this->type::type;
		if ($this->flags->isArray()) $text.= "[]";
		$text.= " ";
		if ($this->flags->isRequired()) $text.= "required ";
		if ($this->flags->isReadonly()) $text.= "readonly ";
		if ($this->flags->isDisabled()) $text.= "disabled ";
		if ($this->flags->isMultiple()) $text.= "multiple ";
		if ($this->autocomplete) $text.= "autocomplete({$this->autocomplete}) ";
		if (method_exists($this->type, "getMinimumRange") && $min = $this->type->getMinimumRange()) {
			$text.= " min($min)";
		}
		if (method_exists($this->type, "getMaximumRange") && $max = $this->type->getMaximumRange()) {
			$text.= " max($max)";
		}
		if (method_exists($this->type, "getStep") && $step = $this->type->getStep()) {
			$text.= " step($step)";
		}
		if (method_exists($this->type, "getMinimumLength") && $minlength = $this->type->getMinimumLength()) {
			$text.= " minlength($minlength)";
		}
		if (method_exists($this->type, "getMaximumLength") && $maxlength = $this->type->getMaximumLength()) {
			$text.= " maxlength($maxlength)";
		}
		if (method_exists($this->type, "getPatternName") && $patternName = $this->type->getPatternName()) {
			$text.= " pattern($patternName)";
		}
		if (method_exists($this->type, "getLink") && $link = $this->type->getLink()) {
			$text.= " link($link)";
		}
		$text.= parent::__toString();
		return rtrim($text);
	}
}
