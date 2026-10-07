<?php
/**
 * 資料檢查
 *
 * 資料檢查方式原則上走 MediaWiki 生態系內建機制
 * 不過下列幾項內建機制做不到, 需要自己補足
 *
 * - 字串長度檢查
 * - bool 字串檢查
 * - bool 字串轉 bool 值
 */

namespace MediaWiki\Extension\QuickMMD;

class Validator {

	// Mermaid 語法內容上限 (1M)
	public const MAX_SYNTAX_SIZE = 1048576;

	// 命名最短字數
	public const NAME_LBOUND = 2;

	// 命名最長字數
	public const NAME_UBOUND = 25;

	/**
	 * 各欄位資料限制定義
	 *
	 * @return TODO
	 */
	public static function getDescriptor() {
		$boolSpec = [
			'type' => 'select',
			'options' => [
				'true' => 'true',
				'false' => 'false',
			],
			'default' => 'false',
			'filter-callback' => static function ( $value ) {
				return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			}
		];
		return [
			'syntax' => [
				'type' => 'text',
				'validation-callback' => static function ( $value ) {
					if ( mb_strlen( $value, 'UTF-8' ) > self::MAX_SYNTAX_SIZE ) {
						return false;
					}
					return true;
				}
			],
			'name' => [
				'type' => 'text',
				'validation-callback' => static function ( $value ) {
					$slen = mb_strlen( $value, 'UTF-8' );
					if ( $slen < self::NAME_LBOUND || $slen > self::NAME_UBOUND ) {
						return false;
					}
					return true;
				}
			],
			'theme' => [
				'type' => 'select',
				'options' => [
					'Default' => 'default',
					'Dark'    => 'dark',
					'Forest'  => 'forest',
					'Neutral' => 'neutral',
					'Base'    => 'base',
				],
				'default' => 'default',
			],
			'dump-env' => $boolSpec,
			'dump-mmd' => $boolSpec,
		];
	}

	/**
	 * 生成套版後的錯誤/警示訊息
	 *
	 * @param string $fieldName TODO
	 * @param string $fieldSpec TODO
	 * @return TODO
	 */
	public static function buildValidationMessage( $fieldName, $fieldSpec ) {
		$langKey = sprintf( 'validation-%s', $fieldName );
		$template = wfMessage( $langKey )->plain();
		$message = '';
		switch ( $langKey ) {
			case 'validation-syntax':
				$message = sprintf( $template, self::MAX_SYNTAX_SIZE );
				break;
			case 'validation-name':
				$message = sprintf( $template, self::NAME_LBOUND, self::NAME_UBOUND );
				break;
			case 'validation-theme':
				$values = array_values( $fieldSpec['options'] );
				$message = sprintf( $template, implode( ', ', $values ) );
				break;
			default:
				$message = $template;
		}
		return $message;
	}

	/**
	 * 檢查所有欄位的資料
	 *
	 * @param array $rawArgs Raw input arguments to validate
	 * @return array{Passed: bool, Fields: FieldResult[]}
	 */
	public static function validate( array $rawArgs ) {
		$descriptor = self::getDescriptor();
		$passed = true;
		$fieldResults = [];

		foreach ( $descriptor as $name => $fieldSpec ) {
			$rawValue = $rawArgs[$name] ?? null;

			// No value provided & has default → use default, skip validation
			if ( $rawValue === null && isset( $fieldSpec['default'] ) ) {
				$fieldResults[] = FieldResult::CreateOkay( $name, $fieldSpec['default'] );
				continue;
			}

			// No value provided & no default → error
			if ( $rawValue === null ) {
				$message = self::buildValidationMessage( $name, $fieldSpec );
				$fieldResults[] = FieldResult::CreateError( $name, $message );
				$passed = false;
				continue;
			}

			$valid = true;

			// Run validation-callback if defined
			if ( isset( $fieldSpec['validation-callback'] ) ) {
				$valid = call_user_func( $fieldSpec['validation-callback'], $rawValue );
			}

			// For select type, ensure value is in allowed options
			if ( $valid && $fieldSpec['type'] === 'select' ) {
				$valid = in_array( $rawValue, array_values( $fieldSpec['options'] ), true );
			}

			if ( $valid !== true ) {
				$message = self::buildValidationMessage( $name, $fieldSpec );
				if ( isset( $fieldSpec['default'] ) ) {
					$fieldResults[] = FieldResult::CreateWarning( $name, $fieldSpec['default'], $message );
				} else {
					$fieldResults[] = FieldResult::CreateError( $name, $message );
					$passed = false;
				}
				continue;
			}

			// Apply filter-callback if defined (e.g. bool string → bool)
			$filtered = $rawValue;
			if ( isset( $fieldSpec['filter-callback'] ) ) {
				$filtered = call_user_func( $fieldSpec['filter-callback'], $rawValue );
			}

			$fieldResults[] = FieldResult::CreateOkay( $name, $filtered );
		}

		return [
			'Passed' => $passed,
			'Fields' => $fieldResults
		];
	}
}
