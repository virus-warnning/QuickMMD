<?php
namespace MediaWiki\Extension\QuickMMD;

class FileSystemUtils {

	/**
	 * TODO
	 *
	 * @param string $file_name TODO
	 * @param string $file_content TODO
	 * @param int $mode TODO
	 */
	public static function dumpDebugFile( $file_name, $file_content, $mode = 0 ) {
		if ( !ExtensionConstants::DUMP_DEBUG_FILES ) {
			return;
		}

		$debug_dir = sprintf( '%s/../debug', __DIR__ );
		if ( !is_dir( $debug_dir ) ) {
			mkdir( $debug_dir );
		}

		$target_file = sprintf( '%s/%s', $debug_dir, $file_name );
		if ( $mode === FILE_APPEND ) {
			file_put_contents( $target_file, $file_content . "\n", $mode );
		} else {
			file_put_contents( $target_file, $file_content );
		}
	}

	/**
	 * 取得人性化的檔案大小
	 *
	 * @param string $svgfile TODO
	 * @return TODO
	 */
	public static function getFriendlySize( $svgfile ) {
		static $unit_ch = [ 'B', 'KB', 'MB' ];

		$size = file_exists( $svgfile ) ? filesize( $svgfile ) : 0;
		$unit_lv = 0;
		while ( $size >= 1024 && $unit_lv <= 2 ) {
			$size /= 1024;
			$unit_lv++;
		}

		if ( $unit_lv == 0 ) {
			return sprintf( '%d %s', $size, $unit_ch[$unit_lv] );
		} else {
			return sprintf( '%.2f %s', $size, $unit_ch[$unit_lv] );
		}
	}

	/**
	 * 檔名迴避 Windows 不接受的字元
	 *
	 * @param string $unsafename TODO
	 * @return TODO
	 */
	public static function getSafeName( $unsafename ) {
		$safename = '';
		$slen = strlen( $unsafename );

		// escape non-ascii chars
		for ( $i = 0;$i < $slen;$i++ ) {
			$ch = $unsafename[$i];
			$cc = ord( $ch );
			if ( $cc < 32 || $cc > 127 ) {
				$safename .= sprintf( 'x%02x', $cc );
			} else {
				$safename .= $ch;
			}
		}

		return $safename;
	}

	/**
	 * 載入 SVG 轉檔摘要資訊, 以及容錯處理
	 *
	 * @param string $file_path
	 * @return TODO
	 */
	public static function loadSummary( $file_path ) {
		if ( is_file( $file_path ) ) {
			$summary = json_decode( file_get_contents( $file_path ), true );
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
	 *
	 * @param string $file_path TODO
	 * @param string $summary TODO
	 */
	public static function saveSummary( $file_path, $summary ) {
		$json_options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		$summary_ser = json_encode( $summary, $json_options );
		file_put_contents( $file_path, $summary_ser );
	}

}
