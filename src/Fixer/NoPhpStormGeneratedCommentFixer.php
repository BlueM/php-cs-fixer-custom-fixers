<?php declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer: custom fixers.
 *
 * (c) 2018 Kuba Werłos
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace PhpCsFixerCustomFixers\Fixer;

use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Preg;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixerCustomFixers\TokenRemover;

final class NoPhpStormGeneratedCommentFixer extends AbstractFixer
{
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'There must be no comments generated by PhpStorm.',
            [new CodeSample('<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 01.01.70
 * Time: 12:00
 */
namespace Foo;
')],
            '',
        );
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound([\T_COMMENT, \T_DOC_COMMENT]);
    }

    public function isRisky(): bool
    {
        return false;
    }

    public function fix(\SplFileInfo $file, Tokens $tokens): void
    {
        for ($index = $tokens->count() - 1; $index > 0; $index--) {
            if (!$tokens[$index]->isGivenKind([\T_COMMENT, \T_DOC_COMMENT])) {
                continue;
            }

            if (!Preg::match('/\\*\\h+Created by PhpStorm/i', $tokens[$index]->getContent(), $matches)) {
                continue;
            }

            TokenRemover::removeWithLinesIfPossible($tokens, $index);
        }
    }
}
