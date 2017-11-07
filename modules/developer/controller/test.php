<?php
namespace hodphp\modules\developer\controller;

use hodphp\core\Controller;

class Test extends Controller
{
    function home()
    {
        return $this->response->renderView();
    }

    function runUnitTest()
    {
        $model = $this->model->test->unitTest;
        $this->db->startTestMode();
        $model->setupDatabase();
        $model->runTests();
        $model->destroyDatabase();
        $this->db->stopTestMode();

        $this->response->renderView($model);
    }
}


