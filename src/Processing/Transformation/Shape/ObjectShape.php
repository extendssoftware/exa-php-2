<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Transformation\Shape;

use ExtendsSoftware\ExaPHP\Processing\Transformation\Exception\InvalidShapeException;
use ExtendsSoftware\ExaPHP\Processing\ProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Transformer;
use ExtendsSoftware\ExaPHP\Processing\Violation;
use stdClass;
use Throwable;

use function get_mangled_object_vars;
use function is_object;
use function str_contains;

/**
 * Processes initialized public object properties into a new stdClass without retaining the source class.
 *
 * @implements Transformer<mixed, stdClass>
 */
final readonly class ObjectShape implements Transformer
{
    /**
     * The input is not an object.
     */
    public const string CODE_NOT_OBJECT = 'not_object';

    /**
     * A required property is absent or not exposed as an initialized public property.
     */
    public const string CODE_MISSING_FIELD = ArrayShape::CODE_MISSING_FIELD;

    /**
     * An undeclared public property is present.
     */
    public const string CODE_UNKNOWN_FIELD = ArrayShape::CODE_UNKNOWN_FIELD;

    /**
     * The field processor shared with array shapes.
     *
     * @var ArrayShape
     */
    private ArrayShape $shape;

    /**
     * Creates an object shape with explicit property definitions.
     *
     * @param array<array-key, Field> $fields Property definitions; numeric keys denote numeric property names.
     * @param UnknownFields $unknownFields The policy for undeclared public properties.
     *
     * @throws InvalidShapeException When definitions are invalid or property names contain null bytes.
     */
    public function __construct(array $fields, UnknownFields $unknownFields = UnknownFields::Reject)
    {
        foreach ($fields as $key => $field) {
            if (str_contains((string) $key, "\0")) {
                throw new InvalidShapeException('Object property names must not contain null bytes.');
            }
        }
        $this->shape = new ArrayShape($fields, $unknownFields);
    }

    /**
     * Processes public property values without invoking magic accessors or changing the source object.
     *
     * Only initialized, public, stored properties are included; getter hooks and virtual properties are bypassed.
     * Paths use string property names. Nested objects remain shared unless transformed by a configured field.
     *
     * @param mixed $value The source object.
     *
     * @return ProcessingResult<stdClass> A new object or collected property violations.
     *
     * @throws Throwable When property extraction or field execution fails, propagated unchanged.
     */
    public function transform(mixed $value): ProcessingResult
    {
        if (!is_object($value)) {
            return ProcessingResult::failure(new Violation(self::CODE_NOT_OBJECT, 'Value must be an object.'));
        }
        $properties = [];
        foreach (get_mangled_object_vars($value) as $key => $entry) {
            if (!str_contains((string) $key, "\0")) {
                $properties[$key] = $entry;
            }
        }
        $result = $this->shape->transform($properties);
        if ($result->isValid()) {
            return ProcessingResult::success((object) $result->value());
        }
        $violations = [];
        foreach ($result->violations() as $violation) {
            $path = $violation->path();
            $path[0] = (string) $path[0];
            $violations[] = new Violation($violation->code(), $violation->message(), $path, $violation->parameters());
        }

        return ProcessingResult::failure(...$violations);
    }
}
