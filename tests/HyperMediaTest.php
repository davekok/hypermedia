<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\{
	Cache,
	HyperMedia,
	Journal,
	JournalBranch,
	JournalRepository,
	Resource
};
use Sturdy\Activity\Meta\{
	CacheItem_Resource,
	Field,
	FieldFlags,
	Verb
};
use PHPUnit\Framework\TestCase;
use Prophecy\{
	Argument,
	Prophet
};
use Throwable;

class HyperMediaTest extends TestCase
{
	public function testResource()
	{
		$prophet = new Prophet;

		$cache = $prophet->prophesize();
		$cache->willImplement(Cache::class);
		$cache->getResource('TestUnit1', TestUnit1\Resource1::class, [])
			->shouldBeCalledTimes(1)
			->willReturn((new CacheItem_Resource())
				->setClass(TestUnit1\Resource1::class)
				->setTags([])
				->setField("name", "string", FieldFlags::required)
				->setVerb("GET", "foo")
				->setVerb("POST", "bar", Verb::NO_CONTENT)
			);

		$journalRepository = $prophet->prophesize();
		$journalRepository->willImplement(JournalRepository::class);
		$journalRepository->createJournal("TestUnit1", Journal::resource, TestUnit1\Resource1::class, [])
			->shouldBeCalledTimes(1)
			->will(function($args)use($journal){
				[$sourceUnit, $type, $class, $tags] = $args;
				$journal->getSourceUnit()->willReturn($sourceUnit);
				$journal->getType()->willReturn($type);
				$journal->getClass()->willReturn($class);
				$journal->getTags()->willReturn($tags);
				return $journal->reveal();
			});
		$journalRepository->saveJournal(Argument::type(Journal::class))
			->shouldBeCalled();

		$request = [ "verb" => "GET", "path" => "/", ];

		$hm = new HyperMedia($cache->reveal(), $journalRepository->reveal(), "TestUnit1", "/", new stdClass);
		$hm->process();
	}
}
