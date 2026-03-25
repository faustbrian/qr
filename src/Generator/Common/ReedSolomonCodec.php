<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Qr\Generator\Common;

use Cline\Qr\Generator\Internal\Exception\InvalidArgumentException;
use Cline\Qr\Generator\Internal\Exception\RuntimeException;
use SplFixedArray;

use function array_fill;
use function array_pop;
use function array_unshift;
use function count;
use function intdiv;
use function min;

/**
 * Reed-Solomon codec for the package's 8-bit QR field arithmetic.
 *
 * The encoder uses this type to derive parity bytes for each data block and
 * the decoder uses the same field tables to recover from erasures and
 * corrections. The instance caches the Galois field lookup tables and the
 * generator polynomial because those values are expensive to derive but stable
 * for a given symbol size and block layout.
 *
 * Based on libfec by Phil Karn, KA9Q.
 * @author Brian Faust <brian@cline.sh>
 */
final class ReedSolomonCodec
{
    /**
     * Block size in symbols.
     */
    private int $blockSize;

    /**
     * Prim-th root of 1, index form.
     */
    private int $iPrimitive;

    /**
     * Log lookup table.
     */
    private SplFixedArray $alphaTo;

    /**
     * Anti-Log lookup table.
     */
    private SplFixedArray $indexOf;

    /**
     * Generator polynomial.
     */
    private SplFixedArray $generatorPoly;

    /**
     * @throws InvalidArgumentException if first root is invalid
     * @throws InvalidArgumentException if num roots is invalid
     * @throws InvalidArgumentException if padding is invalid
     * @throws InvalidArgumentException if symbol size ist not between 0 and 8
     * @throws RuntimeException         if field generator polynomial is not primitive
     */
    public function __construct(
        /**
         * Symbol size in bits.
         */
        private readonly int $symbolSize,
        int $gfPoly,
        /**
         * First root of RS code generator polynomial, index form.
         */
        private readonly int $firstRoot,
        /**
         * Primitive element to generate polynomial roots, index form.
         */
        private readonly int $primitive,
        /**
         * RS code generator polynomial degree (number of roots).
         */
        private readonly int $numRoots,
        /**
         * Padding bytes at front of shortened block.
         */
        private readonly int $padding,
    ) {
        if ($symbolSize < 0 || $symbolSize > 8) {
            throw InvalidArgumentException::withMessage('Symbol size must be between 0 and 8');
        }

        if ($firstRoot < 0 || $firstRoot >= (1 << $symbolSize)) {
            throw InvalidArgumentException::withMessage('First root must be between 0 and '.(1 << $symbolSize));
        }

        if ($numRoots < 0 || $numRoots >= (1 << $symbolSize)) {
            throw InvalidArgumentException::withMessage('Num roots must be between 0 and '.(1 << $symbolSize));
        }

        if ($padding < 0 || $padding >= ((1 << $symbolSize) - 1 - $numRoots)) {
            throw InvalidArgumentException::withMessage(
                'Padding must be between 0 and '.((1 << $symbolSize) - 1 - $numRoots),
            );
        }

        $this->blockSize = (1 << $symbolSize) - 1;
        $this->alphaTo = SplFixedArray::fromArray(array_fill(0, $this->blockSize + 1, 0), false);
        $this->indexOf = SplFixedArray::fromArray(array_fill(0, $this->blockSize + 1, 0), false);

        // Generate galous field lookup table
        $this->indexOf[0] = $this->blockSize;
        $this->alphaTo[$this->blockSize] = 0;

        $sr = 1;

        for ($i = 0; $i < $this->blockSize; ++$i) {
            $this->indexOf[$sr] = $i;
            $this->alphaTo[$i] = $sr;

            $sr <<= 1;

            if ($sr & (1 << $symbolSize)) {
                $sr ^= $gfPoly;
            }

            $sr &= $this->blockSize;
        }

        if (1 !== $sr) {
            throw RuntimeException::withMessage('Field generator polynomial is not primitive');
        }

        // Form RS code generator polynomial from its roots
        $this->generatorPoly = SplFixedArray::fromArray(array_fill(0, $numRoots + 1, 0), false);

        // Find prim-th root of 1, used in decoding
        for ($iPrimitive = 1; ($iPrimitive % $primitive) !== 0; $iPrimitive += $this->blockSize) {
        }

        $this->iPrimitive = intdiv($iPrimitive, $primitive);

        $this->generatorPoly[0] = 1;

        for ($i = 0, $root = $firstRoot * $primitive; $i < $numRoots; ++$i, $root += $primitive) {
            $this->generatorPoly[$i + 1] = 1;

            for ($j = $i; $j > 0; --$j) {
                if ($this->generatorPoly[$j] !== 0) {
                    $this->generatorPoly[$j] = $this->generatorPoly[$j - 1] ^ $this->alphaTo[
                        $this->modNn($this->indexOf[$this->generatorPoly[$j]] + $root)
                    ];
                } else {
                    $this->generatorPoly[$j] = $this->generatorPoly[$j - 1];
                }
            }

            $this->generatorPoly[$j] = $this->alphaTo[$this->modNn($this->indexOf[$this->generatorPoly[0]] + $root)];
        }

        // Convert generator poly to index form for quicker encoding
        for ($i = 0; $i <= $numRoots; ++$i) {
            $this->generatorPoly[$i] = $this->indexOf[$this->generatorPoly[$i]];
        }
    }

    /**
     * Encode the supplied data block and write the parity bytes in place.
     *
     * The method mutates only the parity buffer. Callers are expected to pass a
     * buffer sized to the codec's number of roots; the input data buffer is read
     * as-is and is not resized or copied.
     */
    public function encode(SplFixedArray $data, SplFixedArray $parity): void
    {
        for ($i = 0; $i < $this->numRoots; ++$i) {
            $parity[$i] = 0;
        }

        $iterations = $this->blockSize - $this->numRoots - $this->padding;

        for ($i = 0; $i < $iterations; ++$i) {
            $feedback = $this->indexOf[$data[$i] ^ $parity[0]];

            if ($feedback !== $this->blockSize) {
                // Feedback term is non-zero
                $feedback = $this->modNn($this->blockSize - $this->generatorPoly[$this->numRoots] + $feedback);

                for ($j = 1; $j < $this->numRoots; ++$j) {
                    $parity[$j] = $parity[$j] ^ $this->alphaTo[
                        $this->modNn($feedback + $this->generatorPoly[$this->numRoots - $j])
                    ];
                }
            }

            for ($j = 0; $j < $this->numRoots - 1; ++$j) {
                $parity[$j] = $parity[$j + 1];
            }

            if ($feedback !== $this->blockSize) {
                $parity[$this->numRoots - 1] = $this->alphaTo[$this->modNn($feedback + $this->generatorPoly[0])];
            } else {
                $parity[$this->numRoots - 1] = 0;
            }
        }
    }

    /**
     * Attempt to correct the received codeword in place.
     *
     * The return value is the number of corrected errors, `0` when the input is
     * already a valid codeword, or `null` when the locator polynomial cannot be
     * resolved. When erasures are provided, the method also writes the resolved
     * locations back into that buffer.
     */
    public function decode(SplFixedArray $data, ?SplFixedArray $erasures = null): ?int
    {
        // This speeds up the initialization a bit.
        $numRootsPlusOne = SplFixedArray::fromArray(array_fill(0, $this->numRoots + 1, 0), false);
        $numRoots = SplFixedArray::fromArray(array_fill(0, $this->numRoots, 0), false);

        $lambda = clone $numRootsPlusOne;
        $b = clone $numRootsPlusOne;
        $t = clone $numRootsPlusOne;
        $omega = clone $numRootsPlusOne;
        $root = clone $numRoots;
        $loc = clone $numRoots;

        $numErasures = null !== $erasures ? count($erasures) : 0;

        // Form the Syndromes; i.e., evaluate data(x) at roots of g(x)
        $syndromes = SplFixedArray::fromArray(array_fill(0, $this->numRoots, $data[0]), false);

        for ($i = 1; $i < $this->blockSize - $this->padding; ++$i) {
            for ($j = 0; $j < $this->numRoots; ++$j) {
                if ($syndromes[$j] === 0) {
                    $syndromes[$j] = $data[$i];
                } else {
                    $syndromes[$j] = $data[$i] ^ $this->alphaTo[
                        $this->modNn($this->indexOf[$syndromes[$j]] + ($this->firstRoot + $j) * $this->primitive)
                    ];
                }
            }
        }

        // Convert syndromes to index form, checking for nonzero conditions
        $syndromeError = 0;

        for ($i = 0; $i < $this->numRoots; ++$i) {
            $syndromeError |= $syndromes[$i];
            $syndromes[$i] = $this->indexOf[$syndromes[$i]];
        }

        if (!$syndromeError) {
            // If syndrome is zero, data[] is a codeword and there are no errors to correct, so return data[]
            // unmodified.
            return 0;
        }

        $lambda[0] = 1;

        if ($numErasures > 0) {
            // Init lambda to be the erasure locator polynomial
            $lambda[1] = $this->alphaTo[$this->modNn($this->primitive * ($this->blockSize - 1 - $erasures[0]))];

            for ($i = 1; $i < $numErasures; ++$i) {
                $u = $this->modNn($this->primitive * ($this->blockSize - 1 - $erasures[$i]));

                for ($j = $i + 1; $j > 0; --$j) {
                    $tmp = $this->indexOf[$lambda[$j - 1]];

                    if ($tmp === $this->blockSize) {
                        continue;
                    }

                    $lambda[$j] = $lambda[$j] ^ $this->alphaTo[$this->modNn($u + $tmp)];
                }
            }
        }

        for ($i = 0; $i <= $this->numRoots; ++$i) {
            $b[$i] = $this->indexOf[$lambda[$i]];
        }

        // Begin Berlekamp-Massey algorithm to determine error+erasure locator polynomial
        $r = $numErasures;
        $el = $numErasures;

        while (++$r <= $this->numRoots) {
            // Compute discrepancy at the r-th step in poly form
            $discrepancyR = 0;

            for ($i = 0; $i < $r; ++$i) {
                if ($lambda[$i] === 0 || $syndromes[$r - $i - 1] === $this->blockSize) {
                    continue;
                }

                $discrepancyR ^= $this->alphaTo[
                    $this->modNn($this->indexOf[$lambda[$i]] + $syndromes[$r - $i - 1])
                ];
            }

            $discrepancyR = $this->indexOf[$discrepancyR];

            if ($discrepancyR === $this->blockSize) {
                $tmp = $b->toArray();
                array_unshift($tmp, $this->blockSize);
                array_pop($tmp);
                $b = SplFixedArray::fromArray($tmp, false);

                continue;
            }

            $t[0] = $lambda[0];

            for ($i = 0; $i < $this->numRoots; ++$i) {
                if ($b[$i] !== $this->blockSize) {
                    $t[$i + 1] = $lambda[$i + 1] ^ $this->alphaTo[$this->modNn($discrepancyR + $b[$i])];
                } else {
                    $t[$i + 1] = $lambda[$i + 1];
                }
            }

            if (2 * $el <= $r + $numErasures - 1) {
                $el = $r + $numErasures - $el;

                for ($i = 0; $i <= $this->numRoots; ++$i) {
                    $b[$i] = $lambda[$i] === 0
                        ? $this->blockSize
                        : $this->modNn($this->indexOf[$lambda[$i]] - $discrepancyR + $this->blockSize);
                }
            } else {
                $tmp = $b->toArray();
                array_unshift($tmp, $this->blockSize);
                array_pop($tmp);
                $b = SplFixedArray::fromArray($tmp, false);
            }

            $lambda = clone $t;
        }

        // Convert lambda to index form and compute deg(lambda(x))
        $degLambda = 0;

        for ($i = 0; $i <= $this->numRoots; ++$i) {
            $lambda[$i] = $this->indexOf[$lambda[$i]];

            if ($lambda[$i] === $this->blockSize) {
                continue;
            }

            $degLambda = $i;
        }

        // Find roots of the error+erasure locator polynomial by Chien search.
        $reg = clone $lambda;
        $reg[0] = 0;
        $count = 0;
        $i = 1;

        for ($k = $this->iPrimitive - 1; $i <= $this->blockSize; ++$i, $k = $this->modNn($k + $this->iPrimitive)) {
            $q = 1;

            for ($j = $degLambda; $j > 0; --$j) {
                if ($reg[$j] === $this->blockSize) {
                    continue;
                }

                $reg[$j] = $this->modNn($reg[$j] + $j);
                $q ^= $this->alphaTo[$reg[$j]];
            }

            if ($q !== 0) {
                // Not a root
                continue;
            }

            // Store root (index-form) and error location number
            $root[$count] = $i;
            $loc[$count] = $k;

            if (++$count === $degLambda) {
                break;
            }
        }

        if ($degLambda !== $count) {
            // deg(lambda) unequal to number of roots: uncorrectable error detected
            return null;
        }

        // Compute err+eras evaluate poly omega(x) = s(x)*lambda(x) (modulo x**numRoots). In index form. Also find
        // deg(omega).
        $degOmega = $degLambda - 1;

        for ($i = 0; $i <= $degOmega; ++$i) {
            $tmp = 0;

            for ($j = $i; $j >= 0; --$j) {
                if ($syndromes[$i - $j] === $this->blockSize || $lambda[$j] === $this->blockSize) {
                    continue;
                }

                $tmp ^= $this->alphaTo[$this->modNn($syndromes[$i - $j] + $lambda[$j])];
            }

            $omega[$i] = $this->indexOf[$tmp];
        }

        // Compute error values in poly-form. num1 = omega(inv(X(l))), num2 = inv(X(l))**(firstRoot-1) and
        // den = lambda_pr(inv(X(l))) all in poly form.
        for ($j = $count - 1; $j >= 0; --$j) {
            $num1 = 0;

            for ($i = $degOmega; $i >= 0; --$i) {
                if ($omega[$i] === $this->blockSize) {
                    continue;
                }

                $num1 ^= $this->alphaTo[$this->modNn($omega[$i] + $i * $root[$j])];
            }

            $num2 = $this->alphaTo[$this->modNn($root[$j] * ($this->firstRoot - 1) + $this->blockSize)];
            $den = 0;

            // lambda[i+1] for i even is the formal derivativelambda_pr of lambda[i]
            for ($i = min($degLambda, $this->numRoots - 1) & ~1; $i >= 0; $i -= 2) {
                if ($lambda[$i + 1] === $this->blockSize) {
                    continue;
                }

                $den ^= $this->alphaTo[$this->modNn($lambda[$i + 1] + $i * $root[$j])];
            }

            // Apply error to data
            if ($num1 === 0 || $loc[$j] < $this->padding) {
                continue;
            }

            $data[$loc[$j] - $this->padding] = $data[$loc[$j] - $this->padding] ^
                $this->alphaTo[
                    $this->modNn(
                        $this->indexOf[$num1] + $this->indexOf[$num2] + $this->blockSize - $this->indexOf[$den],
                    )
                ];
        }

        if (null !== $erasures) {
            if (count($erasures) < $count) {
                $erasures->setSize($count);
            }

            for ($i = 0; $i < $count; ++$i) {
                $erasures[$i] = $loc[$i];
            }
        }

        return $count;
    }

    /**
     * Compute `$x % GF_SIZE` without a general-purpose divide.
     *
     * The helper keeps values inside the finite-field range used by the codec
     * tables, which avoids the cost of repeated modulo operations in the inner
     * encode and decode loops.
     */
    private function modNn(int $x): int
    {
        while ($x >= $this->blockSize) {
            $x -= $this->blockSize;
            $x = ($x >> $this->symbolSize) + ($x & $this->blockSize);
        }

        return $x;
    }
}
