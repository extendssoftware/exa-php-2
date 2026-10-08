<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Processing\Transformation\Shape;

use Override;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Exception\InvalidShapeException;
use ExtendsSoftware\ExaPHP\Processing\ProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Transformer;
use ExtendsSoftware\ExaPHP\Processing\Violation;
use Throwable;

use function array_key_exists;
use function is_array;

/**
 * Processes declared array fields independently and collects violations across fields.
 *
 * @implements Transformer<mixed, array<array-key, mixed>>
 */
final readonly class ArrayShape implements Transformer
{
    /**
     * The input is not an array.
     */
    public const string CODE_NOT_ARRAY = 'not_array';

    /**
     * A required field is absent.
     */
    public const string CODE_MISSING_FIELD = 'missing_field';

    /**
     * An undeclared field is present.
     */
    public const string CODE_UNKNOWN_FIELD = 'unknown_field';

    /**
     * Creates a shape with explicit field definitions.
     *
     * @param array<array-key, Field> $fields Definitions in processing order.
     * @param UnknownFields $unknownFields The policy for undeclared input keys.
     *
     * @throws InvalidShapeException When a definition is not a Field.
     */
    public function __construct(private array $fields, private UnknownFields $unknownFields = UnknownFields::Reject)
    {
        foreach ($fields as $field) {
            if (!$field instanceof Field) {
                throw new InvalidShapeException('Shape definitions must map field names or keys to Field instances.');
            }
        }
    }

    /**
     * {@inheritDoc}
     *
     * Known fields run in definition order, followed by unknown fields in input order. Missing optional fields are
     * omitted; explicit null is processed. Failures expose no partial output. Preserved values are not cloned.
     *
     * @throws Throwable When field execution fails, propagated unchanged without continuing.
     */
    #[Override]
    public function transform(mixed $value): ProcessingResult
    {
        if (!is_array($value)) {
            return ProcessingResult::failure(new Violation(self::CODE_NOT_ARRAY, 'Value must be an array.'));
        }
        $output = [];
        $violations = [];
        foreach ($this->fields as $key => $field) {
            if (!array_key_exists($key, $value)) {
                if ($field->presence === FieldPresence::Required) {
                    $violations[] = new Violation(self::CODE_MISSING_FIELD, 'Required field is missing.', [$key]);
                }
                continue;
            }
            $result = $field->processor->transform($value[$key]);
            if ($result->isValid()) {
                $output[$key] = $result->value();
            } else {
                foreach ($result->violations() as $violation) {
                    $violations[] = $violation->withPrefix($key);
                }
            }
        }
        foreach ($value as $key => $entry) {
            if (array_key_exists($key, $this->fields)) {
                continue;
            }
            if ($this->unknownFields === UnknownFields::Reject) {
                $violations[] = new Violation(self::CODE_UNKNOWN_FIELD, 'Unknown field is not allowed.', [$key]);
            } elseif ($this->unknownFields === UnknownFields::Preserve) {
                $output[$key] = $entry;
            }
        }

        return $violations === []
            ? ProcessingResult::success($output)
            : ProcessingResult::failure(...$violations);
    }
}
