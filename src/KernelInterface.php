<?php declare(strict_types=1);

namespace Sturdy\Worker;

use Symfony\Component\HttpKernel\KernelInterface as BaseKernelInterface;

class KernelInterface extends BaseKernelInterface
{
	/**
	 * Gets the run directory.
	 *
	 * @return string The run directory
	 */
	public function getRunDir(): string;

	/**
	 * Gets safe environment variables.
	 *
	 * @return array safe environment variables
	 */
	public function getSafeEnvironmentVariables(): array;

	/**
	 * Gets environment variable defaults.
	 *
	 * @return array environment variable defaults
	 */
	public function getEnvironmentVariableDefaults(): array;
}
