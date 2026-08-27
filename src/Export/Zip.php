<?php

namespace App\Export;

/**
 * A minimal ZIP writer, enough to build an Office Open XML package, avoiding
 * a dependency on PHP's zip extension. Files are stored uncompressed.
 */
final class Zip
{
    /** @var array<int, array{name: string, data: string}> */
    private array $files = [];

    public function add(string $name, string $contents): void
    {
        $this->files[] = ['name' => $name, 'data' => $contents];
    }

    public function toString(): string
    {
        [$date, $time] = self::stamp();

        $local = '';
        $central = '';
        $offset = 0;

        foreach ($this->files as $file) {
            $name = $file['name'];
            $data = $file['data'];
            $size = strlen($data);
            $crc = crc32($data);

            // Local file header; method 0 ("stored") means sizes are equal.
            $header = "PK\x03\x04"
                . pack('v', 20)          // version needed
                . pack('v', 0)           // flags
                . pack('v', 0)           // method: stored
                . pack('v', $time)
                . pack('v', $date)
                . pack('V', $crc)
                . pack('V', $size)
                . pack('V', $size)
                . pack('v', strlen($name))
                . pack('v', 0)           // extra field length
                . $name;

            $local .= $header . $data;

            $central .= "PK\x01\x02"
                . pack('v', 20)          // version made by
                . pack('v', 20)          // version needed
                . pack('v', 0)
                . pack('v', 0)
                . pack('v', $time)
                . pack('v', $date)
                . pack('V', $crc)
                . pack('V', $size)
                . pack('V', $size)
                . pack('v', strlen($name))
                . pack('v', 0)           // extra
                . pack('v', 0)           // comment
                . pack('v', 0)           // disk number
                . pack('v', 0)           // internal attributes
                . pack('V', 32)          // external attributes: archive
                . pack('V', $offset)
                . $name;

            $offset += strlen($header) + $size;
        }

        $count = count($this->files);

        return $local . $central . "PK\x05\x06"
            . pack('v', 0)               // this disk
            . pack('v', 0)               // disk with the central directory
            . pack('v', $count)
            . pack('v', $count)
            . pack('V', strlen($central))
            . pack('V', $offset)
            . pack('v', 0);              // comment length
    }

    /** @return array{0: int, 1: int} DOS date and time */
    private static function stamp(): array
    {
        $now = getdate();

        $date = (($now['year'] - 1980) << 9) | ($now['mon'] << 5) | $now['mday'];
        $time = ($now['hours'] << 11) | ($now['minutes'] << 5) | ((int) ($now['seconds'] / 2));

        return [$date, $time];
    }
}
