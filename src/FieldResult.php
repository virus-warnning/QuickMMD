<?php
namespace MediaWiki\Extension\QuickMMD;

class FieldResult {
	public function __construct(
		public string $Field,
		public mixed $FilteredValue,
		public string $ErrorMessage,
		public string $WarningMessage,
	) {
	}

	/**
	 * TODO
	 *
	 * @param string $field_name TODO
	 * @param string $value TODO
	 * @return TODO
	 */
	public static function createOkay( $field_name, $value ) {
		return new self( $field_name, $value, '', '' );
	}

	/**
	 * TODO
	 *
	 * @param string $field_name TODO
	 * @param string $value TODO
	 * @param string $warning_message TODO
	 * @return TODO
	 */
	public static function createWarning( $field_name, $value, $warning_message ) {
		return new self( $field_name, $value, '', $warning_message );
	}

	/**
	 * TODO
	 *
	 * @param string $field_name TODO
	 * @param string $error_message TODO
	 * @return TODO
	 */
	public static function createError( $field_name, $error_message ) {
		return new self( $field_name, null, $error_message, '' );
	}
}
