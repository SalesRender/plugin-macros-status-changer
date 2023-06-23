<?php


namespace Leadvertex\Plugin\Instance\Excel\Components;


use Leadvertex\Plugin\Components\Access\Token\GraphqlInputToken;
use Leadvertex\Plugin\Components\ApiClient\ApiClient;

class Statuses
{
    private ApiClient $client;

    public function __construct()
    {
        $token = GraphqlInputToken::getInstance();
        $this->client = new ApiClient(
            "{$token->getBackendUri()}companies/{$token->getPluginReference()->getCompanyId()}/CRM",
            (string)$token->getOutputToken()
        );
    }

    public function getList(): array
    {
        return $this->getStatuses();
    }

    private function getStatuses(): array
    {
        $query = <<<QUERY
query(\$filters: StatusSearchFilter) {
  statusesFetcher(filters: \$filters) {
    statuses {
      ...Status
      __typename
    }
    __typename
  }
}

fragment Status on Status {
  id
  name
  group
  archived
  __typename
}

QUERY;
        $variables = [
            'filters' => [
                'include' => [
                    'archived' => false
                ]
            ]
        ];

        $response = $this->client->query($query, $variables)->getData();

        $result = [];
        foreach ($response['statusesFetcher']['statuses'] as $status) {
            $result[$status['id']] = [
                'title' => $status['name'],
                'group' => $status['group']
            ];
        }

        return $result;
    }
}