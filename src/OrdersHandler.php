<?php
/**
 * Created for plugin-exporter-excel
 * Date: 05.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Instance\Excel;


use Adbar\Dot;
use Exception;
use GuzzleHttp\Exception\RequestException;
use SalesRender\Plugin\Components\Access\Token\GraphqlInputToken;
use SalesRender\Plugin\Components\ApiClient\ApiClient;
use SalesRender\Plugin\Components\Batch\Batch;
use SalesRender\Plugin\Components\Batch\BatchHandlerInterface;
use SalesRender\Plugin\Components\Batch\Process\Error;
use SalesRender\Plugin\Components\Batch\Process\Process;
use SalesRender\Plugin\Components\Settings\Settings;
use SalesRender\Plugin\Components\Translations\Translator;
use SalesRender\Plugin\Instance\Excel\Components\OrdersFetcherIterator;
use function Sentry\captureException;

class OrdersHandler implements BatchHandlerInterface
{

    private ApiClient $client;

    public function __invoke(Process $process, Batch $batch)
    {
        $token = GraphqlInputToken::getInstance();
        $this->client = new ApiClient(
            "{$token->getBackendUri()}companies/{$token->getPluginReference()->getCompanyId()}/CRM",
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

        if (is_null($maximumOrdersCount)) {
            $maximumOrdersCount = $_ENV['MAX_ORDERS_COUNT_DEFAULT'];
        }

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
        $process->save();

        $orderIds = [];
        foreach ($ordersIterator as $orderId => $orderData) {
            $orderIds[$orderId] = $orderData['status']['id'];
        }

        $orderQuery = <<<QUERY
query (\$filter: OrderSearchFilter) {
  ordersFetcher(filters: \$filter) {
    orders {
      id
      status {
        id
      }
    }
  }
}
QUERY;

        try {
            foreach ($orderIds as $orderId => $statusId) {
                $variables = [
                    'filter' => [
                        'include' => [
                            'ids' => [$orderId]
                        ]
                    ],
                ];

                try {
                    $response = $this->client->query($orderQuery, $variables);
                } catch (RequestException $exception) {
                    $process->addError(new Error($this->getRequestErrorText($exception), $orderId));
                    $process->save();
                    continue;
                }

                if ($response->hasErrors()) {
                    $errors = [];
                    foreach ($response->getErrors() as $error) {
                        $errors[] = $error['message'];
                    }
                    $process->addError(new Error(
                        implode('; ', $errors),
                        $orderId
                    ));
                    $process->save();
                    continue;
                }

                $responseOrders = (new Dot($response->getData()))->get('ordersFetcher.orders');
                try {
                    foreach ($responseOrders as $responseOrder) {
                        $responseOrder = new Dot($responseOrder);
                        if ($responseOrder->get('status.id', -1) != $statusId) {
                            $process->skip();
                            $process->save();
                            throw new Exception('Order already changed status');
                        }
                    }
                } catch (Exception $exception) {
                    continue;
                }

                try {
                    $this->applyOrderTransaction($orderId, $targetStatus);
                    $process->handle();
                } catch (RequestException $exception) {
                    $process->addError(new Error($this->getRequestErrorText($exception), $orderId));
                } catch (Exception $exception) {
                    $errorMessages = $exception->getMessage();
                    $errors = explode(";", $errorMessages);
                    $errorTexts = [];
                    foreach ($errors as $error) {
                        $errorParts = array_pad(explode("|", $error, 2), 2, '');
                        [$errorCode, $errorMessage] = $errorParts;

                        $errorText = 'PROCESS_UNKNOWN_ERROR';

                        if (!empty($errorCode)) {
                            $errorText = $errorCode;
                        } elseif (!empty($errorMessage)) {
                            $errorText = $errorMessage;
                        }
                        $errorTexts[] = Translator::get('process_errors', trim($errorText));
                    }
                    $process->addError(new Error(implode('; ', $errorTexts), $orderId));
                } finally {
                    $process->save();
                }
            }
        } catch (Exception $exception) {
            captureException($exception);
        }


        $process->finish(true);
        $process->save();
    }

    private function getRequestErrorText(RequestException $exception): string
    {
        $response = $exception->getResponse();
        $errorText = 'PROCESS_UNKNOWN_ERROR';

        if ($response !== null && $response->getStatusCode() === 429) {
            $errorText = 'PROCESS_TOO_MANY_REQUESTS_ERROR';
            if (strpos((string)$response->getBody(), 'Too many updates of same entity.') !== false) {
                $errorText = 'ORDER_CONCURRENT_UPDATE_ERROR';
            }
        } else {
            captureException($exception);
        }

        return Translator::get('process_errors', $errorText);
    }

    private function applyOrderTransaction(string $orderId, int $targetStatus)
    {
        $statusChangeMutation = <<<QUERY
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

        $errors = [];

        $variables = [
            'input' => [
                'id' => $orderId,
                'statusId' => $targetStatus
            ]
        ];

        $response = $this->client->query($statusChangeMutation, $variables);

        if ($response->hasErrors()) {
            foreach ($response->getErrors() as $error) {
                $textCodeError = $error['extensions']['code'] ?? '';
                $messageError = $error['message'] ?? '';
                $errors[] = "{$textCodeError}|{$messageError}";
            }

            throw new Exception(implode("; ", $errors));
        }
    }
}
