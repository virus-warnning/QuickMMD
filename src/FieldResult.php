<?php
namespace MediaWiki\Extension\QuickMMD;

class FieldResult {
    public function __construct(
        public string $Field,
        public mixed $FilteredValue,
        public string $ErrorMessage,
        public string $WarningMessage,
    ) {}

    public static function CreateOkay($field_name, $value) {
        return new self($field_name, $value, '', '');
    }

    public static function CreateWarning($field_name, $value, $warning_message) {
        return new self($field_name, $value, '', $warning_message);
    }

    public static function CreateError($field_name, $error_message) {
        return new self($field_name, null, $error_message, '');
    }
}
