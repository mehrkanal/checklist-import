<?php

use Jira\JiraClient;
use Zend\Config\Factory as ConfigFactory;

require_once 'vendor/autoload.php';
$config = ConfigFactory::fromFiles(glob(__DIR__ . '/config/{,*.}{global,local}.php', GLOB_BRACE));

$jiraClient = new JiraClient(
    $config['auth']
);

$missingIds = $jiraClient->getDifferenceFromTwoBackups('checklist_items/all_found_checklist_issue.xml', 'checklist_items/all_found_checklist_itmes.xml');

$jiraClient->printIssueDetailsFromIssueIds($missingIds);

die;