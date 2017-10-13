<?php declare(strict_types=1);

namespace Sturdy\Activity\Meta\Type;

use stdClass, DOMDocument;

final class HTMLType extends Type
{
	const type = "html";
	const tidyconfig = [
		'clean' => true,
		'output-html' => true,
		'drop-empty-paras' => true,
		'doctype' => 'omit',
		'enclose-block-text' => true,
		'fix-backslash' => true,
		'fix-uri' => true,
		'drop-proprietary-attributes' => true,
		'join-classes' => true,
		'join-styles' => true,
		'char-encoding' => 'utf8',
		'newline' => 'LF',
		'output-bom' => false,
	];

	/**
	 * Constructor
	 *
	 * @param array|null $state the objects state
	 */
	public function __construct(array $state = null)
	{

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
	 * Filter value
	 *
	 * @param  &$value the value to filter
	 * @return bool whether the value is valid
	 */
	public function filter(&$value): bool
	{
		$tidy = tidy_parse_string($value, self::tidyconfig, 'utf8');
		if (tidy_error_count($tidy)) return false;
		$value = tidy_get_output($tidy);
		return true;
	}
}
