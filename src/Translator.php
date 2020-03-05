<?php declare(strict_types=1);

namespace Sturdy\Activity;

/**
 * A interface to be implemented by the application to
 * provide this component with a journal repository.
 */
interface Translator
{
	/**
	 * Translate message
	 *
	 * @return string  the translation
	 */
	public function __invoke(string $message, array $parameters = []): string;
}
