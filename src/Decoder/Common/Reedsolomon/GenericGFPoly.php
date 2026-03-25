<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common\Reedsolomon;

use InvalidArgumentException;

use function arraycopy;
use function count;
use function fill_array;
use function is_countable;
use function is_int;
use function mb_strlen;

/**
 * Immutable polynomial over a finite field.
 *
 * The Reed-Solomon decoder builds, divides, and evaluates these polynomials as
 * part of syndrome analysis and error correction. Coefficients are normalized so
 * the leading term is always non-zero unless the polynomial is the zero polynomial.
 *
 * @author Sean Owen
 */
final class GenericGFPoly
{
    /** @var null|array<float>|array<int> */
    private $coefficients;

    /**
     * @param GenericGF $field        field used for arithmetic
     * @param array     $coefficients coefficients ordered from highest degree to constant term
     *
     * @throws InvalidArgumentException when the coefficient list is empty
     *                                  or the polynomial is malformed
     */
    public function __construct(
        private readonly GenericGF $field,
        $coefficients,
    ) {
        if (count($coefficients) === 0) {
            throw \Cline\Qr\Decoder\InvalidArgumentException::withMessage();
        }
        $coefficientsLength = count($coefficients);

        if ($coefficientsLength > 1 && $coefficients[0] === 0) {
            // Leading term must be non-zero for anything except the constant polynomial "0"
            $firstNonZero = 1;

            while ($firstNonZero < $coefficientsLength && $coefficients[$firstNonZero] === 0) {
                ++$firstNonZero;
            }

            if ($firstNonZero === $coefficientsLength) {
                $this->coefficients = [0];
            } else {
                $this->coefficients = fill_array(0, $coefficientsLength - $firstNonZero, 0);
                $this->coefficients = arraycopy(
                    $coefficients,
                    $firstNonZero,
                    $this->coefficients,
                    0,
                    is_countable($this->coefficients) ? count($this->coefficients) : 0,
                );
            }
        } else {
            $this->coefficients = $coefficients;
        }
    }

    /**
     * Returns the normalized coefficient array.
     *
     * @psalm-return array<float|int>|null
     */
    public function getCoefficients(): ?array
    {
        return $this->coefficients;
    }

    /**
     * Evaluates the polynomial at the supplied field element.
     * @param mixed $a
     */
    public function evaluateAt($a): int|float|null
    {
        if ($a === 0) {
            // Just return the x^0 coefficient
            return $this->getCoefficient(0);
        }
        $size = is_countable($this->coefficients) ? count($this->coefficients) : 0;

        if ($a === 1) {
            // Just the sum of the coefficients
            $result = 0;

            foreach ($this->coefficients as $coefficient) {
                $result = GenericGF::addOrSubtract($result, $coefficient);
            }

            return $result;
        }
        $result = $this->coefficients[0];

        for ($i = 1; $i < $size; ++$i) {
            $result = GenericGF::addOrSubtract($this->field->multiply($a, $result), $this->coefficients[$i]);
        }

        return $result;
    }

    /**
     * Returns the coefficient of the requested degree.
     *
     * @param float|int $degree polynomial degree counted from the constant term
     */
    public function getCoefficient(int|float $degree): int|float|null
    {
        return $this->coefficients[(is_countable($this->coefficients) ? count($this->coefficients) : 0) - 1 - $degree];
    }

    /**
     * Multiplies this polynomial by either a scalar or another polynomial.
     * @param mixed $other
     */
    public function multiply($other): self
    {
        $aCoefficients = [];
        $bCoefficients = [];
        $aLength = null;
        $bLength = null;
        $product = [];

        if (is_int($other)) {
            return $this->multiply_($other);
        }

        if ($this->field !== $other->field) {
            throw \Cline\Qr\Decoder\InvalidArgumentException::withMessage('GenericGFPolys do not have same GenericGF field');
        }

        if ($this->isZero() || $other->isZero()) {
            return $this->field->getZero();
        }
        $aCoefficients = $this->coefficients;
        $aLength = count($aCoefficients);
        $bCoefficients = $other->coefficients;
        $bLength = count($bCoefficients);
        $product = fill_array(0, $aLength + $bLength - 1, 0);

        for ($i = 0; $i < $aLength; ++$i) {
            $aCoeff = $aCoefficients[$i];

            for ($j = 0; $j < $bLength; ++$j) {
                $product[$i + $j] = GenericGF::addOrSubtract(
                    $product[$i + $j],
                    $this->field->multiply($aCoeff, $bCoefficients[$j]),
                );
            }
        }

        return new self($this->field, $product);
    }

    /**
     * Multiplies every coefficient by a scalar field element.
     */
    public function multiply_(int $scalar): self
    {
        if ($scalar === 0) {
            return $this->field->getZero();
        }

        if ($scalar === 1) {
            return $this;
        }
        $size = is_countable($this->coefficients) ? count($this->coefficients) : 0;
        $product = fill_array(0, $size, 0);

        for ($i = 0; $i < $size; ++$i) {
            $product[$i] = $this->field->multiply($this->coefficients[$i], $scalar);
        }

        return new self($this->field, $product);
    }

    /**
     * Returns true when this polynomial is the zero polynomial.
     */
    public function isZero(): bool
    {
        return $this->coefficients[0] === 0;
    }

    /**
     * Multiplies this polynomial by a monomial term.
     * @param mixed $degree
     * @param mixed $coefficient
     */
    public function multiplyByMonomial($degree, $coefficient): self
    {
        if ($degree < 0) {
            throw \Cline\Qr\Decoder\InvalidArgumentException::withMessage();
        }

        if ($coefficient === 0) {
            return $this->field->getZero();
        }
        $size = is_countable($this->coefficients) ? count($this->coefficients) : 0;
        $product = fill_array(0, $size + $degree, 0);

        for ($i = 0; $i < $size; ++$i) {
            $product[$i] = $this->field->multiply($this->coefficients[$i], $coefficient);
        }

        return new self($this->field, $product);
    }

    /**
     * Divides this polynomial by another polynomial.
     *
     * @param mixed $other
     * @psalm-return array{0: mixed, 1: mixed} quotient and remainder
     */
    public function divide($other): array
    {
        if ($this->field !== $other->field) {
            throw \Cline\Qr\Decoder\InvalidArgumentException::withMessage('GenericGFPolys do not have same GenericGF field');
        }

        if ($other->isZero()) {
            throw \Cline\Qr\Decoder\InvalidArgumentException::withMessage('Divide by 0');
        }

        $quotient = $this->field->getZero();
        $remainder = $this;

        $denominatorLeadingTerm = $other->getCoefficient($other->getDegree());
        $inverseDenominatorLeadingTerm = $this->field->inverse($denominatorLeadingTerm);

        while ($remainder->getDegree() >= $other->getDegree() && !$remainder->isZero()) {
            $degreeDifference = $remainder->getDegree() - $other->getDegree();
            $scale = $this->field->multiply($remainder->getCoefficient($remainder->getDegree()), $inverseDenominatorLeadingTerm);
            $term = $other->multiplyByMonomial($degreeDifference, $scale);
            $iterationQuotient = $this->field->buildMonomial($degreeDifference, $scale);
            $quotient = $quotient->addOrSubtract($iterationQuotient);
            $remainder = $remainder->addOrSubtract($term);
        }

        return [$quotient, $remainder];
    }

    /**
     * Returns the polynomial degree.
     */
    public function getDegree(): int
    {
        return (is_countable($this->coefficients) ? count($this->coefficients) : 0) - 1;
    }

    /**
     * Adds another polynomial, which is the same operation as subtraction in this field.
     */
    public function addOrSubtract(self $other): self
    {
        $smallerCoefficients = [];
        $largerCoefficients = [];
        $sumDiff = [];
        $lengthDiff = null;
        $countLargerCoefficients = null;

        if ($this->field !== $other->field) {
            throw \Cline\Qr\Decoder\InvalidArgumentException::withMessage('GenericGFPolys do not have same GenericGF field');
        }

        if ($this->isZero()) {
            return $other;
        }

        if ($other->isZero()) {
            return $this;
        }

        $smallerCoefficients = $this->coefficients;
        $largerCoefficients = $other->coefficients;

        if (count($smallerCoefficients) > count($largerCoefficients)) {
            $temp = $smallerCoefficients;
            $smallerCoefficients = $largerCoefficients;
            $largerCoefficients = $temp;
        }
        $sumDiff = fill_array(0, count($largerCoefficients), 0);
        $lengthDiff = count($largerCoefficients) - count($smallerCoefficients);
        // Copy high-order terms only found in higher-degree polynomial's coefficients
        $sumDiff = arraycopy($largerCoefficients, 0, $sumDiff, 0, $lengthDiff);

        $countLargerCoefficients = count($largerCoefficients);

        for ($i = $lengthDiff; $i < $countLargerCoefficients; ++$i) {
            $sumDiff[$i] = GenericGF::addOrSubtract($smallerCoefficients[$i - $lengthDiff], $largerCoefficients[$i]);
        }

        return new self($this->field, $sumDiff);
    }

    public function toString(): string
    {
        $result = '';

        for ($degree = $this->getDegree(); $degree >= 0; --$degree) {
            $coefficient = $this->getCoefficient($degree);

            if ($coefficient === 0) {
                continue;
            }

            if ($coefficient < 0) {
                $result .= ' - ';
                $coefficient = -$coefficient;
            } else {
                if ((string) $result !== '') {
                    $result .= ' + ';
                }
            }

            if ($degree === 0 || $coefficient !== 1) {
                $alphaPower = $this->field->log($coefficient);

                if ($alphaPower === 0) {
                    $result .= '1';
                } elseif ($alphaPower === 1) {
                    $result .= 'a';
                } else {
                    $result .= 'a^';
                    $result .= $alphaPower;
                }
            }

            if ($degree === 0) {
                continue;
            }

            if ($degree === 1) {
                $result .= 'x';
            } else {
                $result .= 'x^';
                $result .= $degree;
            }
        }

        return $result;
    }
}
