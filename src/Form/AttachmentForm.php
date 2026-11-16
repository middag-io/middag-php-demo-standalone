<?php

declare(strict_types=1);

/**
 * middag-io/demo-standalone — standalone proof harness for the MIDDAG OSS stack.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\Demo\Standalone\Form;

use Middag\Framework\Form\AbstractForm;
use Middag\Framework\Form\FieldFactory;
use Middag\Ui\Block\Contract\LayoutElementInterface;
use Middag\Ui\Form\Contract\FieldInterface;

/**
 * The ticket attachment form: one file field. The first submit uploads the
 * file; the form comes back with the stored file as its value, upload token
 * included, and the second submit sends the token back to attach it.
 */
final class AttachmentForm extends AbstractForm
{
    /** Largest accepted file, in bytes. */
    public const int MAX_SIZE = 1024 * 1024;

    /** Accepted extensions and the media type each is served with. */
    public const array TYPES = [
        'txt' => 'text/plain',
        'csv' => 'text/csv',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
    ];

    /** @return array<int, FieldInterface|LayoutElementInterface> */
    public function schema(): array
    {
        return [
            FieldFactory::file('file')->label('File')->required()
                ->accept(array_map(static fn (string $ext): string => '.' . $ext, array_keys(self::TYPES)))
                ->maxSize(self::MAX_SIZE),
        ];
    }
}
