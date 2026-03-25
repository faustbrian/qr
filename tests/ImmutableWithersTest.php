<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Color\Color;
use Cline\Qr\Encoding\Encoding;
use Cline\Qr\ErrorCorrectionLevel;
use Cline\Qr\Label\Font\Font;
use Cline\Qr\Label\Font\OpenSans;
use Cline\Qr\Label\Label;
use Cline\Qr\Label\LabelAlignment;
use Cline\Qr\Label\Margin\Margin;
use Cline\Qr\Logo\Logo;
use Cline\Qr\QrCode;
use Cline\Qr\RoundBlockSizeMode;

it('creates modified qr code copies through withers', function (): void {
    $foreground = new Color(10, 20, 30);
    $background = new Color(200, 210, 220);
    $qrCode = new QrCode('Initial');

    $modified = $qrCode
        ->withData('Updated')
        ->withEncoding(
            new Encoding('ISO-8859-1'),
        )
        ->withErrorCorrectionLevel(ErrorCorrectionLevel::High)
        ->withSize(512)
        ->withMargin(4)
        ->withRoundBlockSizeMode(RoundBlockSizeMode::Shrink)
        ->withForegroundColor($foreground)
        ->withBackgroundColor($background);

    expect($modified)->not->toBe($qrCode)
        ->and($qrCode->getData())->toBe('Initial')
        ->and((string) $qrCode->getEncoding())->toBe('UTF-8')
        ->and($qrCode->getErrorCorrectionLevel())->toBe(ErrorCorrectionLevel::Low)
        ->and($qrCode->getSize())->toBe(300)
        ->and($qrCode->getMargin())->toBe(10)
        ->and($qrCode->getRoundBlockSizeMode())->toBe(RoundBlockSizeMode::Margin)
        ->and($modified->getData())->toBe('Updated')
        ->and((string) $modified->getEncoding())->toBe('ISO-8859-1')
        ->and($modified->getErrorCorrectionLevel())->toBe(ErrorCorrectionLevel::High)
        ->and($modified->getSize())->toBe(512)
        ->and($modified->getMargin())->toBe(4)
        ->and($modified->getRoundBlockSizeMode())->toBe(RoundBlockSizeMode::Shrink)
        ->and($modified->getForegroundColor())->toBe($foreground)
        ->and($modified->getBackgroundColor())->toBe($background);
});

it('creates modified logo copies through withers', function (): void {
    $logo = new Logo(__DIR__.'/assets/bender.png');

    $modified = $logo
        ->withPath(__DIR__.'/assets/bender.svg')
        ->withResizeToWidth(120)
        ->withResizeToHeight(80)
        ->withPunchoutBackground(true);

    expect($modified)->not->toBe($logo)
        ->and($logo->getPath())->toBe(__DIR__.'/assets/bender.png')
        ->and($logo->getResizeToWidth())->toBeNull()
        ->and($logo->getResizeToHeight())->toBeNull()
        ->and($logo->getPunchoutBackground())->toBeFalse()
        ->and($modified->getPath())->toBe(__DIR__.'/assets/bender.svg')
        ->and($modified->getResizeToWidth())->toBe(120)
        ->and($modified->getResizeToHeight())->toBe(80)
        ->and($modified->getPunchoutBackground())->toBeTrue();
});

it('creates modified label copies through withers', function (): void {
    $font = new Font(__DIR__.'/../assets/open_sans.ttf', 14);
    $margin = new Margin(1, 2, 3, 4);
    $textColor = new Color(5, 6, 7);
    $label = new Label('Initial');

    $modified = $label
        ->withText('Updated')
        ->withFont($font)
        ->withAlignment(LabelAlignment::Left)
        ->withMargin($margin)
        ->withTextColor($textColor);

    expect($modified)->not->toBe($label)
        ->and($label->getText())->toBe('Initial')
        ->and($label->getAlignment())->toBe(LabelAlignment::Center)
        ->and($modified->getText())->toBe('Updated')
        ->and($modified->getFont())->toBe($font)
        ->and($modified->getAlignment())->toBe(LabelAlignment::Left)
        ->and($modified->getMargin())->toBe($margin)
        ->and($modified->getTextColor())->toBe($textColor);
});

it('creates modified font copies through withers', function (): void {
    $font = new Font(__DIR__.'/../assets/open_sans.ttf', 16);

    $modified = $font
        ->withPath(__DIR__.'/../assets/open_sans.ttf')
        ->withSize(24);

    expect($modified)->not->toBe($font)
        ->and($font->getSize())->toBe(16)
        ->and($modified->getPath())->toBe(__DIR__.'/../assets/open_sans.ttf')
        ->and($modified->getSize())->toBe(24);
});

it('creates modified open sans copies through withers', function (): void {
    $font = new OpenSans();

    $modified = $font->withSize(22);

    expect($modified)->not->toBe($font)
        ->and($font->getSize())->toBe(16)
        ->and($modified->getSize())->toBe(22)
        ->and($modified->getPath())->toBe($font->getPath());
});

it('creates modified margin copies through withers', function (): void {
    $margin = new Margin(1, 2, 3, 4);

    $modified = $margin
        ->withTop(10)
        ->withRight(20)
        ->withBottom(30)
        ->withLeft(40);

    expect($modified)->not->toBe($margin)
        ->and($margin->toArray())->toBe([
            'top' => 1,
            'right' => 2,
            'bottom' => 3,
            'left' => 4,
        ])
        ->and($modified->toArray())->toBe([
            'top' => 10,
            'right' => 20,
            'bottom' => 30,
            'left' => 40,
        ]);
});

it('creates modified color copies through withers', function (): void {
    $color = new Color(1, 2, 3, 4);

    $modified = $color
        ->withRed(10)
        ->withGreen(20)
        ->withBlue(30)
        ->withAlpha(40);

    expect($modified)->not->toBe($color)
        ->and($color->toArray())->toBe([
            'red' => 1,
            'green' => 2,
            'blue' => 3,
            'alpha' => 4,
        ])
        ->and($modified->toArray())->toBe([
            'red' => 10,
            'green' => 20,
            'blue' => 30,
            'alpha' => 40,
        ]);
});

it('creates modified encoding copies through withers', function (): void {
    $encoding = new Encoding('UTF-8');

    $modified = $encoding->withValue('ISO-8859-1');

    expect($modified)->not->toBe($encoding)
        ->and((string) $encoding)->toBe('UTF-8')
        ->and((string) $modified)->toBe('ISO-8859-1');
});
