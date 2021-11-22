<?php declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer: custom fixers.
 *
 * (c) 2018 Kuba Werłos
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PhpCsFixerCustomFixersDev;

use PhpCsFixer\Fixer\FixerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @implements \IteratorAggregate<FixerInterface>
 *
 * @internal
 */
final class Fixers implements \IteratorAggregate
{
    /**
     * @return \Generator<FixerInterface>
     */
    public function getIterator(): \Generator
    {
        $finder = Finder::create()
            ->files()
            ->in(__DIR__ . '/Fixer/')
            ->sortByName();

        /** @var SplFileInfo $fileInfo */
        foreach ($finder as $fileInfo) {
            $className = __NAMESPACE__ . '\\Fixer\\' . $fileInfo->getBasename('.php');

            /** @var FixerInterface $fixer */
            $fixer = new $className();

            yield $fixer;
        }
    }
}
