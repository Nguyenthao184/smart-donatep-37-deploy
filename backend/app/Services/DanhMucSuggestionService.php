<?php

namespace App\Services;

class DanhMucSuggestionService
{
    /**
     * Chỉ chuẩn hóa ASCII - không phải Unicode
     */
    private function normalize(string $text): string
    {
        $value = trim(mb_strtolower($text, 'UTF-8'));
        $value = preg_replace('/\s+/', ' ', $value);
        return $value;
    }

    /**
     * Chuẩn hóa để so sánh ASCII (bỏ dấu)
     */
    private function normalizeAscii(string $text): string
    {
        $value = trim(mb_strtolower($text, 'UTF-8'));
        // Chuẩn hóa dấu tiếng Việt thủ công (chính xác hơn iconv)
        $value = $this->removeVietnameseAccents($value);
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return $value;
    }

    /**
     * Bỏ dấu tiếng Việt một cách chính xác
     */
    private function removeVietnameseAccents(string $str): string
    {
        $accents = [
            'á' => 'a', 'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
            'ă' => 'a', 'ắ' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
            'â' => 'a', 'ấ' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
            'é' => 'e', 'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
            'ê' => 'e', 'ế' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'í' => 'i', 'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
            'ô' => 'o', 'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
            'ơ' => 'o', 'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
            'ư' => 'u', 'ứ' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
            'ý' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
            'đ' => 'd',
        ];

        foreach ($accents as $from => $to) {
            $str = str_replace($from, $to, $str);
        }

        return $str;
    }

    public function suggest(string $title, string $description): array
    {
        $raw = trim((string) ($title ?? '') . ' ' . (string) ($description ?? ''));
        
        if ($raw === '') {
            return [];
        }

        $rawLower = mb_strtolower($raw, 'UTF-8');
        $textNormalized = $this->normalize($raw); // Unicode - cho Unicode keywords
        $textAscii = $this->normalizeAscii($raw);  // ASCII - cho ASCII keywords

        $candidates = [];

        $add = function (string $code, float $conf) use (&$candidates) {
            $conf = max(0.0, min(1.0, $conf));
            if (!isset($candidates[$code]) || $conf > $candidates[$code]['confidence']) {
                $candidates[$code] = [
                    'danh_muc_code' => $code,
                    'confidence' => $conf,
                ];
            }
        };

        // ========== EDUCATION ==========
        $eduKw = ['hoc tap', 'sach', 'sach giao khoa', 'sach but', 'sach vo', 'vo sach', 'but sach', 'vo', 'but', 'hoc phi', 'laptop', 'may tinh', 'giao khoa', 'dung hoc tap', 'do hoc tap'];
        foreach ($eduKw as $kw) {
            if (str_contains($textAscii, $kw)) {
                $add('education', 0.85);
                break;
            }
        }

        $eduVi = ['sách bút', 'sách vở', 'vở sách', 'bút sách', 'sách', 'sách giáo khoa', 'vở', 'bút', 'học tập', 'học phí', 'laptop', 'máy tính', 'đồ dùng học tập', 'cần sách', 'xin sách', 'tặng sách', 'đồ học tập', 'dụng cụ học tập'];
        foreach ($eduVi as $w) {
            if (mb_strpos($rawLower, mb_strtolower($w, 'UTF-8')) !== false) {
                $add('education', 0.88);
                break;
            }
        }

        // ========== FOOD ==========
        $foodKw = ['gao', 'my tom', 'mi tom', 'thuc pham', 'do an', 'sua', 'thieu an', 'khong du an', 'doi', 'can thuc pham', 'can gao', 'can do an', 'luong thuc', 'thuc an', 'an uong', 'com', 'banh mi'];
        foreach ($foodKw as $kw) {
            if (str_contains($textAscii, $kw)) {
                $add('food', 0.85);
                break;
            }
        }

        $foodVi = ['đồ ăn', 'thực phẩm', 'lương thực', 'gạo', 'mì tôm', 'sữa', 'đói', 'cần đồ ăn', 'cần thực phẩm', 'ăn uống'];
        foreach ($foodVi as $w) {
            if (mb_strpos($rawLower, mb_strtolower($w, 'UTF-8')) !== false) {
                $add('food', 0.88);
                break;
            }
        }

        // ========== CLOTHES ==========
        $clothesKw = ['quan ao', 'ao quan', 'quan jean', 'jean', 'ao khoac', 'giay', 'dep', 'do mac', 'chan', 'man'];
        foreach ($clothesKw as $kw) {
            if (str_contains($textAscii, $kw)) {
                $add('clothes', 0.8);
                break;
            }
        }

        $clothesVi = ['áo quần', 'quần áo', 'áo khoác', 'giày', 'dép', 'chăn', 'màn', 'đồ mặc'];
        foreach ($clothesVi as $w) {
            if (mb_strpos($rawLower, mb_strtolower($w, 'UTF-8')) !== false) {
                $add('clothes', 0.88);
                break;
            }
        }

        // ========== VEHICLE ==========
        $vehicleKw = ['xe may', 'xe dap', 'xe lan', 'phuong tien'];
        foreach ($vehicleKw as $kw) {
            if (str_contains($textAscii, $kw)) {
                $add('vehicle', 0.8);
                break;
            }
        }

        // ========== HOUSEHOLD ==========
        $houseKw = ['noi', 'bep', 'noi com', 'gia dung', 'do sinh hoat', 'vat dung sinh hoat', 'chay nha', 'nha bi chay', 'bi chay', 'mat nha', 'khong con nha', 'hoa hoan'];
        foreach ($houseKw as $kw) {
            if (str_contains($textAscii, $kw)) {
                $add('household', 0.8);
                break;
            }
        }

        $houseVi = ['cháy nhà', 'nhà bị cháy', 'hỏa hoạn', 'mất nhà', 'mất hết đồ'];
        foreach ($houseVi as $w) {
            if (mb_strpos($rawLower, mb_strtolower($w, 'UTF-8')) !== false) {
                $add('household', 0.85);
                break;
            }
        }

        // ========== MEDICAL ==========
        $medKw = ['thuoc', 'y te', 'phau thuat', 'vien phi', 'kham benh'];
        foreach ($medKw as $kw) {
            if (str_contains($textAscii, $kw)) {
                $add('medical', 0.8);
                break;
            }
        }

        // ========== EMERGENCY (Cháy nhà) ==========
        $fireAscii = str_contains($textAscii, 'chay nha')
            || str_contains($textAscii, 'nha bi chay')
            || str_contains($textAscii, 'bi chay')
            || (str_contains($textAscii, 'chay') && str_contains($textAscii, 'nha'))
            || str_contains($textAscii, 'hoa hoan')
            || str_contains($textAscii, 'mat nha')
            || str_contains($textAscii, 'mat het')
            || str_contains($textAscii, 'khong con nha');

        $fireUnicode = (bool) preg_match(
            '/cháy\s*nhà|nhà\s*bị\s*cháy|hỏa\s*hoạn|mất\s*nhà|mất\s*hết\s*đồ/u',
            $rawLower
        );

        if ($fireAscii || $fireUnicode) {
            $add('household', 0.9);
            $add('clothes', 0.78);
            $add('food', 0.78);
        }

        $this->applyNegationAndPriorityRules($rawLower, $candidates);

        // Sort by confidence desc and mark primary
        $rows = array_values($candidates);
        usort($rows, fn($a, $b) => ($b['confidence'] <=> $a['confidence']));
        
        if (!$rows) {
            return [];
        }

        $primaryCode = $rows[0]['danh_muc_code'];
        foreach ($rows as &$row) {
            $row['is_primary'] = $row['danh_muc_code'] === $primaryCode;
        }
        unset($row);

        return $rows;
    }

    private function applyNegationAndPriorityRules(string $rawLower, array &$candidates): void
    {
        if ($candidates === []) {
            return;
        }

        // Bỏ nhãn khi "đã có"
        if (preg_match('/đã\s*(có|đủ)\s+(quần\s*áo|áo\s*quần|đồ\s*mặc|giày|dép|chăn|màn)/u', $rawLower)
            && !preg_match('/chưa\s+có\s+(quần\s*áo|áo\s*quần|đồ\s*mặc)/u', $rawLower)) {
            unset($candidates['clothes']);
        }
        if (preg_match('/đã\s*(có|đủ)\s+(thực\s*phẩm|đồ\s*ăn|lương\s*thực|gạo)/u', $rawLower)
            && !preg_match('/chưa\s+có\s+(thực\s*phẩm|đồ\s*ăn)/u', $rawLower)) {
            unset($candidates['food']);
        }
        if (preg_match('/đã\s*(có|đủ)\s+(sách|vở|laptop|máy\s*tính|đồ\s*học\s*tập|học\s*phí)/u', $rawLower)
            && !preg_match('/chưa\s+có\s+(sách|vở|laptop)/u', $rawLower)) {
            unset($candidates['education']);
        }
        if (preg_match('/đã\s*(có|đủ)\s+(xe\s*máy|xe\s*đạp|xe\s*lăn|phương\s*tiện)/u', $rawLower)
            && !preg_match('/chưa\s+có\s+(xe\s*máy|xe\s*đạp)/u', $rawLower)) {
            unset($candidates['vehicle']);
        }
        if (preg_match('/đã\s*(có|đủ)\s+(nồi|bếp|đồ\s*gia\s*dụng|đồ\s*sinh\s*hoạt)/u', $rawLower)) {
            unset($candidates['household']);
        }
        if (preg_match('/đã\s*(có|đủ)\s+(thuốc|y\s*tế|viện\s*phí)/u', $rawLower)) {
            unset($candidates['medical']);
        }

        // Không cần / không nhận
        if (preg_match('/(không\s+cần|không\s+nhận|không\s+thiếu|khỏi\s+cần|khỏi\s+nhận|khỏi\s+gửi)\s+(quần\s*áo|áo\s*quần|đồ\s*mặc|giày|dép)/u', $rawLower)) {
            unset($candidates['clothes']);
        }
        if (preg_match('/(không\s+cần|không\s+nhận|không\s+thiếu|khỏi\s+cần|khỏi\s+nhận)\s+(thực\s*phẩm|đồ\s*ăn|lương\s*thực|gạo)/u', $rawLower)) {
            unset($candidates['food']);
        }

        // Ưu tiên
        $boost = 0.94;
        if (preg_match('/(chỉ\s+)?(cần|nhận|xin|ưu\s*tiên|mong\s+muốn|mong\s+nhận)\s+(nhận\s+)?(đồ\s*ăn|thực\s*phẩm|lương\s*thực|gạo|mì|mì\s*tôm)/u', $rawLower)) {
            if (isset($candidates['food'])) {
                $candidates['food']['confidence'] = max((float) $candidates['food']['confidence'], $boost);
            }
        }
        if (preg_match('/(chỉ\s+)?(cần|nhận|xin|ưu\s*tiên|mong\s+muốn)\s+(nhận\s+)?(quần\s*áo|áo\s*quần|đồ\s*mặc|giày|dép)/u', $rawLower)) {
            if (isset($candidates['clothes'])) {
                $candidates['clothes']['confidence'] = max((float) $candidates['clothes']['confidence'], $boost);
            }
        }
    }
}