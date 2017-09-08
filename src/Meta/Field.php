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
	private $validation;   // validation expression
	private $link;         // in case of type=list, link is the resource to choice from

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
	 * Set link
	 *
	 * @param string $link
	 * @return self
	 */
	public function setLink(string $link): self
	{
		$this->link = $link;
		return $this;
	}

	/**
	 * Get link
	 *
	 * @return string
	 */
	public function getLink(): string
	{
		return $this->link;
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
				case "integer":
				case "long":
				case "float":
				case "double":
				case "boolean": // can be mapped to checkbox or radio 'yes', 'no'
				case "set":     // can be mapped to multiple checkboxes or select multiple
				case "enum":    // can be mapped to radio buttons or select

				// calendar types
				case "date":
				case "datetime":
				case "time":
				case "day":
				case "month":
				case "year":
				case "week":
				case "weekday":

				// special types
				case "uuid":
				case "password":
				case "color":
				case "email":
				case "url":
				case "link": // a link pointing to a resource within this API
				case "list": // like enum but resolve link and use data section for options
				case "table": // an table containing data, requires Column annotations
				case "html": // string containing HTML

					$this->type = $token;
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
					$this->validation["min"] = trim(strok(")"));
					break;

				case "max":
					$this->validation["max"] = trim(strok(")"));
					break;

				case "step":
					$this->validation["step"] = trim(strok(")"));
					break;

				case "minlength":
					$this->validation["minlength"] = trim(strok(")"));
					break;

				case "maxlength":
					$this->validation["maxlength"] = trim(strok(")"));
					break;

				case "pattern":
					$this->validation["pattern"] = trim(strok(")"));
					break;

				case "link":
					$this->setLink(trim(strok(")")));
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
		$text.= $this->type;
		if ($this->flags->isArray()) $text.= "[]";
		$text.= " ";
		if ($this->flags->isRequired()) $text.= "required ";
		if ($this->flags->isReadonly()) $text.= "readonly ";
		if ($this->flags->isDisabled()) $text.= "disabled ";
		if ($this->flags->isMultiple()) $text.= "multiple ";
		if ($this->autocomplete) $text.= "autocomplete({$this->autocomplete}) ";
		if ($this->link) $text.= "link({$this->link}) ";
		foreach ($this->validation??[] as $key => $value) {
			$text.= "$key($value) ";
		}
		$text.= parent::__toString();
		return rtrim($text);
	}
}
