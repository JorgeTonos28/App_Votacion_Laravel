<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class QrCodeService
{
    public function png(string $content, int $size = 360): string
    {
        return (new Builder(writer: new PngWriter, data: $content, encoding: new Encoding('UTF-8'), errorCorrectionLevel: ErrorCorrectionLevel::High, size: $size, margin: 12, roundBlockSizeMode: RoundBlockSizeMode::Margin))->build()->getString();
    }
}
