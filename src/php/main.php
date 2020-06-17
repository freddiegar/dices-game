<?php

class Dice
{
    const NUMBERS = [
        1 => 16,
        2 => 68,
        3 => 84,
        4 => 325,
        5 => 341,
        6 => 365,
    ];

    private $number;
    private $format;

    public function __construct()
    {
        $this->number = $this->getNumber();
        $this->format = $this->getFormat($this->number);
    }

    public function number(): int
    {
        return $this->number;
    }

    public function format(): string
    {
        return $this->format;
    }

    private function getNumber(): int
    {
        return rand(1, 6);
    }

    private function getFormat(int $number): string
    {
        return str_pad(decbin(self::NUMBERS[$number]), 9, '0', STR_PAD_LEFT);
    }
}

class Throwing
{
    private $dices = [];

    public function __construct(int $dices = 2)
    {
        for($i = 0; $i < $dices; $i++) {
            $this->dices[] = $this->getDice();
        }
    }

    private function getDice(): Dice
    {
        return new Dice();
    }

    public function dices(): array
    {
        return $this->dices;
    }

    public function print(): string
    {
        $print = [];
        $text = '';

        foreach ($this->dices() as $d => $dice) {
            $chunk = str_split($dice->format(), 3);

            $print[$d][] = str_repeat('-', 9);

            foreach ($chunk as $line) {
                $line = str_replace(['1', '0'], ['X', ' '], $line);
                $print[$d][] = sprintf("|%s  %s  %s|", $line[0], $line[1], $line[2]);
            }

            $print[$d][] = str_repeat('-', 9);
        }

        $dices = count($print);
        $lines = count($print[0]);

        for ($i = 0; $i < $lines; $i++) {
            $tmp = [];
            for ($j = 0; $j < $dices; $j++) {
                $tmp[] = $print[$j][$i];
            }
            $text .= str_repeat(' ', 5) . implode(str_repeat(' ', 5), $tmp) . PHP_EOL;
        }

        return $text;
    }
}

class Turn
{
    const MAX = 3;
    private $throwing = 0;

    private function getThrowing(): Throwing
    {
        return new Throwing();
    }

    private function isEquals(Dice $diceA, Dice $diceB): bool
    {
        return $diceA->number() === $diceB->number();
    }

    private function repeat(Dice $diceA, Dice $diceB, int $throwNumber): bool
    {
        return $this->isEquals($diceA, $diceB) && $throwNumber < self::MAX;
    }

    public function get(): string
    {
        $turns = [];
        $throwNumber = 0;

        do {
            $trowing = $this->getThrowing();

            list($d1, $d2) = $trowing->dices();

            $turns[] = $trowing->print();

            $throwNumber++;
        } while ($this->repeat($d1, $d2, $throwNumber));

        return implode("\n\n", $turns);
    }
}

function probability(array $throws, int $total)
{
    echo "Number \t Times \t %" . PHP_EOL;

    foreach ($throws as $number => $repeats) {
        echo "{$number} \t {$repeats}\t " . number_format((($repeats) / $total) * 100, 2) . PHP_EOL;
    }
}

switch ($argv[1] ?? null) {
    case 'p':
        // Probability in 1 shot
        $t = 0;
        $throws = [];
        for ($i = 1; $i <= 6; $i++) {
            for ($j = 1; $j <= 6; $j++) {
                $sum = $i + $j;
                $throws[$sum] = !isset($throws[$sum]) ? 1 : $throws[$sum] + 1;
                $t++;
            }
        }

        probability($throws, $t);

        break;
    case 'r':
        // Probability in ? shots
        $t = $argv[2] ?? 1000;
        $throws = [];
        for ($i=0; $i < $t; $i++) {
            list($d1, $d2) = (new Throwing())->dices();
            $sum = $d1->number() + $d2->number();
            $throws[$sum] = !isset($throws[$sum]) ? 1 : $throws[$sum] + 1;
        }

        ksort($throws);

        probability($throws, $t);

        break;
    case 'd':
        // Print dices
        echo (new Throwing($argv[2] ?? 2))->print();
        break;
    case 't':
        // Turn
        echo (new Turn())->get();
        break;
    default:
        break;
}