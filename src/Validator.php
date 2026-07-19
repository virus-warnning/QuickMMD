<?php
/**
 * 資料檢查
 */

namespace MediaWiki\Extension\QuickMMD;

use Html;
use MediaWiki\MediaWikiServices;

class Validator {

    /**
     * 符合 1.39+ HTMLFormFactory 規範的規格陣列
     */
    private static function getDescriptor(): array {
        return [
            'name' => [
                'type' => 'text',      // 官方標準型態字串
                'required' => true,
            ],
            'theme' => [
                'type' => 'select',    // 官方標準型態字串
                'options' => [ 
                    'Default' => 'default',
                    'Dark'    => 'dark',
                    'Forest'  => 'forest',
                    'Neutral' => 'neutral',
                    'Base'    => 'base',
                ],
                'default' => 'default',
            ],
            'dump-env' => [
                'type' => 'boolean',   // 官方標準型態字串
                'default' => false,
            ],
            'dump-mmd' => [
                'type' => 'boolean',
                'default' => false,
            ],
        ];
    }

    public static function validate( array $rawArgs ) {
        $descriptor = self::getDescriptor();
        $validatedData = [];

        // 1.39+ 安全對照表：手動對應型態到實體類別（徹底擺脫 MediaWiki 內部工廠）
        $typeToClassMap = [
            'text'    => \HTMLTextField::class,
            'select'  => \HTMLSelectField::class,
            'boolean' => \HTMLCheckField::class, 
        ];

        foreach ( $descriptor as $name => $fieldSpec ) {
            $fieldSpec['name'] = $name;
            $fieldSpec['fieldname'] = $name;
            
            // 🎯 自製超輕量安全工廠，直接 new 物件
            $type = $fieldSpec['type'];
            if ( !isset( $typeToClassMap[$type] ) ) {
                return Html::errorBox( "系統錯誤：不支援的驗證型態 [{$type}]" );
            }
            
            $className = $typeToClassMap[$type];
            
            /** @var \HTMLFormField $field */
            $field = new $className( $fieldSpec );
            
            // 取得使用者輸入的值
            $rawValue = $rawArgs[$name] ?? null;
            
            // 執行 MediaWiki 欄位自帶的核心驗證（必填檢查、下拉選單安全檢查）
            $validationResult = $field->validate( $rawValue, $rawArgs );
            
            if ( $validationResult !== true ) {
                $errorMsg = is_object( $validationResult ) ? $validationResult->text() : $validationResult;
                return Html::errorBox( "參數 '{$name}' 驗證失敗: " . $errorMsg );
            }
            
            // 通過驗證後，如果值為 null，則手動補上規格裡的預設值
            if ( $rawValue === null ) {
                $validatedData[$name] = $fieldSpec['default'] ?? null;
            } else {
                // 使用欄位內建的過濾器（例如：自動將字串的 'false' 轉成真正的 bool(false)）
                $validatedData[$name] = $field->filter( $rawValue, $rawArgs );
            }
        }

        return $validatedData;
    }
}