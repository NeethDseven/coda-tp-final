<?php

declare(strict_types=1);

final class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    /** @var list<array{label: string, expected: string, actual: string}> */
    private array $failures = [];

    public function same(mixed $expected, mixed $actual, string $label): void
    {
        if ($expected === $actual) {
            $this->passed++;
            echo "OK   {$label}" . PHP_EOL;
            return;
        }

        $this->failed++;
        $this->failures[] = [
            'label' => $label,
            'expected' => var_export($expected, true),
            'actual' => var_export($actual, true),
        ];
        echo "FAIL {$label}" . PHP_EOL;
        echo '     expected: ' . var_export($expected, true) . PHP_EOL;
        echo '     actual:   ' . var_export($actual, true) . PHP_EOL;
    }

    public function near(float $expected, float $actual, string $label, float $delta = 0.001): void
    {
        if (abs($expected - $actual) <= $delta) {
            $this->passed++;
            echo "OK   {$label}" . PHP_EOL;
            return;
        }

        $this->failed++;
        $this->failures[] = [
            'label' => $label,
            'expected' => (string) $expected,
            'actual' => (string) $actual,
        ];
        echo "FAIL {$label}" . PHP_EOL;
        echo "     expected: {$expected}" . PHP_EOL;
        echo "     actual:   {$actual}" . PHP_EOL;
    }

    public function throws(callable $test, string $exceptionClass, string $message, string $label): void
    {
        try {
            $test();
        } catch (\Throwable $error) {
            $this->same($exceptionClass, get_class($error), $label . ' : type');
            $this->same($message, $error->getMessage(), $label . ' : message');
            return;
        }

        $this->same($exceptionClass, 'Aucune exception', $label . ' : exception attendue');
    }

    public function summary(): void
    {
        echo PHP_EOL . "Passed: {$this->passed}, Failed: {$this->failed}" . PHP_EOL;
        if ($this->failed > 0) {
            echo PHP_EOL . 'Detail des echecs :' . PHP_EOL;
            foreach ($this->failures as $index => $failure) {
                echo ($index + 1) . '. ' . $failure['label'] . PHP_EOL;
                echo '     expected: ' . $failure['expected'] . PHP_EOL;
                echo '     actual:   ' . $failure['actual'] . PHP_EOL;
            }
        }
    }


    public function run(string $label, callable $test): void
    {
        try {
            $test();
        } catch (\Throwable $error) {
            $this->failed++;
            $this->failures[] = [
                'label' => $label,
                'expected' => 'Aucune exception',
                'actual' => get_class($error) . ': ' . $error->getMessage(),
            ];

            echo "FAIL {$label}: {$error->getMessage()}" . PHP_EOL;
        }
    }

}
