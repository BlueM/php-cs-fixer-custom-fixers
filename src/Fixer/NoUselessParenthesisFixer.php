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
use PhpCsFixer\Tokenizer\Analyzer\BlocksAnalyzer;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Tokens;

final class NoUselessParenthesisFixer extends AbstractFixer
{
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'There must be no useless parentheses.',
            [
                new CodeSample('<?php
foo(($bar));
'),
            ]
        );
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound(['(', CT::T_BRACE_CLASS_INSTANTIATION_OPEN]);
    }

    public function isRisky(): bool
    {
        return false;
    }

    public function fix(\SplFileInfo $file, Tokens $tokens): void
    {
        for ($index = 0; $index < $tokens->count(); $index++) {
            if (!$tokens[$index]->equalsAny(['(', [CT::T_BRACE_CLASS_INSTANTIATION_OPEN]])) {
                continue;
            }

            /** @var array{isStart: bool, type: int} $blockType */
            $blockType = Tokens::detectBlockType($tokens[$index]);
            $blockEndIndex = $tokens->findBlockEnd($blockType['type'], $index);

            if (!$this->isBlockToRemove($tokens, $index, $blockEndIndex)) {
                continue;
            }

            $this->clearWhitespace($tokens, $index + 1);
            $this->clearWhitespace($tokens, $blockEndIndex - 1);
            $tokens->clearTokenAndMergeSurroundingWhitespace($index);
            $tokens->clearTokenAndMergeSurroundingWhitespace($blockEndIndex);

            /** @var int $prevIndex */
            $prevIndex = $tokens->getPrevMeaningfulToken($index);

            if ($tokens[$prevIndex]->isGivenKind([\T_RETURN, \T_THROW])) {
                $tokens->ensureWhitespaceAtIndex($prevIndex + 1, 0, ' ');
            }
        }
    }

    private function isBlockToRemove(Tokens $tokens, int $startIndex, int $endIndex): bool
    {
        if ($this->isParenthesisBlockInside($tokens, $startIndex, $endIndex)) {
            return true;
        }

        /** @var int $prevStartIndex */
        $prevStartIndex = $tokens->getPrevMeaningfulToken($startIndex);
        /** @var int $nextEndIndex */
        $nextEndIndex = $tokens->getNextMeaningfulToken($endIndex);

        if ((new BlocksAnalyzer())->isBlock($tokens, $prevStartIndex, $nextEndIndex)) {
            return true;
        }

        if ($tokens[$nextEndIndex]->equalsAny(['(', [CT::T_BRACE_CLASS_INSTANTIATION_OPEN]])) {
            return false;
        }

        if ($this->isForbiddenBeforeOpenParenthesis($tokens, $prevStartIndex)) {
            return false;
        }

        if ($this->isExpressionInside($tokens, $startIndex, $endIndex)) {
            return true;
        }

        return $tokens[$prevStartIndex]->equalsAny(['=', [\T_RETURN], [\T_THROW]]) && $tokens[$nextEndIndex]->equals(';');
    }

    private function isForbiddenBeforeOpenParenthesis(Tokens $tokens, int $index): bool
    {
        if (
            $tokens[$index]->isGivenKind([
                \T_ARRAY,
                \T_CATCH,
                \T_CLASS,
                \T_ELSEIF,
                \T_EMPTY,
                \T_EVAL,
                \T_EXIT,
                \T_FUNCTION,
                \T_IF,
                \T_ISSET,
                \T_LIST,
                \T_STATIC,
                \T_STRING,
                \T_SWITCH,
                \T_UNSET,
                \T_VARIABLE,
                \T_WHILE,
                CT::T_CLASS_CONSTANT,
                CT::T_USE_LAMBDA,
            ])
            || \defined('T_FN') && $tokens[$index]->isGivenKind(\T_FN)
            || \defined('T_MATCH') && $tokens[$index]->isGivenKind(\T_MATCH)
        ) {
            return true;
        }

        /** @var null|array{isStart: bool, type: int} $blockType */
        $blockType = Tokens::detectBlockType($tokens[$index]);

        return $blockType !== null && !$blockType['isStart'];
    }

    private function isParenthesisBlockInside(Tokens $tokens, int $startIndex, int $endIndex): bool
    {
        /** @var int $nextStartIndex */
        $nextStartIndex = $tokens->getNextMeaningfulToken($startIndex);

        return $tokens[$nextStartIndex]->equalsAny(['(', [CT::T_BRACE_CLASS_INSTANTIATION_OPEN]])
            && (new BlocksAnalyzer())->isBlock($tokens, $nextStartIndex, $tokens->getPrevMeaningfulToken($endIndex));
    }

    private function isExpressionInside(Tokens $tokens, int $startIndex, int $endIndex): bool
    {
        $expression = false;

        /** @var int $index */
        $index = $tokens->getNextMeaningfulToken($startIndex);

        while ($index < $endIndex) {
            $expression = true;

            if (
                !$tokens[$index]->isGivenKind([
                    \T_CONSTANT_ENCAPSED_STRING,
                    \T_DNUMBER,
                    \T_DOUBLE_COLON,
                    \T_LNUMBER,
                    \T_OBJECT_OPERATOR,
                    \T_STRING,
                    \T_VARIABLE,
                ]) && !$tokens[$index]->isMagicConstant()
            ) {
                return false;
            }

            /** @var int $index */
            $index = $tokens->getNextMeaningfulToken($index);
        }

        return $expression;
    }

    private function clearWhitespace(Tokens $tokens, int $index): void
    {
        if (!$tokens[$index]->isWhitespace()) {
            return;
        }

        /** @var int $prevIndex */
        $prevIndex = $tokens->getNonEmptySibling($index, -1);

        if ($tokens[$prevIndex]->isComment()) {
            $tokens->ensureWhitespaceAtIndex($index, 0, \rtrim($tokens[$index]->getContent(), " \t"));

            return;
        }

        $tokens->clearAt($index);
    }
}
