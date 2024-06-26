<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ClassNotation\VisibilityRequiredFixer;
use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocSeparationFixer;
use SlevomatCodingStandard\Sniffs\Commenting\InlineDocCommentDeclarationSniff;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $config): void {
    $config->paths([
        'src',
        'spec',
        'tests/Behat',
        'tests/Functional',
        'tests/Service',
        'tests/Unit',
    ]);

    $config->import('vendor/sylius-labs/coding-standard/ecs.php');
    $config->ruleWithConfiguration(PhpdocSeparationFixer::class, ['groups' => [['ORM\\*'], ['Given', 'When', 'Then']]]);
    $config->ruleWithConfiguration(
        HeaderCommentFixer::class,
        [
            'location' => 'after_open',
            'comment_type' => HeaderCommentFixer::HEADER_COMMENT,
            'header' => <<<TEXT
This file is part of the Sylius package.

(c) Sylius Sp. z o.o.

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
TEXT
        ]
    );

    $config->skip([
        VisibilityRequiredFixer::class => ['*Spec.php'],
        InlineDocCommentDeclarationSniff::class . '.MissingVariable',
    ]);
};
