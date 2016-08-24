<?php
namespace BookIt\Codeception\TestRail;


use BookIt\Codeception\TestRail\Model\Plan;
use BookIt\Codeception\TestRail\Model\Project;
use BookIt\Codeception\TestRail\Model\Run;
use Codeception\Exception\ModuleException;
use Codeception\Module as CodeceptionModule;
use Codeception\Step;
use Codeception\Test\Cest;
use Codeception\Test\Test;
use Codeception\TestInterface;

class Module extends CodeceptionModule
{
    protected $requiredFields = ['user', 'apikey', 'project', 'suite'];

    protected $config = [ ];

    /**
     * @var Connection
     */
    protected $conn;

    /**
     * @var Project
     */
    protected $project;

    /**
     * @var Plan
     */
    protected $plan;

    /**
     * @var Run
     */
    protected $run;

    /**
     * @var int
     */
    protected $testCase;

    // HOOK: used after configuration is loaded
    public function _initialize()
    {
        $conn = new Connection();
        $conn->setUser($this->config['user']);
        $conn->setApiKey($this->config['apikey']);
        $conn->connect('https://bookit.testrail.com');

        $project = $conn->getProject($this->config['project']);
        $plan = $conn->createTestPlan($project, date('Y-m-d H:i:s'));

        $this->conn = $conn;
        $this->project = $project;
        $this->plan = $plan;
    }

    // HOOK: before each suite
    public function _beforeSuite($settings = array())
    {
        $suite = $this->project->getSuite($this->config['suite']);
        $entry = $this->conn->createTestPlanEntry($this->plan, $suite);
        $entry->setSuite($suite);
        $this->plan->addEntry($entry);
        $this->run = $entry->getRuns()[0];
    }

    // HOOK: after suite
    public function _afterSuite()
    {
        // close the test run
    }

    // HOOK: after test
    public function _after(TestInterface $test)
    {
        if ($test instanceof Test && $this->testCase) {
            $this->_processResult($test->getTestResultObject());
        }
    }

    public function _processResult(\PHPUnit_Framework_TestResult $result)
    {
        if ($result->wasSuccessful()) {
            if ($result->noneSkipped() && $result->allCompletelyImplemented()) {
                $this->conn->addResult($this->run, $this->testCase, 1);
            } else {
                if (!$result->noneSkipped()) {
                    $this->conn->addResult($this->run, $this->testCase, 11);
                } elseif (!$result->allCompletelyImplemented()) {
                    $this->conn->addResult($this->run, $this->testCase, 12);
                }
            }
        } else {
            if ($result->errorCount() > 0) {
                if ($result->failureCount() > 0) {
                    $this->conn->addResult($this->run, $this->testCase, 5);
                } else {
                    $this->conn->addResult($this->run, $this->testCase, 5);
                }
            }
        }
    }

    /**
     * @param int $caseId
     */
    public function setTestCase($caseId)
    {
        $this->testCase = $caseId;
    }

}