<?php

namespace App\Services;

class ImageMappingService
{
    /**
     * @param string $source Path to the raw 'white' mode image
     * @param string $dest Destination path for the mapped image
     * @param array $components The 'diagnostic_components' array from the AI payload
     * @return string Path to the rendered image
     */
    public function generateMap(string $source, string $dest, array $components, int $outW = 460, int $outH = 548): string
    {
        if (is_file($dest) && filemtime($dest) >= filemtime($source)) {
            return realpath($dest) ?: $dest;
        }

        $geo = $this->detectAnalyserAnchor($source);
        $base = $dest . '.base.jpg';
        $this->prepareAnchoredFrame($source, $base, $outW, $outH, $geo);

        $img = imagecreatefromjpeg($base);
        imagealphablending($img, true);

        $spec = $this->frameSpec($geo, $outW, $outH);
        $f = $this->faceGeometry($geo);
        $cx = $f['cx'];
        $top = $f['top'];
        $fh = $f['h'];
        $hw = $f['halfW'];

        $p = function (float $rx, float $ry) use ($cx, $top, $fh, $hw, $spec) {
            return $this->mapPoint($cx + $rx * $hw, $top + $ry * $fh, $spec);
        };

        // Colors
        $goldFill = imagecolorallocatealpha($img, 246, 184, 82, 114);
        $goldStroke = imagecolorallocatealpha($img, 255, 213, 143, 35);
        $purpleFill = imagecolorallocatealpha($img, 183, 123, 255, 113);
        $purpleStroke = imagecolorallocatealpha($img, 225, 188, 255, 30);
        $coralFill = imagecolorallocatealpha($img, 255, 76, 76, 95);
        $coralStroke = imagecolorallocatealpha($img, 255, 150, 130, 20);
        $cyanFill = imagecolorallocatealpha($img, 42, 220, 255, 82);
        $cyanStroke = imagecolorallocatealpha($img, 150, 245, 255, 18);
        $dotColor = imagecolorallocatealpha($img, 255, 224, 160, 65);

        // Pre-defined poly shapes mapping
        $shapes = [
            'forehead' => [
                'points' => [$p(-0.65, 0.07), $p(0.65, 0.07), $p(0.78, 0.25), $p(0.62, 0.31), $p(-0.62, 0.31), $p(-0.78, 0.25)],
                'thickness' => 3
            ],
            'right_cheek' => [ // Patient Right = Viewer Left
                'points' => [$p(-0.92, 0.48), $p(-0.38, 0.43), $p(-0.06, 0.53), $p(-0.24, 0.76), $p(-0.72, 0.74), $p(-0.98, 0.60)],
                'thickness' => 3
            ],
            'left_cheek' => [ // Patient Left = Viewer Right
                'points' => [$p(0.92, 0.48), $p(0.38, 0.43), $p(0.06, 0.53), $p(0.24, 0.76), $p(0.72, 0.74), $p(0.98, 0.60)],
                'thickness' => 3
            ],
            'chin' => [
                'points' => [$p(-0.42, 0.75), $p(0.42, 0.75), $p(0.50, 0.985), $p(-0.50, 0.985)],
                'thickness' => 2
            ],
            'right_periocular' => [ // Patient Right = Viewer Left
                'points' => [$p(-0.73, 0.50), $p(-0.18, 0.47), $p(-0.02, 0.55), $p(-0.25, 0.63), $p(-0.66, 0.62), $p(-0.85, 0.56)],
                'thickness' => 3
            ],
            'left_periocular' => [ // Patient Left = Viewer Right
                'points' => [$p(0.73, 0.50), $p(0.18, 0.47), $p(0.02, 0.55), $p(0.25, 0.63), $p(0.66, 0.62), $p(0.85, 0.56)],
                'thickness' => 3
            ]
        ];

        $drawFlags = [
            'forehead' => null,
            'right_cheek' => null,
            'left_cheek' => null,
            'chin' => null,
            'right_periocular' => null,
            'left_periocular' => null,
        ];
        
        $activeInflammationSides = [];
        $barrierImpairmentSides = [];

        foreach ($components as $c) {
            $regions = $c['regions'] ?? [];
            $role = $c['component_role'] ?? '';
            $family = $c['family_code'] ?? '';

            $colorScheme = ['fill' => $goldFill, 'stroke' => $goldStroke];
            if ($family === 'barrier_or_scale_modifier' || $family === 'periocular_hyperpigmentation') {
                $colorScheme = ['fill' => $purpleFill, 'stroke' => $purpleStroke];
                if (in_array('lower_perioral_chin', $regions)) {
                    $colorScheme = ['fill' => $cyanFill, 'stroke' => $cyanStroke];
                }
            }

            if (in_array('forehead_hairline', $regions) || in_array('glabella', $regions)) {
                $drawFlags['forehead'] = $colorScheme;
            }
            if (in_array('right_malar_cheek', $regions)) {
                $drawFlags['right_cheek'] = $colorScheme;
            }
            if (in_array('left_malar_cheek', $regions)) {
                $drawFlags['left_cheek'] = $colorScheme;
            }
            if (in_array('lower_perioral_chin', $regions)) {
                $drawFlags['chin'] = $colorScheme;
            }
            if (in_array('right_periocular', $regions)) {
                $drawFlags['right_periocular'] = $colorScheme;
            }
            if (in_array('left_periocular', $regions)) {
                $drawFlags['left_periocular'] = $colorScheme;
            }
            
            if ($family === 'active_inflammatory_process') {
                if (in_array('right_malar_cheek', $regions)) $activeInflammationSides[] = 'right';
                if (in_array('left_malar_cheek', $regions)) $activeInflammationSides[] = 'left';
            }
            if ($family === 'barrier_or_scale_modifier') {
                if (in_array('lower_perioral_chin', $regions)) $barrierImpairmentSides[] = 'chin';
            }
        }

        if (!array_filter($drawFlags)) {
            $drawFlags['forehead'] = ['fill' => $goldFill, 'stroke' => $goldStroke];
            $drawFlags['right_cheek'] = ['fill' => $goldFill, 'stroke' => $goldStroke];
            $drawFlags['left_cheek'] = ['fill' => $goldFill, 'stroke' => $goldStroke];
            $drawFlags['chin'] = ['fill' => imagecolorallocatealpha($img, 246, 184, 82, 118), 'stroke' => $goldStroke];
            $drawFlags['right_periocular'] = ['fill' => $purpleFill, 'stroke' => $purpleStroke];
            $drawFlags['left_periocular'] = ['fill' => $purpleFill, 'stroke' => $purpleStroke];
            $activeInflammationSides[] = 'left';
            $barrierImpairmentSides[] = 'chin';
        }

        foreach ($drawFlags as $region => $scheme) {
            if ($scheme) {
                $this->drawPoly($img, $shapes[$region]['points'], $scheme['fill'], $scheme['stroke'], $shapes[$region]['thickness']);
                
                if ($region === 'right_cheek' || $region === 'right_periocular') {
                    $this->drawDot($img, $p(-0.67, 0.57), $dotColor);
                    $this->drawDot($img, $p(-0.52, 0.64), $dotColor);
                    $this->drawDot($img, $p(-0.34, 0.60), $dotColor);
                }
                if ($region === 'left_cheek' || $region === 'left_periocular') {
                    $this->drawDot($img, $p(0.67, 0.57), $dotColor);
                    $this->drawDot($img, $p(0.52, 0.64), $dotColor);
                    $this->drawDot($img, $p(0.34, 0.60), $dotColor);
                }
            }
        }

        if (in_array('right', $activeInflammationSides)) { // Patient Right = Viewer Left
            [$rx, $ry] = $p(-0.55, 0.66);
            imagefilledellipse($img, (int)$rx, (int)$ry, 42, 42, $coralFill);
            imagesetthickness($img, 3);
            imageellipse($img, (int)$rx, (int)$ry, 42, 42, $coralStroke);
        }
        if (in_array('left', $activeInflammationSides)) { // Patient Left = Viewer Right
            [$rx, $ry] = $p(0.55, 0.66);
            imagefilledellipse($img, (int)$rx, (int)$ry, 42, 42, $coralFill);
            imagesetthickness($img, 3);
            imageellipse($img, (int)$rx, (int)$ry, 42, 42, $coralStroke);
        }

        if (in_array('chin', $barrierImpairmentSides)) {
            [$cxm, $cym] = $p(0.03, 0.985);
            imagefilledellipse($img, (int)$cxm, (int)$cym, 30, 30, $cyanFill);
            imagesetthickness($img, 3);
            imageellipse($img, (int)$cxm, (int)$cym, 30, 30, $cyanStroke);
            imagesetthickness($img, 1);
        }

        imagesetthickness($img, 1);
        $this->saveJpeg($img, $dest, 92);
        imagedestroy($img);
        @unlink($base);
        return $dest;
    }

    private function drawDot($img, array $pt, int $color): void
    {
        imagefilledellipse($img, (int)$pt[0], (int)$pt[1], 8, 8, $color);
    }

    private function drawPoly($img, array $points, int $fill, int $stroke, int $thickness = 3): void
    {
        $flat = [];
        foreach ($points as [$x, $y]) {
            $flat[] = (int)round($x);
            $flat[] = (int)round($y);
        }
        imagefilledpolygon($img, $flat, $fill);
        imagesetthickness($img, $thickness);
        imagepolygon($img, $flat, $stroke);
        imagesetthickness($img, 1);
    }

    private function loadImage(string $path)
    {
        $raw = file_get_contents($path);
        if ($raw === false) throw new \RuntimeException("Could not read image: {$path}");
        $img = @imagecreatefromstring($raw);
        if (!$img) throw new \RuntimeException("Unsupported image: {$path}");
        if (function_exists('exif_read_data') && preg_match('/\.jpe?g$/i', $path)) {
            $exif = @exif_read_data($path);
            $orientation = (int)($exif['Orientation'] ?? 1);
            if ($orientation === 3) $img = imagerotate($img, 180, 0);
            elseif ($orientation === 6) $img = imagerotate($img, -90, 0);
            elseif ($orientation === 8) $img = imagerotate($img, 90, 0);
        }
        return $img;
    }

    private function rgbAt($img, int $x, int $y): array
    {
        $rgb = imagecolorat($img, $x, $y);
        return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
    }

    private function isLikelySkin(int $r, int $g, int $b): bool
    {
        $y = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        $cb = 128 - 0.168736 * $r - 0.331264 * $g + 0.5 * $b;
        $cr = 128 + 0.5 * $r - 0.418688 * $g - 0.081312 * $b;
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        return $y > 28 && $cb >= 68 && $cb <= 148 && $cr >= 116 && $cr <= 195 && ($max - $min) > 9 && $r >= $b * 0.92;
    }

    private function detectAnalyserAnchor(string $source): array
    {
        $img = $this->loadImage($source);
        $w = imagesx($img);
        $h = imagesy($img);
        $x0 = (int)round($w * 0.28);
        $x1 = (int)round($w * 0.72);
        $y0 = (int)round($h * 0.78);
        $y1 = (int)round($h * 0.98);
        $ratios = [];
        for ($y = $y0; $y <= $y1; $y += 2) {
            $count = 0;
            $total = 0;
            for ($x = $x0; $x <= $x1; $x += 3) {
                [$r, $g, $b] = $this->rgbAt($img, $x, $y);
                $max = max($r, $g, $b);
                $min = min($r, $g, $b);
                $lum = ($r + $g + $b) / 3;
                if (($max - $min) <= 24 && $lum >= 45 && $lum <= 205 && abs($r - $g) <= 18 && abs($g - $b) <= 18) $count++;
                $total++;
            }
            $ratios[$y] = $total ? $count / $total : 0;
        }
        $holderTop = null;
        $ys = array_keys($ratios);
        for ($i = 0; $i < count($ys) - 4; $i++) {
            $ok = true;
            for ($j = 0; $j < 5; $j++) if (($ratios[$ys[$i + $j]] ?? 0) < 0.28) {
                $ok = false;
                break;
            }
            if ($ok) {
                $holderTop = $ys[$i];
                break;
            }
        }
        $found = $holderTop !== null;
        if (!$found) $holderTop = (int)round($h * 0.895);
        $holderTop = max((int)round($h * 0.80), min((int)round($h * 0.95), $holderTop));

        $sumX = 0.0;
        $n = 0;
        $bandEnd = min($h - 1, $holderTop + (int)round($h * 0.065));
        for ($y = $holderTop; $y <= $bandEnd; $y += 3) {
            for ($x = $x0; $x <= $x1; $x += 3) {
                [$r, $g, $b] = $this->rgbAt($img, $x, $y);
                $max = max($r, $g, $b);
                $min = min($r, $g, $b);
                $lum = ($r + $g + $b) / 3;
                if (($max - $min) <= 24 && $lum >= 45 && $lum <= 205 && abs($r - $g) <= 18 && abs($g - $b) <= 18) {
                    $sumX += $x;
                    $n++;
                }
            }
        }
        $centerX = $n ? $sumX / $n : $w / 2;
        $faceBounds = $this->detectFaceVerticalBounds($img, $centerX, $w, $h);
        imagedestroy($img);
        return ['width' => $w, 'height' => $h, 'center_x' => $centerX, 'holder_top' => $holderTop, 'holder_found' => $found, 'face_top' => $faceBounds['top'], 'face_chin' => $faceBounds['chin']];
    }

    private function detectFaceVerticalBounds($img, float $centerX, int $w, int $h): array
    {
        $x0 = max(0, (int)round($centerX - 0.17 * $w));
        $x1 = min($w - 1, (int)round($centerX + 0.17 * $w));
        $ratios = [];
        for ($y = (int)round(0.07 * $h); $y <= (int)round(0.995 * $h); $y += 2) {
            $skin = 0;
            $total = 0;
            for ($x = $x0; $x <= $x1; $x += 3) {
                [$r, $g, $b] = $this->rgbAt($img, $x, $y);
                if ($this->isLikelySkin($r, $g, $b)) $skin++;
                $total++;
            }
            $ratios[$y] = $total ? $skin / $total : 0;
        }
        $smooth = function (int $y) use ($ratios, $h): float {
            $sum = 0.0;
            $n = 0;
            for ($dy = -6; $dy <= 6; $dy += 2) {
                $yy = max(0, min($h - 1, $y + $dy));
                if (isset($ratios[$yy])) {
                    $sum += $ratios[$yy];
                    $n++;
                }
            }
            return $n ? $sum / $n : 0.0;
        };
        $top = (int)round(0.16 * $h);
        for ($y = (int)round(0.08 * $h); $y <= (int)round(0.38 * $h); $y += 2) {
            $ok = true;
            for ($k = 0; $k < 10; $k += 2) {
                if ($smooth($y + $k) < 0.32) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $top = $y;
                break;
            }
        }
        $chin = (int)round(0.985 * $h);
        $lastHigh = null;
        for ($y = (int)round(0.55 * $h); $y <= (int)round(0.995 * $h); $y += 2) {
            if ($smooth($y) >= 0.34) $lastHigh = $y;
        }
        if ($lastHigh !== null) $chin = $lastHigh;
        $top = max((int)round(0.10 * $h), min((int)round(0.27 * $h), $top));
        $chin = max((int)round(0.77 * $h), min((int)round(0.995 * $h), $chin));
        if ($chin - $top < 0.58 * $h) {
            $top = max((int)round(0.10 * $h), (int)round($chin - 0.68 * $h));
        }
        return ['top' => $top, 'chin' => $chin];
    }

    private function frameSpec(array $geo, int $outW, int $outH): array
    {
        $w = $geo['width'];
        $h = $geo['height'];
        $top = 0.0;
        $bottom = (float)$h;
        $cropH = max(1.0, $bottom - $top);
        $targetAspect = $outW / $outH;
        $cropW = min((float)$w, $cropH * $targetAspect);
        $left = max(0.0, min($w - $cropW, $geo['center_x'] - $cropW / 2));
        $scale = min($outW / $cropW, $outH / $cropH);
        $drawW = $cropW * $scale;
        $drawH = $cropH * $scale;
        return ['x' => $left, 'y' => $top, 'w' => $cropW, 'h' => $cropH, 'scale' => $scale, 'dx' => ($outW - $drawW) / 2, 'dy' => ($outH - $drawH) / 2, 'outW' => $outW, 'outH' => $outH];
    }

    private function mapPoint(float $x, float $y, array $spec): array
    {
        return [$spec['dx'] + ($x - $spec['x']) * $spec['scale'], $spec['dy'] + ($y - $spec['y']) * $spec['scale']];
    }

    private function saveJpeg($img, string $path, int $quality = 91): string
    {
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        imagejpeg($img, $path, $quality);
        return realpath($path) ?: $path;
    }

    private function prepareAnchoredFrame(string $source, string $dest, int $outW, int $outH, array $geo): string
    {
        if (is_file($dest) && filemtime($dest) >= filemtime($source)) return realpath($dest) ?: $dest;
        $src = $this->loadImage($source);
        $spec = $this->frameSpec($geo, $outW, $outH);
        $dst = imagecreatetruecolor($outW, $outH);
        $bg = imagecolorallocate($dst, 1, 7, 15);
        imagefill($dst, 0, 0, $bg);
        imagecopyresampled($dst, $src, (int)round($spec['dx']), (int)round($spec['dy']), (int)round($spec['x']), (int)round($spec['y']), (int)round($spec['w'] * $spec['scale']), (int)round($spec['h'] * $spec['scale']), (int)round($spec['w']), (int)round($spec['h']));
        $path = $this->saveJpeg($dst, $dest);
        imagedestroy($src);
        imagedestroy($dst);
        return $path;
    }

    private function faceGeometry(array $geo): array
    {
        $h = $geo['height'];
        $top = (float)($geo['face_top'] ?? (0.16 * $h));
        $chin = (float)($geo['face_chin'] ?? ($geo['holder_top'] - 0.012 * $h));
        $faceH = max(0.58 * $h, $chin - $top);
        $top = $chin - $faceH;
        $halfW = 0.325 * $geo['width'];
        return ['cx' => $geo['center_x'], 'top' => $top, 'chin' => $chin, 'h' => $faceH, 'halfW' => $halfW, 'w' => $halfW * 2];
    }
}
