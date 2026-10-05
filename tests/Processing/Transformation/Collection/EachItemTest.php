<?php

declare(strict_types=1);

namespace ExtendsSoftware\ExaPHP\Tests\Processing\Transformation\Collection;

use ExtendsSoftware\ExaPHP\Processing\Transformation\Collection\CollectionKeys;
use ExtendsSoftware\ExaPHP\Processing\Transformation\Collection\EachItem;
use ExtendsSoftware\ExaPHP\Processing\Transformation\String\TrimString;
use ExtendsSoftware\ExaPHP\Processing\Validation\NotNull;
use PHPUnit\Framework\TestCase;

final class EachItemTest extends TestCase
{
    public function testPreservesKeysAndOrder(): void
    {
        $processor = new EachItem(new TrimString());
        $this->assertSame(['b' => 'x', 4 => 'y'], $processor->transform(['b' => ' x ', 4 => ' y '])->value());
        $this->assertSame([], $processor->transform([])->value());
    }

    public function testRequiresListOnlyWhenConfigured(): void
    {
        $processor = new EachItem(new NotNull(), CollectionKeys::RequireList);
        $this->assertTrue($processor->transform([])->isValid());
        $this->assertSame([1, 2], $processor->transform([1, 2])->value());
        $this->assertSame(EachItem::CODE_NOT_LIST, $processor->transform([1 => 2])->violations()[0]->code());
        $this->assertSame(EachItem::CODE_NOT_ARRAY, $processor->transform(null)->violations()[0]->code());
    }

    public function testCollectsAllFailuresWithoutKeepingPreviousState(): void
    {
        $processor = new EachItem(new NotNull());
        $result = $processor->transform(['a' => null, 3 => null]);
        $this->assertCount(2, $result->violations());
        $this->assertSame(['a'], $result->violations()[0]->path());
        $this->assertSame([3], $result->violations()[1]->path());
        $this->assertTrue($processor->transform([1])->isValid());
    }
}
