<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Doctrine\Common\Annotations\Annotation\{Annotation,Target,Attributes,Attribute};
use Exception;
use Sturdy\Activity\Expression;
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
	private $inputToken;
	private $noInputToken;
	private $componentToken;
	private $tupleToken;
	private $htmlToken;
	private $dataToken;
	private $metaToken;
	private $hiddenToken;
	private $requiredToken;
	private $readonlyToken;
	private $disabledToken;
	private $multipleToken;
	private $mapToken;
	private $placeHolderToken;
	private $stateToken;
	private $sharedToken;
	private $privateToken;
	private $reconToken;
	private $lookupToken;
	private $autoSubmitToken;
	private $arrayToken;
	private $minToken;
	private $maxToken;
	private $stepToken;
	private $minDateToken;
	private $maxDateToken;
	private $minlengthToken;
	private $maxlengthToken;
	private $noValidateToken;
	private $patternToken;
	private $tagToken;
	private $equalsToken;
	private $valueToken;
	private $optionToken;
	private $autocompleteToken;
	private $autocompleteOption;
	private $iconToken;
	private $labelToken;
	private $slotToken;
	private $bindToken;
	private $listStartToken;
	private $listDelimiterToken;
	private $listEndToken;
	private $listEscapeEndToken;
	private $newline;
	private $quotedString;
	private $quote;
	private $escapedQuote;

	private $exprToken;
	private $exprVar;
	private $exprOps;
	private $exprProps;
	private $exprValue;

	private $field;
	private $text;
	private $done;

	public function __construct()
	{
		$int = '0|[1-9][0-9]*';
		$nsname = '[A-Za-z_][A-Za-z0-9_]+(?:\\\\[A-Za-z_][A-Za-z0-9_]+)*';
		$name = '[A-Za-z_][A-Za-z0-9_]+';
		$variable = '\$[A-Za-z_][A-Za-z0-9_]+';
		$date = '[0-9]{4}-[0-9]{2}-[0-9]{2}';

		$this->spaceToken         = "/^[\s\*]+/"; // include * character as space character to allow annotation in docblocks
		$this->tagToken           = "/^#($name)/";
		$this->equalsToken        = "/^=/";
		$this->valueToken         = "/^([A-Za-z0-9@$%^&*._~{}\[\]<>():+-]*)(?=,|\s|\*|$|)/";
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
		$this->noValidateToken    = "/^novalidate/";
		$this->colorToken         = "/^color/";
		$this->emailToken         = "/^email/";
		$this->urlToken           = "/^url/";
		$this->linkToken          = "/^link/";
		$this->listToken          = "/^list/";
		$this->objectToken        = "/^object(:$name)?/";
		$this->inputToken         = "/^input/";
		$this->noInputToken       = "/^no-input/";
		$this->referenceToken     = "/^reference/";
		$this->startTupleToken    = "/^\[/";
		$this->endTupleToken      = "/^\]/";
		$this->htmlToken          = "/^html/";
		$this->dataToken          = "/^data/";
		$this->metaToken          = "/^meta/";
		$this->hiddenToken        = "/^hidden/";
		$this->requiredToken      = "/^required/";
		$this->readonlyToken      = "/^readonly/";
		$this->mapToken           = "/^map/";
		$this->placeHolderToken   = "/^placeholder\(('[^\v']*')\)/";
		$this->slotToken          = "/^slot\(($name)\)/";
		$this->bindToken          = "/^bind\(($name)\)/";
		$this->disabledToken      = "/^disabled/";
		$this->multipleToken      = "/^multiple/";
		$this->stateToken         = "/^state/";
		$this->sharedToken        = "/^shared/";
		$this->privateToken       = "/^private/";
		$this->reconToken         = "/^recon/";
		$this->lookupToken        = "/^lookup/";
		$this->autoSubmitToken    = "/^autosubmit/";
		$this->arrayToken         = "/^\[\]/";

		$this->minToken           = "/^min=($int|$variable)/";
		$this->maxToken           = "/^max=($int|$variable)/";
		$this->stepToken          = "/^step=($int|$variable)/";
		$this->minDateToken       = "/^min=($date|$variable)/";
		$this->maxDateToken       = "/^max=($date|$variable)/";

		$this->minlengthToken     = "/^minlength=($int)/";
		$this->maxlengthToken     = "/^maxlength=($int)/";
		$this->patternToken       = "/^pattern=((?:$nsname::)?$name)/";

		$this->optionToken        = "/^($name)/";
		$this->autocompleteToken  = "/^autocomplete/";
		$this->autocompleteOption = '/^([^ \t\n\v\f\r\*\(\)]+)/';

		$this->labelToken         = '/^label/';
		$this->newline            = '/^\v+(?:\h*\*\h*)?/';
		$this->quotedString       = '/^([^\'\v]+)/';
		$this->quote              = '/^\'/';
		$this->escapedQuote       = '/^\'\'/';
		$this->iconToken          = '/^icon/';
		$this->name               = "/^($name)/";

		$this->keyToken   	      = "/^($name)/";
		$this->valToken  		  = '/^(\"[^\v\"]*\"|\'[^\v\']*\'|[1-9][0-9]*(?=\.[0-9]+)?|0\.[0-9]+|true|false)/';
		$this->colonToken 		  = '/^:/';

		$this->listStartToken     = '/^\(/';
		$this->listDelimiterToken = '/^,/';
		$this->listEndToken       = '/^\)/';
		$this->listEscapeEndToken = '/^\\)/';

		$this->defaultValueToken  = '/^default=(\"[^\v\"]*\"|\'[^\v\']*\'|[1-9][0-9]*(?=\.[0-9]+)?|0\.[0-9]+|true|false)/';
		$this->descriptionToken   = '/^(\"[^\v\"]*\"|\'[^\v\']*\')/';

		$this->exprToken          = '/^expr/';
		$this->exprVar            = "/^($name)/";
		$this->exprValue          = '/^(true|false|[1-9][0-9]*(?:\.[0-9]+)?|0(?:\.[0-9]+)?|\'[^\v\']*\')/';
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
		// bit 2: meta, data, state, private, hidden, lookup or autosubmit field
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
		// bit 14: recon token
		// bit 15: label token
		// bit 16: icon token
		// bit 17: minDate token
		// bit 18: maxDate token
		// bit 19: shared token
		// bit 20: expression token
		// bit 21: placeholder token
		// bit 22: input token
		// bit 23: noInput token
		// bit 24: noValidate token
		// bit 25: slot token
		// bit 26: bind token
		$this->clearbit($mask, 4); // multiple
		$this->clearbit($mask, 5); // min token
		$this->clearbit($mask, 6); // max token
		$this->clearbit($mask, 7); // step token
		$this->clearbit($mask, 8); // min length token
		$this->clearbit($mask, 9); // max length token
		$this->clearbit($mask, 10); // pattern token
		$this->clearbit($mask, 11); // autocomplete token
		$this->clearbit($mask, 17); // minDate token
		$this->clearbit($mask, 18); // maxDate token
		$this->clearbit($mask, 19); // shared token requires state or private token
		$this->clearbit($mask, 21); // placeholder
		$this->clearbit($mask, 24); // noValidate token
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
				$this->setbit($mask, 21);
				$field->setType($type = new Type\SetType());
				$this->parseArray($flags);
				$this->parseOptions($type);
			} elseif ($this->isbitset($mask, 1) && $this->match('enumToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$this->setbit($mask, 21);
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
				$this->setbit($mask, 17);
				$this->setbit($mask, 18);
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
				$this->setbit($mask, 24);
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
			}  elseif ($this->isbitset($mask, 1) && $this->match('iconToken')) {
				$this->clearbit($mask, 1);
				$field->setType($type = new Type\IconType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 1) && $this->match('listToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 11);
				$this->setbit($mask, 21);
				$field->setType($type = new Type\ListType());
				$this->parseArray($flags);
				$this->parseList($type);
			} elseif ($this->isbitset($mask, 1) && $this->match('mapToken')) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 12);
				$this->setbit($mask, 21);
				$field->setType($type = new Type\MapType());
				$this->parseArray($flags);
				$this->parseMapOptions($type);
			} elseif ($this->isbitset($mask, 1) && $this->match('objectToken', $component)) {
				$this->clearbit($mask, 1);
				$this->setbit($mask, 20);
				$type = new Type\ObjectType();
				$type->setComponent(ltrim($component ?? "", ":"));
				$field->setType($type);
				$this->parseArray($flags);
				$this->parseFields($type);
			} elseif ($this->isbitset($mask, 25) && $this->match('slotToken', $name)) {
				$this->clearbit($mask, 25);
				$field->setSlot($name);
			} elseif ($this->isbitset($mask, 26) && $this->match('bindToken', $name)) {
				$this->clearbit($mask, 26);
				$field->setBind($name);
			} elseif ($this->isbitset($mask, 1) && $this->match('referenceToken')) {
				$this->clearbit($mask, 1);
				$field->setType($type = new Type\ReferenceType());
			} elseif ($this->isbitset($mask, 1) && $this->match('startTupleToken')) {
				$this->clearbit($mask, 1);
				$field->setType($type = new Type\TupleType());
				$this->parseTuple($type);
			} elseif ($this->isbitset($mask, 1) && $this->match('htmlToken')) {
				$this->clearbit($mask, 1);
				$field->setType($type = new Type\HTMLType());
				$this->parseArray($flags);
			} elseif ($this->isbitset($mask, 22) && $this->match('inputToken')) {
				$this->clearbit($mask, 22);
				$this->clearbit($mask, 23);
				$flags->setInput();
			} elseif ($this->isbitset($mask, 23) && $this->match('noInputToken')) {
				$this->clearbit($mask, 23);
				$this->clearbit($mask, 22);
				$flags->setNoInput();
			} elseif ($this->isbitset($mask, 2) && $this->isbitset($mask, 14) && $this->match('dataToken')) {
				$this->clearbit($mask, 2);
				$this->clearbit($mask, 14);
				$flags->setData();
			} elseif ($this->isbitset($mask, 2) && $this->isbitset($mask, 14) && $this->match('metaToken')) {
				$this->clearbit($mask, 2);
				$this->clearbit($mask, 14);
				$flags->setMeta();
			} elseif ($this->isbitset($mask, 2) && $this->isbitset($mask, 14) && $this->match('hiddenToken')) {
				$this->clearbit($mask, 2);
				$flags->setHidden();
			} elseif ($this->isbitset($mask, 2) && $this->isbitset($mask, 14) && $this->match('lookupToken')) {
				$this->clearbit($mask, 2);
				$this->clearbit($mask, 14);
				$flags->setLookup();
			} elseif ($this->isbitset($mask, 2) && $this->isbitset($mask, 14) && $this->match('autoSubmitToken')) {
				$this->clearbit($mask, 2);
				$this->clearbit($mask, 14);
				$flags->setAutoSubmit();
			} elseif ($this->isbitset($mask, 2) && $this->match('stateToken')) {
				$this->clearbit($mask, 2);
				$this->setbit($mask, 19);
				$flags->setState();
			} elseif ($this->isbitset($mask, 2) && $this->match('privateToken')) {
				$this->clearbit($mask, 2);
				$this->setbit($mask, 19);
				$flags->setPrivate();
			} elseif ($this->isbitset($mask, 14) && $this->match('reconToken')) {
				$this->clearbit($mask, 14);
				$flags->setRecon();
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
			} elseif ($this->isbitset($mask, 19) && $this->match('sharedToken')) {
				$this->clearbit($mask, 19);
				$flags->setShared();
				$field->setSharedStatePoolName($this->parseNameToken());
			} elseif ($this->isbitset($mask, 21) && $this->match('placeHolderToken', $label)) {
				$this->clearbit($mask, 21);
				$field->setPlaceHolder($label);
			} elseif ($this->isbitset($mask, 5) && $this->match('minToken', $min)) {
				$this->clearbit($mask, 5);
				if ($min[0] === '$') {
					$type->setMinimumRange($min);
				} else {
					$type->setMinimumRange((int)$min);
				}
			} elseif ($this->isbitset($mask, 6) && $this->match('maxToken', $max)) {
				$this->clearbit($mask, 6);
				if ($max[0] === '$') {
					$type->setMaximumRange($max);
				} else {
					$type->setMaximumRange((int)$max);
				}
			} elseif ($this->isbitset($mask, 7) && $this->match('stepToken', $step)) {
				$this->clearbit($mask, 7);
				if ($step[0] === '$') {
					$type->setStep($step);
				} else {
					$type->setStep((int)$step);
				}
			} elseif ($this->isbitset($mask, 17) && $this->match('minDateToken', $min)) {
				$this->clearbit($mask, 17);
				$type->setMinimumRange($min);
			} elseif ($this->isbitset($mask, 18) && $this->match('maxDateToken', $max)) {
				$this->clearbit($mask, 18);
				$type->setMaximumRange($max);
			} elseif ($this->isbitset($mask, 8) && $this->match('minlengthToken', $min)) {
				$this->clearbit($mask, 8);
				$type->setMinimumLength((int)$min);
			} elseif ($this->isbitset($mask, 9) && $this->match('maxlengthToken', $max)) {
				$this->clearbit($mask, 9);
				$type->setMaximumLength((int)$max);
			} elseif ($this->isbitset($mask, 20) && $this->match('noValidateToken')) {
				$this->clearbit($mask, 20);
				$type->setNoValidate(true);
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
			} elseif ($this->isbitset($mask, 16) && $this->match('iconToken')) {
				$this->clearbit($mask, 16);
				$field->setIcon($this->parseNameToken());
			} elseif ($this->isbitset($mask, 20) && $this->match('exprToken')) {
				$this->clearbit($mask, 20);
				$this->parseExpression($field);
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
				throw new ParserError($this->parseError("{$field->getResource()->getClass()}::\${$field->getName()}: Unexpected token"));
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
			throw new ParserError($this->parseError("Unexpected token while processing subfields"));
		}
	}

	private function parseTuple(Type\TupleType $type): void
	{
		$i = 0;
		$sequence = 0;
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif (0 === $sequence) {
				$field = new Field;
				$field->setName((string)$i++);
				$type->addField($field);
				$this->parseTokens($field, true);
				$sequence = 1;
			} elseif (1 === $sequence && $this->match('listDelimiterToken')) {
				$sequence = 0;
			} elseif ($this->match('endTupleToken')) {
				break;
			} else {
				throw new ParserError($this->parseError("Unexpected token while processing tuple"));
			}
		}
	}

	private function parseArray(FieldFlags $flags): void
	{
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif ($this->match('arrayToken')) {
				if ($flags->isArray()) {
					$flags->clearArray();
					$flags->setMatrix();
					return;
				} else {
					$flags->setArray();
				}
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

	private function parseMapOptions(Type\MapType $type)
	{
		$sequence = 0;
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif (1 === $sequence && $this->match('keyToken', $key)) {
				$sequence = 2;
			} elseif (2 === $sequence && $this->match('colonToken')) {
				$sequence = 3;
			} elseif (3 === $sequence && $this->match('valToken', $val)) {
				$sequence = 4;
				$type->addOption($key, trim($val, "'"));
			} elseif (4 === $sequence && $this->match('listDelimiterToken')) {
				$sequence = 1;

			} elseif ((1 === $sequence || 4 === $sequence) && $this->match('listEndToken')) {
				$sequence = 5;
				break;
			} elseif (0 === $sequence && $this->match('listStartToken')) {
				$sequence = 1;
			} else {
				break;
			}
		}

		if (0 === $sequence) {
			throw new ParserError($this->parseError("Expected options for MapType"));
		} else if (5 !== $sequence) {
			throw new ParserError($this->parseError("Not completed maptoken"));
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

	private function parseNameToken(): string
	{
		$sequence = 0;
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif (1 === $sequence && $this->match('name', $name)) {
				// do nothing
			} elseif (0 !== $sequence && $this->match('listEndToken')) {
				break;
			} elseif (0 === $sequence && $this->match('listStartToken')) {
				$sequence = 1;
			} else {
				break;
			}
		}
		if (0 === $sequence) {
			throw new ParserError($this->parseError("Expected name"));
		}
		if ($name === null) {
			throw new ParserError($this->parseError("Expected name"));
		}
		return $name;
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
					if ($this->match('newline')) {
						$string.= "\n";
					} elseif ($this->match('quotedString', $text)) {
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

	private function parseExpression(Field $field): void
	{
		// The actual codes don't matter as long as they are one character and are not special regex characters.
		$t = [
			"end"        => "0", // 0
			"list"       => "1", // 1
			"listitem"   => "2", // 2
			"expr"       => "3", // 3
			"variable"   => "4", // 4
			"value"      => "5", // 5
			"prop"       => "6", // 6
			"==="        => "7", // 7
			"!=="        => "8", // 8
			"=="         => "9", // 9
			"!="         => "A", // 10
			"++"         => "B", // 11
			"--"         => "C", // 12
			"&&"         => "D", // 13
			"||"         => "E", // 14
			"*"          => "F", // 15
			"/"          => "G", // 16
			"+"          => "H", // 17
			"-"          => "I", // 18
			"!"          => "J", // 19
			"("          => "K", // 20
			")"          => "L", // 21
			":"          => "M", // 22
			","          => "N", // 23
			"active"     => "O", // 24
			"required"   => "P", // 25
			"readonly"   => "Q", // 26
			"disabled"   => "R", // 27
		];
		// $m = array_flip($t);
		$keys = array_keys($t);
		$ops = array_slice($keys, 7, 24-7);
		$props = array_slice($keys, 24, 28-24);

		$this->exprOps   = "/^(".implode("|", array_map(function(string $op){return preg_quote($op, "/");}, $ops)).")/";
		$this->exprProps = "/^(".implode("|", array_map(function(string $prop){return preg_quote($prop, "/");}, $props)).")/";

		// the rules, if one matches reduce the matched tokens to a $expr token
		$rules = [
			"{$t['++']}{$t['variable']}" => $t['expr'],
			"{$t['--']}{$t['variable']}" => $t['expr'],
			"{$t['variable']}{$t['++']}" => $t['expr'],
			"{$t['variable']}{$t['--']}" => $t['expr'],
			"{$t['variable']}" => $t['expr'],
			"{$t['value']}" => $t['expr'],
			"{$t['-']}{$t['expr']}" => $t['expr'],
			"{$t['!']}{$t['expr']}" => $t['expr'],
			"{$t['expr']}{$t['===']}{$t['expr']}" => $t['expr'],
			"{$t['expr']}{$t['!==']}{$t['expr']}" => $t['expr'],
			"{$t['expr']}{$t['==']}{$t['expr']}" => $t['expr'],
			"{$t['expr']}{$t['!=']}{$t['expr']}" => $t['expr'],
			"{$t['expr']}{$t['*']}{$t['expr']}" => $t['expr'],
			"{$t['expr']}{$t['/']}{$t['expr']}" => $t['expr'],
			"{$t['expr']}{$t['+']}{$t['expr']}" => $t['expr'],
			"{$t['expr']}{$t['-']}{$t['expr']}" => $t['expr'],
			"{$t['expr']}{$t['&&']}{$t['expr']}" => $t['expr'],
			"{$t['expr']}{$t['||']}{$t['expr']}" => $t['expr'],
			"{$t['(']}{$t['expr']}{$t[')']}" => $t['expr'],
			"{$t['prop']}{$t[':']}{$t['expr']}{$t[',']}" => $t['list'],
			"{$t['list']}{$t['list']}" => $t['list'],
			"{$t['(']}{$t['prop']}{$t[':']}{$t['expr']}{$t[')']}" => $t['end'],
			"{$t['(']}{$t['list']}{$t['prop']}{$t[':']}{$t['expr']}{$t[')']}" => $t['end'],
		];

		// create a regex pattern from the rules
		$ruleregex = "/(?:" . implode("|", array_keys($rules)) . ")$/";

		$abs = [];
		$stack = [];
		$buffer = "";
		$variables = [];
		$listCount = 0;
		$previous = "";

		$i = 0;
		while ($this->valid()) {
			// match a token and shift the token on the buffer
			if ($this->match('spaceToken')) {
				continue;
			} else if ($this->match('exprOps', $text)) {
				$stack[] = $text;
				$buffer.= $t[$text];
				if ($text === "(") {
					++$listCount;
				} else if ($text === ")") {
					--$listCount;
				}
			} else if ($this->match('exprValue', $text)) {
				$stack[] = $text;
				$buffer.= $t['value'];
			} else if ($this->match('exprProps', $text)) {
				$stack[] = $text;
				$buffer.= $t['prop'];
			} else if ($this->match('exprVar', $name)) {
				$variables[] = $name;
				$stack[] = "$".$name;
				$buffer.= $t['variable'];
			} else {
				throw new ParserError($this->parseError("lexer error while parsing expression"));
			}

			// keep going until there is no more to match
			while (1 === preg_match($ruleregex, $buffer, $matches)) {
				// $str = "";
				// for ($i = 0; $i < strlen($buffer); ++$i) $str.= $m[$buffer[$i]] . " ";
				// $str = trim($str);
				// echo $str, str_repeat(" ", 40 - strlen($str));
				// $str = "";
				// for ($i = 0; $i < strlen($matches[0]); ++$i) $str.= $m[$matches[0][$i]] . " ";
				// $str.= "=>";
				// for ($i = 0; $i < strlen($rules[$matches[0]]); ++$i) $str.= " " . $m[$rules[$matches[0]][$i]];
				// echo $str, str_repeat(" ", 40 - strlen($str));
				// reduce
				$buffer = substr($buffer, 0, strlen($buffer) - strlen($matches[0])) . $rules[$matches[0]];
				// $str = "";
				// for ($i = 0; $i < strlen($buffer); ++$i) $str.= $m[$buffer[$i]] . " ";
				// echo trim($str), "\n";

				switch ($matches[0]) {
					case "{$t['variable']}":
					case "{$t['value']}":
						// nothing
						break;
					case "{$t['++']}{$t['variable']}":
					case "{$t['--']}{$t['variable']}":
					case "{$t['variable']}{$t['++']}":
					case "{$t['variable']}{$t['--']}":
					case "{$t['-']}{$t['expr']}":
					case "{$t['!']}{$t['expr']}":
						$lastArg = array_pop($stack);
						$forLastArg = array_pop($stack);
						$stack[] = $forLastArg.$lastArg;
						break;
					case "{$t['expr']}{$t['===']}{$t['expr']}":
					case "{$t['expr']}{$t['!==']}{$t['expr']}":
					case "{$t['expr']}{$t['==']}{$t['expr']}":
					case "{$t['expr']}{$t['!=']}{$t['expr']}":
					case "{$t['expr']}{$t['*']}{$t['expr']}":
					case "{$t['expr']}{$t['/']}{$t['expr']}":
					case "{$t['expr']}{$t['+']}{$t['expr']}":
					case "{$t['expr']}{$t['-']}{$t['expr']}":
					case "{$t['expr']}{$t['&&']}{$t['expr']}":
					case "{$t['expr']}{$t['||']}{$t['expr']}":
					case "{$t['(']}{$t['expr']}{$t[')']}":
						$lastArg = array_pop($stack);
						$forLastArg = array_pop($stack);
						$forForLastArg = array_pop($stack);
						$stack[] = $forForLastArg.$forLastArg.$lastArg;
						break;
					case "{$t['prop']}{$t[':']}{$t['expr']}{$t[',']}":
						$comma = array_pop($stack);
						$expr = array_pop($stack);
						$colon = array_pop($stack);
						$prop = array_pop($stack);
						$stack[] = [$prop => $expr];
						break;
					case "{$t['list']}{$t['list']}":
						$list2 = array_pop($stack);
						$list1 = array_pop($stack);
						$stack[] = array_merge($list1, $list2);
						break;
					case "{$t['(']}{$t['prop']}{$t[':']}{$t['expr']}{$t[')']}":
						$close = array_pop($stack);
						$expr = array_pop($stack);
						$colon = array_pop($stack);
						$prop = array_pop($stack);
						$open = array_pop($stack);
						$stack[] = [$prop => $expr];
						break;
					case "{$t['(']}{$t['list']}{$t['prop']}{$t[':']}{$t['expr']}{$t[')']}":
						$close = array_pop($stack);
						$expr = array_pop($stack);
						$colon = array_pop($stack);
						$prop = array_pop($stack);
						$list = array_pop($stack);
						$open = array_pop($stack);
						$stack[] = array_merge($list, [$prop => $expr]);
						break;
				}
			}

			if ($listCount === 0 && $buffer === $t['end'] && count($stack) === 1) {
				$field->setExpr(new Expression($stack[0], $variables));
				return;
			}

			if ($buffer === $previous) {
				throw new ParserError($this->parseError("no shift or reduce happened: $buffer"));
			}

			$previous = $buffer;

			if (++$i > 1000) {
				throw new ParserError($this->parseError("infinite loop protection"));
			}
		}
		throw new ParserError($this->parseError("unexpected end of expression"));
	}
}
