<?php
/**
 * @package axy\sourcemap
 * @author Oleg Grigoriev <go.vasac@gmail.com>
 */

namespace axy\sourcemap\parsing;

use axy\sourcemap\PosMap;
use axy\sourcemap\errors\InvalidMappings;

/**
 * A line in the generated content
 */
class Line
{
    /**
     * The constructor
     *
     * @param int $num
     *        the line number
     * @param \axy\sourcemap\PosMap[] $positions [optional]
     *        a list of ordered positions
     */
    public function __construct($num, array $positions = null)
    {
        $this->num = $num;
        $this->positions = $positions ?: [];
    }

    /**
     * Loads the positions list from a numeric array
     *
     * @param int $num
     * @param \axy\sourcemap\PosMap[] $positions
     * @return \axy\sourcemap\parsing\Line
     */
    public static function loadFromPlainList($num, array $positions)
    {
        $rPositions = [];
        foreach ($positions as $pos) {
            $rPositions[$pos->generated->column] = $pos;
        }
        return new self($num, $rPositions);
    }

    /**
     * Loads the positions list from a mappings line
     *
     * @param int $num
     * @param string $lMappings
     * @param \axy\sourcemap\parsing\SegmentParser $parser
     * @param \axy\sourcemap\parsing\Context $context
     * @return \axy\sourcemap\parsing\Line
     * @throws \axy\sourcemap\errors\InvalidMappings
     */
    public static function loadFromMappings($num, $lMappings, SegmentParser $parser, Context $context)
    {
        $positions = [];
        $names = $context->names;
        $files = $context->sources;
        $parser->nextLine($num);
        foreach (explode(',', $lMappings) as $segment) {
            $pos = $parser->parse($segment);
            $positions[$pos->generated->column] = $pos;
            $source = $pos->source;
            $fi = $source->fileIndex;
            if ($fi !== null) {
                if (isset($files[$fi])) {
                    $source->fileName = $files[$fi];
                } else {
                    $message = 'Invalid segment "'.$segment.'" (source offset '.$fi.')';
                    throw new InvalidMappings($message);
                }
                $ni = $source->nameIndex;
                if ($ni !== null) {
                    if (isset($names[$ni])) {
                        $source->name = $names[$ni];
                    } else {
                        $message = 'Invalid segment "'.$segment.'" (name offset '.$ni.')';
                        throw new InvalidMappings($message);
                    }
                }
            }
        }
        return new self($num, $positions);
    }

    /**
     * Returns the line number
     *
     * @return int
     */
    public function getNum()
    {
        return $this->num;
    }

    /**
     * Returns the positions list
     *
     * @return \axy\sourcemap\PosMap[]
     */
    public function getPositions()
    {
        return $this->positions;
    }

    /**
     * Packs the line to the mappings
     *
     * @param \axy\sourcemap\parsing\SegmentParser $parser
     * @return string
     */
    public function pack(SegmentParser $parser)
    {
        ksort($this->positions);
        $segments = [];
        foreach ($this->positions as $pos) {
            $segments[] = $parser->pack($pos);
        }
        return implode(',', $segments);
    }

    /**
     * Adds a position to the mappings
     *
     * @param \axy\sourcemap\PosMap $position
     */
    public function addPosition(PosMap $position)
    {
        $this->positions[$position->generated->column] = $position;
    }

    /**
     * Removes a position
     *
     * @param int $column
     *        the generated column number
     * @return bool
     *         the position was found and removed
     */
    public function removePosition($column)
    {
        $removed = isset($this->positions[$column]);
        if ($removed) {
             unset($this->positions[$column]);
        }
        return $removed;
    }

    /**
     * Renames a file name
     *
     * @param int $fileIndex
     * @param string $newFileName
     */
    public function renameFile($fileIndex, $newFileName)
    {
        $fileIndex = (int)$fileIndex;
        foreach ($this->positions as $position) {
            $source = $position->source;
            if ($source->fileIndex === $fileIndex) {
                $source->fileName = $newFileName;
            }
        }
    }

    /**
     * Renames a symbol name
     *
     * @param int $nameIndex
     * @param string $newName
     */
    public function renameName($nameIndex, $newName)
    {
        $nameIndex = (int)$nameIndex;
        foreach ($this->positions as $position) {
            $source = $position->source;
            if ($source->nameIndex === $nameIndex) {
                $source->name = $newName;
            }
        }
    }

    /**
     * Removes a file
     *
     * @param int $fileIndex
     * @return bool
     */
    public function removeFile($fileIndex)
    {
        $removed = false;
        $positions = $this->positions;
        foreach ($positions as $cn => $position) {
            $source = $position->source;
            $fi = $source->fileIndex;
            if ($fi === $fileIndex) {
                $removed = true;
                unset($this->positions[$cn]);
            } elseif ($fi > $fileIndex) {
                $source->fileIndex--;
            }
        }
        return $removed;
    }

    /**
     * Checks if the line is empty
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->positions);
    }

    /**
     * {@inheritdoc}
     */
    public function __clone()
    {
        foreach ($this->positions as &$pos) {
            $pos = clone $pos;
        }
        unset($pos);
    }

    /**
     * @var int
     */
    private $num;

    /**
     * @var \axy\sourcemap\PosMap[]
     */
    private $positions;
}
