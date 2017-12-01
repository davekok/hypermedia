<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Doctrine\Common\Annotations\Annotation\{Annotation,Target,Attributes,Attribute};
use Exception;
use Sturdy\Activity\Meta\FieldParserError as ParserError;

/**
 * Parser for the field annotation.
 */
final class FieldParser
{
	private $spaceToken;
	private $nameToken;
	private $stringToken;
	private $integerToken;
	private $floatToken;
	private $booleanToken;
	private $setToken;
	private $enumToken;
	private $dateToken;
	private $datetimeToken;
	private $timeToken;
	private $dayToken;
	private $monthToken;
	private $yearToken;
	private $weekToken;
	private $weekdayToken;
	private $uuidToken;
	private $passwordToken;
	private $colorToken;
	private $emailToken;
	private $urlToken;
	private $linkToken;
	private $listToken;
	private $objectToken;
	private $htmlToken;
	private $dataToken;
	private $metaToken;
	private $requiredToken;
	private $readonlyToken;
	private $disabledToken;
	private $multipleToken;
	private $stateToken;
	private $arrayToken;
	private $minToken;
	private $maxToken;
	private $stepToken;
	private $minlengthToken;
	private $maxlengthToken;
	private $patternToken;
	private $tagToken;
	private $equalsToken;
	private $valueToken;
	private $optionToken;
	private $autocompleteToken;
	private $autocompleteOption;
	private $labelToken;
	private $listStartToken;
	private $listDelimiterToken;
	private $listEndToken;
	private $listEscapeEndToken;
	private $quotedString;
	private $quote;
	private $escapedQuote;

	private $field;
	private $text;
	private $done;

	public function __construct()
	{
		$int = '0|[1-9][0-9]*';
		$nsname = '[A-Za-z_][A-Za-z0-9_]+(?:\\\\[A-Za-z_][A-Za-z0-9_]+)*';
		$name = '[A-Za-z_][A-Za-z0-9_]+';

		$this->spaceToken         = "/^[\s\*]+/"; // include * character as space character to allow annotation in docblocks
		$this->tagToken           = "/^#($name)/";
		$this->equalsToken        = "/^=/";
		$this->valueToken         = "/^(\S*)(?=\s|\*|$)/";
		$this->nameToken          = "/^($name):/";
		$this->stringToken        = "/^string/";
		$this->integerToken       = "/^int(?:eger)?/";
		$this->floatToken         = "/^float/";
		$this->booleanToken       = "/^bool(?:ean)?/";
		$this->setToken           = "/^set/";
		$this->enumToken          = "/^enum/";
		$this->dateToken          = "/^date/";
		$this->datetimeToken      = "/^datetime/";
		$this->timeToken          = "/^time/";
		$this->dayToken           = "/^day/";
		$this->monthToken         = "/^month/";
		$this->yearToken          = "/^year/";
		$this->weekToken          = "/^week/";
		$this->weekdayToken       = "/^weekday/";
		$this->uuidToken          = "/^uuid/";
		$this->passwordToken      = "/^password/";
		$this->colorToken         = "/^color/";
		$this->emailToken         = "/^email/";
		$this->urlToken           = "/^url/";
		$this->linkToken          = "/^link/";
		$this->listToken          = "/^list/";
		$this->objectToken        = "/^object/";
		$this->htmlToken          = "/^html/";
		$this->dataToken          = "/^data/";
		$this->metaToken          = "/^meta/";
		$this->requiredToken      = "/^required/";
		$this->readonlyToken      = "/^readonly/";
		$this->disabledToken      = "/^disabled/";
		$this->multipleToken      = "/^multiple/";
		$this->stateToken         = "/^state/";
		$this->arrayToken         = "/^\[\]/";

		$this->minToken           = "/^min=($int)/";
		$this->maxToken           = "/^max=($int)/";
		$this->stepToken          = "/^step=($int)/";

		$this->minlengthToken     = "/^minlength=($int)/";
		$this->maxlengthToken     = "/^maxlength=($int)/";
		$this->patternToken       = "/^pattern=((?:$nsname::)?$name)/";

		$this->optionToken        = "/^($name)/";
		$this->autocompleteToken  = "/^autocomplete/";
		$this->autocompleteOption = "/^[^\S\*\(\)]+/";

		$this->labelToken         = "/^label/";
		$this->quotedString       = "/^([^']+)/";
		$this->quote              = "/^'/";
		$this->escapedQuote       = "/^''/";

		$this->listStartToken     = "/^\(/";
		$this->listDelimiterToken = "/^,/";
		$this->listEndToken       = "/^\)/";
		$this->listEscapeEndToken = "/^\\)/";

		$this->defaultValueToken  = "/^default=(\"[^\v\"]*\"|\'[^\v\']*\'|[1-9][0-9]*(?=\.[0-9]+)?|0\.[0-9]+|true|false)/";
		$this->descriptionToken   = "/^(\"[^\v\"]*\"|\'[^\v\']*\')/";
	}

	private function valid(): bool
	{
		return strlen($this->text) > 0;
	}

	private function match(string $token, &$capture = null)
	{
		if (preg_match($this->$token, $this->text, $matches)) {
			$matched = array_shift($matches);
			$capture = array_shift($matches);
			$this->done.= $matched;
			$this->text = substr($this->text, strlen($matched));
			return true;
		} else {
			return false;
		}
	}

	private function clearbit(int &$mask, int $bit): void
	{
		$mask &= ~(1 << --$bit); // clear bit
	}

	private function setbit(int &$mask, int $bit): void
	{
		$bit = 1 << --$bit;
		$mask |= $bit; // set bit
	}

	private function isbitset(int $mask, int $bit): bool
	{
		$bit = 1 << --$bit;
		return (bool)($mask & $bit);
	}

	/**
	 * Parse the text and set the properties accordingly.
	 */
	public function parse(Field $field, string $text): Field
	{
		$this->text = $text;
		$this->done = "";
		$this->parseTokens($field);
		return $field;
	}

	private function parseName(Field $field): void
	{
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif ($this->match('nameToken', $name)) {
				if ($field->getName() === null) {
					$field->setName($name);
				} else {
					throw new ParserError($this->parseError("Name token not allowed as field is already named."));
				}
				break;
			} else {
				break;
			}
		}
	}

	private function parseTokens(Field $field, bool $subfield = false): void
	{
		$field->setFlags($flags = new FieldFlags);

		$mask = ~0;
		// bit 1: type
		// bit 2: meta, data or state field
		// bit 3: required/readonly/disabled
		// bit 4: multiple token
		// bit 5: min token
		// bit 6: max token
		// bit 7: step token
		// bit 8: min length token
		// bit 9: max length token
		// bit 10: pattern token
		// bit 11: autocomplete token
		// bit 12: default value token
		// bit 13: description token
		// bit 14: object type allowed
		// bit 15: label token
		$this->clearbit($mask, 4); // multiple
		$this->clearbit($mask, 5); // min token
		$this->clearbit($mask, 6); // max token
		$this->clearbit($mask, 7); // step token
		$this->clearbit($mask, 8); // min length token
		$this->clearbit($mask, 9); // max length token
		$this->clearbit($mask, 10); // pattern token
		$this->clearbit($mask, 11); // autocomplete token
		// $this->clearbit($mask, 14); // object type allowed
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif ($this->match('tagToken', $tag)) {
				$this->parseTag($field, $tag);
			} elseif ($this->isbitset($mask, 1) && $this->match('stringToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 8);
				$this->setbit($mask, 9);
				$this->setbit($mask, 10);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\StringType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('integerToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 5);
				$this->setbit($mask, 6);
				$this->setbit($mask, 7);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\IntegerType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('floatToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 5);
				$this->setbit($mask, 6);
				$this->setbit($mask, 7);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\FloatType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('booleanToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\BooleanType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('setToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\SetType());
				$this->parseArray($flags);
				$this->parseOptions($type);
			} elseif ($this->isbitset($mask, 1) && $this->match('enumToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\EnumType());
				$this->parseArray($flags);
				$this->parseOptions($type);
			} elseif ($this->isbitset($mask, 1) && $this->match('datetimeToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\DateTimeType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('dateToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\DateType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('timeToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\TimeType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('dayToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\DayType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('monthToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\MonthType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('yearToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\YearType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('weekdayToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\WeekDayType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('weekToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\WeekType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('uuidToken')) {
				$this->clearbit($mask, 1);
				$field->setType($type = new Type\UUIDType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('passwordToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 8);
				$this->setbit($mask, 9);
				$field->setType($type = new Type\PasswordType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('colorToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\ColorType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('emailToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 4);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\EmailType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('urlToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\URLType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('linkToken')) {
				$this->clearbit($mask, 1);
				$field->setType($type = new Type\LinkType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('listToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$field->setType($type = new Type\ListType());
				$this->parseArray($flags);
				$this->parseList($type);
			} elseif ($this->isbitset($mask, 1) /*&& $this->isbitset($mask, 14)*/ && $this->match('objectToken')) {
				$this->clearbit($mask, 1);
				// $this->clearbit($mask, 14);
				$field->setType($type = new Type\ObjectType());
				$this->parseArray($flags);
				$this->parseFields($type);
			} elseif ($this->isbitset($mask, 1) && $this->match('htmlToken')) {
				$this->clearbit($mask, 1);
				$field->setType($type = new Type\HTMLType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 2) && $this->match('dataToken')) {
				$this->clearbit($mask, 2);
				$this->setbit($mask, 14);
				$flags->setData();
			} elseif ($this->isbitset($mask, 2) && $this->match('metaToken')) {
				$this->clearbit($mask, 2);
				$flags->setMeta();
			} elseif ($this->isbitset($mask, 2) && $this->match('stateToken')) {
				$this->clearbit($mask, 2);
				$flags->setState();
			} elseif ($this->isbitset($mask, 3) && $this->match('requiredToken')) {
				$this->clearbit($mask, 3);
				$this->clearbit($mask, 12);
				$flags->setRequired();
			} elseif ($this->isbitset($mask, 3) && $this->match('readonlyToken')) {
				$this->clearbit($mask, 3);
				$this->clearbit($mask, 12);
				$flags->setReadonly();
			} elseif ($this->isbitset($mask, 3) && $this->match('disabledToken')) {
				$this->clearbit($mask, 3);
				$flags->setDisabled();
			} elseif ($this->isbitset($mask, 4) && $this->match('multipleToken')) {
				$this->clearbit($mask, 4);
				$flags->setMultiple();
			} elseif ($this->isbitset($mask, 5) && $this->match('minToken', $min)) {
				$this->clearbit($mask, 5);
				$type->setMinimumRange((int)$min);
			} elseif ($this->isbitset($mask, 6) && $this->match('maxToken', $max)) {
				$this->clearbit($mask, 6);
				$type->setMaximumRange((int)$max);
			} elseif ($this->isbitset($mask, 7) && $this->match('stepToken', $step)) {
				$this->clearbit($mask, 7);
				$type->setStep((int)$step);
			} elseif ($this->isbitset($mask, 8) && $this->match('minlengthToken', $min)) {
				$this->clearbit($mask, 8);
				$type->setMinimumLength((int)$min);
			} elseif ($this->isbitset($mask, 9) && $this->match('maxlengthToken', $max)) {
				$this->clearbit($mask, 9);
				$type->setMaximumLength((int)$max);
			} elseif ($this->isbitset($mask, 10) && $this->match('patternToken', $pattern)) {
				$this->clearbit($mask, 10);
				if (!defined($pattern)) {
					throw new ParserError($this->parseError("Pattern $pattern not defined."));
				}
				$type->setPatternName($pattern);
			} elseif ($this->isbitset($mask, 11) && $this->match('autocompleteToken')) {
				$this->clearbit($mask, 11);
				$this->parseAutocomplete($field);
			} elseif ($this->isbitset($mask, 15) && $this->match('labelToken')) {
				$this->clearbit($mask, 15);
				$this->parseLabel($field);
			} elseif ($subfield && $this->isbitset($mask, 12) && $this->match('defaultValueToken', $defaultValue)) {
				$this->clearbit($mask, 12);
				if ($defaultValue === "true") {
					$defaultValue = true;
				} elseif ($defaultValue === "false") {
					$defaultValue = false;
				} elseif ($defaultValue[0] === "\"" || $defaultValue[0] === "'") {
					$defaultValue = substr($defaultValue, 1, -1);
				} elseif (strpos($defaultValue, ".") !== false) {
					$defaultValue = (float)$defaultValue;
				} else {
					$defaultValue = (int)$defaultValue;
				}
				$field->setDefaultValue($defaultValue);
			} elseif ($subfield && $this->isbitset($mask, 13) && $this->match('descriptionToken', $description)) {
				$this->clearbit($mask, 13);
				$field->setDescription(substr($description, 1, -1));
			} elseif ($subfield) {
				break;
			} else {
				throw new ParserError($this->parseError("Unexpected token"));
			}
		}
		if ($this->isbitset($mask, 1)) {
			throw new ParserError($this->parseError("Expected a type token"));
		}
	}

	private function parseFields(Type\ObjectType $type): void
	{
		$sequence = 0;
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif (1 === $sequence && $this->match('nameToken', $name)) {
				$field = new Field;
				$field->setName($name);
				$type->addField($field);
				$this->parseTokens($field, true);
				$sequence = 2;
			} elseif (2 === $sequence && $this->match('listDelimiterToken')) {
				$sequence = 1;
			} elseif (0 !== $sequence && $this->match('listEndToken')) {
				break;
			} elseif (0 === $sequence && $this->match('listStartToken')) {
				$sequence = 1;
			} else {
				break;
			}
		}
		if (0 === $sequence) {
			throw new ParserError($this->parseError("Unexpected token"));
		}
	}

	private function parseArray(FieldFlags $flags): void
	{
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif ($this->match('arrayToken')) {
				$flags->setArray();
				return;
			} else {
				return;
			}
		}
	}

	private function parseTag(Taggable $taggable, string $tag): void
	{
		$sequence = 0;
		while ($this->valid()) {
			if ($sequence === 0 && $this->match('equalsToken')) {
				++$sequence;
			} elseif ($sequence === 1 && $this->match('valueToken', $value)) {
				++$sequence;
			} else {
				break;
			}
		}
		switch ($sequence) {
			case 0:
				$taggable->needsTag($tag);
				return;
			case 1:
				$taggable->matchTagValue($tag, "");
				return;
			case 2:
				$taggable->matchTagValue($tag, $value);
				return;
		}
	}

	private function parseOptions(Type\Type $type)
	{
		$sequence = 0;
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif (1 === $sequence && $this->match('optionToken', $option)) {
				$sequence = 2;
				$type->addOption($option);
			} elseif (2 === $sequence && $this->match('listDelimiterToken')) {
				$sequence = 1;
			} elseif (0 !== $sequence && $this->match('listEndToken')) {
				break;
			} elseif (0 === $sequence && $this->match('listStartToken')) {
				$sequence = 1;
			} else {
				break;
			}
		}
		if (0 === $sequence) {
			throw new ParserError($this->parseError("Expected options"));
		}
	}

	private function parseLink(Type\LinkType $link)
	{
		$sequence = 0;
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif (1 === $sequence && $this->match('optionToken', $option)) {
				$link->setLink($option);
			} elseif (0 !== $sequence && $this->match('listEndToken')) {
				break;
			} elseif (0 === $sequence && $this->match('listStartToken')) {
				$sequence = 1;
			} else {
				break;
			}
		}
		if (0 === $sequence) {
			throw new ParserError($this->parseError("Expected options"));
		}
	}

	private function parseList(Type\ListType $list)
	{
		$sequence = 0;
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif (1 === $sequence && $this->match('optionToken', $option)) {
				$list->setLink($option);
			} elseif (0 !== $sequence && $this->match('listEndToken')) {
				break;
			} elseif (0 === $sequence && $this->match('listStartToken')) {
				$sequence = 1;
			} else {
				break;
			}
		}
		if (0 === $sequence) {
			throw new ParserError($this->parseError("Expected options"));
		}
	}

	private function parseAutocomplete(Field $field)
	{
		$sequence = 0;
		$autocomplete = "";
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif (1 === $sequence && $this->match('autocompleteOption', $option)) {
				$autocomplete.= "$option ";
			} elseif (0 !== $sequence && $this->match('listEndToken')) {
				break;
			} elseif (0 === $sequence && $this->match('listStartToken')) {
				$sequence = 1;
			} else {
				break;
			}
		}
		if (0 === $sequence) {
			throw new ParserError($this->parseError("Expected options"));
		}
		$field->setAutocomplete(trim($autocomplete));
	}

	private function parseLabel(Field $field)
	{
		$field->setLabel($this->parseQuotedString());
	}

	private function parseQuotedString(): string
	{
		$sequence = 0;
		$string = "";
		while ($this->valid()) {
			switch ($sequence) {
				case 0:
					if ($this->match('spaceToken')) {
						// do nothing
					} elseif ($this->match('listStartToken')) {
						++$sequence;
					} else {
						throw new ParserError($this->parseError("expected space or list start"));
					}
					break;

				case 1:
					if ($this->match('spaceToken')) {
						// do nothing
					} elseif ($this->match('quote')) {
						++$sequence;
					} else {
						throw new ParserError($this->parseError("expected space or quote"));
					}
					break;

				case 2:
					if ($this->match('quotedString', $text)) {
						$string.= $text;
					} elseif ($this->match('escapedQuote')) {
						$string.= "'";
					} elseif ($this->match('quote')) {
						++$sequence;
					} else {
						throw new ParserError($this->parseError("expected text, escaped quoted or quote"));
					}
					break;

				case 3:
					if ($this->match('spaceToken')) {
						// do nothing
					} elseif ($this->match('listEndToken')) {
						break 2;
					} else {
						throw new ParserError($this->parseError("expected list end"));
					}
					break;
				}
		}
		return $string;
	}

	private function parseError(string $msg): string
	{
		return "$msg\n{$this->done}{$this->text}\n".str_repeat(" ",strlen($this->done))."^\n";
	}
}
