<?php declare(strict_types=1);

namespace Tests\Sturdy\Activity;

use Sturdy\Activity\Meta\Type;
use PHPUnit\Framework\TestCase;
use Faker;

class TypeTest extends TestCase
{
	private $faker;
	
	public function __construct($name = null, array $data = [], $dataName = '')
	{
		parent::__construct($name, $data, $dataName);
		$this->faker = Faker\Factory::create();
	}
	
	public function testPasswordType()
	{
		$type = new Type\PasswordType;
		$password = "SqZ2nTFCnM$"; $this->assertTrue($type->filter($password));
		$password = "jhsdfkhsfkjhsfkjhskfjhskjfhskjhfskjhfkshf uihkjsdhfoe hfskjdhf uwef ksjndf ksdfkjh"; $this->assertTrue($type->filter($password));
		$password = "isdjfjdi"; $this->assertFalse($type->filter($password));
		$password = "㗐㗑㗒㗓㗔㗕㗖㗗㗘㗙㗚㗛㗜㗝㗞㗟㗠㗡㗢㗣㗤㗥㗦㗧㗨㗩㗪㗫㗬㗭㗮㗯㗰㗱㗲"; $this->assertTrue($type->filter($password));
		$password = "SqZ2n㗓FCnM$"; $this->assertTrue($type->filter($password));
		$password = "㗐㗑㗒㗓㗔㗕8#"; $this->assertTrue($type->filter($password));
	}
	
	public function testBooleanType()
	{
		$type = new Type\BooleanType();
		$boolean = "123"; $this->assertFalse($type->filter($boolean));
		$boolean = 123; $this->assertFalse($type->filter($boolean));
		$boolean = "false"; $this->assertTrue($type->filter($boolean)); $this->assertTrue($boolean === false);
		$boolean = false; $this->assertTrue($type->filter($boolean));
	}
	
	public function testColorType()
	{
		$type = new Type\ColorType();
		$color = "#fff"; $this->assertTrue($type->filter($color));
		$color = "#fFfF"; $this->assertTrue($type->filter($color));
		$color = "#ffffff"; $this->assertTrue($type->filter($color));
		$color = "#ffffffff"; $this->assertTrue($type->filter($color));
		$color = "#zfffff"; $this->assertFalse($type->filter($color));
		$color = $this->faker->boolean; $this->assertFalse($type->filter($color));
		$color = "fff123"; $this->assertFalse($type->filter($color));
		$color = false; $this->assertFalse($type->filter($color));
	}
	
	public function testDateTimeType()
	{
		$type = new Type\DateTimeType();
		$datetime = "1995-09-03T23:59:59Z"; $this->assertTrue($type->filter($datetime));
		$datetime = "1995-09-03 23:59:59"; $this->assertFalse($type->filter($datetime));
		$datetime = "1995-09-03T23:59:"; $this->assertFalse($type->filter($datetime));
		$datetime = true; $this->assertFalse($type->filter($datetime));
	}
	
	// TODO: All Below
	public function testDateType()
	{
		$type = new Type\DateType();
		$datetime = "1995-09-03"; $this->assertTrue($type->filter($datetime));
		$datetime = "1995-13-03"; $this->assertFalse($type->filter($datetime));
		$datetime = 1; $this->assertFalse($type->filter($datetime));
	}
	
	public function testDayType()
	{
		$type = new Type\DayType();
		$day = "31"; $this->assertTrue($type->filter($day));
		$day = "01"; $this->assertTrue($type->filter($day));
		$day = "2"; $this->assertTrue($type->filter($day));
		$day = 2; $this->assertFalse($type->filter($day));
	}
	
	public function testEmailType()
	{
		$type = new Type\EmailType();
		$email = "firstname_lastname@gmail.com"; $this->assertTrue($type->filter($email));
		$email = "firstname.lastname@hotmail.com"; $this->assertTrue($type->filter($email));
		$email = "firstname-lastname@www.weirdsubdomain.com"; $this->assertTrue($type->filter($email));
		$email = "firstname-lastname2hotmail.com"; $this->assertFalse($type->filter($email));
		$email = "firstname"; $this->assertFalse($type->filter($email));
		$email = 2; $this->assertFalse($type->filter($email));
	}
	
	public function testEnumType()
	{
		$type = new Type\EnumType();
		$type->addOption("a");
		$type->addOption("b");
		$type->addOption("c");
		$choice = "b";
		$this->assertTrue($type->filter($choice));
		$choice = "d";
		$this->assertFalse($type->filter($choice));
	}
	
	public function testFloatType()
	{
		$type = new Type\FloatType();
		$float = 5.2; $this->assertTrue($type->filter($float));
		$float = 5; $this->assertFalse($type->filter($float));
		$float = "Test"; $this->assertFalse($type->filter($float));
	}
	
	public function testHTMLType()
	{
		$type = new Type\HTMLType();
		$html = $this->faker->randomHtml(); $this->assertTrue($type->filter($html));
	}
	
	public function testIntegerType()
	{
		$type = new Type\IntegerType();
		$int = 5; $this->assertTrue($type->filter($int));
		$int = "Invalid"; $this->assertFalse($type->filter($int));
	}
	
	public function testMonthType()
	{
		$type = new Type\MonthType();
		$month = "Sep"; 	$this->assertTrue($type->filter($month));
		$month = 2; 		$this->assertTrue($type->filter($month));
		$month = 12; 		$this->assertTrue($type->filter($month));
		$month = 13; 		$this->assertFalse($type->filter($month));
		$month = 00; 		$this->assertFalse($type->filter($month));
	}
	
	public function testSetType()
	{
		$type = new Type\SetType();
		$type->addOption("a");
		$type->addOption("b");
		$type->addOption("c");
		$set = "a"; $this->assertTrue($type->filter($set));
		$set = "a,b"; $this->assertTrue($type->filter($set));
		$set = "a,b,d"; $this->assertFalse($type->filter($set));
		$set = "a,b;d"; $this->assertFalse($type->filter($set));
	}
	
	public function testStringType()
	{
		$type = new Type\StringType();
		$string = "abc"; $this->assertTrue($type->filter($string));
		$string = 123; $this->assertFalse($type->filter($string));
		$string = $this->faker->boolean(); $this->assertFalse($type->filter($string));
		$string = ["a","b"]; $this->assertFalse($type->filter($string));
	}
	
	public function testTimeType()
	{
		$type = new Type\TimeType();
		$time = "20:00"; $this->assertTrue($type->filter($time));
		$time = "20:00:59"; $this->assertTrue($type->filter($time));
		$time = "T20:00:59"; $this->assertTrue($type->filter($time));
		$time = "20:00:60";  $this->assertFalse($type->filter($time));
	}
	
	public function testURLType()
	{
		$type = new Type\URLType();
		$url = "htpps://a.com"; $this->assertTrue($type->filter($url));
		$url = "http://a.b.nl"; $this->assertTrue($type->filter($url));
		$url = "http://a.be/hwa/wa"; $this->assertTrue($type->filter($url));
		$url = "http://a.be/?v=R2HhW431"; $this->assertTrue($type->filter($url));
		$url = "a"; $this->assertFalse($type->filter($url));
		$url = 2; $this->assertFalse($type->filter($url));
	}
	
	public function testUUIDType()
	{
		$type = new Type\UUIDType();
		$uuid = $this->faker->uuid; $this->assertTrue($type->filter($uuid));
	}
	
	public function testWeekDayType()
	{
		$type = new Type\WeekDayType();
		$weekday = "Monday"; $this->assertTrue($type->filter($weekday));
		$weekday = "mon"; $this->assertTrue($type->filter($weekday));
		$weekday = 7; $this->assertTrue($type->filter($weekday));
		$weekday = 0; $this->assertFalse($type->filter($weekday));
		$weekday = "mond"; $this->assertFalse($type->filter($weekday));
	}
	
	public function testWeekType()
	{
		$type = new Type\WeekType();
		$week = 52; $this->assertTrue($type->filter($week));
		$week = 40; $this->assertTrue($type->filter($week));
		$week = 0; $this->assertFalse($type->filter($week));
		$week = "Fifty-two"; $this->assertFalse($type->filter($week));
	}
	
	public function testYearType()
	{
		$type = new Type\YearType();
		$year = 1995; $this->assertTrue($type->filter($year));
		$year = 0; $this->assertFalse($type->filter($year));
	}
}
