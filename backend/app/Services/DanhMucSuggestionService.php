<?php

namespace App\Services;

class DanhMucSuggestionService
{
    private function normalize(string $text): string
    {
        $value = trim(mb_strtolower($text, 'UTF-8'));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($value === false) {
            $value = trim(mb_strtolower($text, 'UTF-8'));
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value));
        return $value;
    }

    /**
     * Return danh muc goi y as array:
     * [
     *   ['danh_muc_code' => 'food', 'confidence' => 0.9, 'is_primary' => true],
     *   ...
     * ]
     */
    public function suggest(string $title, string $description): array
    {
        $raw = trim((string) ($title ?? '') . ' ' . (string) ($description ?? ''));
        $text = $this->normalize($raw);
        if ($text === '' && $raw === '') {
            return [];
        }

        $rawLower = mb_strtolower($raw, 'UTF-8');

        $candidates = [];

        $add = function (string $code, float $conf) use (&$candidates, $text) {
            $conf = max(0.0, min(1.0, $conf));
            if (!isset($candidates[$code]) || $conf > $candidates[$code]['confidence']) {
                $candidates[$code] = [
                    'danh_muc_code' => $code,
                    'confidence' => $conf,
                ];
            }
        };

        // Education (ASCII normalized)
        $eduKw = ['hoc tap', 'sach', 'sach giao khoa', 'sach but', 'sach vo', 'vo sach', 'but sach', 'vo', 'but', 'hoc phi', 'laptop', 'may tinh', 'giao khoa', 'dung hoc tap', 'do hoc tap'];
        foreach ($eduKw as $kw) {
            if (str_contains($text, $kw)) {
                $add('education', 0.85);
                break;
            }
        }
        // Education (Unicode — tránh mất "sách" khi iconv/preg làm sai chuỗi)
        $eduVi = ['sách bút', 'sách vở', 'vở sách', 'bút sách', 'sách', 'sách giáo khoa', 'vở', 'bút', 'học tập', 'học phí', 'laptop', 'máy tính', 'đồ dùng học tập', 'cần sách', 'xin sách', 'tặng sách', 'đồ học tập', 'dụng cụ học tập'];
        foreach ($eduVi as $w) {
            if (mb_strpos($rawLower, mb_strtolower($w, 'UTF-8')) !== false) {
                $add('education', 0.88);
                break;
            }
        }

        // Food (ASCII normalized)
        // Không dùng 'mi' đơn lẻ (dính "xe máy", "máy tính"...); dùng cụm rõ nghĩa
        $foodKw = ['gao', 'my tom', 'mi tom', 'thuc pham', 'do an', 'sua', 'thieu an', 'khong du an', 'doi', 'can thuc pham', 'can gao', 'can do an', 'luong thuc', 'thuc an', 'an uong', 'com ', ' com', 'banh mi'];
        foreach ($foodKw as $kw) {
            if ($text !== '' && str_contains($text, $kw)) {
                $add('food', 0.85);
                break;
            }
        }
        // Food (Unicode — tránh mất "đồ ăn" khi iconv/preg làm sai chuỗi)
        $foodVi = ['đồ ăn', 'thực phẩm', 'lương thực', 'gạo', 'mì tôm', 'sữa', 'đói', 'cần đồ ăn', 'cần thực phẩm', 'ăn uống'];
        foreach ($foodVi as $w) {
            if (mb_strpos($rawLower, mb_strtolower($w, 'UTF-8')) !== false) {
                $add('food', 0.88);
                break;
            }
        }

        // Clothes (ASCII)
        $clothesKw = ['quan ao', 'ao quan', 'quan jean', 'jean', 'ao khoac', 'giay', 'dep', 'do mac', 'chan ', ' man'];
        foreach ($clothesKw as $kw) {
            $k = $this->normalize($kw);
            if ($text !== '' && $k !== '' && str_contains($text, $k)) {
                $add('clothes', 0.8);
                break;
            }
        }
        // "ao"/"quan" riêng lẻ dễ dính nhầm (vd "tao"); chỉ dùng kèm ngữ cảnh hoặc tiếng Việt đủ dài
        $clothesVi = ['áo quần', 'quần áo', 'áo khoác', 'giày', 'dép', 'chăn', 'màn', 'đồ mặc'];
        foreach ($clothesVi as $w) {
            if (mb_strpos($rawLower, mb_strtolower($w, 'UTF-8')) !== false) {
                $add('clothes', 0.88);
                break;
            }
        }
        if ($text !== '' && preg_match('/\bao\b/', $text) && (str_contains($text, 'quan') || str_contains($text, 'mac') || str_contains($text, 'khoac'))) {
            $add('clothes', 0.82);
        }

        // Vehicle
        $vehicleKw = ['xe may', 'xe dap', 'xe lan', 'phuong tien', 'xe lan'];
        foreach ($vehicleKw as $kw) {
            $k = $this->normalize($kw);
            if ($k !== '' && str_contains($text, $k)) {
                $add('vehicle', 0.8);
                break;
            }
        }

        // Household / sinh hoat
        $houseKw = ['noi', 'bep', 'noi com', 'gia dung', 'do sinh hoat', 'vat dung sinh hoat', 'chay nha', 'nha bi chay', 'bi chay', 'mat nha', 'khong con nha', 'hoa hoan'];
        foreach ($houseKw as $kw) {
            $k = $this->normalize($kw);
            if ($text !== '' && $k !== '' && str_contains($text, $k)) {
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

        // Medical
        $medKw = ['thuoc', 'y te', 'phau thuat', 'vien phi', 'kham benh'];
        foreach ($medKw as $kw) {
            $k = $this->normalize($kw);
            if ($k !== '' && str_contains($text, $k)) {
                $add('medical', 0.8);
                break;
            }
        }

        // Emergency / cháy nhà: luôn cân nhắc đồ sinh hoạt + thực phẩm + quần áo (đủ 3 nhãn để match nhiều bài CHO)
        $fireAscii = $text !== '' && (
            str_contains($text, 'chay nha')
            || str_contains($text, 'nha bi chay')
            || str_contains($text, 'bi chay')
            || (str_contains($text, 'chay') && str_contains($text, 'nha'))
            || str_contains($text, 'hoa hoan')
            || str_contains($text, 'mat nha')
            || str_contains($text, 'mat het')
            || str_contains($text, 'khong con nha')
        );
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

    /**
     * Xử lý ngữ cảnh: "đã có …" → bỏ nhãn không còn nhu cầu;
     * "chỉ cần / chỉ thiếu / ưu tiên …" → tăng confidence cho nhãn đích.
     * (Không thể bao phủ mọi cách nói; bổ sung dần theo log thực tế.)
     */
    private function applyNegationAndPriorityRules(string $rawLower, array &$candidates): void
    {
        if ($candidates === []) {
            return;
        }

        // --- Bỏ nhãn: đã có / đã đủ (trừ khi "chưa có" cùng loại) ---
        if (
            preg_match('/đã\s*(có|đủ)\s+(quần\s*áo|áo\s*quần|đồ\s*mặc|giày|dép|chăn|màn)/u', $rawLower)
            && !preg_match('/chưa\s+có\s+(quần\s*áo|áo\s*quần|đồ\s*mặc)/u', $rawLower)
        ) {
            unset($candidates['clothes']);
        }
        if (
            preg_match('/đã\s*(có|đủ)\s+(thực\s*phẩm|đồ\s*ăn|lương\s*thực|gạo)/u', $rawLower)
            && !preg_match('/chưa\s+có\s+(thực\s*phẩm|đồ\s*ăn)/u', $rawLower)
        ) {
            unset($candidates['food']);
        }
        if (
            preg_match('/đã\s*(có|đủ)\s+(sách|vở|laptop|máy\s*tính|đồ\s*học\s*tập|học\s*phí)/u', $rawLower)
            && !preg_match('/chưa\s+có\s+(sách|vở|laptop)/u', $rawLower)
        ) {
            unset($candidates['education']);
        }
        if (
            preg_match('/đã\s*(có|đủ)\s+(xe\s*máy|xe\s*đạp|xe\s*lăn|phương\s*tiện)/u', $rawLower)
            && !preg_match('/chưa\s+có\s+(xe\s*máy|xe\s*đạp)/u', $rawLower)
        ) {
            unset($candidates['vehicle']);
        }
        if (preg_match('/đã\s*(có|đủ)\s+(nồi|bếp|đồ\s*gia\s*dụng|đồ\s*sinh\s*hoạt)/u', $rawLower)) {
            unset($candidates['household']);
        }
        if (preg_match('/đã\s*(có|đủ)\s+(thuốc|y\s*tế|viện\s*phí)/u', $rawLower)) {
            unset($candidates['medical']);
        }

        // Đủ rồi / đủ hết (khẩu ngữ)
        if (preg_match('/(đủ\s+rồi|đủ\s+hết)\s*(,|\.)?\s*(quần\s*áo|áo\s*quần|đồ\s*mặc)/u', $rawLower)
            || preg_match('/(quần\s*áo|áo\s*quần)\s*(đủ\s+rồi|đủ\s+hết)/u', $rawLower)) {
            unset($candidates['clothes']);
        }
        if (preg_match('/(đủ\s+rồi|đủ\s+hết)\s*(,|\.)?\s*(thực\s*phẩm|đồ\s*ăn|lương\s*thực|gạo)/u', $rawLower)) {
            unset($candidates['food']);
        }

        // Không cần / không nhận / không thiếu / khỏi (khẩu ngữ)
        if (preg_match('/(không\s+cần|không\s+nhận|không\s+thiếu|khỏi\s+cần|khỏi\s+nhận|khỏi\s+gửi)\s+(quần\s*áo|áo\s*quần|đồ\s*mặc|giày|dép)/u', $rawLower)) {
            unset($candidates['clothes']);
        }
        if (preg_match('/(không\s+cần|không\s+nhận|không\s+thiếu|khỏi\s+cần|khỏi\s+nhận)\s+(thực\s*phẩm|đồ\s*ăn|lương\s*thực|gạo)/u', $rawLower)) {
            unset($candidates['food']);
        }
        if (preg_match('/(không\s+cần|không\s+nhận)\s+(sách|vở|laptop|máy\s*tính)/u', $rawLower)) {
            unset($candidates['education']);
        }
        if (preg_match('/(không\s+cần|không\s+nhận)\s+(xe\s*máy|xe\s*đạp|phương\s*tiện)/u', $rawLower)) {
            unset($candidates['vehicle']);
        }

        // --- Ưu tiên / tập trung nhu cầu ---
        $boost = 0.94;
        $boostStrong = 0.95;

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
        if (preg_match('/(chỉ\s+)?(cần|nhận|xin|ưu\s*tiên)\s+(nhận\s+)?(xe\s*máy|xe\s*đạp|phương\s*tiện)/u', $rawLower)) {
            if (isset($candidates['vehicle'])) {
                $candidates['vehicle']['confidence'] = max((float) $candidates['vehicle']['confidence'], $boost);
            }
        }
        if (preg_match('/(chỉ\s+)?(cần|nhận|xin|ưu\s*tiên)\s+(nhận\s+)?(sách|vở|laptop|máy\s*tính)/u', $rawLower)) {
            if (isset($candidates['education'])) {
                $candidates['education']['confidence'] = max((float) $candidates['education']['confidence'], $boost);
            }
        }

        // "Chỉ thiếu …" = nhu cầu còn lại rõ (ưu tiên mạnh hơn)
        if (preg_match('/chỉ\s+thiếu\s+(thực\s*phẩm|đồ\s*ăn|lương\s*thực|gạo|mì|mì\s*tôm)/u', $rawLower)) {
            if (isset($candidates['food'])) {
                $candidates['food']['confidence'] = max((float) $candidates['food']['confidence'], $boostStrong);
            }
        }
        if (preg_match('/chỉ\s+thiếu\s+(quần\s*áo|áo\s*quần|đồ\s*mặc|giày|dép)/u', $rawLower)) {
            if (isset($candidates['clothes'])) {
                $candidates['clothes']['confidence'] = max((float) $candidates['clothes']['confidence'], $boostStrong);
            }
        }
        if (preg_match('/chỉ\s+thiếu\s+(sách|vở|laptop|máy\s*tính)/u', $rawLower)) {
            if (isset($candidates['education'])) {
                $candidates['education']['confidence'] = max((float) $candidates['education']['confidence'], $boostStrong);
            }
        }

        // Cần thêm / nhận thêm (ưu tiên vừa)
        if (preg_match('/(cần\s+thêm|nhận\s+thêm|xin\s+thêm)\s+(đồ\s*ăn|thực\s*phẩm|gạo)/u', $rawLower)) {
            if (isset($candidates['food'])) {
                $candidates['food']['confidence'] = max((float) $candidates['food']['confidence'], $boost);
            }
        }
        if (preg_match('/(cần\s+thêm|nhận\s+thêm)\s+(quần\s*áo|áo\s*quần)/u', $rawLower)) {
            if (isset($candidates['clothes'])) {
                $candidates['clothes']['confidence'] = max((float) $candidates['clothes']['confidence'], $boost);
            }
        }
    }
}

