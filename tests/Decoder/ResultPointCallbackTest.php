<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Decoder\Qrcode\Detector\AlignmentPattern;
use Cline\Qr\Decoder\QrReader;

it('decodes successfully with a result point callback hint', function (): void {
    $callback = new class()
    {
        /** @var array<object> */
        public array $points = [];

        public function foundPossibleResultPoint(object $point): void
        {
            $this->points[] = $point;
        }
    };

    $reader = new QrReader(dirname(__DIR__).'/Decoder/fixtures/qrcodes/hello_world.png');

    $result = $reader->text([
        'NEED_RESULT_POINT_CALLBACK' => $callback,
    ]);

    expect($result)->toBe('Hello world!')
        ->and($callback->points)->not->toBeEmpty();
});

it('reports alignment pattern points to the result point callback', function (): void {
    $callback = new class()
    {
        /** @var array<object> */
        public array $points = [];

        public function foundPossibleResultPoint(object $point): void
        {
            $this->points[] = $point;
        }
    };

    $reader = new QrReader(dirname(__DIR__).'/Decoder/fixtures/qrcodes/139225861-398ccbbd-2bfd-4736-889b-878c10573888.png');

    $result = $reader->text([
        'TRY_HARDER' => true,
        'NR_ALLOW_SKIP_ROWS' => 0,
        'NEED_RESULT_POINT_CALLBACK' => $callback,
    ]);

    expect($result)->toBe(
        'https://www.gosuslugi.ru/covid-cert/verify/9770000014233333?lang=ru&ck=733a9d218d312fe134f1c2cc06e1a800',
    );

    expect(
        array_any(
            $callback->points,
            static fn (object $point): bool => $point instanceof AlignmentPattern,
        ),
    )->toBeTrue();
});
