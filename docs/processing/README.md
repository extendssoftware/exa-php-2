# Processing

The `ExtendsSoftware\ExaPHP\Processing` component defines contracts for transformation, validation, and ordered
composition, with final readonly violation and result classes. Use the provided validators and transformers directly, or
implement their contracts for application-specific rules. `SequentialPipeline` implements the ordered composition
contract.

## Transform and validate input

Implement `Transformation\Transformer<TInput, TOutput>::transform()` to produce a `ProcessingResult<TOutput>`.
A successful result exposes the transformed value through `value()`. Input that cannot be converted produces violations.

Implement `Validation\Validator<TInput>::validate()` to check a value and return a `ValidationResult`. Validation
must not change the input. Transformers must also leave their original input and nested objects unchanged, producing
an output value instead.

Both result classes expose `isValid()` and `violations()`. A result is valid exactly when its violation list is empty.
A `Violation` exposes a stable code, diagnostic message, named parameters, and a path represented by property
names or collection keys. An empty path identifies the whole input; `['articles', 0, 'title']` identifies a nested value.

Only read `ProcessingResult::value()` after checking `isValid()`. Reading an invalid result's value throws
`Exception\ProcessingValueUnavailableException`, implementing `ProcessingException`. A valid value may be null.

Create results explicitly:

```php
use ExtendsSoftware\ExaPHP\Processing\ProcessingResult;
use ExtendsSoftware\ExaPHP\Processing\Validation\ValidationResult;
use ExtendsSoftware\ExaPHP\Processing\Violation;

$violation = new Violation(
    code: 'required',
    message: 'A title is required.',
    path: ['title'],
);

$valid = new ValidationResult();
$invalid = new ValidationResult($violation);
$converted = ProcessingResult::success(42);
$failed = ProcessingResult::failure($violation);
```

`ProcessingResult::failure()` requires at least one violation. `ProcessingResult` uses composition rather than extending
`ValidationResult`; both expose the same validity and violation methods. Values and parameter objects are retained
without cloning or freezing them, so prefer immutable objects when a stable snapshot is required.

A violation requires a non-empty code, a list of string or integer path segments, and string parameter keys. Invalid
metadata throws `Exception\InvalidViolationException`, implementing `ProcessingException`.

## Use the built-in steps

All steps reject unsupported input types through violations without coercion. Their violation paths identify the root.
Each step exposes its codes through public typed constants, such as `NotBlank::CODE_BLANK_STRING` and
`StringToInteger::CODE_INTEGER_OVERFLOW`. Use these constants when matching violations. Shared code values such as
`not_string` are declared independently by each step that emits them; messages remain diagnostic text.

| Class under `Processing` | Behavior | Failure codes |
| --- | --- | --- |
| `Validation\NotNull` | Accepts any value except null, including false, zero, and an empty string | `null_value` |
| `Validation\String\NotBlank` | Requires a string that is not empty after trimming | `not_string`, `blank_string` |
| `Validation\Number\IntegerRange` | Requires an integer within inclusive bounds | `not_integer`, `integer_out_of_range` |
| `Transformation\String\TrimString` | Trims both ends of a string | `not_string` |
| `Transformation\String\StringToInteger` | Parses a decimal integer string | `not_string`, `invalid_integer_format`, `integer_overflow` |

`TrimString` and `NotBlank` use PHP's default trim characters: space, tab, LF, CR, NUL, and vertical tab. They do not
remove all Unicode whitespace; for example, a non-breaking space is retained and counts as non-blank. Internal whitespace
is preserved. `NotBlank` checks without modifying the input.

`StringToInteger` accepts ASCII digits with an optional leading `+` or `-` and permits leading zeros. It rejects
whitespace, decimal fractions, scientific notation, and hexadecimal prefixes. Trim first if whitespace should be
accepted. Overflow is checked against `PHP_INT_MIN` and `PHP_INT_MAX` before conversion; those bounds are included as
`minimum` and `maximum` violation parameters.

```php
use ExtendsSoftware\ExaPHP\Processing\Transformation\String\StringToInteger;
use ExtendsSoftware\ExaPHP\Processing\Transformation\String\TrimString;
use ExtendsSoftware\ExaPHP\Processing\Validation\Number\IntegerRange;

$trimmed = new TrimString()->transform(' 42 ');
$parsed = new StringToInteger()->transform($trimmed->value());
if ($parsed->isValid()) {
    $validation = new IntegerRange(1, 100)->validate($parsed->value());
}
```

This example supplies a known string to the trimmer; check its result too when accepting arbitrary input.
`IntegerRange` requires both bounds and accepts equal bounds. A minimum greater than the maximum throws
`Validation\Exception\InvalidIntegerRangeException`. Out-of-range violations include `minimum` and `maximum` parameters.

## Validate string length, patterns, and allowed values

`Validation\String\StringLength($minimum, $maximum)` counts UTF-8 Unicode code points with inclusive bounds.
Both bounds must be non-negative and ordered, otherwise construction throws `Validation\Exception\InvalidStringLengthException`.
Zero bounds are permitted. Combining marks count separately: `é` has one code point, while `e` followed by a combining
accent has two. No normalization is performed; this is neither byte length nor grapheme-cluster length. Newlines count.
It requires no mbstring or intl dependency.

Length failures use `CODE_STRING_LENGTH_OUT_OF_RANGE` with `minimum`, `maximum`, and `length` parameters. Non-string
input uses `CODE_NOT_STRING`; malformed UTF-8 uses `CODE_INVALID_UTF8`.

`Validation\String\MatchesPattern($pattern)` accepts a complete delimited PCRE expression, including modifiers.
Patterns are not automatically anchored: use `\A` and `\z` to require a whole-string match. Invalid expressions throw
`Validation\Exception\InvalidPatternException` during construction, preserving compiler warnings as previous exceptions.
Ordinary mismatches use `CODE_PATTERN_MISMATCH`; non-string input uses `CODE_NOT_STRING`. With Unicode matching enabled,
malformed UTF-8 produces `CODE_INVALID_UTF8`. Without Unicode matching, byte strings follow the supplied pattern's rules.
Regex execution failures, such as backtracking limits, throw `Validation\Exception\PatternExecutionException`; the same exception
reports an unexpected failure while counting string length.

`Validation\OneOf(...$values)` compares using strict PHP equality. For example, `new OneOf(1, 2)` rejects `'1'`, `true`,
and `1.0`. Arrays require strict equality and objects require the same instance. Pass an array of choices with argument
unpacking (`new OneOf(...$choices)`); passing the array directly makes that array one allowed value. An empty set rejects
all input. Values are retained without cloning nested objects. A mismatch uses `CODE_NOT_ONE_OF`.

```php
use ExtendsSoftware\ExaPHP\Processing\Validation\OneOf;
use ExtendsSoftware\ExaPHP\Processing\Validation\String\MatchesPattern;
use ExtendsSoftware\ExaPHP\Processing\Validation\String\StringLength;

$length = new StringLength(1, 80)->validate('Article title');
$slug = new MatchesPattern('/\A[a-z0-9-]+\z/')->validate('article-title');
$status = new OneOf('draft', 'published')->validate('draft');
```

The emitted codes are public constants on each validator. Pattern and membership violations do not include the configured
pattern or allowed values in their parameters.

## Collect violations from independent checks

`Validation\AllOf` accepts validators as variadic arguments and runs every validator against the same input:

```php
use ExtendsSoftware\ExaPHP\Processing\Validation\AllOf;
use ExtendsSoftware\ExaPHP\Processing\Validation\NotNull;
use ExtendsSoftware\ExaPHP\Processing\Validation\Number\IntegerRange;

$validator = new AllOf(new NotNull(), new IntegerRange(1, 100));
$result = $validator->validate(null); // Both null_value and not_integer violations.
```

Use independent checks that each accept the original input type. Violations do not stop evaluation: all are retained
in validator order, preserving their objects, paths, parameters, and duplicates. Repeated validator registrations run
repeatedly. An empty `AllOf` succeeds. Composites can be nested, and each call collects a fresh set of violations.
Configuration and execution exceptions stop evaluation immediately and propagate unchanged.

`AllOf` can be appended as one pipeline step. The pipeline stops if its combined result is invalid, after all validators
inside the composite have run. Use separate pipeline steps when later checks depend on earlier success.

## Compose steps

Create a `SequentialPipeline` and append steps before processing:

```php
use ExtendsSoftware\ExaPHP\Processing\SequentialPipeline;
use ExtendsSoftware\ExaPHP\Processing\Transformation\String\StringToInteger;
use ExtendsSoftware\ExaPHP\Processing\Transformation\String\TrimString;
use ExtendsSoftware\ExaPHP\Processing\Validation\Number\IntegerRange;
use ExtendsSoftware\ExaPHP\Processing\Validation\String\NotBlank;

$pipeline = new SequentialPipeline();
$pipeline->append(new TrimString());
$pipeline->append(new NotBlank());
$pipeline->append(new StringToInteger());
$pipeline->append(new IntegerRange(1, 100));

$result = $pipeline->process(' 42 ');
if ($result->isValid()) {
    $integer = $result->value(); // 42
} else {
    $violations = $result->violations();
}
```

Registration does not execute steps. The pipeline can process multiple inputs; each call starts with the supplied value.
Step instances are reused, so any state in application-defined steps remains their responsibility. Input objects are not
cloned: steps must honor their contracts by leaving inputs unchanged. Violations retain their original identity and order.
Exceptions and engine errors propagate unchanged and prevent subsequent steps from executing.

`Pipeline::append()` accepts a transformer or validator. `process()` runs registered steps in order, passing each the
current value. Transformers replace that value on success; validators leave it unchanged. A step returning violations
stops processing and its violations become the pipeline result. Earlier successful steps are not rolled back.

An empty pipeline returns a successful result with the original input. Repeated registrations execute repeatedly.
Each step must implement exactly one role. Appending an object that implements both `Transformer` and `Validator`
throws `Exception\AmbiguousPipelineStepException` without changing registrations or executing the step. Register
separate transformation and validation steps instead.
Ensure that each step accepts the preceding output type; the heterogeneous pipeline returns `ProcessingResult<mixed>`
and does not guarantee static checking of compatibility between steps.

For example, a configured pipeline can trim a string, validate that it is non-empty, parse it as an integer, then
validate its range. Parsing unsuitable text returns violations and prevents the range validator from running.

## Handle failures

Invalid input is an ordinary result, not an exception. `ProcessingException` is the root exception contract for
configuration and execution failures. Such failures stop pipeline execution instead of becoming input violations.

## Process object trees, array shapes, and collections

Use `Transformation\Shape\ObjectShape` for objects and `Transformation\Shape\ArrayShape` for arrays. Each takes a
map of names or keys to `Field` definitions. A field accepts one validator, transformer, or pipeline. These processors
implement `Transformer` and can themselves be fields, collection steps, or steps in a sequential pipeline.

```php
use ExtendsSoftware\ExaPHP\Processing\SequentialPipeline;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Collection\CollectionKeys;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Collection\EachItem;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Shape\ArrayShape;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Shape\Field;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Shape\FieldPresence;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Shape\ObjectShape;
use ExtendsSoftware\ExaPHP\Processing\Transformation\String\StringToInteger;
use ExtendsSoftware\ExaPHP\Processing\Transformation\String\TrimString;
use ExtendsSoftware\ExaPHP\Processing\Validation\NotNull;
use ExtendsSoftware\ExaPHP\Processing\Validation\Number\IntegerRange;

$count = new SequentialPipeline();
$count->append(new TrimString());
$count->append(new StringToInteger());
$count->append(new IntegerRange(1, 100));

$shape = new ObjectShape([
    'title' => new Field(new TrimString()),
    'note' => new Field(new NotNull(), FieldPresence::Optional),
    'items' => new Field(new EachItem(
        new ArrayShape(['count' => new Field($count)]),
        CollectionKeys::RequireList,
    )),
]);

$result = $shape->transform((object) [
    'title' => ' Order ',
    'items' => [['count' => ' 42 ']],
]);
// Successful output: a new stdClass with title 'Order' and items [['count' => 42]].
```

These processors are independent of JSON. `ObjectShape` accepts any object and reads initialized, stored public
properties. It ignores private, protected, static, uninitialized, and virtual properties, and bypasses getter hooks and
magic accessors. For computed properties or values exposed through methods, use an explicit application transformer.
The output is always a new `stdClass`, not an instance or clone of the source class. Domain constructors and setters are
not called. `ArrayShape` accepts any PHP array and returns an array, preserving declared keys.

Required fields are the default. Missing required fields produce `CODE_MISSING_FIELD`; missing optional fields are
omitted without running their step. Present null values always run through the configured step. No defaults are inserted.
Uninitialized or inaccessible object properties count as missing.

Unknown fields default to rejection (`CODE_UNKNOWN_FIELD`). Pass `UnknownFields::Preserve` or `UnknownFields::Discard`
from the `Transformation\Shape` namespace as the shape's second argument to change this policy. Preserved fields bypass
processing. Declared fields are processed and emitted in definition order; unknown fields follow in input order.
An empty shape accepts an empty container; unknown-field policy still applies to non-empty input.

`Transformation\Collection\EachItem` accepts arrays and preserves their keys and iteration order. The default
`CollectionKeys::Preserve` accepts associative and sparse arrays. `CollectionKeys::RequireList` rejects arrays whose
keys are not consecutive integers starting at zero with `CODE_NOT_LIST`. Empty arrays succeed. Traversable objects
are not accepted as collections; convert them explicitly if needed.

Shapes collect failures across fields, and collections collect failures across items. Each nested pipeline still stops
at its first unsuccessful step. All violations from a failed field or item are retained. No partial output is exposed.
An execution exception stops processing immediately and propagates unchanged. Step instances are reused between fields,
items, and calls as configured, so application steps must manage any state deliberately.

Each parent prefixes violation paths: `['items', 0, 'count']` identifies the first item's count. Object property segments
are strings (including numeric property names); array segments retain their PHP key types. PHP's normal numeric-string
array-key conversion applies to definitions and array input. `Violation::withPrefix()` creates a new violation retaining
its code, message, and parameters; original violations are not modified.

Input containers are not changed. Output containers are newly constructed, but nested objects and preserved unknown
values are not automatically cloned. Custom steps must honor the input immutability contract.

Wrong container types yield `ObjectShape::CODE_NOT_OBJECT`, `ArrayShape::CODE_NOT_ARRAY`, or `EachItem::CODE_NOT_ARRAY`.
Malformed field definitions throw `Transformation\Exception\InvalidShapeException`; object definition names cannot contain null bytes.
Objects with multiple processing roles are rejected by `Field` and `EachItem` with
`Transformation\Exception\AmbiguousProcessingStepException` before execution. The same rule applies to
`Transformation\StepTransformer`, which adapts one validator, transformer, or pipeline to the transformer interface.
Use that adapter to append a pipeline inside another pipeline:

```php
use ExtendsSoftware\ExaPHP\Processing\Transformation\StepTransformer;

$outer = new SequentialPipeline();
$outer->append(new StepTransformer($count));
```
