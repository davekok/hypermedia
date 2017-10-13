<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\Meta\Type;
use PHPUnit\Framework\TestCase;

class TypeTest extends TestCase
{
	public function testPassword()
	{
		$type = new Type\PasswordType;
		$password = "SqZ2nTFCnM$"; $this->assertTrue($type->filter($password));
		$password = "jhsdfkhsfkjhsfkjhskfjhskjfhskjhfskjhfkshf uihkjsdhfoe hfskjdhf uwef ksjndf ksdfkjh"; $this->assertTrue($type->filter($password));
		$password = "isdjfjdi"; $this->assertFalse($type->filter($password));
		$password = "㗐㗑㗒㗓㗔㗕㗖㗗㗘㗙㗚㗛㗜㗝㗞㗟㗠㗡㗢㗣㗤㗥㗦㗧㗨㗩㗪㗫㗬㗭㗮㗯㗰㗱㗲㗳㗴㗵㗶㗷㗸㗹㗺㗻㗼㗽㗾㗿㘀㘁㘂㘃㘄㘅㘆㘇㘈㘉㘊㘋㘌㘍㘎㘏"; $this->assertTrue($type->filter($password));
		$password = "SqZ2n㗓FCnM$"; $this->assertTrue($type->filter($password));
		$password = "㗐㗑㗒㗓㗔㗕8#"; $this->assertTrue($type->filter($password));
	}
}
