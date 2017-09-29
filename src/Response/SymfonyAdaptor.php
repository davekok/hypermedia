<?php declare(strict_types=1);

namespace Sturdy\Activity\Response;

/**
 * An adaptor for symfony responses.
 */
final class SymfonyAdaptor extends \Symfony\Component\HttpFoundation\Response
{
	/**
	 * Constructor
	 *
	 * Fills in the symfony response.
	 *
	 * @param Response $response  the response
	 */
	public function __construct(Response $response)
	{
        $this->headers = new \Symfony\Component\HttpFoundation\ResponseHeaderBag();
		$this->setProtocolVersion($response->getProtocolVersion());
		$this->setStatusCode($response->getStatusCode(), $response->getStatusText());
		$this->setDate($response->getDate());
		if ($location = $response->getLocation()) {
			$this->headers->set("Location", $location);
		}
		if ($contentType = $response->getContentType()) {
			$this->headers->set("Content-Type", $contentType);
		}
		if ($content = $response->getContent()) {
			$this->setContent($content);
		}
	}
}
