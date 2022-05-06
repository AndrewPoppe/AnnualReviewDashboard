<?php
try {
    $id = $module->authenticate();
}
catch (\CAS_GracefullTerminationException $e) {
    if ($e->getCode() !== 0) {
        $module->log($e->getMessage());
    }
}
catch (\Exception $e) {
    $module->log($e->getMessage());
    $module->exitAfterHook();
}
finally {
    if ($id === FALSE) {
        $module->exitAfterHook();
        return;
    }

    $data = $module->getData($id);
    $module->displayDataTable($data);

}            