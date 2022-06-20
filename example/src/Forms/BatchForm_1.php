<?php
/**
 * Created for plugin-exporter-excel
 * Date: 06.03.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Instance\Excel\Forms;


use Leadvertex\Plugin\Components\Form\FieldDefinitions\ListOfEnum\Limit;
use Leadvertex\Plugin\Components\Form\FieldDefinitions\ListOfEnum\Values\StaticValues;
use Leadvertex\Plugin\Components\Form\FieldDefinitions\ListOfEnumDefinition;
use Leadvertex\Plugin\Components\Form\FieldGroup;
use Leadvertex\Plugin\Components\Form\Form;
use Leadvertex\Plugin\Components\Translations\Translator;
use Leadvertex\Plugin\Instance\Excel\Components\Statuses;

class BatchForm_1 extends Form
{

    public function __construct()
    {
        $statuses = new Statuses();
        parent::__construct(
            Translator::get(
                'batch_settings',
                'BATCH_FORM_TITLE'
            ),
            null,
            [
                'options' => new FieldGroup(
                    Translator::get('batch_settings', 'BATCH_FORM_DESCRIPTION'),
                    null,
                    [
                        'targetStatus' => new ListOfEnumDefinition(
                            Translator::get(
                                'batch_settings',
                                'TARGET_STATUS_FIELD'
                            ),
                            Translator::get(
                                'batch_settings',
                                'TARGET_STATUS_FIELD_DESCRIPTION'
                            ),
                            function ($values) use ($statuses) {
                                if (!is_array($values)) {
                                    return [Translator::get(
                                        'batch_errors',
                                        'INVALID_VALUE_ERROR'
                                    )];
                                }

                                $errors = [];
                                if (count($values) < 1) {
                                    $errors[] = Translator::get(
                                        'batch_errors',
                                        'STATUS_NOT_SELECTED_ERROR'
                                    );
                                }

                                foreach ($values as $value) {
                                    if (!isset($statuses->getList()[$value])) {
                                        $errors[] = Translator::get(
                                            'batch_errors',
                                            'NON_EXISTENT_STATUS_ERROR {status}',
                                            ['status' => $value]
                                        );
                                    }
                                }

                                return $errors;
                            },
                            new StaticValues($statuses->getList()),
                            new Limit(1, 1)
                        )
                    ]
                )
            ],
            Translator::get(
                'batch_settings',
                'BATCH_SEND_BUTTON'
            )
        );
    }

}