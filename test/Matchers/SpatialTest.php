<?php

declare(strict_types=1);

namespace ZxcvbnPhp\Test\Matchers;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use ZxcvbnPhp\Matchers\SpatialMatch;
use ZxcvbnPhp\Math\Binomial;

#[\PHPUnit\Framework\Attributes\CoversClass(\ZxcvbnPhp\Matchers\SpatialMatch::class)]
class SpatialTest extends AbstractMatchTest
{
    /**
     * @return Iterator<int, mixed>
     */
    public static function shortPatternDataProvider(): Iterator
    {
        yield [''];
        yield ['/'];
        yield ['qw'];
        yield ['*/'];
    }

    #[DataProvider('shortPatternDataProvider')]
    public function testShortPatterns(string $password): void
    {
        $this->assertSame(
            [],
            SpatialMatch::match($password),
            "doesn't match 1- and 2-character spatial patterns"
        );
    }

    public function testNoPattern(): void
    {
        $this->assertSame(
            [],
            SpatialMatch::match('qzpm'),
            "doesn't match non-pattern"
        );
    }

    public function testSurroundedPattern(): void
    {
        $pattern = '6tfGHJ';
        $password = "rz!{$pattern}%z";

        // for testing, make a subgraph that contains a single keyboard
        $graphs = ['qwerty' => SpatialMatch::getAdjacencyGraphs()['qwerty']];

        $this->checkMatches(
            'matches against spatial patterns surrounded by non-spatial patterns',
            SpatialMatch::match($password, [], $graphs),
            'spatial',
            [$pattern],
            [[3, 8]],
            [
                'graph' => ['qwerty'],
                'turns' => [2],
                'shiftedCount' => [3],
            ]
        );
    }

    /**
     * @return Iterator<int, mixed>
     */
    public static function spatialDataProvider(): Iterator
    {
        yield ['12345',        'qwerty',     1, 0];
        yield ['@WSX',         'qwerty',     1, 4];
        yield ['6tfGHJ',       'qwerty',     2, 3];
        yield ['hGFd',         'qwerty',     1, 2];
        yield ['/;p09876yhn',  'qwerty',     3, 0];
        yield ['Xdr%',         'qwerty',     1, 2];
        yield ['159-',         'keypad',     1, 0];
        yield ['*84',          'keypad',     1, 0];
        yield ['/8520',        'keypad',     1, 0];
        yield ['369',          'keypad',     1, 0];
        yield ['/963.',        'mac_keypad', 1, 0];
        yield ['*-632.0214',   'mac_keypad', 9, 0];
        yield ['aoEP%yIxkjq:', 'dvorak',     4, 5];
        yield [';qoaOQ:Aoq;a', 'dvorak',    11, 4];
    }

    #[DataProvider('spatialDataProvider')]
    public function testSpatialPatterns(string $password, string $keyboard, int $turns, int $shifts): void
    {
        $graphs = [$keyboard => SpatialMatch::getAdjacencyGraphs()[$keyboard]];

        $this->checkMatches(
            "matches '{$password}' as a {$keyboard} pattern",
            SpatialMatch::match($password, [], $graphs),
            'spatial',
            [$password],
            [[0, strlen($password) - 1]],
            [
                'graph' => [$keyboard],
                'turns' => [$turns],
                'shiftedCount' => [$shifts],
            ]
        );
    }

    public function testShiftedCountForMultipleMatches(): void
    {
        $password = '!QAZ1qaz';
        $this->checkMatches(
            'shifted count is correct for two matches in a row',
            SpatialMatch::match($password),
            'spatial',
            ['!QAZ', '1qaz'],
            [[0, 3], [4, 7]],
            [
                'graph' => ['qwerty', 'qwerty'],
                'turns' => [1, 1],
                'shiftedCount' => [4, 0],
            ]
        );
    }

    public function testGuessesBasic(): void
    {
        $token = 'zxcvbn';
        $match = new SpatialMatch($token, 0, strlen($token) - 1, $token, [
            'graph' => 'qwerty',
            'turns' => 1,
            'shifted_count' => 0,
        ]);

        $this->assertSame(
            $this->getBaseGuessCount($token),
            $match->getGuesses(),
            'with no turns or shifts, guesses is starts * degree * (len-1)'
        );
    }

    public function testGuessesShifted(): void
    {
        $token = 'ZxCvbn';
        $match = new SpatialMatch($token, 0, strlen($token) - 1, $token, [
            'graph' => 'qwerty',
            'turns' => 1,
            'shifted_count' => 2,
        ]);

        $this->assertSame(
            $this->getBaseGuessCount($token) * (Binomial::binom(6, 2) + Binomial::binom(6, 1)),
            $match->getGuesses(),
            'guesses is added for shifted keys, similar to capitals in dictionary matching'
        );
    }

    public function testGuessesEverythingShifted(): void
    {
        $token = 'ZXCVBN';
        $match = new SpatialMatch($token, 0, strlen($token) - 1, $token, [
            'graph' => 'qwerty',
            'turns' => 1,
            'shifted_count' => 6,
        ]);

        $this->assertSame(
            $this->getBaseGuessCount($token) * 2,
            $match->getGuesses(),
            'when everything is shifted, guesses are double'
        );
    }

    /**
     * @return Iterator<int, mixed>
     */
    public static function complexGuessProvider(): Iterator
    {
        yield ['6yhgf',        2, 19596];
        yield ['asde3w',       3, 203315];
        yield ['zxcft6yh',     3, 558460];
        yield ['xcvgy7uj',     3, 558460];
        yield ['ertghjm,.',    5, 30160744];
        yield ['qwerfdsazxcv', 5, 175281377];
    }

    #[DataProvider('complexGuessProvider')]
    public function testGuessesComplexCase(string $token, int $turns, float $expected): void
    {
        $match = new SpatialMatch($token, 0, strlen($token) - 1, $token, [
            'graph' => 'qwerty',
            'turns' => $turns,
            'shifted_count' => 0,
        ]);

        $actual = $match->getGuesses();

        $this->assertEqualsWithDelta(
            $expected,
            $actual,
            1.0,
            'spatial guesses accounts for turn positions, directions and starting keys'
        );
    }

    public function testFeedbackStraightLine(): void
    {
        $token = 'dfghjk';
        $match = new SpatialMatch($token, 0, strlen($token) - 1, $token, [
            'graph' => 'qwerty',
            'turns' => 1,
            'shifted_count' => 0,
        ]);
        $feedback = $match->getFeedback(true);

        $this->assertSame(
            'Straight rows of keys are easy to guess',
            $feedback['warning'],
            'spatial match in straight line gives correct warning'
        );
        $this->assertContains(
            'Use a longer keyboard pattern with more turns',
            $feedback['suggestions'],
            'spatial match in straight line gives correct suggestion'
        );
    }

    public function testFeedbackWithTurns(): void
    {
        $token = 'xcvgy789';
        $match = new SpatialMatch($token, 0, strlen($token) - 1, $token, [
            'graph' => 'qwerty',
            'turns' => 3,
            'shifted_count' => 0,
        ]);
        $feedback = $match->getFeedback(true);

        $this->assertSame(
            'Short keyboard patterns are easy to guess',
            $feedback['warning'],
            'spatial match with turns gives correct warning'
        );
        $this->assertContains(
            'Use a longer keyboard pattern with more turns',
            $feedback['suggestions'],
            'spatial match with turns gives correct suggestion'
        );
    }

    protected function getBaseGuessCount(string $token): float
    {
        // KEYBOARD_STARTING_POSITIONS * KEYBOARD_AVERAGE_DEGREE * (length - 1)
        // - 1 term because: not counting spatial patterns of length 1
        // eg for length==6, multiplier is 5 for needing to try len2,len3,..,len6
        return SpatialMatch::KEYBOARD_STARTING_POSITION
            * SpatialMatch::KEYBOARD_AVERAGE_DEGREES
            * (strlen($token) - 1);
    }
}
