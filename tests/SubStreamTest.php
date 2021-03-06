<?php

namespace iqb\stream;

use iqb\ErrorMessage;
use PHPUnit\Framework\TestCase;

class SubStreamTest extends TestCase
{
    private $string;
    private $memoryStream;
    private $filename = __DIR__ . '/ipsum.txt';


    public function setUp()
    {
        $this->string = \file_get_contents($this->filename);
        $this->memoryStream = \fopen('php://memory', 'r+');
        if (\strlen($this->string) !== ($bytesWritten = \fwrite($this->memoryStream, $this->string))) {
            throw new \RuntimeException('Setup failed!: ' . $bytesWritten);
        }
    }


    public function offsetProvider()
    {
        $length = \filesize($this->filename);

        return [
            "SubStream for 0:$length" => [0, $length, 0, $length, 31, 32],
            "SubStream for 10:" . ($length-10) => [10, $length-10, 10, $length-10, 31, 32],
            "SubStream for " . ($length-53) . ':53' => [$length-53, 53, 0, 55, 31, 32],

            // Different seek variants
            "SubStream for " . ($length-350) . ':256 and SEEK_CUR' => [$length-350, 256, 0, 256, 1, 32, \SEEK_CUR],
            "SubStream for " . ($length-350) . ':256 and SEEK_END' => [$length-350, 256, -256, 0, 1, 32, \SEEK_END],
        ];
    }


    /**
     * @dataProvider offsetProvider
     */
    public function testSubStream(int $offset, int $length, int $iterationStart, int $iterationLimit, int $iterationStep, int $probeLength, int $seekMode = \SEEK_SET)
    {
        $subStream = \fopen(SUBSTREAM_SCHEME . '://' . $offset . ':' . $length . '/' . (int)$this->memoryStream, 'r');
        $this->assertTrue(\is_resource($subStream));

        $referenceName = \tempnam(\sys_get_temp_dir(), 'phpunit_substream_ref');
        $referenceStream = \fopen($referenceName, 'r+');
        $this->assertSame($length, \fwrite($referenceStream, \substr($this->string, $offset, $length)));
        \fclose($referenceStream);
        $referenceStream = \fopen($referenceName, 'r');

        for ($i=$iterationStart; $i<$iterationLimit; $i+=$iterationStep) {
            \fseek($referenceStream, $i, $seekMode);
            \fseek($subStream, $i, $seekMode);
            $this->assertEquals($string = \fread($referenceStream, $probeLength), \fread($subStream, $probeLength), "Iteration: $i");
        }
    }


    public function testReadWithoutSeek()
    {
        $offset = 500;
        $length = 1000;

        $subStream = \fopen(SUBSTREAM_SCHEME . '://' . $offset . ':' . $length . '/' . (int)$this->memoryStream, 'r');
        $this->assertTrue(\is_resource($subStream));

        $referenceStream = \fopen('php://memory', 'r+');
        $this->assertSame($length, \fwrite($referenceStream, \substr($this->string, $offset, $length)));
        \fseek($referenceStream, 0);

        $this->assertEquals(\fread($subStream, 2*$length), \fread($referenceStream, 2*$length));
    }
}
