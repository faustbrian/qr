<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Decoder\QrReader;
use Cline\Qr\Decoder\Result;

beforeEach(function (): void {
    error_reporting(\E_ALL);
    ini_set('memory_limit', '2G');
});

test('text1', function (): void {
    $image = dirname(__DIR__).'/Decoder/fixtures/qrcodes/hello_world.png';

    $qrcode = new QrReader($image);
    $this->assertSame('Hello world!', $qrcode->text());
});

test('no text', function (): void {
    $image = dirname(__DIR__).'/Decoder/fixtures/qrcodes/empty.png';
    $qrcode = new QrReader($image);
    $this->assertFalse($qrcode->text());
});

test('text2', function (): void {
    $image = dirname(__DIR__).'/Decoder/fixtures/qrcodes/139225861-398ccbbd-2bfd-4736-889b-878c10573888.png';
    $qrcode = new QrReader($image);
    $hints = [
        'TRY_HARDER' => true,
        'NR_ALLOW_SKIP_ROWS' => 0,
    ];
    $qrcode->decode($hints);
    $this->assertNotInstanceOf(Exception::class, $qrcode->getError());
    $this->assertInstanceOf(Result::class, $qrcode->getResult());
    $this->assertEquals('https://www.gosuslugi.ru/covid-cert/verify/9770000014233333?lang=ru&ck=733a9d218d312fe134f1c2cc06e1a800', $qrcode->getResult()->getText());
    $this->assertSame('https://www.gosuslugi.ru/covid-cert/verify/9770000014233333?lang=ru&ck=733a9d218d312fe134f1c2cc06e1a800', $qrcode->text($hints));
});

test('text3', function (): void {
    $image = dirname(__DIR__).'/Decoder/fixtures/qrcodes/test.png';
    $qrcode = new QrReader($image);
    $qrcode->decode([
        'TRY_HARDER' => true,
    ]);
    $this->assertNotInstanceOf(Exception::class, $qrcode->getError());
    $this->assertSame('https://www.gosuslugi.ru/covid-cert/verify/9770000014233333?lang=ru&ck=733a9d218d312fe134f1c2cc06e1a800', $qrcode->text());
});

test('binary', function (): void {
    $image = dirname(__DIR__).'/Decoder/fixtures/qrcodes/binary-test.png';
    $expected = hex2bin(
        '000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f'.
            '202122232425262728292a2b2c2d2e2f303132333435363738393a3b3c3d3e3f'.
            '404142434445464748494a4b4c4d4e4f505152535455565758595a5b5c5d5e5f'.
            '606162636465666768696a6b6c6d6e6f707172737475767778797a7b7c7d7e7f'.
            '808182838485868788898a8b8c8d8e8f909192939495969798999a9b9c9d9e9f'.
            'a0a1a2a3a4a5a6a7a8a9aaabacadaeafb0b1b2b3b4b5b6b7b8b9babbbcbdbebf'.
            'c0c1c2c3c4c5c6c7c8c9cacbcccdcecfd0d1d2d3d4d5d6d7d8d9dadbdcdddedf'.
            'e0e1e2e3e4e5e6e7e8e9eaebecedeeeff0f1f2f3f4f5f6f7f8f9fafbfcfdfeff',
    );

    $qrcode = new QrReader($image);
    $qrcode->decode([
        'BINARY_MODE' => true,
    ]);
    $this->assertNotInstanceOf(Exception::class, $qrcode->getError());
    $result = $qrcode->getResult();
    $this->assertInstanceOf(Result::class, $result);
    $text = $result->getText();
    $this->assertEquals($expected, $text);
});
