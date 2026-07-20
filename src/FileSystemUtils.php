<?php
namespace MediaWiki\Extension\QuickMMD;

class FileSystemUtils {

    public static function dumpDebugFile($file_name, $file_content, $mode = 0) {
        if (!ExtensionConstants::DUMP_DEBUG_FILES) return;

        $debug_dir = sprintf('%s/../debug', __DIR__);
        if (!is_dir($debug_dir)) {
            mkdir($debug_dir);
        }
        
        $target_file = sprintf('%s/%s', $debug_dir, $file_name);
        if ($mode === FILE_APPEND) {
            file_put_contents($target_file, $file_content . "\n", $mode);
        } else {
            file_put_contents($target_file, $file_content);
        }
    }

    /**
     * 搜尋程式的完整路徑
     * - 只會在 init() 時呼叫
     * - Windows 以外的系統用 which 找
     * - Windows 待研究
     *
     * @param  $exec_name 程式名稱
     * @return 程式完整路徑
     */
    public static function findExecutable($exec_name) {
        if (PHP_OS !== 'WINNT') {
            // 先嘗試用 which 找看看
            $exec_path = exec("which $exec_name");
            // 不行再去特定 bin 目錄找
            if ($exec_path==='') {
                $search_dirs = array(
                    '/usr/bin',
                    '/usr/local/bin'
                );
                foreach ($search_dirs as $dir) {
                    $p = sprintf('%s/%s',$dir,$exec_name);
                    if (file_exists($p)) {
                        $exec_path = $p;
                        break;
                    }
                }
            }
        }

        // 產生沒找到的錯誤訊息
        if ($exec_path === '') {
            Hook::$sys_errors[] = sprintf('%s not found.', $exec_name);
            return '';
        }

        // 產生有找到但不能執行的錯誤訊息
        if (!is_executable($exec_path)) {
            Hook::$sys_errors[] = sprintf('%s is not executable.', $exec_name);
            return '';
        }

        return $exec_path;
    }

    /**
     * 取得人性化的檔案大小
     *
     * @param $size 位元組數
     */
    public static function getFriendlySize($svgfile) {
        static $unit_ch = array('B','KB','MB');

        $size = file_exists($svgfile) ? filesize($svgfile) : 0;
        $unit_lv = 0;
        while ($size>=1024 && $unit_lv<=2) {
            $size /= 1024;
            $unit_lv++;
        }

        if ($unit_lv==0) {
            return sprintf('%d %s', $size, $unit_ch[$unit_lv]);
        } else {
            return sprintf('%.2f %s', $size, $unit_ch[$unit_lv]);
        }
    }

    /**
     * 檔名迴避 Windows 不接受的字元
     *
     * @param $unsafename
     */
    public static function getSafeName($unsafename) {
        $safename = '';
        $slen = strlen($unsafename);

        // escape non-ascii chars
        for($i=0;$i<$slen;$i++) {
            $ch = $unsafename[$i];
            $cc = ord($ch);
            if ($cc<32 || $cc>127) {
                $safename .= sprintf('x%02x',$cc);
            } else {
                $safename .= $ch;
            }
        }

        return $safename;
    }

    /**
     * 載入 SVG 轉檔摘要資訊, 以及容錯處理
     */
    public static function loadSummary($file_path) {
        if (is_file($file_path)) {
            $summary = json_decode(file_get_contents($file_path), true);
        } else {
            $summary = [
                'md5' => '',
                'elapsed' => 0.0
            ];
        }
        return $summary;
    }

    /**
     * 儲存 SVG 轉檔摘要資訊
     * - md5     輸入值的 MD5 摘要
     * - elapsed 轉換 SVG 的消耗時間
     */
    public static function saveSummary($file_path, $summary) {
        $json_options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $summary_ser = json_encode($summary, $json_options);
        file_put_contents($file_path, $summary_ser);
    }

}