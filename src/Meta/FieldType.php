<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

/**
 * Field type
 */
final class FieldType
{
	private $type;
	private $valid;

	/**
	 * Constructor
	 *
	 * @param string $type
	 */
	public function __construct(string $type)
	{
		$this->type = $type;
	}

	public function filterValue(&$value, array $validation): bool
	{
		switch ($type) {
			default:
			case "string":
				$value = filter_var($value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW);
				if (isset($validation["pattern"])) {
					if (!preg_match("/".$validation["pattern"]."/", $value)) {
						return false;
					}
				}
				return true;

			case "integer":
				$options = array(
					'options' => array(
						'default' => 3, // value to return if the filter fails
						// other options here
						'min_range' => 0
					),
					'flags' => 0,
				);

				$value = filter_var($value, FILTER_VALIDATE_INT, );
				if (isset($validation["pattern"])) {
					if (!preg_match("/".$validation["pattern"]."/", $value)) {
						return false;
					}
				}
				return true;

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
			case "link":  // a link pointing to a resource within this API
			case "list":  // like enum but resolve link and use data section for options
			case "table": // an table containing data, requires Column annotations
			case "html":  // string containing HTML
				$this->type = $type;
				break;
		}
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return $this->type;
	}
}
