<?php

use Jira\JiraClient;
use Zend\Config\Factory as ConfigFactory;

require_once 'vendor/autoload.php';
$config = ConfigFactory::fromFiles(glob(__DIR__ . '/config/{,*.}{global,local}.php', GLOB_BRACE));

$jiraClient = new JiraClient(
    $config['auth']
);

$result = $jiraClient->getChecklistData('example.xml');

$issueCounter = 0;
foreach ($result as $issueId => $item) {
    if ($jiraClient->addChecklistItems($issueId, $item) === false) {
        echo "Issue " . $issueId . " \033[31m failed \033[0m to import. " . PHP_EOL;
    } else {
        echo "Issue " . $issueId . " \033[32m successful \033[0m imported. " . PHP_EOL;
        $issueCounter++;
    }

}

echo 'Imported ' . $issueCounter . ' Checklists into https://jira.example.com/' . PHP_EOL;
die;
