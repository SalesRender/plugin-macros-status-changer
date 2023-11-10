<?php
/**
 * Created for plugin-exporter-excel
 * Datetime: 03.03.2020 15:43
 * @author Timur Kasumov aka XAKEPEHOK
 */

namespace SalesRender\Plugin\Instance\Excel\Forms;


use SalesRender\Plugin\Components\Form\FieldDefinitions\FieldDefinition;
use SalesRender\Plugin\Components\Form\FieldDefinitions\IntegerDefinition;
use SalesRender\Plugin\Components\Form\FieldGroup;
use SalesRender\Plugin\Components\Form\Form;
use SalesRender\Plugin\Components\Translations\Translator;

class SettingsForm extends Form
{

    public function __construct()
    {
        parent::__construct(
            Translator::get(
                'settings',
                'SETTINGS_TITLE'
            ),
            Translator::get(
                'settings',
                'SETTINGS_DESCRIPTION'
            ),
            [
                'main' => new FieldGroup(
                    Translator::get('settings', 'SETTINGS_MAIN_FIELDS_TITLE'),
                    null,
                    [
                        'maxOrders' => new IntegerDefinition(
                            Translator::get(
                                'settings',
                                'MAX_ORDERS_FIELD_NAME'
                            ),
                            Translator::get(
                                'settings',
                                'MAX_ORDERS_COUNT_DEFAULT_FIELD_DESCRIPTION'
                            ),
                            function ($value, FieldDefinition $definition) {
                                $errors = [];
                                if (!is_int($value) || $value <= 0) {
                                    $errors[] = Translator::get(
                                        'settings_errors',
                                        'INVALID_FIELD_VALUE_ERROR {field}',
                                        ['field' => $definition->getTitle()]
                                    );
                                }
                                return $errors;
                            },
                            $_ENV['MAX_ORDERS_COUNT_DEFAULT']
                        )
                    ]
                )
            ],
            Translator::get(
                'settings',
                'SETTINGS_SAVE_BUTTON'
            )
        );
    }

}