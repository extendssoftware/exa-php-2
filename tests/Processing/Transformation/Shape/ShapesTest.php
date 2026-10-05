<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Transformation\Shape;

use ExtendsSoftware\ExaPHP\Processing\Exception\InvalidShapeException;
use ExtendsSoftware\ExaPHP\Processing\Exception\ProcessingValueUnavailableException;
use ExtendsSoftware\ExaPHP\Processing\ProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\SequentialPipeline;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Collection\EachItem;
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
        $this->assertTrue($result->isValid());
        $this->assertInstanceOf(stdClass::class, $result->value());
        $this->assertNotSame($input, $result->value());
        $this->assertSame('Title', $result->value()->title);
        $this->assertSame(['key' => ['count' => 42]], $result->value()->entries);
        $this->assertSame(' Title ', $input->title);
        $this->assertSame(' 42 ', $input->entries['key']['count']);
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
        $this->assertCount(4, $violations);
        $this->assertSame(['entries', 0, 'title'], $violations[0]->path());
        $this->assertSame(['entries', 1, 'title'], $violations[1]->path());
        $this->assertSame(['required'], $violations[2]->path());
        $this->assertSame(['extra'], $violations[3]->path());
        $this->expectException(ProcessingValueUnavailableException::class);
        $result->value();
    }

    public function testMissingOptionalDiffersFromExplicitNull(): void
    {
        $shape = new ArrayShape(['name' => new Field(new NotNull(), FieldPresence::Optional)]);
        $this->assertSame([], $shape->transform([])->value());
        $result = $shape->transform(['name' => null]);
        $this->assertSame(NotNull::CODE_NULL_VALUE, $result->violations()[0]->code());
        $this->assertSame(['name'], $result->violations()[0]->path());
    }

    public function testNullCanBeSuccessfulAndUnknownPoliciesAreExplicit(): void
    {
        $fields = ['name' => new Field(new SequentialPipeline())];
        $object = new stdClass();
        $input = ['extra' => $object, 'name' => null];
        $this->assertSame(['name' => null], new ArrayShape($fields, UnknownFields::Discard)->transform($input)->value());
        $this->assertSame(
            ['name' => null, 'extra' => $object],
            new ArrayShape($fields, UnknownFields::Preserve)->transform($input)->value(),
        );
        $this->assertSame(
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
        $this->assertSame(['name' => 'raw'], (array) $shape->transform($input)->value());
        $missing = new ObjectShape(['computed' => new Field(new NotNull())], UnknownFields::Discard);
        $this->assertSame(ObjectShape::CODE_MISSING_FIELD, $missing->transform($input)->violations()[0]->code());
    }

    public function testNumericObjectPropertyPathsAreStringsWhileArrayKeysRemainIntegers(): void
    {
        $fields = [0 => new Field(new NotNull())];
        $this->assertSame(['0'], new ObjectShape($fields)->transform((object) [null])->violations()[0]->path());
        $this->assertSame([0], new ArrayShape($fields)->transform([null])->violations()[0]->path());
    }

    public function testWrongContainerTypesProduceRootViolations(): void
    {
        $this->assertSame(ObjectShape::CODE_NOT_OBJECT, new ObjectShape([])->transform([])->violations()[0]->code());
        $this->assertSame(ArrayShape::CODE_NOT_ARRAY, new ArrayShape([])->transform(new stdClass())->violations()[0]->code());
        $this->assertSame([], new ArrayShape([])->transform(null)->violations()[0]->path());
    }

    public function testPrefixesWithoutChangingOriginalViolation(): void
    {
        $violation = new Violation('invalid', 'Message', ['leaf'], ['limit' => 2]);
        $step = $this->createStub(Transformer::class);
        $step->method('transform')->willReturn(ProcessingResult::failure($violation));
        $result = new ArrayShape(['parent' => new Field($step)])->transform(['parent' => 1]);
        $prefixed = $result->violations()[0];
        $this->assertNotSame($violation, $prefixed);
        $this->assertSame(['leaf'], $violation->path());
        $this->assertSame(['parent', 'leaf'], $prefixed->path());
        $this->assertSame($violation->code(), $prefixed->code());
        $this->assertSame($violation->message(), $prefixed->message());
        $this->assertSame($violation->parameters(), $prefixed->parameters());
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
