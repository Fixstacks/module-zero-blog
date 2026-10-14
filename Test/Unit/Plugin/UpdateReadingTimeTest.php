<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Blog\Test\Unit\Plugin;

use PHPUnit\Framework\TestCase;
use Zero\Blog\Plugin\UpdateReadingTime;

class UpdateReadingTimeTest extends TestCase
{
    public function testCountsTheTextsOfEveryComponent(): void
    {
        $words = static fn (int $n) => implode(' ', array_fill(0, $n, 'word'));
        $content = ['components' => [
            ['uid' => 'a1', 'type' => 'article', 'data' => ['body' => $words(400)]],
            ['uid' => 'b2', 'type' => 'figure', 'data' => ['image' => ['path' => '/x.jpg', 'alt' => $words(10)], 'caption' => $words(30)]],
            ['uid' => 'c3', 'type' => 'html', 'data' => ['html' => '<p>' . $words(220) . '</p>']],
        ]];
        $this->assertSame(3, UpdateReadingTime::minutes($content)); // 660 words
        $this->assertSame(1, UpdateReadingTime::minutes(['components' => []]));
    }
}
