<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\{
	Cache,
	HyperMedia,
	Journal,
	JournalBranch,
	JournalRepository
};
use Sturdy\Activity\Request\Request;
use Sturdy\Activity\Response;
use Sturdy\Activity\Meta\{
	CacheItem_Resource,
	FieldFlags,
	Verb
};
use PHPUnit\Framework\TestCase;
use Prophecy\{
	Argument,
	Prophet
};
use Throwable;
use stdClass;

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
				->setField("name", "string,,,", FieldFlags::required|FieldFlags::meta)
				->setVerb("GET", "foo")
				->setVerb("POST", "bar", Verb::NO_CONTENT)
			);

		$journalRepository = $prophet->prophesize();
		$journalRepository->willImplement(JournalRepository::class);
		$journalRepository->findOneJournalById(5)
			->shouldBeCalledTimes(1)
			->will(function($args)use($prophet){
				[$journalId] = $args;
				$journal = $prophet->prophesize();
				$journal->willImplement(Journal::class);
				$journal->getId()->willReturn($journalId);
				$journal->getSourceUnit()->willReturn("TestUnit1");
				$journal->getType()->willReturn(Journal::resource);
				$journal->getTags()->willReturn([]);
				$journal->getMainBranch()->will(function($args)use($prophet){
					$branch = $prophet->prophesize();
					$branch->willImplement(JournalBranch::class);
					$branch->addEntry(Argument::type("object"), "foo", 200, "OK")->willReturn($branch);
					return $branch->reveal();
				});
				return $journal->reveal();
			});

		// $journalRepository->createJournal("TestUnit1", Journal::resource, [])
		// 	->shouldBeCalledTimes(1)
		// 	->will(function($args)use($prophet){
		// 		[$sourceUnit, $type, $tags] = $args;
		// 		$journal = $prophet->prophesize();
		// 		$journal->willImplement(Journal::class);
		// 		$journal->getSourceUnit()->willReturn($sourceUnit);
		// 		$journal->getType()->willReturn($type);
		// 		$journal->getTags()->willReturn($tags);
		// 		return $journal->reveal();
		// 	});

		$journalRepository->saveJournal(Argument::type(Journal::class))->shouldBeCalled();

		$request = $prophet->prophesize();
		$request->willImplement(Request::class);
		$request->getProtocolVersion()->shouldBeCalled()->willReturn("1.1");
		$request->getVerb()->shouldBeCalled()->willReturn("GET");
		$request->getPath()->shouldBeCalled()->willReturn("/5/".strtr(TestUnit1\Resource1::class,"\\","/"));
		$request->getQuery()->shouldBeCalled()->willReturn("name=Spock");
		$request->getContentType()->shouldNotBeCalled()->willReturn(null);
		$request->getContent()->shouldNotBeCalled()->willReturn(null);

		$hm = new HyperMedia($cache->reveal(), $journalRepository->reveal(), "TestUnit1", "/", new stdClass);
		$response = $hm->handle([], $request->reveal());
		if ($response instanceof Response\Error) {
			throw $response;
		}
		$prophet->checkPredictions();
		$this->assertTrue($response instanceof Response\OK, "OK response");
		$this->assertJsonStringEqualsJsonString('{"main":{"fields":{"name":{"meta":true,"type":"string","required":true,"value":"Spock"}}}}', $response->getContent());
	}
}
