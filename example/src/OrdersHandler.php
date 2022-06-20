<?php
/**
 * Created for plugin-exporter-excel
 * Date: 05.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Instance\Excel;


use Adbar\Dot;
use Exception;
use Leadvertex\Plugin\Components\Access\Token\GraphqlInputToken;
use Leadvertex\Plugin\Components\ApiClient\ApiClient;
use Leadvertex\Plugin\Components\Batch\Batch;
use Leadvertex\Plugin\Components\Batch\BatchHandlerInterface;
use Leadvertex\Plugin\Components\Batch\Process\Error;
use Leadvertex\Plugin\Components\Batch\Process\Process;
use Leadvertex\Plugin\Components\Settings\Settings;
use Leadvertex\Plugin\Components\Translations\Translator;
use Leadvertex\Plugin\Instance\Excel\Components\OrdersFetcherIterator;

class OrdersHandler implements BatchHandlerInterface
{

    private ApiClient $client;

    public function __invoke(Process $process, Batch $batch)
    {
        $token = GraphqlInputToken::getInstance();
        $this->client = new ApiClient(
            $token->getBackendUri() . 'companies/stark-industries/CRM',
            (string)$token->getOutputToken()
        );

        $settings = Settings::find()->getData();
        $maximumOrdersCount = $settings->get('main.maxOrders');
        $targetStatus = current($batch->getOptions(1)->get('options.targetStatus'));

        $orderFields = [
            'orders' => [
                'id',
                'status' => [
                    'id'
                ],
            ]
        ];
        $ordersIterator = new OrdersFetcherIterator(
            $orderFields,
            $batch->getApiClient(),
            $batch->getFsp()
        );

        $ordersCount = count($ordersIterator);

        if ($ordersCount > $maximumOrdersCount) {
            $process->terminate(new Error(
                Translator::get(
                    'process_errors',
                    'MAXIMUM_ORDERS_COUNT_EXCEEDED_ERROR {maximumOrdersCount}',
                    ['maximumOrdersCount' => $maximumOrdersCount]
                )
            ));
            $process->save();
            return;
        }

        $process->initialize($ordersCount);

        $query = <<<QUERY
mutation updateOrder(\$input: UpdateOrderInput!) {
  orderMutation {
    updateOrder(input: \$input) {
      status {
        id
      }
    }
  }
}
QUERY;

        foreach ($ordersIterator as $id => $order) {
            try {
                $errors = [];
                $order = new Dot($order);

                if ($order->get('status.id') == $targetStatus) throw new Exception(Translator::get(
                    'process_errors',
                    'ORDER_ALREADY_IN_STATUS_ERROR'
                ));

                $variables = [
                    'input' => [
                        'id' => $id,
                        'statusId' => $targetStatus
                    ]
                ];

                $response = $this->client->query($query, ($variables));

                if ($response->hasErrors()) {
                    foreach ($response->getErrors() as $error) {
                        $errors[] = $error['message'];
                    }

                    throw new Exception(implode("; ", $errors));
                }

                $process->handle();

            } catch (Exception $exception) {
                $process->addError(new Error(
                    Translator::get(
                        'process_errors',
                        'PROCESS_UNKNOWN_ERROR {errorMessage}',
                        ['errorMessage' => $exception->getMessage()]
                    ),
                    $id
                ));
            }
            $process->save();
        }

        $process->finish(true);
        $process->save();
    }
}