<?php

namespace Bundle\LichessBundle\Notation;
use Bundle\LichessBundle\Document\Game;
use Bundle\LichessBundle\Document\Piece;
use Bundle\LichessBundle\Chess\Board;
use Bundle\LichessBundle\Chess\Analyser;
use Bundle\LichessBundle\Chess\PieceFilter;

class Forsyth
{
    /**
     * Transform a game to standard Forsyth Edwards Notation
     * http://en.wikipedia.org/wiki/Forsyth%E2%80%93Edwards_Notation
     */
    public static function export(Game $game, $positionOnly = false)
    {
        $board = $game->getBoard();
        $emptySquare = 0;
        $forsyth = '';

        for($y = 8; $y > 0; $y --) {
            for($x = 1; $x < 9; $x ++) {
                if ($piece = $board->getPieceByPos($x, $y)) {
                    if ($emptySquare) {
                        $forsyth .= $emptySquare;
                        $emptySquare = 0;
                    }
                    $forsyth .= self::pieceToForsyth($piece);
                } else {
                    ++$emptySquare;
                }
            }
            if ($emptySquare) {
                $forsyth .= $emptySquare;
                $emptySquare = 0;
            }
            $forsyth .= '/';
        }

        $forsyth = trim($forsyth, '/');

        if ($positionOnly) {
            return $forsyth;
        }

        // b ou w to indicate turn
        $forsyth .= ' ';
        $forsyth .= substr($game->getTurnColor(), 0, 1);

        // possibles castles
        $forsyth .= ' ';
        $hasCastle = false;
        $analyser = new Analyser($board);
        foreach($game->getPlayers() as $player) {
            if ($analyser->canCastleKingSide($player)) {
                $hasCastle = true;
                $forsyth .= $player->isWhite() ? 'K' : 'k';
            }
            if ($analyser->canCastleQueenSide($player)) {
                $hasCastle = true;
                $forsyth .= $player->isWhite() ? 'Q' : 'q';
            }
        }
        if(!$hasCastle) {
            $forsyth .= '-';
        }

        // en passant
        $enPassant = '-';
        foreach(PieceFilter::filterClass(PieceFilter::filterAlive($game->getPieces()), 'Pawn') as $piece) {
            if($piece->getFirstMove() === ($game->getTurns() - 1)) {
                $color = $piece->getPlayer()->getColor();
                $y = $piece->getY();
                if(($color === 'white' && 4 === $y) || ($color === 'black' && 5 === $y)) {
                    $enPassant = Board::posToKey($piece->getX(), 'white' === $color ? $y - 1 : $y + 1);
                    break;
                }
            }
        }
        $forsyth .= ' '.$enPassant;

        // Halfmove clock: This is the number of halfmoves since the last pawn advance or capture.
        // This is used to determine if a draw can be claimed under the fifty-move rule.
        $forsyth .= ' '.$game->getHalfmoveClock();

        // Fullmove number: The number of the full move. It starts at 1, and is incremented after Black's move.
        $forsyth .= ' '.$game->getFullMoveNumber();

        return $forsyth;
    }

    /**
     * Create and position pieces of the game for the forsyth string
     *
     * @param Game $game
     * @param string $forsyth
     * @return Game $game
     */
    public static function import(Game $game, $forsyth)
    {
        $x = 1;
        $y = 8;

        $board = $game->getBoard();
        $forsyth = str_replace('/', '', preg_replace('#\s*([\w\d/]+)\s.+#i', '$1', $forsyth));
        $pieces = array('white' => array(), 'black' => array());

        for($itForsyth = 0, $forsythLen = strlen($forsyth); $itForsyth < $forsythLen; $itForsyth++) {
            $letter = $forsyth{$itForsyth};

            if (is_numeric($letter)) {
                $x += intval($letter);
            } else {
                $color = ctype_lower($letter) ? 'black' : 'white';
                switch(strtolower($letter)) {
                    case 'p': $class = 'Pawn'; break;
                    case 'r': $class = 'Rook'; break;
                    case 'n': $class = 'Knight'; break;
                    case 'b': $class = 'Bishop'; break;
                    case 'q': $class = 'Queen'; break;
                    case 'k': $class = 'King'; break;
                }
                $pieces[$color][] = self::createPiece($class, $x, $y);
                ++$x;
            }

            if($x > 8) {
                $x = 1;
                --$y;
            }
        }

        foreach ($game->getPlayers() as $player) {
            $player->setPieces($pieces[$player->getColor()]);
        }
        $game->ensureDependencies();
    }

    public static function diffToMove(Game $game, $forsyth)
    {
        $moves = array(
            'from'  => array(),
            'to'    => array()
        );

        $x = 1;
        $y = 8;

        $board = $game->getBoard();
        $forsyth = str_replace('/', '', preg_replace('#\s*([\w\d/]+)\s.+#i', '$1', $forsyth));

        for($itForsyth = 0, $forsythLen = strlen($forsyth); $itForsyth < $forsythLen; $itForsyth++) {
            $letter = $forsyth{$itForsyth};
            $key = Board::posToKey($x, $y);

            if (is_numeric($letter)) {
                for($x = $x, $max = $x+intval($letter); $x < $max; $x++) {
                    $_key = Board::posToKey($x, $y);
                    if (!$board->getSquareByKey($_key)->isEmpty()) {
                        $moves['from'][] = $_key;
                    }
                }
            } else {
                $color = ctype_lower($letter) ? 'black' : 'white';

                switch(strtolower($letter)) {
                case 'p': $class = 'Pawn'; break;
                case 'r': $class = 'Rook'; break;
                case 'n': $class = 'Knight'; break;
                case 'b': $class = 'Bishop'; break;
                case 'q': $class = 'Queen'; break;
                case 'k': $class = 'King'; break;
                }

                if ($piece = $board->getPieceByKey($key)) {
                    if($class != $piece->getClass() || $color !== $piece->getColor()) {
                        $moves['to'][] = $key;
                    }
                }
                else {
                    $moves['to'][] = $key;
                }

                ++$x;
            }

            if($x > 8) {
                $x = 1;
                --$y;
            }
        }

        if(empty($moves['from'])) {
            return null;
        }
        elseif(1 === count($moves['from']) && 1 === count($moves['to'])) {
            $from = $moves['from'][0];
            $to = $moves['to'][0];
        }
        // two pieces moved: it's a castle
        elseif(2 === count($moves['from']) && 2 === count($moves['to'])) {
            if ($board->getPieceByKey($moves['from'][0])->isClass('King')) {
                $from = $moves['from'][0];
            } else {
                $from = $moves['from'][1];
            }
            if (in_array($board->getSquareByKey($moves['to'][0])->getX(), array(3, 7))) {
                $to = $moves['to'][0];
            } else {
                $to = $moves['to'][1];
            }
        }
        // two from, one to: it may be a "en passant"
        elseif(
            2 === count($moves['from']) &&
            1 === count($moves['to']) &&
            $board->getPieceByKey($moves['from'][0])->isClass('Pawn') &&
            $board->getPieceByKey($moves['from'][1])->isClass('Pawn') &&
            ! $board->hasPieceByKey($moves['to'][0])) {
                if($moves['from'][0]{0} === $moves['to'][0]{0}) {
                    $from = $moves['from'][1];
                } else {
                    $from = $moves['from'][0];
                }
                $to = $moves['to'][0];
            } else {
                throw new \RuntimeException(sprintf('Forsyth:diffToMove game:%s, variant:%s, moves: %s, forsyth:%s',
                    $game->getId(),
                    $game->getVariantName(),
                    str_replace("\n", " ", var_export($moves, true)),
                    $forsyth
                ));
            }

        return $from.' '.$to;
    }

    protected static function pieceToForsyth(Piece $piece)
    {
        $class = $piece->getClass();

        if ('Knight' === $class) {
            $notation = 'N';
        } else {
            $notation = $class{0};
        }

        if('black' === $piece->getColor()) {
            $notation = strtolower($notation);
        }

        return $notation;
    }

    /**
     * @return Piece
     */
    protected static function createPiece($class, $x, $y)
    {
        $fullClass = 'Bundle\\LichessBundle\\Document\\Piece\\'.$class;

        $piece = new $fullClass($x, $y);

        return $piece;
    }
}
