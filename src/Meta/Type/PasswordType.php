<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass;

final class PasswordType extends Type
{
	const type = "password";
	private $minimumLength;
	private $maximumLength;

	/**
	 * Constructor
	 *
	 * @param array|null $state the objects state
	 */
	public function __construct(array $state = null)
	{
		if ($state !== null) {
			[$min, $max] = $state;
			if (strlen($min) > 0) $this->minimumLength = (int)$min;
			if (strlen($max) > 0) $this->maximumLength = (int)$max;
		}
	}

	/**
	 * Get descriptor
	 *
	 * @return string
	 */
	public function getDescriptor(): string
	{
		return self::type;
	}

	/**
	 * Set meta properties on object
	 *
	 * @param stdClass $meta
	 */
	public function meta(stdClass $meta): void
	{
		$meta->type = self::type;
	}

	/**
	 * Set minimumLength
	 *
	 * @param ?int $minimumLength
	 * @return self
	 */
	public function setMinimumLength(?int $minimumLength): self
	{
		$this->minimumLength = $minimumLength;
		return $this;
	}

	/**
	 * Get minimumLength
	 *
	 * @return ?int
	 */
	public function getMinimumLength(): ?int
	{
		return $this->minimumLength;
	}

	/**
	 * Set maximum length
	 *
	 * @param ?int $maximumLength
	 * @return self
	 */
	public function setMaximumLength(?int $maximumLength): self
	{
		$this->maximumLength = $maximumLength;
		return $this;
	}

	/**
	 * Get maximum length
	 *
	 * @return ?int
	 */
	public function getMaximumLength(): ?int
	{
		return $this->maximumLength;
	}

	/**
	 * Filter value
	 *
	 * @param  &$value string the value to filter
	 * @return bool whether the value is valid
	 */
	public function filter(&$value): bool
	{
/*
Passwords must not contain the user's entire user name, given name or family name value. Both checks are not case sensitive:

    The given name and family name is checked in its entirety only to determine whether it is part of the password. If the user name, given name or family name is less than three characters long, this check is skipped.
    The user name is parsed for delimiters: commas, periods, dashes or hyphens, underscores, spaces, pound signs, and tabs. If any of these delimiters are found, the user name is split and all parsed sections (tokens) are confirmed not to be included in the password. Tokens that are less than three characters in length are ignored, and substrings of the tokens are not checked. For example, the name "Erin M. Hagens" is split into three tokens: "Erin," "M," and "Hagens." Because the second token is only one character long, it is ignored. Therefore, this user could not have a password that included either "erin" or "hagens" as a substring anywhere in the password.

Passwords must contain characters from three of the following five categories:

    Uppercase characters of European languages (A through Z, with diacritic marks, Greek and Cyrillic characters)
    Lowercase characters of European languages (a through z, sharp-s, with diacritic marks, Greek and Cyrillic characters)
    Base 10 digits (0 through 9)
    Nonalphanumeric characters: ~!@#$%^&*_-+=`|\(){}[]:;"'<>,.?/
    Any Unicode character that is categorized as an alphabetic character but is not uppercase or lowercase. This includes Unicode characters from Asian languages.

Passwords of a length lower then 16 must have at least an upper and lower case character, a base 10 digit.
Passwords of a length lower then 8 must have at least an upper and lower case character, a base 10 digit and nonalphanumeric character.
*/
		
		$l = strlen($value);
		if (isset($this->minimumLength) && $l < $this->minimumLength) {
			return false;
		} elseif (isset($this->maximumLength) && $l > $this->maximumLength) {
			return false;
		} elseif ($l > 128) { // hard maximum
			return false;
		} else {
			$lower = "\p{Ll}"; // lower case alphabet characters
			$upper = "\p{Lu}"; // upper case alphabet characters
			$digit = "0-9";
			$alpha = "\p{Lo}"; // other characters
			$special = addcslashes("~!@#$%^&*_-+=`|\\(){}[]:;\"'<>,.?/ ", "[]-\\/");
			if ($l > 16) { // for long password entropy is good enough to not require any type of characters
				return 1 === preg_match("/^[$lower$upper$alpha$digit$special]*$/u", $value);
			} elseif ($l > 8) { // require at least a lower, upper alphabet character and one digit character
				return 1 === preg_match("/^((?=.*[$alpha])|(?=.*[$lower])(?=.*[$upper]))(?=.*[$digit])[$lower$upper$alpha$digit$special]*$/u", $value);
			} else { // require also a special character
				return 1 === preg_match("/^((?=.*[$alpha])|(?=.*[$lower])(?=.*[$upper]))(?=.*[$digit])(?=.*[$special])[$lower$upper$alpha$digit$special]*$/u", $value);
			}
		}
	}
}
