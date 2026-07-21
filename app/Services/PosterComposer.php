<?php

namespace App\Services;

use App\Models\Promotion;
use GdImage;

/**
 * Turns an AI-generated background (asked for a text-free product/brand
 * scene — see PromotionController::buildPosterPrompt()) into a finished
 * promotional poster by compositing the real title/price/discount text
 * with GD. Diffusion models render prices and short marketing copy
 * unreliably (garbled digits, misspellings), which is unacceptable for a
 * real price tag — so every number and word visible on the poster comes
 * from this class, not the model.
 */
class PosterComposer
{
    private const WIDTH = 1200;

    private const HEIGHT = 900;

    private const BRAND_BLUE = [59, 130, 246];

    private const ACCENT_GOLD = [251, 191, 36];

    private const ACCENT_RED = [239, 68, 68];

    public function compose(string $backgroundBytes, Promotion $promotion): string
    {
        $background = @imagecreatefromstring($backgroundBytes);

        $canvas = $background
            ? $this->coverResize($background, self::WIDTH, self::HEIGHT)
            : $this->fallbackBackground();

        imagesavealpha($canvas, true);
        imagealphablending($canvas, true);

        $this->drawBrandBadge($canvas);
        $this->drawDiscountBadge($canvas, (float) $promotion->discount_percentage);
        $this->drawBottomPanel($canvas, $promotion);

        ob_start();
        imagejpeg($canvas, null, 92);
        $bytes = ob_get_clean();

        imagedestroy($canvas);
        if ($background) {
            imagedestroy($background);
        }

        return $bytes;
    }

    /**
     * Crop-then-scale ("object-fit: cover") so the AI image fills the
     * whole poster canvas without distortion, regardless of what aspect
     * ratio the model returned.
     */
    private function coverResize(GdImage $source, int $targetW, int $targetH): GdImage
    {
        $srcW = imagesx($source);
        $srcH = imagesy($source);
        $srcRatio = $srcW / $srcH;
        $targetRatio = $targetW / $targetH;

        if ($srcRatio > $targetRatio) {
            $cropH = $srcH;
            $cropW = (int) round($srcH * $targetRatio);
        } else {
            $cropW = $srcW;
            $cropH = (int) round($srcW / $targetRatio);
        }

        $srcX = (int) (($srcW - $cropW) / 2);
        $srcY = (int) (($srcH - $cropH) / 2);

        $canvas = imagecreatetruecolor($targetW, $targetH);
        imagecopyresampled($canvas, $source, 0, 0, $srcX, $srcY, $targetW, $targetH, $cropW, $cropH);

        return $canvas;
    }

    /**
     * Used when the AI call fails (unconfigured, timeout, model error) so
     * "Generate Again" always produces something rather than a hard error —
     * a brand-blue gradient stands in for the missing product photography.
     */
    private function fallbackBackground(): GdImage
    {
        $canvas = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        [$r1, $g1, $b1] = [29, 78, 216];
        [$r2, $g2, $b2] = self::BRAND_BLUE;

        for ($y = 0; $y < self::HEIGHT; $y++) {
            $ratio = $y / self::HEIGHT;
            $color = imagecolorallocate(
                $canvas,
                (int) ($r1 + ($r2 - $r1) * $ratio),
                (int) ($g1 + ($g2 - $g1) * $ratio),
                (int) ($b1 + ($b2 - $b1) * $ratio),
            );
            imageline($canvas, 0, $y, self::WIDTH, $y, $color);
        }

        return $canvas;
    }

    private function drawBrandBadge(GdImage $canvas): void
    {
        $blue = imagecolorallocate($canvas, ...self::BRAND_BLUE);
        imagefilledrectangle($canvas, 32, 32, 260, 92, $blue);
        $this->centeredText($canvas, 'FOODCITY', $this->font('bold'), 22, 32, 260, 32, 92, [255, 255, 255]);
    }

    private function drawDiscountBadge(GdImage $canvas, float $discountPercent): void
    {
        $cx = self::WIDTH - 130;
        $cy = 130;
        $radius = 100;

        $red = imagecolorallocate($canvas, ...self::ACCENT_RED);
        imagefilledellipse($canvas, $cx, $cy, $radius * 2, $radius * 2, $red);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagesetthickness($canvas, 4);
        imageellipse($canvas, $cx, $cy, $radius * 2 - 8, $radius * 2 - 8, $white);

        $discountLabel = '-'.number_format($discountPercent, 0).'%';
        $this->centeredText($canvas, $discountLabel, $this->font('bold'), 34, $cx - $radius, $cx + $radius, $cy - 34, $cy + 6, [255, 255, 255]);
        $this->centeredText($canvas, 'OFF', $this->font('semibold'), 18, $cx - $radius, $cx + $radius, $cy + 10, $cy + 40, [255, 255, 255]);
    }

    private function drawBottomPanel(GdImage $canvas, Promotion $promotion): void
    {
        $panelTop = 560;

        // Vertical alpha gradient (transparent -> ~85% black) rather than a
        // flat rectangle, so the panel blends into the photo instead of
        // reading as a hard-edged box.
        for ($y = $panelTop; $y < self::HEIGHT; $y++) {
            $ratio = ($y - $panelTop) / (self::HEIGHT - $panelTop);
            $alpha = (int) (127 - min($ratio * 108, 108)); // 127 = fully transparent .. ~19 = ~85% opaque
            $color = imagecolorallocatealpha($canvas, 15, 23, 42, max(0, $alpha));
            imageline($canvas, 0, $y, self::WIDTH, $y, $color);
        }

        $left = 48;
        $right = self::WIDTH - 48;

        // "LIMITED OFFER · TODAY ONLY" pill
        $pillY = $panelTop + 24;
        $gold = imagecolorallocate($canvas, ...self::ACCENT_GOLD);
        $pillWidth = $this->textWidth($this->font('semibold'), 16, 'LIMITED OFFER  ·  TODAY ONLY') + 40;
        imagefilledrectangle($canvas, $left, $pillY, $left + $pillWidth, $pillY + 34, $gold);
        $this->leftText($canvas, 'LIMITED OFFER  ·  TODAY ONLY', $this->font('semibold'), 16, $left + 20, $pillY + 24, [15, 23, 42]);

        // Title (wrapped to at most 2 lines)
        $titleY = $pillY + 76;
        $titleLines = $this->wrapText($promotion->title, $this->font('bold'), 40, self::WIDTH - $left - 380);
        foreach (array_slice($titleLines, 0, 2) as $i => $line) {
            $this->leftText($canvas, $line, $this->font('bold'), 40, $left, $titleY + ($i * 50), [255, 255, 255]);
        }

        // Price row: strikethrough current price, then large offer price
        $priceY = $titleY + (min(count($titleLines), 2) * 50) + 50;
        $currentLabel = 'Rs '.number_format((float) $promotion->current_price, 2);
        $currentWidth = $this->textWidth($this->font('regular'), 24, $currentLabel);
        $this->leftText($canvas, $currentLabel, $this->font('regular'), 24, $left, $priceY, [203, 213, 225]);
        $strikeY = $priceY - 8;
        $lineColor = imagecolorallocate($canvas, 203, 213, 225);
        imagesetthickness($canvas, 2);
        imageline($canvas, $left, $strikeY, $left + $currentWidth, $strikeY, $lineColor);

        $offerLabel = 'Rs '.number_format((float) $promotion->offer_price, 2);
        $this->leftText($canvas, $offerLabel, $this->font('bold'), 48, $left + $currentWidth + 24, $priceY + 8, [...self::ACCENT_GOLD]);

        // "BUY NOW" CTA button, bottom-right
        $ctaLabel = 'BUY NOW';
        $ctaWidth = $this->textWidth($this->font('bold'), 20, $ctaLabel) + 56;
        $ctaHeight = 56;
        $ctaX = $right - $ctaWidth;
        $ctaY = self::HEIGHT - 48 - $ctaHeight;
        $blue = imagecolorallocate($canvas, ...self::BRAND_BLUE);
        imagefilledrectangle($canvas, $ctaX, $ctaY, $right, $ctaY + $ctaHeight, $blue);
        $this->centeredText($canvas, $ctaLabel, $this->font('bold'), 20, $ctaX, $right, $ctaY, $ctaY + $ctaHeight, [255, 255, 255]);
    }

    private function font(string $weight): string
    {
        return resource_path('fonts/Poppins-'.match ($weight) {
            'bold' => 'Bold',
            'semibold' => 'SemiBold',
            default => 'Regular',
        }.'.ttf');
    }

    private function textWidth(string $font, int $size, string $text): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return abs($box[4] - $box[0]);
    }

    /** @return array<int, string> */
    private function wrapText(string $text, string $font, int $size, int $maxWidth): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = trim($current.' '.$word);
            if ($this->textWidth($font, $size, $candidate) > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function leftText(GdImage $canvas, string $text, string $font, int $size, int $x, int $baselineY, array $rgb): void
    {
        $color = imagecolorallocate($canvas, ...$rgb);
        imagettftext($canvas, $size, 0, $x, $baselineY, $color, $font, $text);
    }

    private function centeredText(GdImage $canvas, string $text, string $font, int $size, int $x1, int $x2, int $y1, int $y2, array $rgb): void
    {
        $width = $this->textWidth($font, $size, $text);
        $box = imagettfbbox($size, 0, $font, $text);
        $textHeight = abs($box[5] - $box[1]);

        $x = $x1 + (($x2 - $x1 - $width) / 2);
        $baselineY = $y1 + (($y2 - $y1 + $textHeight) / 2);

        $color = imagecolorallocate($canvas, ...$rgb);
        imagettftext($canvas, $size, 0, (int) $x, (int) $baselineY, $color, $font, $text);
    }
}
