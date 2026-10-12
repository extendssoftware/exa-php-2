<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Transformation\Shape;

use ExtendsSoftware\ExaPHP\Processing\Exception\ProcessingValueUnavailableException;
use ExtendsSoftware\ExaPHP\Processing\FailedProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\SequentialPipeline;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Collection\EachItem;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Exception\InvalidShapeException;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Shape\ArrayShape;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Shape\Field;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Shape\FieldPresence;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Shape\ObjectShape;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Shape\UnknownFields;
use ExtendsSoftware\ExaPHP\Processing\Transformation\String\StringToInteger;
use ExtendsSoftware\ExaPHP\Processing\Transformation\String\TrimString;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Transformer;
use ExtendsSoftware\ExaPHP\Processing\Validation\NotNull;
use ExtendsSoftware\ExaPHP\Processing\Validation\String\NotBlank;
use ExtendsSoftware\ExaPHP\Processing\Violation;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

final class ShapesTest extends TestCase
{
    public function testProcessesNestedObjectsArraysAndPipelinesWithoutChangingInput(): void
    {
        $pipeline = new SequentialPipeline();
        $pipeline->append(new TrimString());
        $pipeline->append(new StringToInteger());
        $shape = new ObjectShape([
            'title' => new Field(new TrimString()),
            'entries' => new Field(new EachItem(new ArrayShape(['count' => new Field($pipeline)]))),
        ]);
        $input = (object) ['title' => ' Title ', 'entries' => ['key' => ['count' => ' 42 ']]];
        $result = $shape->transform($input);
        self::assertTrue($result->isValid());
        self::assertInstanceOf(stdClass::class, $result->value());
        self::assertNotSame($input, $result->value());
        self::assertSame('Title', $result->value()->title);
        self::assertSame(['key' => ['count' => 42]], $result->value()->entries);
        self::assertSame(' Title ', $input->title);
        self::assertSame(' 42 ', $input->entries['key']['count']);
    }

    public function testAggregatesNestedViolationsAndUnknownFieldsInOrder(): void
    {
        $shape = new ObjectShape([
            'entries' => new Field(new EachItem(new ObjectShape(['title' => new Field(new NotBlank())]))),
            'required' => new Field(new NotNull()),
        ]);
        $input = (object) ['entries' => [(object) ['title' => ''], (object) []], 'extra' => 1];
        $result = $shape->transform($input);
        $violations = $result->violations();
        self::assertCount(4, $violations);
        self::assertSame(['entries', 0, 'title'], $violations[0]->path());
        self::assertSame(['entries', 1, 'title'], $violations[1]->path());
        self::assertSame(['required'], $violations[2]->path());
        self::assertSame(['extra'], $violations[3]->path());
        $this->expectException(ProcessingValueUnavailableException::class);
        $result->value();
    }

    public function testMissingOptionalDiffersFromExplicitNull(): void
    {
        $shape = new ArrayShape(['name' => new Field(new NotNull(), FieldPresence::Optional)]);
        self::assertSame([], $shape->transform([])->value());
        $result = $shape->transform(['name' => null]);
        self::assertSame(NotNull::CODE_NULL_VALUE, $result->violations()[0]->code());
        self::assertSame(['name'], $result->violations()[0]->path());
    }

    public function testNullCanBeSuccessfulAndUnknownPoliciesAreExplicit(): void
    {
        $fields = ['name' => new Field(new SequentialPipeline())];
        $object = new stdClass();
        $input = ['extra' => $object, 'name' => null];
        self::assertSame(['name' => null], new ArrayShape($fields, UnknownFields::Discard)->transform($input)->value());
        self::assertSame(
            ['name' => null, 'extra' => $object],
            new ArrayShape($fields, UnknownFields::Preserve)->transform($input)->value(),
        );
        self::assertSame(
            ArrayShape::CODE_UNKNOWN_FIELD,
            new ArrayShape($fields)->transform($input)->violations()[0]->code(),
        );
    }

    public function testAcceptsGeneralObjectsAndBypassesAccessors(): void
    {
        $input = new class {
            public string $name = ' raw ' {
                get => $this->name === ' raw ' ? throw new TypeError('Getter must not run') : $this->name;
            }
            public string $computed { get => throw new TypeError('Virtual getter must not run'); }
            public string $uninitialized;
            private string $secret = 'secret';
            public static string $staticValue = 'static';

            public function __get(string $name): mixed
            {
                throw new TypeError('Magic getter must not run');
            }
        };
        $shape = new ObjectShape(['name' => new Field(new TrimString())]);
        self::assertSame(['name' => 'raw'], (array) $shape->transform($input)->value());
        $missing = new ObjectShape(['computed' => new Field(new NotNull())], UnknownFields::Discard);
        self::assertSame(ObjectShape::CODE_MISSING_FIELD, $missing->transform($input)->violations()[0]->code());
    }

    public function testNumericObjectPropertyPathsAreStringsWhileArrayKeysRemainIntegers(): void
    {
        $fields = [0 => new Field(new NotNull())];
        self::assertSame(['0'], new ObjectShape($fields)->transform((object) [null])->violations()[0]->path());
        self::assertSame([0], new ArrayShape($fields)->transform([null])->violations()[0]->path());
    }

    public function testWrongContainerTypesProduceRootViolations(): void
    {
        self::assertSame(ObjectShape::CODE_NOT_OBJECT, new ObjectShape([])->transform([])->violations()[0]->code());
        self::assertSame(ArrayShape::CODE_NOT_ARRAY, new ArrayShape([])->transform(new stdClass())->violations()[0]->code());
        self::assertSame([], new ArrayShape([])->transform(null)->violations()[0]->path());
    }

    public function testPrefixesWithoutChangingOriginalViolation(): void
    {
        $violation = new Violation('invalid', 'Message', ['leaf'], ['limit' => 2]);
        $step = self::createStub(Transformer::class);
        $step->method('transform')->willReturn(new FailedProcessingResult($violation));
        $result = new ArrayShape(['parent' => new Field($step)])->transform(['parent' => 1]);
        $prefixed = $result->violations()[0];
        self::assertNotSame($violation, $prefixed);
        self::assertSame(['leaf'], $violation->path());
        self::assertSame(['parent', 'leaf'], $prefixed->path());
        self::assertSame($violation->code(), $prefixed->code());
        self::assertSame($violation->message(), $prefixed->message());
        self::assertSame($violation->parameters(), $prefixed->parameters());
    }

    public function testRejectsInvalidFieldDefinitions(): void
    {
        $this->expectException(InvalidShapeException::class);
        new ArrayShape(['field' => new NotNull()]);
    }

    public function testRejectsNullBytesInObjectPropertyDefinitions(): void
    {
        $this->expectException(InvalidShapeException::class);
        new ObjectShape(["\0hidden" => new Field(new NotNull())]);
    }
}
