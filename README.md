[![GitHub Workflow Status][ico-tests]][link-tests]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Total Downloads][ico-downloads]][link-downloads]

------

# qr

`cline/qr` is a standalone QR code generation and decoding package. The
repository keeps the Cline project skeleton for tooling and maintenance
while the implementation and test coverage live directly in this package.

## Requirements

> **Requires [PHP 8.4+](https://php.net/releases/)**

## Installation

```bash
composer require cline/qr
```

## Usage

```php
use Cline\Qr\Builder\Builder;
use Cline\Qr\Writer\PngWriter;

$result = new Builder(
    data: 'Hello world',
)
    ->withWriter(new PngWriter())
    ->build();

file_put_contents('qr.png', $result->getString());
```

Public immutable value objects also expose additive `with*` methods when
you want to derive a modified copy without going back through the builder:

```php
use Cline\Qr\Color\Color;
use Cline\Qr\QrCode;

$qrCode = (new QrCode('Hello world'))
    ->withSize(512)
    ->withMargin(4)
    ->withForegroundColor(new Color(12, 34, 56));
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please use the [GitHub security reporting form][link-security] rather than the issue queue.

## Credits

- [Brian Faust][link-maintainer]
- [All Contributors][link-contributors]

## License

The MIT License. Please see [License File](LICENSE.md) for more information.

[ico-tests]: https://github.com/faustbrian/qr/actions/workflows/quality-assurance.yaml/badge.svg
[ico-version]: https://img.shields.io/packagist/v/cline/qr.svg
[ico-license]: https://img.shields.io/badge/License-MIT-green.svg
[ico-downloads]: https://img.shields.io/packagist/dt/cline/qr.svg

[link-tests]: https://github.com/faustbrian/qr/actions
[link-packagist]: https://packagist.org/packages/cline/qr
[link-downloads]: https://packagist.org/packages/cline/qr
[link-security]: https://github.com/faustbrian/qr/security
[link-maintainer]: https://github.com/faustbrian
[link-contributors]: ../../contributors
