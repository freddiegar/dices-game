<?php

define('PIECE_STATUS_JAIL', 'J');
define('PIECE_STATUS_ACTIVE', 'A');
define('PIECE_STATUS_INACTIVE', 'I');

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

class Board
{
    private $colors;
    private $pieces = [];

    public function __construct(int $colors)
    {
        $this->colors = $colors;
    }

    private function getCells(string $color): array
    {
        return array_reduce(range(-6, 20), function($cells, $key): array {
            switch ($key) {
                case -6:
                    $value = 'J'; // Jail
                    break;
                case 0:
                    $value = 'H'; // Home
                    break;
                case -5:
                case 7:
                case 12:
                    $value = 'S'; // Safe
                    break;
                case 20:
                    $value = 'F'; // Arrival
                    break;
                default:
                    $value = 'C'; // Cell
                    break;
            }

            $cells[$key] = $value;

            return $cells;
        }, []);
    }

    private function getCell(string $color, string $type, int $position): string
    {
        switch ($type) {
            case 'J':
                $cell = sprintf('% 8s', implode('', $this->getPiecesInJail($color)));
                break;
            case 'H':
                $cell = '--HOME--';
                break;
            case 'S':
                $cell = '--SAFE--';
                break;
            case 'F':
                $cell = '-FINISH-';
                break;
            case 'C':
            default:
                $cell = '        ';
                break;
        }

        $pieces = $this->getPiecesInCell($color, $position);

        if (!empty($pieces)) {
            $cell = sprintf('% 8s', implode('', $pieces));
        }

        return sprintf('|% 2d|%s|%s|', $position, $type, $cell);
    }

    public function colors(): int
    {
        return $this->colors;
    }

    public function get(): string
    {
        $board = '';
        $station = [];
        $stations = [];

        for ($i = 0; $i < $this->colors; $i++) {
            $color = chr($i + 65);

            $stations[$color] = $this->getCells($color);
        }

        foreach ($stations as $color => $cells) {
            foreach ($cells as $pos => $type) {
                $station[$color][$pos] = $this->getCell($color, $type, $pos);
            }
        }

        for ($j = -6; $j <= 20 ; $j++) {
            foreach ($station as $color => $cell) {
                $board .= str_repeat(' ', 5) . $cell[$j];
            }

            $board .= PHP_EOL;
        }

        return $board;
    }

    public function setPieces(array $pieces)
    {
        array_map(function($piece){
            if (!$piece instanceof Piece) {
                throw new RuntimeException(__FUNCTION__ . ': Only accept Piece in array', 1);
            }

            $this->setPiece($piece);
        }, $pieces);
    }

    public function setPiece(Piece $piece)
    {
        $this->pieces[$piece->color()][$piece->number()] = $piece;
    }

    private function getPiecesInJail(string $color)
    {
        $inJail = [];

        if (empty($this->pieces[$color])) {
            return $inJail;
        }

        foreach ($this->pieces[$color] as $piece) {
            if ($piece->isInJail()) {
                $inJail[] = $piece->name();
            }
        }

        return $inJail;
    }

    private function getPiecesInCell(string $color, int $position)
    {
        $inCell = [];

        for ($i = 0; $i < $this->colors; $i++) {
            $_color = chr($i + 65);

            if (empty($this->pieces[$_color])) {
                continue;
            }

            foreach ($this->pieces[$_color] as $piece) {
                if ($piece->isActive()) {
                    continue;
                }

                list($currentColor, $currentPosition) = $piece->position();

                if ($currentColor === $color && $currentPosition === $position) {
                    $inCell[] = $piece->name();
                }
            }
        }

        return $inCell;
    }
}

class Piece
{
    private $name;
    private $color;
    private $number;
    private $position = [];
    private $status = PIECE_STATUS_JAIL;

    public function __construct(string $color, int $number)
    {
        $this->color = $color;
        $this->number = $number;
        $this->name = sprintf('%s%d', $color, $number);
        $this->position = [$this->color(), -6];
    }

    public function name(): string
    {
        return $this->name;
    }

    public function color(): string
    {
           return $this->color;
    }

    public function number(): int
    {
        return $this->number;
    }

    public function setPosition(string $color, int $cell): void
    {
        list($currentColor, $currentCell) = $this->position();

        if ($color !== $this->color() && ord($color) < ord($currentColor)) {
            throw new RuntimeException('Another lap?', __LINE__);
        }

        if (!in_array($cell, range(-5, 20))) {
            throw new RuntimeException("Cell [{$color}:{$cell}] is not allowed, try using value between -5 and 20", __LINE__);
        }

        if ($color === $this->color() && $cell === 20) {
            $this->status = PIECE_STATUS_INACTIVE;
        } else {
            $this->status = PIECE_STATUS_ACTIVE;
        }

        $this->position = [$color, $cell];
    }

    public function position(): array
    {
        return $this->position;
    }

    public function activate(): void
    {
        $this->status = PIECE_STATUS_ACTIVE;

        $this->setPosition($this->color, 0);
    }

    public function isInJail(): bool
    {
        return $this->status === PIECE_STATUS_JAIL;
    }

    public function isActive(): bool
    {
        return $this->status === PIECE_STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === PIECE_STATUS_INACTIVE;
    }
}

class Player
{
    private $name;
    private $color;
    private $pieces = [];

    public function __construct(string $name, string $color)
    {
        $this->name = $name;
        $this->color = $color;
        $this->pieces = $this->getPieces($this->color);
    }

    private function getPieces($color)
    {
        $pieces = [];

        for ($i = 1; $i <= 4; $i++) {
            $pieces[$i] = new Piece($color, $i);
        }

        return $pieces;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function color(): string
    {
        return $this->color;
    }

    public function pieces(): array
    {
        return $this->pieces;
    }

    public function throwing(): Throwing
    {
        return new Throwing();
    }

    public function activatePiece(int $number): void
    {
        if (empty($this->pieces[$number])) {
            throw new RuntimeException("Piece [{$number}] not exist", __LINE__);
        }

        $this->pieces[$number]->activate();
    }

    public function movePiece(int $number, string $color, int $position): void
    {
        if (empty($this->pieces[$number])) {
            throw new RuntimeException("Piece [{$number}] not exist", __LINE__);
        }

        if ($this->pieces[$number]->isInJail()) {
            throw new RuntimeException("Piece [{$number}] is in jail", __LINE__);
        }

        if ($this->pieces[$number]->isInactive()) {
            throw new RuntimeException("Piece [{$number}] is out", __LINE__);
        }

        $this->pieces[$number]->setPosition($color, $position);
    }

    public function piecesInJail(): array
    {
        $piecesInJail = [];

        foreach ($this->pieces as $number => $piece) {
            if ($piece->isInJail()) {
                $piecesInJail[] = $number;
            }
        }

        return $piecesInJail;
    }
}

class Game
{
    private $board;
    private $players;
    private $rounds = 0;

    public function __construct(Board $board, array $players)
    {
        $this->board = $board;
        $this->players = $players;
    }

    public function start()
    {
        $winner = null;

        echo $this->board->get();

        readline();

        do {
            foreach ($this->players as $player) {
                $throwing = $player->throwing();

                echo $throwing->print();

                list($d1, $d2) = $throwing->dices();

                if (count($player->piecesInJail()) && $d1->number() === $d2->number()) {
                    $activate = 2;

                    if (in_array($d1->number(), [1, 6])) {
                        $activate = count($player->piecesInJail());
                    }

                    foreach ($player->piecesInJail() as $number) {
                        if ($activate > 0) {
                            $player->activatePiece($number);
                            $activate--;
                        }
                    }

                    var_dump($player->pieces());
                }

                $this->board->setPieces($player->pieces());

                echo $this->board->get();

                readline();
            }


            $this->rounds++;
        } while (!$winner && $this->rounds <= 5);
    }

    public function rounds(): int
    {
        return $this->rounds;
    }

    public function board(): Board
    {
        return $this->board;
    }

    public function players(): array
    {
        return $this->players;
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
    case 'b':
        $board = new Board($argv[2] ?? 4);
        echo $board->get() . PHP_EOL;
        $board->setPiece(new Piece('A', 1));
        echo $board->get() . PHP_EOL;
        $board->setPiece(new Piece('A', 1));
        echo $board->get() . PHP_EOL;
        $board->setPieces([new Piece('A', 1), new Piece('A', 2), new Piece('C', 1)]);
        echo $board->get() . PHP_EOL;
        $piece = new Piece('A', 3);
        $piece->setPosition('B', -4);
        $board->setPiece($piece);
        echo $board->get() . PHP_EOL;
        $piece->setPosition('D', 20);
        $board->setPiece($piece);
        echo $board->get() . PHP_EOL;
        break;
    case 'i':
        $piece = new Piece('A', 1);
        echo $piece->name() .  PHP_EOL;
        var_dump($piece->position()) .  PHP_EOL;
        $piece->setPosition('A', 5) .  PHP_EOL;
        var_dump($piece->position()) .  PHP_EOL;
        $piece->setPosition('A', 7) .  PHP_EOL;
        var_dump($piece->position()) .  PHP_EOL;
        $piece->setPosition('B', -4) .  PHP_EOL;
        var_dump($piece->position()) .  PHP_EOL;
        break;
    case 'l':
        $player = new Player('Freddie', 'A');
        echo $player->name() . PHP_EOL;
        echo $player->color() . PHP_EOL;
        var_dump($player->pieces()) . PHP_EOL;
        $player->activatePiece(1);
        $player->movePiece(1, 'A', 3);
        var_dump($player->pieces()) . PHP_EOL;
        break;
    case 'g':
        $game = new Game(new Board(4), [new Player('F', 'A')]);
        $game->start();
    default:
        break;
}
