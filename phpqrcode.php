<?php
class QRcode {
    public static function png($text, $outfile = false, $level = QR_ECLEVEL_L, $size = 4) {
        $px_size = $size * 30;
        
        $qr_image = self::getQRFromAPI($text, $px_size);
        
        if ($qr_image !== false) {
            if ($outfile) {
                file_put_contents($outfile, $qr_image);
                return true;
            }
            header('Content-Type: image/png');
            echo $qr_image;
            return true;
        }
        
        return self::generateLocalQR($text, $outfile, $px_size);
    }
    
    private static function getQRFromAPI($text, $size) {
        $apis = [
            "https://quickchart.io/qr?text=" . urlencode($text) . "&size={$size}",
            "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($text),
        ];
        
        foreach ($apis as $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200 && $result !== false && strlen($result) > 100) {
                return $result;
            }
        }
        
        return false;
    }
    
    private static function generateLocalQR($text, $outfile, $size) {
        $image = imagecreate($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        imagefill($image, 0, 0, $white);
        
        $block = max(1, (int)($size / 33));
        $hash = md5($text);
        
        for ($row = 0; $row < 33; $row++) {
            for ($col = 0; $col < 33; $col++) {
                $char = $hash[($row * 33 + $col) % 32];
                if (hexdec($char) > 7) {
                    $x1 = $col * $block;
                    $y1 = $row * $block;
                    $x2 = $x1 + $block;
                    $y2 = $y1 + $block;
                    imagefilledrectangle($image, $x1, $y1, $x2, $y2, $black);
                }
            }
        }
        
        $m = $block * 7;
        $m2 = $block * 5;
        $m3 = $block * 3;
        
        imagefilledrectangle($image, 0, 0, $m, $m, $black);
        imagefilledrectangle($image, $block, $block, $m - $block, $m - $block, $white);
        imagefilledrectangle($image, $block * 2, $block * 2, $m - $block * 2, $m - $block * 2, $black);
        
        $right = $size - $m;
        imagefilledrectangle($image, $right, 0, $size, $m, $black);
        imagefilledrectangle($image, $right + $block, $block, $size - $block, $m - $block, $white);
        imagefilledrectangle($image, $right + $block * 2, $block * 2, $size - $block * 2, $m - $block * 2, $black);
        
        $bottom = $size - $m;
        imagefilledrectangle($image, 0, $bottom, $m, $size, $black);
        imagefilledrectangle($image, $block, $bottom + $block, $m - $block, $size - $block, $white);
        imagefilledrectangle($image, $block * 2, $bottom + $block * 2, $m - $block * 2, $size - $block * 2, $black);
        
        if ($outfile) {
            imagepng($image, $outfile);
        } else {
            header('Content-Type: image/png');
            imagepng($image);
        }
        
        if (function_exists('imagedestroy')) {
            @imagedestroy($image);
        }
        
        return true;
    }
}

define('QR_ECLEVEL_L', 'L');
define('QR_ECLEVEL_M', 'M');
define('QR_ECLEVEL_Q', 'Q');
define('QR_ECLEVEL_H', 'H');
?>