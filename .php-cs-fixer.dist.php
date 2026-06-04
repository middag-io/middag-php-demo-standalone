<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

$currentYear = date('Y');
$header = <<<"EOF"
middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.

@author      Michael Meneses <michael@middag.io>
@copyright   {$currentYear} MIDDAG (https://middag.io)
@license     Apache-2.0
EOF;

$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

$cacheDir = __DIR__ . '/.cache';
if (!is_dir($cacheDir) && (!mkdir($cacheDir, 0755, true) && !is_dir($cacheDir))) {
    throw new RuntimeException(sprintf('Directory "%s" was not created', $cacheDir));
}

return (new Config())
    ->setCacheFile($cacheDir . '/php-cs-fixer.cache')
    ->setRiskyAllowed(true)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        '@PSR12' => true,
        '@PhpCsFixer' => true,

        // Emit PHPUnit metadata as attributes, not doc-comments — PHPUnit 11
        // deprecates doc-comment metadata, and `@PhpCsFixer`'s
        // `php_unit_test_class_requires_covers` would otherwise inject a
        // doc-comment `@coversNothing` into every test class.
        'php_unit_attributes' => true,

        'header_comment' => [
            'header' => $header,
            'comment_type' => 'PHPDoc',
            'location' => 'after_declare_strict',
            'separate' => 'both',
        ],

        'class_attributes_separation' => [
            'elements' => [
                'const' => 'one',
                'property' => 'one',
                'method' => 'one',
                'trait_import' => 'one',
                'case' => 'one',
            ],
        ],

        'concat_space' => ['spacing' => 'one'],
        'global_namespace_import' => true,

        'is_null' => false,
        'heredoc_to_nowdoc' => false,
        'multiline_whitespace_before_semicolons' => ['strategy' => 'no_multi_line'],
        'native_function_invocation' => false,
        'no_blank_lines_after_phpdoc' => false,
        'phpdoc_no_package' => false,
        'yoda_style' => false,

        'comment_to_phpdoc' => ['ignored_tags' => ['var']],
        'no_superfluous_phpdoc_tags' => false,
    ])
    ->setFinder($finder);
