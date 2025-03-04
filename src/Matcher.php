<?php

declare(strict_types=1);

namespace ZxcvbnPhp;

use ZxcvbnPhp\Matchers\BaseMatch;
use ZxcvbnPhp\Matchers\MatchInterface;

class Matcher
{
    private const DEFAULT_MATCHERS = [
        Matchers\DateMatch::class,
        Matchers\DictionaryMatch::class,
        Matchers\ReverseDictionaryMatch::class,
        Matchers\L33tMatch::class,
        Matchers\RepeatMatch::class,
        Matchers\SequenceMatch::class,
        Matchers\SpatialMatch::class,
        Matchers\YearMatch::class,
    ];

    /**
     * @var array<class-string, BaseMatch>
     */
    private array $additionalMatchers = [];

    /**
     * Get matches for a password.
     *
     * @param string $password  Password string to match
     * @param array<int, mixed> $userInputs Array of values related to the user (optional)
     *
     * @code array('Alice Smith')
     *
     * @endcode
     *
     * @return array<int, BaseMatch> Array of Match objects.
     *
     * @see  zxcvbn/src/matching.coffee::omnimatch
     */
    public function getMatches(string $password, array $userInputs = []): array
    {
        $matches = [];
        /** @var MatchInterface $matcher */
        foreach ($this->getMatchers() as $matcher) {
            $matched = $matcher::match($password, $userInputs);
            if ($matched !== []) {
                $matches[] = $matched;
            }
        }

        $matches = array_merge([], ...$matches);
        self::usortStable($matches, $this->compareMatches(...));

        return $matches;
    }

    /**
     * @param class-string $className
     *
     * @throws \InvalidArgumentException
     */
    public function addMatcher(string $className): self
    {
        if (! is_a($className, BaseMatch::class, true)) {
            throw new \InvalidArgumentException(sprintf('Matcher class must extend %s', BaseMatch::class));
        }

        // @phpstan-ignore-next-line
        $this->additionalMatchers[$className] = $className;

        return $this;
    }

    /**
     * A stable implementation of usort().
     *
     * Whether or not the sort() function in JavaScript is stable or not is implementation-defined.
     * This means it's impossible for us to match all browsers exactly, but since most browsers implement sort() using
     * a stable sorting algorithm, we'll get the highest rate of accuracy by using a stable sort in our code as well.
     *
     * This function taken from https://github.com/vanderlee/PHP-stable-sort-functions
     * Copyright © 2015-2018 Martijn van der Lee (http://martijn.vanderlee.com). MIT License applies.
     *
     * @param array<int, mixed> $array
     */
    public static function usortStable(array &$array, callable $value_compare_func): bool
    {
        $index = 0;
        foreach ($array as &$item) {
            $item = [$index++, $item];
        }
        $result = usort($array, static function ($a, $b) use ($value_compare_func) {
            $result = $value_compare_func($a[1], $b[1]);
            return $result === 0 ? $a[0] - $b[0] : $result;
        });
        foreach ($array as &$item) {
            $item = $item[1];
        }
        return $result;
    }

    public static function compareMatches(BaseMatch $a, BaseMatch $b): int
    {
        $beginDiff = $a->begin - $b->begin;
        if ($beginDiff) {
            return $beginDiff;
        }
        return $a->end - $b->end;
    }

    /**
     * Load available Match objects to match against a password.
     *
     * @return array<int, BaseMatch> Array of classes extending BaseMatch
     */
    protected function getMatchers(): array
    {
        /** @var array<int, BaseMatch> $additionalMatchers */
        $additionalMatchers = array_values($this->additionalMatchers);

        // @phpstan-ignore-next-line
        return array_merge(
            self::DEFAULT_MATCHERS,
            $additionalMatchers
        );
    }
}
