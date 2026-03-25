<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Decoder\Common\Reedsolomon;

use function count;
use function fill_array;
use function is_countable;

/**
 * Corrects QR code codewords using Reed-Solomon error correction.
 *
 * The decoder works in-place on the received codeword array. It first derives a
 * syndrome polynomial, then solves for the error locator and evaluator, and
 * finally patches the corrupted symbols back into the input.
 *
 * @author sanfordsquires
 * @author Sean Owen
 * @author William Rucklidge
 */
final class ReedSolomonDecoder
{
    /**
     * @param GenericGF $field finite field used for all error-correction math
     */
    public function __construct(
        private readonly GenericGF $field,
    ) {}

    /**
     * Decodes the received codewords in place.
     *
     * The input must contain both data and error-correction symbols. On success,
     * the array is mutated to contain corrected codewords. If the corruption is too
     * severe or the algebra fails to converge, a {@see ReedSolomonException} is
     * thrown.
     *
     * @param array     $received received codewords, mutated in place
     * @param float|int $twoS     number of error-correction codewords available
     *
     * @throws ReedSolomonException if decoding fails for any reason
     */
    public function decode($received, $twoS): void
    {
        $poly = new GenericGFPoly($this->field, $received);
        $syndromeCoefficients = fill_array(0, $twoS, 0);
        $noError = true;

        for ($i = 0; $i < $twoS; ++$i) {
            $eval = $poly->evaluateAt($this->field->exp($i + $this->field->getGeneratorBase()));
            $syndromeCoefficients[(is_countable($syndromeCoefficients) ? count($syndromeCoefficients) : 0) - 1 - $i] = $eval;

            if ($eval === 0) {
                continue;
            }

            $noError = false;
        }

        if ($noError) {
            return;
        }
        $syndrome = new GenericGFPoly($this->field, $syndromeCoefficients);
        $sigmaOmega =
            $this->runEuclideanAlgorithm($this->field->buildMonomial($twoS, 1), $syndrome, $twoS);
        $sigma = $sigmaOmega[0];
        $omega = $sigmaOmega[1];
        $errorLocations = $this->findErrorLocations($sigma);
        $errorMagnitudes = $this->findErrorMagnitudes($omega, $errorLocations);
        $errorLocationsCount = is_countable($errorLocations) ? count($errorLocations) : 0;

        for ($i = 0; $i < $errorLocationsCount; ++$i) {
            $position = (is_countable($received) ? count($received) : 0) - 1 - $this->field->log($errorLocations[$i]);

            if ($position < 0) {
                throw ReedSolomonException::withMessage('Bad error location');
            }
            $received[$position] = GenericGF::addOrSubtract($received[$position], $errorMagnitudes[$i]);
        }
    }

    /**
     * Runs the Euclidean algorithm to derive the error locator and evaluator.
     *
     * @param mixed $a
     * @psalm-return array{0: mixed, 1: mixed}
     */
    private function runEuclideanAlgorithm($a, GenericGFPoly $b, int|float $R): array
    {
        // Assume a's degree is >= b's
        if ($a->getDegree() < $b->getDegree()) {
            $temp = $a;
            $a = $b;
            $b = $temp;
        }

        $rLast = $a;
        $r = $b;
        $tLast = $this->field->getZero();
        $t = $this->field->getOne();

        // Run Euclidean algorithm until r's degree is less than R/2
        while ($r->getDegree() >= $R / 2) {
            $rLastLast = $rLast;
            $tLastLast = $tLast;
            $rLast = $r;
            $tLast = $t;

            // Divide rLastLast by rLast, with quotient in q and remainder in r
            if ($rLast->isZero()) {
                // Oops, Euclidean algorithm already terminated?
                throw ReedSolomonException::withMessage('r_{i-1} was zero');
            }
            $r = $rLastLast;
            $q = $this->field->getZero();
            $denominatorLeadingTerm = $rLast->getCoefficient($rLast->getDegree());
            $dltInverse = $this->field->inverse($denominatorLeadingTerm);

            while ($r->getDegree() >= $rLast->getDegree() && !$r->isZero()) {
                $degreeDiff = $r->getDegree() - $rLast->getDegree();
                $scale = $this->field->multiply($r->getCoefficient($r->getDegree()), $dltInverse);
                $q = $q->addOrSubtract($this->field->buildMonomial($degreeDiff, $scale));
                $r = $r->addOrSubtract($rLast->multiplyByMonomial($degreeDiff, $scale));
            }

            $t = $q->multiply($tLast)->addOrSubtract($tLastLast);

            if ($r->getDegree() >= $rLast->getDegree()) {
                throw ReedSolomonException::withMessage('Division algorithm failed to reduce polynomial?');
            }
        }

        $sigmaTildeAtZero = $t->getCoefficient(0);

        if ($sigmaTildeAtZero === 0) {
            throw ReedSolomonException::withMessage('sigmaTilde(0) was zero');
        }

        $inverse = $this->field->inverse($sigmaTildeAtZero);
        $sigma = $t->multiply($inverse);
        $omega = $r->multiply($inverse);

        return [$sigma, $omega];
    }

    /**
     * Locates the field elements corresponding to each error position.
     *
     * @param mixed $errorLocator
     * @psalm-return array<int, mixed>
     */
    private function findErrorLocations($errorLocator): array
    {
        // This is a direct application of Chien's search
        $numErrors = $errorLocator->getDegree();

        if ($numErrors === 1) { // shortcut
            return [$errorLocator->getCoefficient(1)];
        }
        $result = fill_array(0, $numErrors, 0);
        $e = 0;

        for ($i = 1; $i < $this->field->getSize() && $e < $numErrors; ++$i) {
            if ($errorLocator->evaluateAt($i) !== 0) {
                continue;
            }

            $result[$e] = $this->field->inverse($i);
            ++$e;
        }

        if ($e !== $numErrors) {
            throw ReedSolomonException::withMessage('Error locator degree does not match number of roots');
        }

        return $result;
    }

    /**
     * Computes the magnitude of each error at the discovered locations.
     *
     * @param mixed $errorEvaluator
     * @psalm-param array<int, mixed> $errorLocations field elements representing error locations
     * @psalm-return array<int, mixed>
     */
    private function findErrorMagnitudes($errorEvaluator, array $errorLocations): array
    {
        // This is directly applying Forney's Formula
        $s = is_countable($errorLocations) ? count($errorLocations) : 0;
        $result = fill_array(0, $s, 0);

        for ($i = 0; $i < $s; ++$i) {
            $xiInverse = $this->field->inverse($errorLocations[$i]);
            $denominator = 1;

            for ($j = 0; $j < $s; ++$j) {
                if ($i === $j) {
                    continue;
                }

                // denominator = field.multiply(denominator,
                //    GenericGF.addOrSubtract(1, field.multiply(errorLocations[j], xiInverse)));
                // Above should work but fails on some Apple and Linux JDKs due to a Hotspot bug.
                // Below is a funny-looking workaround from Steven Parkes
                $term = $this->field->multiply($errorLocations[$j], $xiInverse);
                $termPlus1 = ($term & 0x1) === 0 ? $term | 1 : $term & ~1;
                $denominator = $this->field->multiply($denominator, $termPlus1);
            }
            $result[$i] = $this->field->multiply(
                $errorEvaluator->evaluateAt($xiInverse),
                $this->field->inverse($denominator),
            );

            if ($this->field->getGeneratorBase() === 0) {
                continue;
            }

            $result[$i] = $this->field->multiply($result[$i], $xiInverse);
        }

        return $result;
    }
}
