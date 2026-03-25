<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common\Reedsolomon;

use Cline\Qr\Decoder\InvalidArgumentException;
use Cline\Qr\Decoder\RuntimeException;

use function dechex;
use function fill_array;

/**
 * Represents the finite field used by the Reed-Solomon encoder and decoder.
 *
 * The QR implementation uses preconfigured GF(256) instances and a handful of
 * other decoder field definitions. This class owns the exponent/log tables and the
 * cached zero/one polynomials used by the polynomial arithmetic layer.
 *
 * @author David Olivier
 * @author Sean Owen
 */
final class GenericGF
{
    public static $AZTEC_DATA_12;

    public static $AZTEC_DATA_10;

    public static $AZTEC_DATA_6;

    public static $AZTEC_PARAM;

    public static $QR_CODE_FIELD_256;

    public static $DATA_MATRIX_FIELD_256;

    public static $AZTEC_DATA_8;

    public static $MAXICODE_FIELD_64;

    private array $expTable = [];

    private array $logTable = [];

    private readonly GenericGFPoly $zero;

    private readonly GenericGFPoly $one;

    /**
     * Creates a field representation from the primitive polynomial and field size.
     *
     * @param int $primitive     irreducible polynomial encoded as bits
     * @param int $size          field size, typically a power of two
     * @param int $generatorBase generator polynomial offset used by the codec
     */
    public function __construct(
        private readonly int $primitive,
        private readonly int $size,
        private readonly int $generatorBase,
    ) {
        $x = 1;

        for ($i = 0; $i < $size; ++$i) {
            $this->expTable[$i] = $x;
            $x *= 2; // we're assuming the generator alpha is 2

            if ($x < $size) {
                continue;
            }

            $x ^= $primitive;
            $x &= $size - 1;
        }

        for ($i = 0; $i < $size - 1; ++$i) {
            $this->logTable[$this->expTable[$i]] = $i;
        }
        // logTable[0] == 0 but this should never be used
        $this->zero = new GenericGFPoly($this, [0]);
        $this->one = new GenericGFPoly($this, [1]);
    }

    /**
     * Initializes the preconfigured Reed-Solomon fields used by the decoder.
     *
     * This is called once at load time so the static field instances are ready
     * before any codewords need to be corrected.
     */
    public static function Init(): void
    {
        self::$AZTEC_DATA_12 = new self(0x10_69, 4_096, 1); // x^12 + x^6 + x^5 + x^3 + 1
        self::$AZTEC_DATA_10 = new self(0x4_09, 1_024, 1); // x^10 + x^3 + 1
        self::$AZTEC_DATA_6 = new self(0x43, 64, 1); // x^6 + x + 1
        self::$AZTEC_PARAM = new self(0x13, 16, 1); // x^4 + x + 1
        self::$QR_CODE_FIELD_256 = new self(0x01_1D, 256, 0); // x^8 + x^4 + x^3 + x^2 + 1
        self::$DATA_MATRIX_FIELD_256 = new self(0x01_2D, 256, 1); // x^8 + x^5 + x^3 + x^2 + 1
        self::$AZTEC_DATA_8 = self::$DATA_MATRIX_FIELD_256;
        self::$MAXICODE_FIELD_64 = self::$AZTEC_DATA_6;
    }

    /**
     * Adds or subtracts two field elements.
     *
     * In characteristic-two fields, addition and subtraction are the same XOR
     * operation, so this helper intentionally serves both roles.
     *
     * @return float|int sum or difference of the operands
     */
    public static function addOrSubtract(int $a, int|float|null $b)
    {
        return $a ^ $b;
    }

    public function getZero(): GenericGFPoly
    {
        return $this->zero;
    }

    public function getOne(): GenericGFPoly
    {
        return $this->one;
    }

    /**
     * Builds a monomial in this field.
     *
     * @param  mixed         $degree
     * @return GenericGFPoly polynomial representing coefficient * x^degree
     */
    public function buildMonomial($degree, int $coefficient)
    {
        if ($degree < 0) {
            throw InvalidArgumentException::withMessage();
        }

        if ($coefficient === 0) {
            return $this->zero;
        }
        $coefficients = fill_array(0, $degree + 1, 0); // new int[degree + 1];
        $coefficients[0] = $coefficient;

        return new GenericGFPoly($this, $coefficients);
    }

    /**
     * Returns alpha to the requested power.
     * @param mixed $a
     */
    public function exp($a)
    {
        return $this->expTable[$a];
    }

    /**
     * Returns the base-2 logarithm in this field.
     */
    public function log(float|int|null $a)
    {
        if ($a === 0) {
            throw InvalidArgumentException::withMessage();
        }

        return $this->logTable[$a];
    }

    /**
     * Returns the multiplicative inverse of the given element.
     * @param mixed $a
     */
    public function inverse($a)
    {
        if ($a === 0) {
            throw RuntimeException::withMessage();
        }

        return $this->expTable[$this->size - $this->logTable[$a] - 1];
    }

    /**
     * Multiplies two field elements.
     *
     * @return int product of the operands in this field
     */
    public function multiply(int|float|null $a, int|float|null $b)
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        return $this->expTable[($this->logTable[$a] + $this->logTable[$b]) % ($this->size - 1)];
    }

    /**
     * Returns the field size.
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * Returns the generator base used by this field.
     */
    public function getGeneratorBase()
    {
        return $this->generatorBase;
    }

    /**
     * @Override
     */
    public function toString(): string
    {
        return 'GF(0x'.dechex((int) $this->primitive).','.$this->size.')';
    }
}

GenericGF::Init();
