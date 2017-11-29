<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta;

use Doctrine\Common\Annotations\Annotation\{Annotation,Target,Attributes,Attribute};
use Exception;
use Sturdy\Activity\Meta\FieldParserError as ParserError;

/**
 * Parser for the hints annotation.
 */
final class HintsParser
{
	private $spaceToken;
	private $nameToken;
	private $tagToken;
	private $equalsToken;
	private $valueToken;
	private $labelToken;
	private $sectionToken;
	private $layoutToken;
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
		$name = '[A-Za-z_][A-Za-z0-9_]+';

		$this->spaceToken         = "/^[\s\*]+/"; // include * character as space character to allow annotation in docblocks
		$this->nameToken          = "/^($name)/";
		$this->tagToken           = "/^#($name)/";
		$this->equalsToken        = "/^=/";
		$this->valueToken         = "/^(\S*)(?=\s|\*|$)/";

		$this->labelToken         = "/^label/";
		$this->sectionToken       = "/^section/";
		$this->layoutToken        = "/^layout/";
		$this->componentToken     = "/^component/";

		$this->listStartToken     = "/^\(/";
		$this->listDelimiterToken = "/^,/";
		$this->listEndToken       = "/^\)/";
		$this->listEscapeEndToken = "/^\\)/";

		$this->quotedString       = "/^([^']+)/";
		$this->quote              = "/^'/";
		$this->escapedQuote       = "/^''/";
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
	public function parse(Hints $hints, string $text): Hints
	{
		$this->text = $text;
		$this->done = "";
		$this->parseTokens($hints);
		return $hints;
	}

	private function parseTokens(Hints $hints): void
	{
		$mask = ~0;
		while ($this->valid()) {
			if ($this->match('spaceToken')) {
				// do nothing
			} elseif ($this->match('tagToken', $tag)) {
				$this->parseTag($hints, $tag);
			} elseif ($this->isbitset($mask, 1) && $this->match('labelToken')) {
				$this->clearbit($mask, 1);
				$hints->setLabel($this->parseQuotedString());
			} elseif ($this->isbitset($mask, 2) && $this->match('sectionToken')) {
				$this->clearbit($mask, 2);
				$hints->setSection($this->parseName());
			} elseif ($this->isbitset($mask, 3) && $this->match('componentToken')) {
				$this->clearbit($mask, 3);
				$hints->setComponent($this->parseName());
			} elseif ($this->isbitset($mask, 4) && $this->match('layoutToken')) {
				$this->clearbit($mask, 4);
				$hints->setLayout($this->parseName());
			} else {
				throw new ParserError($this->parseError("Unexpected token"));
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

	private function parseName(): string
	{
		$sequence = 0;
		$name = null;
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
					} elseif ($this->match('nameToken', $name)) {
						++$sequence;
					} else {
						throw new ParserError($this->parseError("expected space or name"));
					}
					break;

				case 2:
					if ($this->match('spaceToken')) {
						// do nothing
					} elseif ($this->match('listEndToken')) {
						break 2;
					} else {
						throw new ParserError($this->parseError("expected space or list end"));
					}
					break;
				}
		}
		return $name;
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
