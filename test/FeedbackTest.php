<?php

declare(strict_types=1);

namespace ZxcvbnPhp\Test;

use PHPUnit\Framework\TestCase;
use ZxcvbnPhp\Feedback;
use ZxcvbnPhp\Matchers\Bruteforce;
use ZxcvbnPhp\Matchers\DateMatch;
use ZxcvbnPhp\Matchers\SequenceMatch;

class FeedbackTest extends TestCase
{
    private Feedback $feedback;

    public function setUp(): void
    {
        $this->feedback = new Feedback();
    }

    public function testFeedbackForEmptyPassword(): void
    {
        $feedback = $this->feedback->getFeedback(0, []);

        $this->assertSame('', $feedback['warning'], 'default warning');
        $this->assertContains(
            'Use a few words, avoid common phrases',
            $feedback['suggestions'],
            'default suggestion #1'
        );
        $this->assertContains(
            'No need for symbols, digits, or uppercase letters',
            $feedback['suggestions'],
            'default suggestion #1'
        );
    }

    public function testHighScoringSequence(): void
    {
        $match = new Bruteforce('a', 0, 1, 'a');
        $feedback = $this->feedback->getFeedback(3, [$match]);

        $this->assertSame('', $feedback['warning'], 'no warning for good score');
        $this->assertEmpty($feedback['suggestions'], 'no suggestions for good score');
    }

    public function testLongestMatchGetsFeedback(): void
    {
        $match1 = new SequenceMatch('abcd26-01-1991', 0, 4, 'abcd');
        $match2 = new DateMatch('abcd26-01-1991', 4, 14, '26-01-1991', [
            'day' => 26,
            'month' => 1,
            'year' => 1991,
            'separator' => '-',
        ]);
        $feedback = $this->feedback->getFeedback(1, [$match1, $match2]);

        $this->assertSame(
            'Dates are often easy to guess',
            $feedback['warning'],
            'warning provided for the longest match'
        );
        $this->assertContains(
            'Avoid dates and years that are associated with you',
            $feedback['suggestions'],
            'suggestion provided for the longest match'
        );
        $this->assertNotContains(
            'Avoid sequences',
            $feedback['suggestions'],
            'no suggestion provided for the shorter match'
        );
    }

    public function testDefaultSuggestion(): void
    {
        $match = new DateMatch('26-01-1991', 0, 10, '26-01-1991', [
            'day' => 26,
            'month' => 1,
            'year' => 1991,
            'separator' => '-',
        ]);
        $feedback = $this->feedback->getFeedback(1, [$match]);

        $this->assertContains(
            'Add another word or two. Uncommon words are better.',
            $feedback['suggestions'],
            'default suggestion provided'
        );
        $this->assertCount(2, $feedback['suggestions'], "default suggestion doesn\'t override existing suggestion");
    }

    public function testBruteforceFeedback(): void
    {
        $match = new Bruteforce('qkcriv', 0, 6, 'qkcriv');
        $feedback = $this->feedback->getFeedback(1, [$match]);

        $this->assertSame('', $feedback['warning'], 'bruteforce match has no warning');
        $this->assertSame(
            ['Add another word or two. Uncommon words are better.'],
            $feedback['suggestions'],
            'bruteforce match only has the default suggestion'
        );
    }
}
