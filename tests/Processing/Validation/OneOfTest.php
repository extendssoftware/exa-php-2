<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Validation;

use ExtendsSoftware\ExaPHP\Processing\Validation\OneOf;
use PHPUnit\Framework\TestCase;
use stdClass;

final class OneOfTest extends TestCase
{
    public function testViolationCodesRemainStable(): void
    {
        self::assertSame('not_one_of', OneOf::CODE_NOT_ONE_OF);
    }

    public function testUsesStrictMembership(): void
    {
        $validator = new OneOf(1, null, ['x' => 1]);
        self::assertTrue($validator->validate(1)->isValid());
        self::assertTrue($validator->validate(null)->isValid());
        self::assertTrue($validator->validate(['x' => 1])->isValid());
        foreach (['1', true, 1.0, ['x' => '1']] as $input) {
            $result = $validator->validate($input);
            self::assertFalse($result->isValid());
            self::assertSame(OneOf::CODE_NOT_ONE_OF, $result->violations()[0]->code());
        }
    }

    public function testObjectsMatchByIdentity(): void
    {
        $object = new stdClass();
        $validator = new OneOf($object);
        self::assertTrue($validator->validate($object)->isValid());
        self::assertFalse($validator->validate(new stdClass())->isValid());
    }

    public function testEmptySetRejectsInput(): void
    {
        self::assertFalse(new OneOf()->validate(null)->isValid());
    }
}
