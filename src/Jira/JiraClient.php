<?php

namespace Jira;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;

class JiraClient
{
    /** @var array */
    protected $authSettings;
    /** @var Client */
    protected $httpClient;

    /**
     * @param array $authSettings
     */
    public function __construct(array $authSettings)
    {
        $this->authSettings = $authSettings;
        $this->httpClient = new Client();
    }

    /**
     * @return string
     */
    private function getBasicAuthCredentials(): string
    {
        return base64_encode($this->authSettings['username'] . ':' . $this->authSettings['password']);
    }

    public function getIssueIdsFromXmlFiles(string $filePath): array
    {
        if (!file_exists($filePath)) {
            echo 'File not found' . PHP_EOL;
            die;
        };
        $xmlData = file_get_contents($filePath);

        $simpleXml = new \SimpleXMLElement($xmlData);
        $issues = $simpleXml->xpath('EntityProperty');
        $issueIds = [];


        foreach ($issues as $issue) {
            foreach ($issue->attributes() as $attribute) {
                if ($attribute->getName() === 'id') {
                    $issueIds[] = (int)$attribute;
                }
            }
        }

        return $issueIds;
    }

    public function getChecklistData(string $filePath): array
    {
        if (!file_exists($filePath)) {
            echo 'File not found' . PHP_EOL;
            die;
        };
        $xmlData = file_get_contents($filePath);

        $simpleXml = new \SimpleXMLElement($xmlData);
        $issues = $simpleXml->xpath('EntityProperty');

        $checklistData = [];

        foreach ($issues as $issue) {
            $cachedItems = json_decode((string)$issue->attributes()->value, true);
            $checklistData[(int)$issue->attributes()->entityId] = json_decode($cachedItems['cachedItems'], true);
        }

        return $checklistData;
    }


    public function getChecklistItemsByIssueId(int $issueId): array
    {
        $basicAuth = $this->authSettings['basicAuthString'] ?: $this->getBasicAuthCredentials();
        $checklistUrl = sprintf('https://example.atlassian.net/rest/api/2/issue/%d/properties/checklist', $issueId);

        $response = $this->httpClient->get($checklistUrl, [
            'headers' => [
                'Authorization' => 'Basic ' . $basicAuth,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    public function addChecklistItems(int $issueId, array $checklistData): bool
    {
        $updateUrl = sprintf('https://jira.example.com/rest/api/2/issue/%d?notifyUsers=false', $issueId);
        $basicAuth = $this->authSettings['basicAuthString'] ?: $this->getBasicAuthCredentials();

        $jsonData = $this->prepareJsonData($checklistData);

        try {
            $response = $this->httpClient->put($updateUrl, [
                RequestOptions::JSON => $jsonData,
                'headers' => [
                    'Authorization' => 'Basic ' . $basicAuth,
                ],
            ]);
        } catch (ClientException $exception) {
            do {
                echo $exception->getMessage() . PHP_EOL;
            } while ($exception = $exception->getPrevious());

            return false;
        }

        return $response->getStatusCode() === 204;
    }

    private function prepareJsonData(array $checklistData): array
    {
        $data = [
            'update' => [
                'customfield_15600' => [
                    0 => [
                        'set' => [

                        ],
                    ],
                ],
            ],
        ];

        foreach ($checklistData as $checklistElement) {
            $data['update']['customfield_15600'][0]['set'][] = [
                'name' => trim($checklistElement['summary']),
                'checked' => $checklistElement['fixed'],
            ];
        }

        return $data;
    }

    public function getDifferenceFromTwoBackups(string $filePath, string $fileToCompare): array
    {
        $file1 = file_get_contents($filePath);
        $file2 = file_get_contents($fileToCompare);

        $simpleXmlFile1 = new \SimpleXMLElement($file1);
        $simpleXmlFile2 = new \SimpleXMLElement($file2);

        $issuesFile1 = $simpleXmlFile1->xpath('EntityProperty');
        $issuesFile2 = $simpleXmlFile2->xpath('EntityProperty');

        $issueIdsFile1 = [];
        $issueIdsFile2 = [];

        foreach ($issuesFile1 as $issue) {
            $issueIdsFile1[] = (int)$issue->attributes()->entityId;
        }

        $issue = [];

        foreach ($issuesFile2 as $issue) {
            $issueIdsFile2[] = (int)$issue->attributes()->entityId;
        }

        return array_diff($issueIdsFile1, $issueIdsFile2);
    }

    public function printIssueDetailsFromIssueIds(array $missingIds): array
    {
        $basicAuth = $this->authSettings['basicAuthString'] ?: $this->getBasicAuthCredentials();
        $missingIssues = [];

        $csvFile = fopen('missing-checklists.csv', 'w+');

        foreach ($missingIds as $id) {
            $queryUrl = sprintf('https://jira.example.com/rest/api/2/issue/%d?fields=key,summary,status', $id);
            try {
                $response = $this->httpClient->get($queryUrl, [
                    'headers' => [
                        'Authorization' => 'Basic ' . $basicAuth,
                    ],
                ]);
            } catch (\Exception $exception) {

                echo "\033[0;31m Error \e[0m bei ID " . $id . PHP_EOL;
                fputcsv($csvFile, ['', $id, '', ''], ';');
                continue;
            }
            if ($response->getStatusCode() === 404) {
                echo "ID " . $id . "\033[0;31m nicht gefunden \e[0m" . PHP_EOL;
                fputcsv($csvFile, ['', $id, '', 'Issue not found'], ';');
                continue;
            }

            $details = json_decode($response->getBody()->getContents(), true);

            echo $details['key'] . ' | ' . $details['fields']['summary'] . PHP_EOL;
            fputcsv($csvFile, [$details['fields']['status']['name'], $id, $details['key'], $details['fields']['summary']], ';');
        }
        fclose($csvFile);

        return $missingIssues;
    }

}
