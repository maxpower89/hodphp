<?php
namespace framework\modules\developer\model\module;

use framework\lib\model\BaseModel;

class Update extends BaseModel
{
    function Process($name)
    {
        $this->event->noCache();
        $result = $this->service->module->update($name);
        if (is_object($result)) {
            $this->message->send($name . ":" . $result->message, $result->type);
        }
        return $this;
    }
}