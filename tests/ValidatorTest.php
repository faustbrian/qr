<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Qr\Builder\Builder;
use Cline\Qr\Writer\Result\PngResult;

it('writes readable validated PNG results', function (string $data): void {
    $builder = new Builder(
        validateResult: true,
        data: $data,
    );

    $result = $builder->build();

    expect($result)->toBeInstanceOf(PngResult::class)
        ->and($result->getMimeType())->toBe('image/png');
})->with([
    'small data' => 'Tiny',
    'data containing spaces' => 'This one has spaces',
    'a large random character string' => 'd2llMS9uU01BVmlvalM2YU9BUFBPTTdQMmJabHpqdndt',
    'a URL containing query parameters' => 'https://this.is.an/url?with=query&string=attached',
    'a long number' => '11111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111111',
    'serialized data' => '{"i":"serialized.data","v":1,"t":1,"d":"4AEPc9XuIQ0OjsZoSRWp9DRWlN6UyDvuMlyOYy8XjOw="}',
    'special characters' => 'Spëci&al ch@ract3rs',
    'chinese characters' => '有限公司',
]);
